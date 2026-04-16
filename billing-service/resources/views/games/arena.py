import os
import json
import threading
import random
import mysql.connector
import google.generativeai as genai
from flask import Flask, request, jsonify

# 1. SETUP GEMINI
genai.configure(api_key="AIzaSyB1SFqq2xXd5ghTzYkGkL1on3-wRNiRpY0")
model = genai.GenerativeModel('models/gemini-2.5-pro')

app = Flask(__name__)

def call_gemini_api(topic, q_type, diff):
    # Fallback for Free users
    if topic == "random":
        topic = random.choice(["Climate Change", "Artificial Intelligence", "Ancient Civilizations", "Global Economics", "Space Exploration"])
    
    prompt = f"""
    Create 5 IELTS Reading multiple-choice questions.
    Topic: {topic}
    Type: {q_type}
    Difficulty: IELTS Band {diff if diff != 'adaptive' else '6.5'}

    Return ONLY a raw JSON array. Do not include markdown code blocks or explanations outside the JSON.
    Each object must have these exact keys:
    "text": (The question text),
    "a": (Option A),
    "b": (Option B),
    "c": (Option C),
    "d": (Option D),
    "correct": (The letter 'a', 'b', 'c', or 'd'),
    "explanation": (Brief reasoning)
    """

    response = model.generate_content(prompt)
    
    # Clean up the response (Gemini sometimes adds ```json ... ```)
    clean_json = response.text.replace('```json', '').replace('```', '').strip()
    return json.loads(clean_json)

def generate_and_insert(match_id, topic, q_type, diff):
    try:
        # 1. Get AI Questions
        questions = call_gemini_api(topic, q_type, diff)

        # 2. Database Connection
        db = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="aireadinggenerator"
        )
        cursor = db.cursor()

        # 3. Insert into arena_math_questions (your specific table)
        query = """INSERT INTO arena_match_questions 
                   (match_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) 
                   VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"""
        
        for q in questions:
            cursor.execute(query, (
                match_id, q['text'], q['a'], q['b'], q['c'], q['d'], q['correct'], q['explanation']
            ))

        # 4. Update Match Status in 'matches' table
        cursor.execute("UPDATE matches SET status = 'ready' WHERE match_id = %s", (match_id,))
        
        db.commit()
        cursor.close()
        db.close()
        print(f"Match {match_id} logic completed successfully.")

    except Exception as e:
        print(f"Critical Error in generate_and_insert: {e}")

@app.route('/generate_arena_questions', methods=['POST'])
def handle_generate():
    data = request.json
    thread = threading.Thread(target=generate_and_insert, args=(
        data['match_id'], data['topic'], data['type'], data['diff']
    ))
    thread.start()
    return jsonify({"status": "processing"}), 202

if __name__ == '__main__':
    app.run(port=5000, debug=True)