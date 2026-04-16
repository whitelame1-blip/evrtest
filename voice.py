import os
import whisper
import subprocess
from difflib import SequenceMatcher
import re
import jsonify
import mysql.connector

from celery_tasks.celery_config import celery_app
import requests
from dotenv import load_dotenv # 1. ADD THIS

# 2. ADD THIS LINE
load_dotenv(override=True)

LARAVEL_URL = os.getenv("LARAVEL_REFUND_URL")
SECRET = os.getenv("AI_INTERNAL_SECRET") # Must match Laravel .env
# ---------------- CONFIG ----------------
UPLOAD_DIR = "uploads"
WHISPER_MODEL = "base"
PORT = 5000

# Update this path if FFMPEG is in a different location
FFMPEG_EXE = os.getenv("FFMPEG_PATH", "ffmpeg")

os.makedirs(UPLOAD_DIR, exist_ok=True)



# ---------------- UTILS ----------------

# ---------------- DATABASE CONFIG ----------------
db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'aireadinggenerator')
}


def convert_to_wav(input_path, output_path):
    subprocess.run(
        [
            FFMPEG_EXE, "-y",
            "-i", input_path,
            "-ar", "16000",
            "-ac", "1",
            output_path
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        check=True
    )

# ---------- Text similarity ----------
def normalize_text(text):
    import string
    """Lowercases, removes punctuation, and strips whitespace."""
    if not text: return ""
    text = text.lower()
    # Remove punctuation
    text = text.translate(str.maketrans('', '', string.punctuation))
    # Remove extra whitespace
    return " ".join(text.split())
def text_accuracy(expected, spoken):
    exp = normalize_text(expected)
    spk = normalize_text(spoken)
    if not exp or not spk: return 0
    return round(SequenceMatcher(None, exp, spk).ratio() * 100)
# ---------- Semantic Score ----------
def semantic_accuracy(expected, spoken):
    exp_words = normalize_text(expected).split()
    spk_words = normalize_text(spoken).split()
    
    if not exp_words: return 0
    
    # Common words that don't carry much meaning
    stop_words = {'the', 'a', 'an', 'is', 'are', 'to', 'of', 'at', 'in'}
    
    # Give more weight to non-stop words
    matches = 0
    total_weight = 0
    
    for word in exp_words:
        weight = 1 if word in stop_words else 3
        total_weight += weight
        if word in spk_words:
            matches += weight
            # Remove from spk_words to handle repeated word logic
            spk_words.remove(word) 
            
    return round((matches / total_weight) * 100) if total_weight > 0 else 0
# ---------- Grammar ----------
def grammar_score(text):
    if not text:
        return 0
    words = text.split()
    if len(words) < 3:
        return 60
    repeated = sum(1 for i in range(1, len(words)) if words[i] == words[i - 1])
    penalty = repeated * 5
    return max(60, 100 - penalty)

# ---------- Fluency ----------
def fluency_score(words_data):
    """
    Calculates fluency based on actual speaking duration.
    words_data: list of dicts with {'word': str, 'start': float, 'end': float}
    """
    if not words_data: return 0
    
    total_words = len(words_data)
    # Speaking duration = end of last word minus start of first word
    duration = words_data[-1]['end'] - words_data[0]['start']
    
    if duration <= 0: return 0
    
    wpm = (total_words / duration) * 60
    
    # IELTS standard: ~120-150 WPM is ideal for 'Fluency'
    if wpm >= 130: return 95
    if wpm >= 100: return 80
    if wpm >= 70: return 65
    return 50
# ---------- Hesitation ----------
def hesitation_score(words_data):
    """Detects silences between individual words."""
    if len(words_data) < 2: return 100
    
    pauses = 0
    long_pauses = 0
    
    for i in range(1, len(words_data)):
        gap = words_data[i]['start'] - words_data[i-1]['end']
        if gap > 0.4: # Significant pause
            pauses += 1
        if gap > 1.0: # Very long struggle
            long_pauses += 1
            
    score = 100 - (pauses * 5) - (long_pauses * 10)
    return max(0, min(100, score))
# ---------- Pronunciation clarity ----------
def pronunciation_clarity(expected, spoken):
    if not expected or not spoken:
        return 0
    diff = abs(len(expected.split()) - len(spoken.split()))
    return max(60, 100 - diff * 5)

# ---------------- ROUTE ----------------
import json # Ensure json is imported

@celery_app.task(bind=True, name="process_script_analysis")
def process_script_analysis(self, params):
    self.update_state(state='PROGRESS', meta={'progress': 20, 'status': 'Started cooking your transcript...'})
    import shutil
    """
    Background task to download/copy audio, transcribe via Whisper, 
    and update the database.
    """
    audio_url = params['audio_url']
    test_id = params['test_id']
    expected_text = params['expected_text']
    db_config = params['db_config']
    
    uid = params['uid']
    upload_dir = "uploads"
    download_path = os.path.join(upload_dir, f"tmp_{uid}_ref.mp3")
    wav_path = os.path.join(upload_dir, f"tmp_{uid}_ref.wav")
    self.update_state(state='PROGRESS', meta={'progress': 40, 'status': 'Gathering neccessary components...'})
    try:
        
        # 1. Handle File Retrieval
        if audio_url.startswith('http'):
            response = requests.get(audio_url, stream=True, timeout=15)
            with open(download_path, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)
        else:
            shutil.copy(audio_url.replace('\\\\', '\\'), download_path)

        # 2. Conversion & Transcription
        # We import inside the task to avoid memory overhead on the main Flask thread
        self.update_state(state='PROGRESS', meta={'progress': 60, 'status': 'Working on transcription...'})
        convert_to_wav(download_path, wav_path)
        
        model = whisper.load_model('tiny')
        result = model.transcribe(
            wav_path, 
            word_timestamps=True, 
            initial_prompt=expected_text
        )

        word_data = []
        for segment in result.get("segments", []):
            if "words" in segment:
                for word_obj in segment["words"]:
                    word_data.append({
                        "word": word_obj["word"].strip(),
                        "start": word_obj["start"],
                        "end": word_obj["end"]
                    })

        # 3. Database Update
        self.update_state(state='PROGRESS', meta={'progress': 90, 'status': 'Wrapping up...'})
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        cursor.execute(
            "UPDATE practice_sessions SET word_data_json = %s WHERE id = %s",
            (json.dumps(word_data), test_id)
        )
        conn.commit()
        cursor.close()
        conn.close()

        return {"status": "success", "test_id": test_id, "word_data": word_data}

    except Exception as e:
        return {"status": "error", "message": str(e)}
    finally:
        if os.path.exists(download_path): os.remove(download_path)
        if os.path.exists(wav_path): os.remove(wav_path)

celery_model_basic = None
celery_model_deep = None

@celery_app.task(bind=True, name='process_audio_analysis')
def process_audio_analysis(self, data):
    current_task_id = self.request.id
    self.update_state(state='PROGRESS', meta={'progress': 10, 'status': 'Starting analysis...'})
    
    # 1. Extract Data
    uid = data['uid']
    webm_path = data['webm_path']
    wav_path = data['wav_path']
    expected_text = data['expected_text']
    mode = data['mode']
    plan_type = data['plan_type']
    user_id = data.get('user_id')
    cost = data.get('credit_cost', 2) # Default to 2 if missing
    task_id = data.get('task_id')
    try:
        # 2. Conversion & Model Loading
        self.update_state(state='PROGRESS', meta={'progress': 30, 'status': 'Preparing audio...'})
        convert_to_wav(webm_path, wav_path)

        if mode == "deepMode" and plan_type == "pro":
            model_name = "small.en"
        else:
            model_name = "base.en"
            
        model = whisper.load_model(model_name)

        # 3. Transcribe
        self.update_state(state='PROGRESS', meta={'progress': 60, 'status': 'Transcribing...'})
        result = model.transcribe(wav_path, word_timestamps=True, fp16=False)
        spoken_text = result.get("text", "").strip()
        
        all_words = [w for segment in result.get("segments", []) for w in segment.get("words", [])]

        # 4. Scoring
        self.update_state(state='PROGRESS', meta={'progress': 80, 'status': 'Calculating scores...'})
        t_acc = text_accuracy(expected_text, spoken_text)
        s_acc = semantic_accuracy(expected_text, spoken_text)
        overall_accuracy = round((s_acc * 0.7) + (t_acc * 0.3))

        # 5. SUCCESS CLEANUP: Remove files before returning
        for path in [webm_path, wav_path]:
            if os.path.exists(path): os.remove(path)

        return {
            "status": "success",
            "uid": uid,
            "spoken_text": spoken_text,
            "accuracy": overall_accuracy,
            "semantic_accuracy": s_acc,
            "text_accuracy": t_acc,
            "grammar": grammar_score(spoken_text),
            "fluency": fluency_score(all_words),
            "hesitation": hesitation_score(all_words),
            "pronunciation_clarity": pronunciation_clarity(expected_text, spoken_text),
        }

    except Exception as e:
        # FAILURE CLEANUP: Must happen before or inside the refund logic
        for path in [webm_path, wav_path]:
            if os.path.exists(path): os.remove(path)

        # TRIGGER REFUND
        print(f"!!! TASK FAILED: Refunding {cost} to User {user_id} for Task {current_task_id} !!!")
        try:
            # We send 'task_id' to Laravel so it knows which row in 'user_tasks' to set to 'failed'
            refund_payload = {
                "user_id": user_id, 
                "amount": cost, 
                "task_id": current_task_id 
            }
            
            response = requests.post(
                LARAVEL_URL, 
                json=refund_payload, 
                headers={"X-Internal-Secret": SECRET}, 
                timeout=5
            )
            print(f"Laravel Response: {response.status_code} - {response.text}")

        except Exception as refund_err:
            print(f"Critical: Refund handshake failed: {refund_err}")
            
        # Re-raise the error so Celery marks the task as failed in its own internal logs
        raise e
@celery_app.task(bind=True, name='voice.pronunciation_tips')
def process_pronunciation_tips_task(self, data):
    # Import inside task to ensure worker has context
    from google import genai
    from google.genai import types
    import json
    import time
    import os

    # Initialize the New Client
    client = genai.Client(api_key=os.getenv("GEMINI_API_KEY"))
    
    uid = data.get('uid')
    expected_text = data.get('expected_text', '')
    scores = data.get('scores', {})
    
    # Path to the WAV file
    wav_path = os.path.abspath(os.path.join("uploads", f"{uid}.wav"))

    if not os.path.exists(wav_path):
        return {"error": "Audio file expired or not found"}

    self.update_state(state='PROGRESS', meta={'progress': 30, 'status': 'Uploading audio to Gemini...'})

    # THE PROMPT (Kept exactly as requested)
    prompt = f"""
    # ROLE
    You are an expert IELTS Speaking Examiner and Phonetician. Your task is to provide high-fidelity feedback on a student's pronunciation.

    # INPUT DATA
    - Target Sentence: "{expected_text}"
    - Heuristic Data (Whisper): {scores}

    # INSTRUCTIONS
    1. Listen carefully to the uploaded audio.
    2. Compare the audio against the Target Sentence.
    3. Evaluate based on four specific IELTS criteria:
       - Individual Phonemes: Are specific vowel/consonant sounds accurate?
       - Word Stress: Is the primary stress placed on the correct syllable?
       - Connected Speech: Does the student use appropriate linking, elision, or assimilation?
       - Rhythm and Intonation: Is the pitch natural or robotic?
    4. Ignore background noise or minor volume fluctuations.
5. Accent Analysis: Identify the student's primary accent influence. 
   Compare their phonetics against:
   - General American (GenAm)
   - Received Pronunciation (British/RP)
   - Australian English (General/Broad)
   - Canadian English (Standard)
   - South African or New Zealand English (if applicable)
   - L1-Influenced (e.g., Vietnamese-English, Chinese-English, etc.)
    # OUTPUT SPECIFICATIONS
    - Return a JSON object ONLY.
    - No markdown blocks, no conversational filler.
    - Ensure "ielts_band_hint" is a specific range (e.g., "6.5 - 7.0").
    
    # JSON STRUCTURE
    {{
      "summary": "A 2-sentence professional overview of their delivery.",
      "issues": [
        {{ 
          "type": "Consonant Cluster / Vowel Length / Intonation", 
          "problem": "Describe exactly what the student did wrong.", 
          "how_to_fix": "Provide a physical instruction, e.g., 'Flatten your tongue more...'" 
        }}
      ],
      "word_focus": [
        {{ 
          "word": "The specific word from the target text", 
          "ipa": "The correct IPA transcription",
          "tip": "How to pronounce this specific word correctly." 
        }}
      ],
      "accent_analysis": {{
    "detected_style": "British / American / Australian / Canadian / L1-Influenced / Neutral",
    "confidence_score": "0.0 - 1.0",
    "key_markers": "Briefly describe the phonetic markers found (e.g., 'Flapping of intervocalic /t/ suggests North American influence', or 'Rising terminal intonation suggests Australian influence').",
    "consistency_rating": "How consistently they maintain this specific accent style throughout the sentence."
      }},
      "ielts_band_hint": "Band X.X"
    }}
    """

    audio_file = None
    try:
        # 1. Upload to Gemini using the new Client.Files API
        audio_file = client.files.upload(file=wav_path)
        
        # 2. REQUIRED: Wait for Gemini to process the audio
        self.update_state(state='PROGRESS', meta={'progress': 45, 'status': 'Gemini is processing audio...'})
        while audio_file.state == "PROCESSING":
            time.sleep(2)
            audio_file = client.files.get(name=audio_file.name)
            
        if audio_file.state == "FAILED":
            raise Exception("Gemini failed to process the audio file.")

        self.update_state(state='PROGRESS', meta={'progress': 70, 'status': 'Gemini is analyzing your voice...'})

        # 3. Model Logic (Fixed model name and configuration structure)
        response = client.models.generate_content(
            model="gemini-3.1-pro-preview", # Use 1.5-flash for speed or 1.5-pro for depth
            contents=[prompt, audio_file],
            config=types.GenerateContentConfig(
                response_mime_type="application/json",
                temperature=0.3
            )
        )
        
        # 4. Clean up files immediately after AI is done
        client.files.delete(name=audio_file.name)
        if os.path.exists(wav_path):
            os.remove(wav_path)

        # Parse and return the result
        return json.loads(response.text)

    except Exception as e:
        # Emergency cleanup of the cloud file if possible
        if audio_file:
            try: client.files.delete(name=audio_file.name)
            except: pass
        return {"error": str(e), "status": "failed"}
