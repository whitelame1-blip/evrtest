import sys
import os
import io
import json
import base64
import requests
import tempfile
import mysql.connector
from flask import Flask, jsonify, request
from flask_cors import CORS
from dotenv import load_dotenv
from voice import process_pronunciation_tips_task, process_script_analysis


# Load variables from .env file
load_dotenv(override=True)

# --- CONFIGURATION (Zero Hardcoding) ---
ELEVENLABS_API_KEY = os.getenv("ELEVENLABS_API", "").strip()
INTERNAL_KEY = os.getenv("FLASK_INTERNAL_KEY")
ALLOWED_EXTENSIONS = {'.pdf', '.docx', '.txt'}

db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'aireadinggenerator')
}

# --- INITIALIZE ---
app = Flask(__name__)
CORS(app)

# Ensure pathing for Celery
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Ensure this matches your local FFMPEG path

# Configure Gemini

# --- HELPERS ---

def allowed_file(filename):
    """Check if the file extension is safe."""
    ext = os.path.splitext(filename)[1].lower()
    return ext in ALLOWED_EXTENSIONS

def get_safe_int(value, default=5):
    if value is None or value == "": return default
    try: return int(value)
    except: return default
# --------------
def get_user_plan(user_id):
    """Fetch plan with proper parameter casting to prevent injection."""
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)
        # Cast to int to ensure we aren't passing malicious strings
        cursor.execute("SELECT plan_type FROM users WHERE id = %s", (int(user_id),))
        user = cursor.fetchone()
        return user['plan_type'] if user else 'free'
    except Exception as e:
        app.logger.error(f"Database Error: {e}")
        return 'free'
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()


@app.before_request
def verify_internal_key():
    """Blocks anyone who isn't your PHP server."""
    protected_routes = ['/generate-reading', '/generate/section', '/preview-voice', '/voices', '/analyze', '/pronunciation-tips', '/analyze-script', '/analyze-performance']
    if request.path in protected_routes:
        client_key = request.headers.get('X-Internal-Key')
        if not INTERNAL_KEY or client_key != INTERNAL_KEY:
            return jsonify({"error": "Unauthorized Access to AI Engine."}), 403
# --- ROUTES ---

@app.route('/generate/section', methods=['POST'])
def generate_section():
    data = request.json
    if not data:
        return jsonify({"error": "No data provided"}), 400
    from task import process_listening_generation

    # Trigger the worker (non-blocking)
    task = process_listening_generation.delay(data)
    
    return jsonify({
        "status": "processing",
        "task_id": task.id
    }), 202



class IELTSValidationError(Exception):
    """Custom exception for logical errors (too short, wrong topic)."""
    pass

@app.errorhandler(IELTSValidationError)
def public_handle_validation_error(e): # Added 'def' here
    # These are the "Actionable" errors for the student
    return jsonify({
        "state": "FAILURE",
        "error_type": "VALIDATION",
        "error": str(e)
    }), 400

@app.errorhandler(Exception)
def public_handle_system_error(e): # Added 'def' here
    # These are the "Technical" crashes
    print(f"SYSTEM CRASH: {str(e)}") 
    
    return jsonify({
        "state": "FAILURE",
        "error_type": "SYSTEM",
        "error": "The AI engine encountered a glitch. Please try again."
    }), 500




@app.route('/status/<task_id>', methods=['GET'])
def get_task_status(task_id):
    """Universal Route for PHP/JS to poll ANY task status with Progress support"""
    from celery_tasks.celery_config import celery_app

    task_result = celery_app.AsyncResult(task_id)
    
    # 1. Handle SUCCESS (Both real success and Logical Errors)
    if task_result.state == 'SUCCESS':
        result = task_result.result
        
        # Check if the task returned a manual error dictionary
        if isinstance(result, dict) and "error" in result:
            # We treat this as a FAILURE for the frontend to trigger the 'catch' block
            return jsonify({
                "state": "FAILURE",
                "error_type": "VALIDATION",  # This tells JS it's a logical error
                "error": result["error"]
            }), 400

        return jsonify({
            "state": "SUCCESS",
            "result": result
        })

    # 2. Handle SYSTEM FAILURE (Actual code crashes or API timeouts)
    # 2. Handle SYSTEM FAILURE (Actual code crashes or API timeouts)
    if task_result.state == 'FAILURE':
        # Log the real error to your terminal so you can fix it
        print(f"!!! CELERY SYSTEM ERROR: {task_result.info}") 
        
        return jsonify({
            "state": "FAILURE",
            "error_type": "SYSTEM",
            # Show a professional, supportive message to the student
            "error": "The AI engine encountered a temporary glitch. Your credits have been automatically restored."
        }), 500

    # 3. Handle PROGRESS
    if task_result.state == 'PROGRESS':
        return jsonify({
            "state": "PROGRESS",
            "meta": task_result.info 
        })

    # 4. Handle PENDING/RETRY
    return jsonify({"state": task_result.state})

import time

# Global variables for simple caching
cached_voices = None
last_fetch_time = 0
CACHE_DURATION = 3600  # 1 hour in seconds

@app.route("/voices", methods=["GET"])
def get_voices():
    print(f"KEY CHECK: {ELEVENLABS_API_KEY}")
    global cached_voices, last_fetch_time
    
    # 1. Cache Check
    if cached_voices and (time.time() - last_fetch_time < CACHE_DURATION):
        return jsonify(cached_voices)

    try:
        response = requests.get(
            "https://api.elevenlabs.io/v1/voices", 
            headers={"xi-api-key": ELEVENLABS_API_KEY},
            timeout=8 # Increased timeout for international API calls
        )
        
        # If ElevenLabs is down or key is bad, this triggers the 'except' block
        response.raise_for_status() 
        data = response.json()
        all_voices = data.get("voices", [])

        # 2. Relaxed Filtering Logic
        target_accents = ["british", "american", "australian"]
        filtered_voices = []

        for v in all_voices:
            # Safely get labels
            labels = v.get("labels", {})
            # Check for matches in BOTH the 'accent' label and the 'description'
            accent_val = str(labels.get("accent", "")).lower()
            desc_val = str(labels.get("description", "")).lower()
            
            is_match = any(target in accent_val or target in desc_val for target in target_accents)

            if is_match:
                filtered_voices.append({ 
                    "voice_id": v["voice_id"], 
                    "name": v["name"], 
                    "gender": labels.get("gender", "unknown"),
                    "accent": accent_val or "standard"
                })

        # 3. SAFETY VALVE: If no matches found, don't return an error!
        # Return the first 10 available voices so the UI still works.
        final_output = filtered_voices if filtered_voices else [
            {
                "voice_id": v["voice_id"], 
                "name": v["name"], 
                "gender": v.get("labels", {}).get("gender", "unknown"),
                "accent": "international"
            } for v in all_voices[:10]
        ]

        # Update cache
        cached_voices = final_output
        last_fetch_time = time.time()
        
        return jsonify(final_output)

    except Exception as e:
        # LOG THE ACTUAL ERROR to your console so you can see if it's a 401 (API Key)
        print(f"ELEVENLABS FETCH ERROR: {e}")
        
        if cached_voices:
            return jsonify(cached_voices)
        return jsonify({"error": "Service temporarily unavailable", "details": str(e)}), 500
import mysql.connector
from mysql.connector import Error

def get_db_connection():
    try:
        # Spreading your db_config dictionary into the connection function
        return mysql.connector.connect(**db_config)
    except Error as e:
        print(f"Error connecting to MySQL: {e}")
        return None

def get_preview_attempts_from_db(user_id):
    """Returns the current number of listening_preview_attempts as an integer."""
    conn = get_db_connection()
    if not conn: return 0
    
    cursor = conn.cursor()
    try:
        # Direct select from the credits table
        sql = "SELECT listening_preview_attempts FROM credits WHERE user_id = %s"
        cursor.execute(sql, (user_id,))
        result = cursor.fetchone()
        
        # Return the value if found, else 0
        return result[0] if result else 0
    except Error as e:
        print(f"Database Error (get): {e}")
        return 0
    finally:
        cursor.close()
        conn.close()

def decrement_preview_attempts_in_db(user_id):
    """Decrements the count by 1 and returns the new balance."""
    conn = get_db_connection()
    if not conn: return 0
    
    cursor = conn.cursor()
    try:
        # Use GREATEST(0, ...) to ensure the balance never drops below zero
        update_sql = """
            UPDATE credits 
            SET listening_preview_attempts = GREATEST(0, listening_preview_attempts - 1) 
            WHERE user_id = %s
        """
        cursor.execute(update_sql, (user_id,))
        conn.commit()

        # Fetch the newly updated balance to sync with the frontend
        cursor.execute("SELECT listening_preview_attempts FROM credits WHERE user_id = %s", (user_id,))
        result = cursor.fetchone()
        
        return result[0] if result else 0
    except Error as e:
        print(f"Database Error (decrement): {e}")
        conn.rollback() # Rollback changes if something went wrong
        return 0
    finally:
        cursor.close()
        conn.close()
@app.route("/preview-voice", methods=["POST"])
def preview_voice():
    data = request.json
    voice_id = data.get('voice_id')
    user_id = data.get('user_id') 
    is_static = data.get('is_static', False) # Capture the flag from JS
    print(f"Preview Request: Voice={voice_id}, User={user_id}, Static={is_static}")
    if not voice_id or not user_id:
        return jsonify({"error": "Missing voice_id or user_id"}), 400
        
    # 1. Fetch current attempts from the backend/database
    # Assuming you have a helper function to query your 'credits' table
    attempts_left = get_preview_attempts_from_db(user_id)
    
    # 2. Logic Check: If attempts are 0, return the static preview
    if is_static or attempts_left <= 0:
        try:
            headers = {"xi-api-key": ELEVENLABS_API_KEY}
            resp = requests.get(f"https://api.elevenlabs.io/v1/voices/{voice_id}", headers=headers)
            if resp.status_code == 200:
                preview_url = resp.json().get("preview_url")
                return jsonify({
                    "audio_data": preview_url, 
                    "is_static": True,
                    "attempts_left": attempts_left
                })
            return jsonify({"error": "Voice metadata not found"}), 404
        except Exception as e:
            return jsonify({"error": str(e)}), 500

    # 3. Dynamic Generation (User has attempts > 0)
    stability = float(data.get('stability', 0.5))
    similarity = float(data.get('similarity_boost', 0.75)) # Ensure this matches JS
    style = float(data.get('style', 0.0))
    print(f"Tuning Voice {voice_id}: Stab={stability}, Sim={similarity}, Style={style}")
    try:
        url = f"https://api.elevenlabs.io/v1/text-to-speech/{voice_id}"
        headers = { "xi-api-key": ELEVENLABS_API_KEY, "Content-Type": "application/json" }
        payload = {
            "text": "Hello! This is a preview of my voice. Can you hear the difference when you change the settings? It's quite remarkable!",
            "model_id": "eleven_multilingual_v2",
            "voice_settings": { 
                "stability": stability, 
                "similarity_boost": similarity, 
                "style": style, 
                "use_speaker_boost": True 
            }
        }
        
        response = requests.post(url, json=payload, headers=headers)
        
        if response.status_code == 200:
            # 4. Success: Decrement the attempt count in the database
            new_balance = decrement_preview_attempts_in_db(user_id)
            
            audio_base64 = base64.b64encode(response.content).decode('utf-8')
            return jsonify({ 
                "audio_data": f"data:audio/mpeg;base64,{audio_base64}", 
                "is_static": False,
                "attempts_left": new_balance
            })
            
        return jsonify({"error": "ElevenLabs API error"}), response.status_code
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

# Helper function placeholders for database operations

@app.route("/generate-reading", methods=["POST"])
def generate_route():
    
    if request.is_json:
        data = request.get_json()
    else:
        data = request.form.to_dict()
        if "mixed_types" in data:
            try: data["mixed_types"] = json.loads(data["mixed_types"])
            except: data["mixed_types"] = []
    if request.files:
        data = request.form.to_dict()
    elif request.is_json:
        data = request.get_json()
    else:
        data = request.form.to_dict()
    temp_path = None
    user_id = request.form.get("user_id")
    if "file" in request.files:
        # Check if they are actually allowed to use files
        if get_user_plan(user_id) == 'pro':
            uploaded_file = request.files["file"]
            suffix = os.path.splitext(uploaded_file.filename)[1].lower()
            
            # Use delete=False because Celery needs to access it in a different process
            with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
                uploaded_file.save(tmp.name)
                temp_path = tmp.name
        else:
            # Block unauthorized file uploads
            return jsonify({
                "success": FALSE, 
                "error": "The file upload feature is reserved for Pro users."
            }), 403
    from ai_backend import generate_test
    params = {
        "user_id": data.get("user_id"),
        "user_choice": data.get("question_type", "multiple_choice"),
        "difficulty": data.get("difficulty", "Intermediate"),
        "mixed_types": data.get("mixed_types", []),
        "topic": data.get("topic", "General Knowledge"),
        "num_questions": get_safe_int(data.get("num_questions"), 5),
        "word_count": get_safe_int(data.get("word_count"), 150),
        "file_path": temp_path,
        "file_type": data.get("file_type")
    }

    # Offload to Celery using .delay() instead of calling it synchronously
    task = generate_test.delay(**params)
    
    return jsonify({
        "status": "processing",
        "task_id": task.id
    }), 202

# ==========================================================
#                   AUDIO ANALYSIS ROUTES (WHISPER)
# ==========================================================

@app.route("/analyze", methods=["POST"])
def analyze():
    sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    from voice import deduct_credit
    from voice import process_audio_analysis
    import uuid

    user_id = request.form.get("user_id")
    mode = request.form.get("mode")
    expected_text = request.form.get("expected_text", "").strip()
    credit_cost = int(request.form.get("required_cost", 2))
    if not user_id: return jsonify({"error": "User ID missing"}), 400
    
    plan_type = get_user_plan(user_id)
    
    # 1. Immediate validation
    if mode == "deepMode" and plan_type != "pro":
        return jsonify({"error": "Upgrade Required", "is_pro_required": True}), 403 

    # 2. Credit check (Still happens in main thread)


    # 3. Save file and prepare paths
    uid = str(uuid.uuid4())
    webm_path = os.path.abspath(os.path.join("uploads", f"{uid}.webm"))
    wav_path = os.path.abspath(os.path.join("uploads", f"{uid}.wav"))
    request.files["audio"].save(webm_path)

    # 4. Trigger Celery
    task = process_audio_analysis.delay({
        "uid": uid,
        "user_id": user_id,       # Added for refund
        "credit_cost": credit_cost, # Added for refund
        "webm_path": webm_path,
        "wav_path": wav_path,
        "expected_text": expected_text,
        "mode": mode,
        "plan_type": plan_type,
    })

    return jsonify({
        "status": "processing",
        "task_id": task.id,
    }), 202


@app.route("/pronunciation-tips", methods=["POST"])
def pronunciation_tips():
    # 1. Verification is already handled by @app.before_request verify_internal_key()
    # just make sure you add '/pronunciation-tips' to your protected_routes list!

    data = request.get_json()
    if not data or not data.get('uid'):
        return jsonify({"error": "Missing UID or data"}), 400

    # We import the task here to avoid circular imports
    from voice import process_pronunciation_tips_task

    # 2. Trigger Celery (Non-blocking)
    # We pass the whole data dictionary (uid, expected_text, scores, etc.)
    task = process_pronunciation_tips_task.delay(data)

    return jsonify({
        "status": "processing",
        "task_id": task.id
    }), 202


@app.route("/analyze-script", methods=["POST"])
def analyze_script():
    data = request.get_json(silent=True) or request.form
    
    # 2. Extract variables from 'data' (which is now a dictionary)
    user_id = data.get("user_id")
    
    if not user_id:
        return jsonify({"error": "User ID missing"}), 400

    if get_user_plan(user_id) != 'pro':
        return jsonify({"error": "Pro Plan Required"}), 403

    audio_url = data.get("audio_url")
    test_id = data.get("test_id")
    expected_text = data.get("expected_text")

    if not audio_url or not test_id:
        return jsonify({"error": "Missing required data"}), 400

    # Import the task
    from voice import process_script_analysis
    import uuid
    # Trigger background work
    task = process_script_analysis.delay({
        "uid": str(uuid.uuid4()),
        "audio_url": audio_url,
        "test_id": test_id,
        "expected_text": expected_text,
        "db_config": db_config # Pass config so Celery can connect
    })

    return jsonify({
        "status": "processing",
        "task_id": task.id
    }), 202


@app.route("/analyze-performance", methods=["POST"])
def analyze_performance():
    # 1. Verification is already handled by @app.before_request verify_internal_key()
    # just make sure you add '/pronunciation-tips' to your protected_routes list!

    data = request.get_json()
    if not data :
        return jsonify({"error": "Missing UID or data"}), 400

    # We import the task here to avoid circular imports
    from ai_test_review import test_review_analysis_task

    # 2. Trigger Celery (Non-blocking)
    # We pass the whole data dictionary (uid, expected_text, scores, etc.)
    task = test_review_analysis_task.delay(data)

    return jsonify({
        "status": "processing",
        "task_id": task.id
    }), 202


@app.route('/generatesinglegame-test', methods=['POST', 'OPTIONS'])
def generate_single_game_test():
    data = request.json
    if not data:
        return jsonify({"error": "No data provided"}), 400
    from game import process_game_generation
    # Trigger the worker (non-blocking)
    task = process_game_generation.delay(data)
    
    return jsonify({
        "status": "processing",
        "task_id": task.id
    }), 202


if __name__ == '__main__':
    print("AI Listening Dispatcher starting on http://127.0.0.1:5000")
    # USE standard Flask threaded mode. DO NOT use eventlet here.
    app.run(debug=True, port=5000, threaded=True)