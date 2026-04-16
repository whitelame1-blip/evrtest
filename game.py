import json
import os
import mysql.connector
from google import genai
from celery_tasks.celery_config import celery_app

# --- 1. SETUP: Configuration ---

DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'aireadinggenerator')
}

# Initialize Gemini Client
client = genai.Client(api_key=os.getenv('GEMINI_API_KEY'))

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

# --- 2. AI LOGIC: Robust Generation ---

def generate_gemini_questions(difficulty, user_level, num_questions, focus):
    # Enhanced Difficulty Rules for Prompting
    rules = {
        "easy": {
            "grammar": "Basic tenses, pronouns, and subject-verb agreement. Focus on A1-A2 level errors.",
            "vocab": "Common survival English, daily routines, and basic synonyms."
        },
        "medium": {
            "grammar": "Perfect tenses, modals, and first/second conditionals. Focus on B1-B2 level traps.",
            "vocab": "IELTS 5.5-6.5 lexical items, phrasal verbs, and common collocations."
        },
        "hard": {
            "grammar": "Third conditionals, passive voice nuances, inversions, and relative clauses.",
            "vocab": "Academic IELTS 7.5+ vocabulary, idiomatic nuance, and formal registers."
        }
    }

    # Dynamic Focus Determination
    if focus == 'grammar':
        mission_goal = f"TEST GRAMMATICAL PRECISION: {rules[difficulty]['grammar']}"
        cat_name = "Grammar Overload"
    elif focus == 'vocabulary':
        mission_goal = f"TEST LEXICAL RANGE: {rules[difficulty]['vocab']}"
        cat_name = "Lexical Strike"
    elif focus == 'paraphrase':
        mission_goal = "TEST PARAPHRASING: The question must provide a complex sentence, and the answer must be the most accurate semantic equivalent."
        cat_name = "Tactical Paraphrase"
    else:
        mission_goal = f"MIXED OPERATION: Balance {rules[difficulty]['grammar']} and {rules[difficulty]['vocab']}"
        cat_name = "Mixed Operation"

    # Robust Prompt Construction
    prompt_text = f"""
    ROLE: You are an expert ELT (English Language Teaching) Game Designer.
    OBJECTIVE: Generate {num_questions} multiple-choice questions for an English learning RPG.
    
    TARGET AUDIENCE:
    - Tier: {difficulty.upper()} 
    - Player Level: {user_level}
    - Mission Goal: {mission_goal}

    STRICT DESIGN CONSTRAINTS:
    1. SCENARIO: Every question MUST start with a 1-sentence immersive scenario (Cyberpunk, Space Exploration, or Noir Spy).
    2. BREVITY: Total question length (scenario + question) must be under 30 words.
    3. DISTRACTORS: Options B, C, and D must be 'High-Frequency Errors' for this specific difficulty level.
    4. EXPLANATION: Write a playful, 1-sentence feedback snippet (e.g., 'Mission Failed! Remember: "He goes" is the standard uplink protocol.').
    5. DATA INTEGRITY: Return valid JSON only. No markdown formatting.

    REQUIRED JSON STRUCTURE:
    {{
      "questions": [
        {{
          "category": "{cat_name}",
          "question": "Scenario: ... Question: ...",
          "a": "string",
          "b": "string",
          "c": "string",
          "d": "string",
          "correct": "a/b/c/d",
          "explanation": "string",
          "points": 10
        }}
      ]
    }}
    """

    try:
        response = client.models.generate_content(
            model="gemini-3.1-pro-preview", # Recommended over 2.5 which isn't standard yet
            config={
                "response_mime_type": "application/json",
                "temperature": 0.8, # Higher temperature for better scenario creative writing
                "system_instruction": "You are a professional game-content generator for an English learning platform. You output strictly valid JSON."
            },
            contents=prompt_text
        )
        
        # Parse and return
        raw_data = json.loads(response.text)
        return raw_data.get('questions', [])
        
    except Exception as e:
        print(f"ERROR: Gemini Generation Failed: {e}")
        return []

# --- 3. CELERY TASK: Database & Orchestration ---

@celery_app.task(bind=True, name='process_game_generation')
def process_game_generation(self, data):
    """
    Background task to generate game questions.
    Args:
        data (dict): Contains user_id, difficulty, level, num_questions, focus.
    """
    print(f"🚀 MISSION START: Processing request for User {data.get('user_id')}")

    try:
        # Extract and Sanitize
        user_id = data.get('user_id')
        difficulty = data.get('difficulty', 'easy')
        user_level = data.get('level', 1)
        num_questions = int(data.get('num_questions', 10))
        focus = data.get('focus', 'mixed')

        if not user_id:
            return {"status": "error", "message": "Missing user_id"}

        # 1. AI Content Generation
        questions = generate_gemini_questions(difficulty, user_level, num_questions, focus)
        
        if not questions:
            return {"status": "error", "message": "AI failed to generate content"}

        # 2. Database Integration
        conn = get_db_connection()
        cursor = conn.cursor()

        try:
            # Step A: Create the Mission Entry
            xp_map = {'easy': 25, 'medium': 50, 'hard': 200}
            cursor.execute(
                "INSERT INTO game_missions (user_id, difficulty, xp_reward, status) VALUES (%s, %s, %s, 'active')",
                (user_id, difficulty, xp_map.get(difficulty, 50))
            )
            mission_id = cursor.lastrowid

            # Step B: Batch Insert Questions
            question_sql = """
                INSERT INTO mission_questions 
                (mission_id, category, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            
            batch_data = [
                (
                    mission_id, 
                    q.get('category', focus.capitalize()), 
                    q.get('question'), 
                    q.get('a'), q.get('b'), q.get('c'), q.get('d'), 
                    q.get('correct', 'a'), 
                    q.get('explanation')
                ) for q in questions
            ]

            cursor.executemany(question_sql, batch_data)

            # Step C: Deduct Attempt Point
            # We only deduct if the insertion was successful up to this point
            deduct_sql = "UPDATE credits SET game_solo_attempts = game_solo_attempts - 1 WHERE user_id = %s AND game_solo_attempts > 0"
            cursor.execute(deduct_sql, (user_id,))

            # Finalize Transaction
            conn.commit()
            print(f"✅ MISSION SYNCED: Mission ID {mission_id} created for User {user_id}")
            
            return {"status": "success", "mission_id": mission_id}

        except mysql.connector.Error as db_err:
            conn.rollback()
            print(f"❌ DATABASE ERROR: {db_err}")
            return {"status": "error", "message": "Database transaction failed"}
        finally:
            cursor.close()
            conn.close()

    except Exception as e:
        print(f"🔥 CRITICAL TASK ERROR: {e}")
        return {"status": "error", "message": str(e)}