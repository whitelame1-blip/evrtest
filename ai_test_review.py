

import os
import json
import mysql.connector
from celery_tasks.celery_config import celery_app
from google import genai
from dotenv import load_dotenv

# 1. Load environment variables
load_dotenv()
db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'aireadinggenerator')
}

# 2. Configure the Client
# Note: Keep the client outside the task for performance, 
# but call the model INSIDE the task.
client = genai.Client(api_key=os.environ.get("GEMINI_API_KEY"))

@celery_app.task(bind=True, name='test_review_analysis_task')

def test_review_analysis_task(self, data):
    self.update_state(state='PROGRESS', meta={'progress': 10, 'status': 'Started cooking your order...'})
    user_id = data.get('user_id')
    test_id = data.get('test_id')
    result_id = data.get('result_id')
    test_type = data.get('test_type', '').upper()
    print(f"Received task for user_id: {user_id}, test_id: {test_id}, result_id: {result_id}, test_type: {test_type}")
    # 3. Connect to MySQL (Using 127.0.0.1 to avoid Named Pipe error)
    self.update_state(state='PROGRESS', meta={'progress': 30, 'status': 'Uploading data...'})
    try:
        db = mysql.connector.connect(**db_config)
    # Ensure dictionary=True is strictly applied here
        cursor = db.cursor(dictionary=True)

    # 1. Check current balance
        cursor.execute("SELECT balance FROM credits WHERE user_id = %s", (user_id,))
        user_row = cursor.fetchone()

        if not user_row or user_row['balance'] < 1:
            return {"error": "Insufficient credits to perform AI analysis."}

    # 2. Deduct 1 credit (Atomic update)
        cursor.execute("UPDATE credits SET balance = balance - 1 WHERE user_id = %s", (user_id,))
        db.commit()

    # 3. RE-FETCH the fresh balance (Your safe source of truth)
        cursor.execute("SELECT balance FROM credits WHERE user_id = %s", (user_id,))
        fresh_row = cursor.fetchone()
    
    # Extract the value safely
        final_balance = fresh_row['balance'] if fresh_row else 0
    except Exception as e:
        return {"error": f"Database Connection Failed: {str(e)}"}

    try:
       
        self.update_state(state='PROGRESS', meta={'progress': 40, 'status': 'Credits deducted. Analyzing performance...'})
        source_text = ""
        questions_data = ""
        user_responses = ""

        # 4. Fetch Data based on Test Type
        if test_type == 'LISTENING':
            cursor.execute("SELECT transcript, questions FROM practice_sessions WHERE id = %s", (test_id,))
            test_row = cursor.fetchone()
            
            cursor.execute("SELECT user_answers FROM practice_results WHERE id = %s", (result_id,))
            result_row = cursor.fetchone()

            if not test_row or not result_row:
                return {"error": "Data not found for Listening review"}

            source_text = test_row['transcript']
            questions_data = test_row['questions']
            user_responses = result_row['user_answers']

        elif test_type == 'READING':
            cursor.execute("SELECT passage, questions FROM reading_tests WHERE id = %s", (test_id,))
            test_row = cursor.fetchone()

            cursor.execute("SELECT answers_json FROM user_test_responses_all WHERE id = %s", (result_id,))
            result_row = cursor.fetchone()

            if not test_row or not result_row:
                return {"error": "Data not found for Reading review"}

            source_text = test_row['passage']
            questions_data = test_row['questions']
            user_responses = result_row['answers_json']
        print(source_text, questions_data, user_responses)
        # 5. Construct the AI Prompt
        prompt = f"""
        You are an elite IELTS/SAT Master Tutor and Psychometrician. Your objective is to diagnose a student's cognitive and linguistic gaps based on their {test_type} test performance.
        
        You must be highly analytical, direct, and constructive. Do not give generic advice. Base every observation STRICTLY on the provided context.

        ========== INPUT DATA ==========
        CONTEXT (PASSAGE/TRANSCRIPT):
        {source_text}

        TEST QUESTIONS:
        {questions_data}

        USER PERFORMANCE:
        {user_responses}
        ================================

        ========== TASK & RULES ==========
        1. Analyze the relationship between the questions, the user's wrong answers, and the source text.
        2. Identify specific IELTS/SAT traps (e.g., synonym matching, false transitions, distractor numbers, word limit violations).
        3. If the user's answers appear to be random gibberish or unattempted, explicitly state this in the overall analysis and skip deep logical breakdowns.
        4. In your detailed breakdown, YOU MUST quote the exact phrase from the source text that proves the correct answer.
        ========== ADDITIONAL TASK: VOCABULARY UPGRADE ==========
        Identify 6-8 high-level vocabulary words or "Synonym Pairs" from the text that were critical to answering the questions. 
        A "Synonym Pair" is the relationship between a word used in the Question and the different word used in the Passage that means the same thing.
        ========== OUTPUT FORMAT ==========
        Respond ONLY with a valid JSON object strictly matching this schema. Do not include markdown formatting or outside text.

        {{
            "overall_analysis": "A blunt, 2-3 sentence diagnostic summary of their performance.",
            "mistake_patterns": [
                "Specific pattern 1 (e.g., 'Struggles with tracking negative transitions like However/Although')",
                "Specific pattern 2"
            ],
            "trap_logic": "An explanation of the most difficult 'trap' in this specific test, and how the test-makers designed it to trick students.",
            "focus_areas": [
                "Actionable, highly specific study directive 1",
                "Actionable, highly specific study directive 2",
                "Actionable, highly specific study directive 3"
            ],
            "detailed_breakdown": [
                {{
                    "question_number": "1",
                    "user_answer": "their answer",
                    "correct_answer": "the actual answer",
                    "text_evidence": "Exact quote from the passage/transcript proving the answer.",
                    "explanation": "Deep pedagogical breakdown of WHY their answer was wrong, WHY the correct answer is right, and HOW to spot this next time."
                }}
            ],
            "vocabulary_expansion": [
                {{
                    "term": "The sophisticated word from the text",
                    "context_quote": "The sentence where it appeared",
                    "synonym_in_test": "The simpler word used in the question/options",
                    "definition": "Simple pedagogical definition",
                    "usage_tip": "How to use this word in the Speaking/Writing test"
                }}
            ],
        }}
        """
        # 6. Call AI (Correct Syntax for 2026 SDK)
        # Using gemini-2.0-flash as it is faster and cheaper for JSON generation
        response = client.models.generate_content(
            model='gemini-3.1-pro-preview', 
            contents=prompt
        )
        self.update_state(state='PROGRESS', meta={'progress': 60, 'status': 'Analyzing your performance...'})
        ai_feedback = response.text
        
        # Clean AI response
        if "```json" in ai_feedback:
            ai_feedback = ai_feedback.split("```json")[1].split("```")[0].strip()

        # 7. Save to dedicated table
        # We include task_id (self.request.id) so the frontend can find it
        self.update_state(state='PROGRESS', meta={'progress': 90, 'status': 'Wrapping up...'})
        insert_query = """
            INSERT INTO ai_review_results 
            (user_id, test_id, result_id, test_type, review_json)
            VALUES (%s, %s, %s, %s, %s)
        """
        cursor.execute(insert_query, (
            user_id, 
            test_id, 
            result_id, 
            test_type, 
            ai_feedback
        ))
        
        # 8. Update task status for polling
        
        db.commit()
        return {"status": "success", "task_id": self.request.id,"new_balance": final_balance, "result_id": result_id, "test_type": test_type } 

    except Exception as e:
        if db: db.rollback()
        return {"error": str(e)}
    finally:
        if 'cursor' in locals(): cursor.close()
        if 'db' in locals(): db.close()