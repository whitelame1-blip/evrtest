
import os
import io
import json
import requests
import mysql.connector
from flask import  jsonify, request
import time
import random
from google import genai
from google.genai import types
from json_repair import repair_json
import subprocess
import base64
import concurrent.futures
from tempfile import NamedTemporaryFile
from celery_tasks.celery_config import celery_app
from dotenv import load_dotenv
from pathlib import Path

# --- CELERY CONFIGURATION ---from dotenv import load_dotenv
load_dotenv(override=True)

# --- CONFIGURATION (Zero Hardcoding) ---
base_path = Path(__file__).resolve().parent

# 2. Point specifically to the .env file in that same folder
# If your .env is one level up, use base_path.parent / '.env'
env_path = base_path / '.env'
LARAVEL_URL = os.getenv("LARAVEL_REFUND_URL")
SECRET = os.getenv("AI_INTERNAL_SECRET") 
base_url = os.getenv("BASE_URL", "http://127.0.0.1:5000")
# 3. Load it with override=True to force it over the Laravel/System vars
load_dotenv(dotenv_path=env_path, override=True)
PROJECT_ROOT = Path(__file__).resolve().parent.parent 
STATIC_DIR = PROJECT_ROOT / "static"

# Ensure the folder exists in the correct location
if not STATIC_DIR.exists():
    STATIC_DIR.mkdir(parents=True, exist_ok=True)
ELEVENLABS_API_KEY = os.getenv("ELEVENLABS_API", "").strip()

# Safely verify it's the right one (last 4 digits)
if ELEVENLABS_API_KEY:
    print(f"Loaded Python-Specific Key ending in: ...{ELEVENLABS_API_KEY[-4:]}")
else:
    print("Failed to load ELEVENLABS_API from:", env_path)
db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'aireadinggenerator')
}

if not os.path.exists("static"): os.makedirs("static")

# --- HELPER FUNCTIONS (Unchanged) ---
def deduct_user_credits(user_id, amount):
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        cursor.execute("SELECT balance FROM credits WHERE user_id = %s", (user_id,))
        result = cursor.fetchone()
        
        if not result or result[0] < amount:
            cursor.close()
            conn.close()
            return False
            
        cursor.execute("UPDATE credits SET balance = balance - %s WHERE user_id = %s", (amount, user_id))
        conn.commit()
        cursor.close()
        conn.close()
        return True
    except Exception as e:
        print(f"Credit Deduction Error: {e}")
        return False

def check_user_balance(user_id, q_type, section):
    required_amount = 2 if q_type == 'mixed' else 1
    if section == 1:
        if q_type == 'mixed':
            required_amount = 3
        else:
            required_amount = 2
    elif section == 2:
        if q_type == 'mixed':
            required_amount = 4
        else:
            required_amount = 3
    elif section == 3:
        if q_type == 'mixed':
            required_amount = 5
        else:
            required_amount = 4
    elif section == 4:
        if q_type == 'mixed':
            required_amount = 6
        else:
            required_amount = 5
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True, buffered=True) 
        cursor.execute("SELECT balance FROM credits WHERE user_id = %s", (user_id,))
        row = cursor.fetchone()
        cursor.close()
        conn.close()
        
        if not row:
            return False, 0, "User not found or no credit record."

        current_balance = row['balance']
        if current_balance <= 0:
            return False, 0, "Balance is empty. Generation stopped."
        if current_balance < required_amount:
            return False, current_balance, f"Insufficient credits. Required: {required_amount}, Found: {current_balance}"

        return True, current_balance, "Success"
        
    except Exception as e:
        print(f"Database Error in check_user_balance: {e}")
        return False, 0, f"System Error: {str(e)}"

SECTION_SPECS = {
    "1": {"desc": "social transactional dialogue (2 people)", "words": 750, "rule": "ONE WORD AND/OR A NUMBER", "constraint": "Answers must be exactly one word, exactly one number, or one word followed by one number. Compound names (e.g., 'Via Roma') must be handled by placing one word in the suffix/prefix.", "logic_adj": "Difficulty comes from spelling traps, rapid numbers, and immediate self-corrections."},
    "2": {"desc": "social monologue (1 person)", "words": 900, "rule": "NO MORE THAN TWO WORDS", "constraint": "Numbers are not allowed unless written as words. Max two words total.", "logic_adj": "Difficulty comes from descriptive synonyms and 'distractor' lists of options."},
    "3": {"desc": "academic discussion (2 people)", "words": 1100, "rule": "NO MORE THAN TWO WORDS AND/OR A NUMBER", "constraint": "Standard academic level. Dates and complex names allowed.", "logic_adj": "Difficulty comes from multi-speaker disagreement and shifting opinions."},
    "4": {"desc": "academic lecture (1 person)", "words": 1250, "rule": "NO MORE THAN THREE WORDS", "constraint": "Scientific or technical terms allowed. No number limit.", "logic_adj": "Difficulty comes from abstract concepts, formal academic paraphrasing, and dense information."}
}

def get_trap_instructions(difficulty, allow_digits=True):
    if difficulty.lower() == "easy":
        num_rule = "Room 14... sorry, Room 40" if allow_digits else "Room fourteen... sorry, Room forty"
        return f"""### TRAP INSTRUCTIONS (EASY):
        - CLEAR SIGNPOSTING: The speakers must clearly announce when they are moving to a new topic.
        - NO TRICKS: State the required information directly. The answer should closely follow the keywords in the question.
        - PERMITTED DISTRACTOR: You may use one simple self-correction involving numbers or spelling (e.g., '{num_rule}').
        - FORBIDDEN: Do not use complex synonyms for keywords. Do not change the topic abruptly or hide the answer in long monologues."""
    elif difficulty.lower() == "medium":
        traps = [
            "SELF-CORRECTION (FALSE START): The speaker begins stating a plan, pauses, and completely pivots. (e.g., 'I was thinking of taking the bus... actually, no, the train is faster.')",
            "SYNONYM SWAP: The speaker must NEVER use the exact keyword found in the question. They must use a parallel phrase. (e.g., if the question asks for 'cheap', the speaker says 'very economical').",
            "LISTING DISTRACTOR: The speaker lists 3 highly plausible options, but explicitly disqualifies two of them based on a specific constraint.",
            "SPELLING/NUMBER TRAP: The speaker spells a name or reads a number, but corrects a single character mid-way. (e.g., 'S-M-I-T... wait, no, S-M-Y-T-H').",
            "PARTIAL AGREEMENT: Speaker A suggests an idea. Speaker B agrees with part of it, but changes the final conclusion. (e.g., 'Yes, the location is great, but the price is too high, so let's look elsewhere.')"
        ]
        selected = random.sample(traps, 2)
        return "### TRAP INSTRUCTIONS (MEDIUM):\n" + "\n".join([f"- {t}" for t in selected])
    else:
        traps = [
            "THE CONDITIONAL COLLAPSE: Speaker A lists an option that perfectly matches the criteria, but Speaker B introduces a retroactive constraint that disqualifies it (e.g., 'That would work, provided the grant was approved last fiscal year—which it wasn't.').",
            "THE META-DEBATE: The speakers don't argue about facts; they argue about the *interpretation* of facts. The listener must infer the final conclusion based on who yields the argument.",
            "THE JARGON SHIELD (OBFUSCATION): Bury the answer inside dense, high-level vocabulary. Do not use synonyms; abstract the concept entirely (e.g., instead of 'we cancelled it because it rained', use 'the initiative was suspended due to unforeseen meteorological parameters').",
            "THE ASYMMETRIC CORRECTION: A 'student' or 'junior' speaker confidently states a detailed, highly plausible fact. The 'expert' speaker corrects them, but only corrects *one specific part* of the statement, implying the rest is true.",
            "THE DISTRIBUTED ANSWER: The answer requires synthesizing two separate pieces of information stated at least 30 seconds apart in the dialogue."
        ]
        selected = random.sample(traps, 3)
        out = "### TRAP INSTRUCTIONS (HARD - C1/C2 Level):\n- CRITICAL RULE: Answers must almost never be stated directly using the phrasing of the question. Require the listener to infer the answer through context.\n"
        out += "\n".join([f"- {t}" for t in selected])
        if not allow_digits:
            out += "\n- FORMATTING STRICTNESS: When specifying numbers, dates, or prices in the dialogue, write them as fully spelled-out words (e.g., 'twenty-five') to comply with character constraints."
        return out

client = genai.Client(api_key=os.getenv("GEMINI_API_KEY"))

def ask_groq_json(system_instruction, user_prompt, difficulty):
    try:
        response = client.models.generate_content(
            model="gemini-3.1-pro-preview",
            contents=user_prompt,
            config=types.GenerateContentConfig(
                system_instruction=system_instruction,
                temperature=0.7,
                response_mime_type="application/json"
            )
        )
        return response.text
    except Exception as e:
        print(f"Gemini API Error: {e}")
        return json.dumps({"error": "Failed to generate content", "details": str(e)})









def generate_audio_from_script(script_list, speakers_from_frontend, output_filename):
    # Paths
    final_file_path = str(STATIC_DIR / output_filename)
    bg_noise_path = os.path.join("assets", "ielts_room_tone.mp3") # Ensure this exists
    
    # 1. Voice Mapping
    
    speaker_map = {}
    for entry in script_list:
        name = entry['speaker']
        if name not in speaker_map:
            # Look for the gender the AI assigned to this speaker
            target_gender = entry.get('gender', 'female').lower() 
            
            # Find a matching voice from the frontend list
            # We use a fallback [0] or [1] in case the frontend list is missing a gender
            matched_voice = next(
                (v for v in speakers_from_frontend if v.get('gender', '').lower() == target_gender), 
                speakers_from_frontend[0] if target_gender == 'female' else speakers_from_frontend[1]
            )
            speaker_map[name] = matched_voice

    # 2. Parallel Fetcher Function
    def fetch_voice_chunk(index, entry):
        config = speaker_map[entry['speaker']]
        url = f"https://api.elevenlabs.io/v1/text-to-speech/{config['voice_id']}?output_format=mp3_44100_128"
        headers = {"xi-api-key": ELEVENLABS_API_KEY, "Content-Type": "application/json"}
        
        payload = {
            "text": entry['utterance'].strip(),
            "model_id": "eleven_turbo_v2_5",
            "voice_settings": {
                "stability": config['settings']['stability'],
                "similarity_boost": config['settings']['similarity_boost'],
                "style": config['settings']['style'],
                "use_speaker_boost": True
            }
        }

        try:
            res = requests.post(url, json=payload, headers=headers, timeout=30)
            if res.status_code == 200:
                # Save to a temporary file for FFmpeg to handle later
                temp_chunk = NamedTemporaryFile(delete=False, suffix=".mp3")
                temp_chunk.write(res.content)
                temp_chunk.close()
                return index, temp_chunk.name
            else:
                # Add this to see WHY it failed (e.g., 401 Unauthorized)
                print(f"ElevenLabs Error {res.status_code}: {res.text}")
            return index, None
        except Exception:
            return index, None

    # 3. Execution via ThreadPoolExecutor
    # max_workers=10 is safe for ElevenLabs; higher might trigger rate limits
    chunk_files = [None] * len(script_list)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as executor:
        future_to_index = {executor.submit(fetch_voice_chunk, i, entry): i for i, entry in enumerate(script_list)}
        for future in concurrent.futures.as_completed(future_to_index):
            idx, file_path = future.result()
            if file_path is None: return None # Fail if any part fails
            chunk_files[idx] = file_path

    # 4. FFmpeg Magic: Stitching, Silence, and Background Noise
    try:
        # Build the complex filter string
        # We add 600ms (0.6s) of delay to every chunk except the first
        filter_complex = ""
        inputs = []
        for i, file in enumerate(chunk_files):
            inputs.extend(['-i', file])
            # Delay the start of each segment (except the first) by 600ms
            delay = 0 if i == 0 else 600
            filter_complex += f"[{i}:a]adelay={delay}|{delay}[a{i}];"
        
        # Concat the voice chunks
        concat_input = "".join([f"[a{i}]" for i in range(len(chunk_files))])
        filter_complex += f"{concat_input}concat=n={len(chunk_files)}:v=0:a=1[voice_only];"
        
        # Add background noise if file exists
        final_inputs = inputs
        if os.path.exists(bg_noise_path):
            final_inputs.extend(['-i', bg_noise_path])
            bg_idx = len(chunk_files)
            # Mix voice with background (bg volume at 0.05 to keep it subtle)
            filter_complex += f"[{bg_idx}:a]volume=0.05,aloop=loop=-1:size=2e9[bg];"
            filter_complex += f"[voice_only][bg]amix=inputs=2:duration=shortest:dropout_transition=2,loudnorm[outa]"
        else:
            filter_complex += "[voice_only]loudnorm[outa]"

        # Run FFmpeg
        try:
            subprocess.run([
                'ffmpeg', *final_inputs,
                '-filter_complex', filter_complex,
                '-map', '[outa]',
                '-codec:a', 'libmp3lame', '-qscale:a', '2',
                final_file_path, '-y'
            ], check=True, 
                stdout=subprocess.DEVNULL,  # Send progress logs to the void (prevents freezing)
                stderr=subprocess.PIPE,     # Keep errors so we can debug
                text=True                   # Decode bytes to string automatically
                )# Added text=True
            
        except subprocess.CalledProcessError as e:
            # THIS IS THE MAGIC LINE: It prints the exact FFmpeg crash log
            print(f"\n[CRITICAL FFMPEG ERROR]:\n{e.stderr}\n")
            return None

    finally:
        # Cleanup temporary chunks
        for f in chunk_files:
            if f and os.path.exists(f): os.remove(f)

    return final_file_path
def partition_multi_select(n):
    groups = []
    temp_n = n
    while temp_n > 0:
        if temp_n == 4: 
            groups.extend([2, 2])
            break
        if temp_n >= 3:
            groups.append(3)
            temp_n -= 3
        else:
            groups.append(2)
            temp_n -= 2
    return groups

# --- FLASK ROUTES (Synchronous API calls) ---



# --- FLASK ROUTES (Asynchronous Celery Trigger) ---



# --- CELERY TASK (The Heavy Lifter) ---

@celery_app.task(bind=True, name='process_listening_generation')
def process_listening_generation(self, data):
    current_task_id = self.request.id
    print(f"Loaded Python-Specific Key ending in: ...{ELEVENLABS_API_KEY[-4:]}")
    full_transcript_text = "Transcription unavailable." 
    script_data = {"script": []}
    """
    This contains the exact, un-altered logic from the original generate_section route.
    Returns dictionaries instead of Flask jsonify objects.
    """
    user_id = data.get('user_id')
    q_type = data.get('q_type', 'completion')
    cost = data.get('cost', '2')
    db = None
    filename = f"ielts_{os.urandom(2).hex()}.mp3" 
    map_url = None
    map_svg_str = None
    visual_data_str = json.dumps("")
    section_costs = {
        1: 2,
        2: 3,
        3: 4,
        4: 5
    }
        
    try:        
        speakers_list = data.get('speakers', [])
        user_id = data.get('user_id')
        raw_topic = data.get('topic', 'RANDOM')
        section = str(data.get('section', '1'))
        difficulty = data.get('difficulty', 'medium')
        q_type = data.get('question_type', 'mixed')
        selected_subtypes = data.get('mixed_type', ["Sentence_completion"])
        spec = SECTION_SPECS.get(section, SECTION_SPECS["1"])
        base_cost = section_costs.get(int(section), 2)
        
        print(f"Received generation request for User {user_id} - Section {section}, Difficulty: {difficulty}, Question Type: {q_type}, Subtypes: {selected_subtypes}")
    # Add a 'Mixed Type' premium (optional)
    # If they want two question types in one audio, it adds complexity
        if q_type == 'mixed':
            required_credits = base_cost + 1
        else:
            required_credits = base_cost
    # 2. Check balance BEFORE calling LLM or Audio APIs
        has_balance, balance, message = check_user_balance(user_id, q_type, section)

        if not has_balance:
        # Return an error dict (Acts as a 403 Forbidden in Celery context)
            return {"error": message, "current_balance": balance}
        print(f"Generating Section {section} with topic '{raw_topic}', difficulty '{difficulty}', question type '{q_type}', and subtypes {selected_subtypes}")
        
        if raw_topic == "RANDOM":
            topic_instruction = f"Invent a specific, realistic scenario suitable for {spec['desc']}."
        else:
            topic_instruction = f"Topic: {raw_topic}. Ensure the scenario fits the format of {spec['desc']}."

        script_diff_rules = {
            "easy": (
                "Linguistic Level: A2/B1 (High School).\n"
                "- Structure: Strictly linear. Each answer appears in order (Q1 before Q2).\n"
                "- Sentence Style: Short, simple sentences with basic connectors ('and', 'but').\n"
                "- Vocabulary: High-frequency words only. Use LITERAL REPETITION (e.g., if the question asks 'cost', the audio says 'cost').\n"
                "- Distractors: Minimal. Only simple number corrections (e.g., '15... sorry, 50').\n"
                "- Pacing: Slow and clear, no overlaps."
            ),
            "medium": (
                "Linguistic Level: B2 (Undergraduate).\n"
                "- Structure: Use explicit signposting ('Moving on to...', 'Now regarding...').\n"
                "- Sentence Style: Compound sentences with clear connectors ('however', 'although').\n"
                "- Vocabulary: Use SYNONYMS (e.g., prompt: 'cheap' -> audio: 'inexpensive').\n"
                "- Distractors: At least 3 self-corrections or negatives ('I thought it was X, but actually it is Y').\n"
                "- Pacing: Moderate, with occasional hesitation or rephrasing.\n"
                "- Logic: Answers are stated clearly but may require noticing contrasts."
            ),
            "hard": (
                "Linguistic Level: C1/C2 (Postgraduate/Professional).\n"
                "- Structure: TANGENTIAL AND NON-LINEAR. Speakers frequently go off on tangents before returning to the main point, placing immense strain on the listener's working memory.\n"
                "- Sentence Style: Heavy use of passive voice, complex relative clauses, and nominalization (e.g., 'The implementation of the protocols' instead of 'When we implemented the protocols').\n"
                "- Pacing: Fast, with overlapping interruptions, mid-sentence abandons, and speakers finishing each other's thoughts.\n"
                "- Logic: The 'Signal-to-Noise' ratio must be low. Bury the required information in paragraphs of deep, heavily academic context.\n"
                "- Distractors: Use the 'Partial Concession'. A speaker agrees with the premise of an argument but completely rejects the conclusion.\n"
                "- Cohesion: Use abstract referencing ('Regarding the former point...', 'That specific caveat...')."
            )
        }
        
        ques_diff_rules = {
            "easy": (
                "STRATEGY: KEYWORD SPOTTING.\n"
                "- Task: Target concrete nouns, numbers, and dates.\n"
                "- Phrasing: Use EXACT words from the transcript (no paraphrasing).\n"
                "- Complexity: Questions are literal and direct (e.g., 'What was the cost?').\n"
                "- Distractors: Minimal, only simple corrections (e.g., '15... sorry, 50').\n"
                "- Focus: Recognition of explicit facts."
            ),
            "medium": (
                "STRATEGY: SYNONYM & CONTRAST RECOGNITION.\n"
                "- Task: Target meaning rather than exact wording.\n"
                "- Phrasing: Replace keywords with common synonyms (e.g., 'cheap' → 'inexpensive').\n"
                "- Logic: Include at least 3 self-corrections or negatives ('I thought it was X, but actually Y').\n"
                "- Distractors: Plausible but incorrect options that echo false starts.\n"
                "- Focus: Understanding contrasts and reformulations."
            ),
            "hard": (
                "STRATEGY: ROOT CAUSE & IMPLICATION.\n"
                "- Task: Test comprehension of underlying systems, speaker attitude, and logical conclusions.\n"
                "- Phrasing: Options must be conceptual summaries. NEVER use the exact nouns or verbs spoken in the audio.\n"
                "- Constraint: The correct answer must require the listener to synthesize at least two different statements made by the speakers.\n"
                "- Distractors: Create 'Plausible Fictions'. Distractors should be logically sound statements that *could* be true based on the topic, but were not explicitly concluded by the speakers.\n"
                "- Focus: Testing the student's ability to filter out academic noise and identify the core logical truth."
            )
        }

        current_script_diff = script_diff_rules.get(difficulty, script_diff_rules["medium"])
        current_ques_diff = ques_diff_rules.get(difficulty, ques_diff_rules["medium"])

        diff_strategy_block = f"### TARGET DIFFICULTY: {difficulty.upper()}\n### EXECUTION RULES:\n{current_ques_diff}"
        allow_digits = True
        if section == "2":
            allow_digits = False
        trap_logic = get_trap_instructions(difficulty, allow_digits=allow_digits)
        
        # =========================================================================
        # STAGE 1: SCRIPT GENERATION
        # =========================================================================
        
        subtype=""
        subtype_inst_script=""
        if q_type == "completion":
            subtype = random.choice(["TABLE","FORM","FLOW-CHART"])
            if subtype == "FORM":
                subtype_inst_script = """
                - SCENARIO: A transactional dialogue (Inquiry, Booking, or Registration) between a staff member and a customer.
                - SCRIPT DYNAMICS: Use a natural 'Question-Answer' flow. The staff member should probe with clarifying questions, while the customer occasionally hesitates or backtracks.
                - DISTRACTOR TECHNIQUE: Include 'The Correction'—one speaker provides wrong info (e.g., an outdated phone number or incorrect spelling), then immediately corrects it.
                - VERBAL SIGNALLING: Use spelling-out markers (e.g., 'Is that P for Papa?') and digit-grouping for numbers (e.g., 'four-two-six, double-three') to mimic IELTS speed.
                - AUTHENTICITY: Add fillers ('um,' 'let me think'), politeness markers ('please,' 'thank you'), and slight overlaps to simulate real conversation.
                """
            elif subtype == "FLOW-CHART":
                subtype_inst_script = """
               - SCENARIO: A presentation or detailed explanation of a linear process (e.g., manufacturing, biology, or a university application pathway).
                - SCRIPT DYNAMICS: Use a single speaker (monologue) or an expert explaining to a novice. Maintain a clear progression from one stage to the next.
                - SIGNPOSTING: Explicitly use transition words ('Moving on to...', 'The subsequent phase...', 'Once this is finalized...') to signal flow-chart box transitions.
                - DISTRACTOR TECHNIQUE: Mention an alternative or 'rejected' step that sounds plausible but is not part of the main sequence.
                - AUTHENTICITY: Include slight self-corrections ('Actually, that comes later...') and emphasize contrasts ('Not this stage, but the next one').
                """
            elif subtype == "TABLE":   
                subtype_inst_script = """
                - SCENARIO: A comparative discussion of multiple options, locations, or time periods.
                - SCRIPT DYNAMICS: Navigate horizontally (rows) and vertically (columns). The speaker should clearly identify the row (e.g., Hotel Name) before describing the column attribute (e.g., Price).
                - PARAPHRASING: Use synonyms or rephrased language. If the table says 'No charge,' the script should say 'Complimentary' or 'Free of cost.'
                - DISTRACTOR TECHNIQUE: Mention features belonging to a different row while discussing the current one, creating a 'Selection Trap.'
                - AUTHENTICITY: Add hedging ('I think this one includes...'), comparisons ('Unlike the previous option...'), and subtle emphasis ('This is slightly cheaper').
                """
        elif q_type == "multiple_choice":
            subtype = random.choice(["MULTI-SELECT"])
            if subtype == "STANDARD":
                subtype_inst_script = """
               - SCENARIO: A high-stakes academic or professional exchange where specific information must be extracted from a dense, often contradictory conversation.
- SCRIPT DYNAMICS (The Pivot): Use the 'Three-Step Reveal'. The speaker should:
    1. Initially suggest Option A (the 'False Start').
    2. Pivot to Option B as a possibility (the 'Alternative').
    3. Finally confirm Option C using a synonym, while explicitly rejecting A and B using negative constraints (e.g., 'too expensive', 'outdated', or 'logistically impossible').
- DISTRACTOR PROTOCOL: 
    - The Sound-Match Trap: Include a distractor that uses the exact keywords found in the question/option, but in a context that makes it wrong.
    - The Synonym Success: The correct answer MUST be a paraphrase. If the option is 'Reduced costs', the script should say 'slashed overheads' or 'economical'.
    - The Temporal Trap: Mention details that were true in the past or may be true in the future to distract from the 'Current' status.
- AUTHENTICITY & FLOW: Mimic natural speech patterns including:
    - Self-correction: "Wait, I'm looking at the wrong year..."
    - Interjection/Agreement: "I thought that too at first, but then I realized..."
    - Hedges: "It’s arguably X, though in strict terms, it's actually Y."
- LOGICAL FINALITY: Ensure that by the end of the segment, a listener could theoretically explain *why* the distractors were disqualified.
                """
            elif subtype == "MULTI-SELECT":
                subtype_inst_script = """
                - SCENARIO: A decision-making process where multiple items are vetted against a set of constraints.
    - SCRIPT DYNAMICS (The Filter): One speaker proposes 4-5 options. The second speaker introduces a 'Hard Constraint' (e.g., 'We only have £500,' or 'It must be completed by Monday').
    - ADVERSARIAL TRAPS:
        1. THE 'TOTAL REJECTION': Mention a distractor with high enthusiasm ('I'd love to do X!'), only to have the lead speaker kill it immediately for a logical reason.
        2. THE 'FUTURE/PAST' TRAP: Mention an activity they *used* to do or *plan* to do next year, which is NOT part of the current program.
        3. THE 'CONSENSUS' TRAP: Speaker A suggests X. Speaker B suggests Y. They eventually agree on Z. The listener must identify the final agreement, not just the suggestions.
    - AUTHENTICITY: Use 'Thinking out loud' phrases: 'Let me look at the ledger...' or 'Wait, I'm misreading this—actually...'
    - ANSWER LOGIC: Correct answers must be justified by at least two distinct speakers or one speaker confirming a change in status.
                """
            elif subtype == "MATCHING":
                subtype_inst_script = """
                - SCENARIO: A comparative analysis where features of one item are weighed against another.
    - SCRIPT DYNAMICS (The Comparison): Avoid discussing items in a linear '1, 2, 3' order. Have speakers compare Item A to Item B (e.g., 'Unlike the library, the gym doesn't require a pass').
    - ADVERSARIAL TRAPS:
        1. THE 'DOUBLE-UP' TRAP: Describe an item using keywords that fit two different matching categories. The listener must wait for a 'Determining Factor' to pick the right one.
        2. THE 'DELAYED CONFIRMATION': Mention the name of the item (e.g., 'The North Wing'), talk for 3 sentences about unrelated history, and then reveal the answer at the very end of the paragraph.
        3. THE 'IMPLICIT SYNONYM': Do not use synonyms of words; use synonyms of *concepts*. (e.g., instead of matching 'Expensive' to 'High cost,' match it to 'beyond our current fiscal allocation').
    - AUTHENTICITY: Include 'False Starts' where a speaker begins to categorize an item but is corrected by their partner ('No, that was the 2018 pilot, for this project we are doing...').
    - ANSWER LOGIC: Each match must be the result of a specific, non-negotiable attribute mentioned in the text.
    - THE 'UNIVERSAL PLAUSIBILITY' MANDATE (CRITICAL): 
        1. SEMANTIC OVERLAP: Every option (A, B, C...) must belong to the same 'Conceptual Family.' If one option is about 'Budget,' all options must involve 'Fiscal/Economic' constraints. Never mix categories (e.g., don't have one option about 'Cost' and another about 'Safety').
        2. THE 'NEAR-MISS' TECHNIQUE: For every Item (1, 2, 3...), the speakers MUST mention a trait that makes at least two other options sound plausible. 
        3. UNIFORM PHRASING: All options in the matching list must start with the same grammatical structure (e.g., all must be Past Participle phrases like 'Designed for...', 'Restricted by...', 'Reliant on...'). This prevents the user from guessing based on sentence flow.
        4. SHARED ATTRIBUTES: Every architectural zone/item must share a common 'baseline' problem (e.g., they all have historical value), so that the 'Historical' option (if it exists) feels like it could apply to anything until the specific 'Determining Factor' is mentioned.
        Prohibit the speaker from ever using the exact words found in the options. If the option is 'Financial Resources,' the speaker must say 'capital injection' or 'monetary reserves
                """
        elif q_type == "matching":
            subtype = random.choice(["FACT_MATCHING","CLASSIFICATION","OPINION","PROCEDURAL"])
            if subtype == "OPINION":
                subtype_inst_script = """
               - SCENARIO: A high-stakes academic tutorial or workplace meeting with 2-3 speakers (e.g., a skeptical student, an over-enthusiastic student, and a grounded professor).
- SCRIPT DYNAMICS: Use the 'Negotiation of Meaning' pattern. Speaker A proposes an opinion, Speaker B disagrees or adds a nuance, and Speaker C (the authority) provides the definitive verdict.
- ROBUST TRAPS: 
    1. THE CONCESSION TRAP: A speaker says "I initially thought X was great..." (inducing the user to pick 'Agree') "...but then I realized the data was skewed" (Correct answer: 'Disagree').
    2. THE ECHO TRAP: One speaker repeats another's opinion as a question ("So you think it's reliable?") which the student might mistake for agreement.
- AUTHENTICITY: Use hedging (e.g., "That's a bit of a stretch," "I wouldn't go that far," "It’s a common misconception").
- ANSWER LOGIC: The 'Answer' must match the *final resolved position* of the specific person mentioned in the item.
- THE 'UNIVERSAL PLAUSIBILITY' MANDATE (CRITICAL): 
        1. SEMANTIC OVERLAP: Every option (A, B, C...) must belong to the same 'Conceptual Family.'
        2. THE 'NEAR-MISS' TECHNIQUE: MUST mention a trait that makes at least two other options sound plausible. 
        3. UNIFORM PHRASING: All options must start with the same grammatical structure.
        4. SHARED ATTRIBUTES: Share a common 'baseline' problem.
        - THE 'ANTI-PATTERN' RESTRAINT (DISTRIBUTION LOGIC):
    1. SEQUENTIAL LIMIT: No single answer may appear more than TWO times in a consecutive sequence.
    2. KEY DIVERSITY: Every available option must be used at least once.
    3. THE 'SYMMETRY BREAK': Avoid 'Predictable Alternation'.
                """
            elif subtype == "CLASSIFICATION":
                subtype_inst_script = """
                - SCENARIO: Categorizing projects, case studies, or resources based on complex criteria.
- SCRIPT DYNAMICS: Avoid direct naming of categories. If Category A is 'Economic Focus', the script should use phrases like "injecting capital," "revenue streams," or "fiscal viability."
- ROBUST TRAPS:
    1. THE OVERLAP TRAP: Mention that an item *could* fit into Category B, but it fails a specific requirement.
    2. THE TEMPORAL TRAP: "It used to be classified as A, but since the 2024 update, we now treat it as C."
- AUTHENTICITY: Use 'Conditionals'.
- ANSWER LOGIC: Each entity must meet the *strict definition* of the category.
- THE 'UNIVERSAL PLAUSIBILITY' MANDATE (CRITICAL): 
        1. SEMANTIC OVERLAP
        2. THE 'NEAR-MISS' TECHNIQUE
        3. UNIFORM PHRASING
        4. SHARED ATTRIBUTES
                """
            elif subtype == "PROCEDURAL":
                subtype_inst_script = """
              - SCENARIO: A lab experiment protocol, an internship application process, or a construction timeline.
- SCRIPT DYNAMICS: Disrupt the linear flow.
- ROBUST TRAPS:
    1. THE REJECTION TRAP: Detailed description of a step that was *proposed* but ultimately rejected.
    2. THE SIMULTANEOUS TRAP: Mentioning two steps that happen at the same time.
- AUTHENTICITY: Use signposting that corrects itself.
- ANSWER LOGIC: Descriptions must be heavy paraphrases.
- THE 'UNIVERSAL PLAUSIBILITY' MANDATE (CRITICAL) & 'ANTI-PATTERN' RESTRAINT.
                """
            elif subtype == "FACT_MATCHING":
                subtype_inst_script = """
               - SCENARIO: A briefing on technical specs, historical dates, or contact details where information is being transcribed or verified.
- SCRIPT DYNAMICS: Use 'The Correction Loop'.
- ROBUST TRAPS:
    1. THE PHONETIC TRAP: Use similar-sounding letters or numbers.
    2. THE CONTEXTUAL SHIFT: "The lecture starts at 9:00, but the doors open at 8:30..."
- AUTHENTICITY: Include natural fillers and spelling checks.
- ANSWER LOGIC: One-to-one mapping with high-precision distractors.
- THE 'UNIVERSAL PLAUSIBILITY' MANDATE (CRITICAL) & 'ANTI-PATTERN' RESTRAINT.
                """
       
        elif q_type == "mixed":
            SUBTYPE_POOL = {
                "Sentence_completion": ["TABLE", "FORM", "FLOW-CHART"],
                "Multiple_choice": ["MULTI-SELECT", "STANDARD", "MATCHING"],
                "Matching": ["FACT_MATCHING", "CLASSIFICATION", "OPINION", "PROCEDURAL"]
            }

            INSTRUCTION_TEXTS = {
                "FORM": """- SCENARIO: A transactional dialogue...""",
                "TABLE": """- SCENARIO: A technical comparison of multi-variable options...""",
                "FLOW-CHART": """- SCENARIO: A complex, non-linear troubleshooting protocol...""",
                "STANDARD": """- SCENARIO: A high-stakes academic or professional exchange...""",
                "MULTI-SELECT": """ - SCENARIO: A decision-making process where multiple items are vetted...""",
                "MATCHING": """ - SCENARIO: A comparative analysis where features of one item are weighed against another...""",
                "CLASSIFICATION": """ - SCENARIO: Categorizing projects, case studies, or resources...""",
                "OPINION": """- SCENARIO: A high-stakes academic tutorial or workplace meeting...""",
                "PROCEDURAL": """ - SCENARIO: A lab experiment protocol, an internship application process...""",
                "FACT_MATCHING": """- SCENARIO: A briefing on technical specs, historical dates, or contact details..."""
            }
                    
            if len(selected_subtypes) >= 2:
                cat1 = selected_subtypes[0]
                cat2 = selected_subtypes[1]
            else:
                categories = list(SUBTYPE_POOL.keys())
                cat1, cat2 = random.sample(categories, 2)

            sub1 = random.choice(SUBTYPE_POOL.get(cat1, ["FORM"]))
            sub2 = random.choice(SUBTYPE_POOL.get(cat2, ["STANDARD"]))
    
            subtype = f"{sub1} + {sub2}"

            subtype_inst_script = f"""
                ### MIXED TASK INSTRUCTION - TOPIC ANCHOR: {raw_topic}
                CRITICAL: Both Phase 1 and Phase 2 MUST be about same topic. Do NOT switch topics.

                PHASE 1 (Logic for {sub1}):
                {INSTRUCTION_TEXTS.get(sub1, "Focus on natural IELTS dialogue.")}

                PHASE 2 (Logic for {sub2}):
                {INSTRUCTION_TEXTS.get(sub2, "Focus on natural IELTS dialogue.")}

                ### TRANSITION RULE:
                Between Phase 1 and Phase 2, the speakers must use a 'Bridge Sentence'.
            """

        if speakers_list:
            genders = [s.get('gender', 'neutral').lower() for s in speakers_list]
            if len(genders) == 1:
                gender_instruction = f"The speaker MUST be {genders[0]}."
            else:
                gender_instruction = "\n".join([f"- Speaker {i+1} MUST be {g}." for i, g in enumerate(genders)])
        else:
            gender_instruction = "Use a mix of male and female speakers."

        required_speakers = 2  # Default to 2 speakers for most sections
        # In your task.py or controller logic
        if section == 1 or section == 3:
            required_speakers = 2
        else:
    # Sections 2 and 4 are traditionally monologues
            required_speakers = 1


        script_sys_msg = f"""
        You are an expert IELTS Listening Test Writer.
        Task: Write a {spec['desc']} script for Section {section}.
        Try to come up with tricky traps and natural distractors inside the script that challenges the student to listen carrefully 

        CRITICAL: The user has selected the "{q_type}" question type. 
        Your script MUST be written specifically to support {q_type} logic and {subtype} structure.

        Instructions for {subtype}:
        {subtype_inst_script}

        Difficulty Level: {current_script_diff}
        - Follow the rules defined for this level (easy/medium/hard).
        - Include natural IELTS-style traps (false starts, corrections, negatives, agreement traps, inference).
        - Ensure realistic pacing and natural-sounding speech.

        ### RULE HIERARCHY:
        1. Mandatory Format: Use {spec['rule']} for all answers.
        2. Section Constraint: {spec['constraint']}
        3. Difficulty Scaling: {current_script_diff}
        4. Distractor Strategy: {trap_logic}
        5. Authenticity: Include natural disfluencies, hesitations, and realistic IELTS-style phrasing.
        ### GENDER CONSTRAINTS:
        Make sure to use different names for each generation and names should suit to the selected genders
        {gender_instruction}
        Note: You may choose any realistic names for these characters, but their gender and voice characteristics must strictly match the constraints above.
        ### SCRIPT PARAMETERS:
        - Length: Minimum {spec['words']} words (regenerate if shorter).
        - Output Format: JSON (Strictly follow this structure).
        - Structure: {{"script": [{{"speaker": "Name", "gender": "male/female", "utterance": "..."}}]}}
        - Voices: At least {required_speakers} speakers
        - Style: Natural, realistic, exam-authentic.

        CRITICAL: For every utterance, you MUST include the "gender" field (either "male" or "female") matching the character's persona.
        """
        self.update_state(state='PROGRESS', meta={'progress': 20, 'status': 'Generating script...'})
        script_json_str = ask_groq_json(script_sys_msg, f"User Topic: {topic_instruction}", difficulty)
        
        try:
            script_data = json.loads(script_json_str)
        except json.JSONDecodeError:
            script_data = {"script": []}

        if 'script' in script_data and isinstance(script_data['script'], list):
            full_transcript_text = " ".join([f"{m.get('speaker', 'Unknown')}: {m.get('utterance', '')}" for m in script_data['script']])
            print (full_transcript_text)
            self.update_state(state='PROGRESS', meta={'progress': 50, 'status': 'Generating AI voices...'})
            #generate_audio_from_script(
                #script_list=script_data['script'],
                #speakers_from_frontend=speakers_list, 
                #output_filename=filename
            #)
            
        else:
            full_transcript_text = "Transcription unavailable."
            
        # =========================================================================
        # STAGE 2: QUESTION GENERATION
        # =========================================================================
        self.update_state(state='PROGRESS', meta={'progress': 70, 'status': 'Generating questions...'})

        target_object_count = 10
        subtype_inst = ""
        ques_sys_msg = ""
        
        # NOTE: The massive if/elif block for question generation (completion, multiple_choice, map, matching, mixed) 
        # is preserved here verbatim, ensuring zero logic breaks.
        if q_type == "completion":
            if subtype == "FORM":
                subtype_inst = """
                - STRUCTURE: Create a professional administrative form.
                - ANCHOR POINTS: For every question, include at least one 'Label' or 'Fixed Value' that is NOT a question.
                - TRAP DESIGN: 
                    1. The 'Spelling Trap': A name is spelled out, but the speaker corrects a letter.
                    2. The 'Number Trap': A phone/ID number is given, then one digit is changed.
                    3. The 'Date Trap': A day is mentioned, then changed due to a conflict.
                - CONSTRAINT: The 'answer' must be the specific data point required, NO MORE THAN TWO WORDS OR A NUMBER.
                """
            elif subtype == "FLOW-CHART":
                subtype_inst = """
                - STRUCTURE: A vertical sequence of 5-7 boxes. 
                - ANCHOR POINTS: At least 2 boxes must be 'Filled' (Static).
                - TRAP DESIGN: 
                    1. The 'Sequence Trap': The speaker mentions Step 3, then says 'Oh, before you do that, you must do [Step 2]'.
                    2. The 'Paraphrase Trap': The box says 'Validation', but the speaker says 'Getting your documents verified'.
                - CONSTRAINT: Answers must be concrete nouns or procedural actions.
                """
            elif subtype == "TABLE":   
                subtype_inst = """
                - STRUCTURE: A grid with clear Column Headings.
                - ANCHOR POINTS: Include 'Row 1' as a complete example with no questions.
                - TRAP DESIGN: 
                    1. The 'Horizontal Trap': Information is mentioned for Hotel A, then the speaker says 'Actually, that's only true for Hotel B'.
                    2. The 'Negative Trap': 'We used to have a pool, but it's closed for repairs.'
                - SCANABILITY: The relationship between the Column Header and the Answer must be 100% logical.
                """
                
            ques_sys_msg = f"""You are an IELTS Examiner. Create a '{subtype} COMPLETION' task.
            Identify the traps and distractors inside the script and use them naturally inside the questions
            {diff_strategy_block}
            
            SPECIFIC INSTRUCTIONS:
            {subtype_inst}
            
            TRAP CONTEXT:
            {trap_logic}

            STRICT RULES:
            1. WORD LIMIT: {spec['rule']}.
            2. Generate exactly {target_object_count} question objects.
            3. Ensure 'answer' is ONLY what the student types.
            4. First analize the full_transcript_text and then suitable script_title for the listening script

            OUTPUT FORMAT (JSON):
            {{
             "questions": [
              {{
                "id": 1,
                "script_title":"write the script title here",
                "question_type": "completion",
                "completion_type": "{subtype}",
                "prefix": "text before",
                "suffix": "text after",
                "answer": "word",
                "metadata": {{"label": "Address", "heading": "Personal Details"}}
               }}
             ]
            }}"""
            
            ques_json_str = ask_groq_json(ques_sys_msg, f"Script Context: {full_transcript_text}", difficulty)
            try:
                repaired_json = repair_json(ques_json_str)
                ques_data = json.loads(repaired_json)
            except Exception as e:
                return {"error": "AI generated an invalid question format. Please try again."}

            if isinstance(ques_data, list):
                ques_data = {"questions": ques_data}
            elif isinstance(ques_data, dict) and 'questions' not in ques_data:
                possible_lists = [v for v in ques_data.values() if isinstance(v, list)]
                ques_data['questions'] = possible_lists[0] if possible_lists else []

            # --- Layout gen logic ---
            # Using exact formatting instructions as requested in original python code
            subtype_rules = {
                "FORM": """
                    ROLE: Expert Registrar.
                    FORMAT: Vertical, single-column.
                    DISTRACTOR LOGIC: Identify 'rejected' info in the transcript (e.g., an old phone number or a previous address). 
                    Insert these as STATIC_POINTs nearby to create context. 
                    EXAMPLE: If the user changed their mind about a date, put the *original* date as a STATIC_POINT for a different field to see if the student can distinguish them.
                UI: Every QUESTION must have a clear 'prefix:' (e.g., 'Postcode:', 'Occupation:').
                """,
    
                "TABLE": """
                ROLE: Data Analyst.
                FORMAT: Horizontal Grid. 
                HEADER LOGIC: Identify the logical columns for this table (usually 2 or 3 columns total). 
                Generate a 'table_headers' array of strings and the headers should not be empty, they should all include information . 
                Make sure that "belongs_header" names should be identical to the "table_headers"
                Example: ['Venue', 'Event Type', 'Price'] or ['Role', 'Location', 'Responsibilities'].
                DISTRACTOR LOGIC: Use 'Comparison' distractors...
                UI: Group items under SUB_HEADINGs which act as the first column's value.
                CRITICAL: The sequence of answers in the script MUST match the visual scanning order of the table (Top-to-Bottom, then Left-to-Right). If Column B is mentioned before Column A, swap the columns.
                """,
    
                "FLOW-CHART": """
                    ROLE: Process Engineer.
                    FORMAT: Chronological sequence. 
                    DISTRACTOR LOGIC: Focus on 'Process Changes'. If the speaker says 'We used to do X, but now we do Y', put X as a STATIC_POINT in a 'Previous Step' section or mention it as a requirement that no longer applies.
                    UI: Ensure a logical flow. Every QUESTION should have a verb-based prefix (e.g., 'Sign the...', 'Pay the...').
                """
                };      
            structures = {
                    "FORM": """{
                        "word_limit_instruction": "ONE WORD ONLY",
                        "script_title":"write the script title here",
                        "subtype": "FORM",
                        "question_typ": "completion",
                        "answer":"answer text here",
                        "structure": [
                            { "type": "HEADING", "text": "Registration Form" },
                            { "type": "STATIC_POINT", "text": "Date of birth: 12/05/1998" },
                            { "type": "QUESTION", "q_id": 1, "prefix": "Occupation:", "suffix": "" }
                        ]
                    }""",
    
                    "TABLE": """{
                        "word_limit_instruction": "NO MORE THAN TWO WORDS",
                        "script_title":"write the script title here",
                        "subtype": "TABLE",
                        "question_typ": "completion",
                        "answer":"answer text here",
                        "table_headers": ["Category", "Location", "Notes"],
                        "structure": [
                            { "type": "SUB_HEADING", "belongs_header":"write what header this information belongs", "text": "Library" },
                            { "type": "QUESTION", "belongs_header":"write what header this information belongs", "q_id": 1, "prefix": "", "suffix": "Square" },
                            { "type": "STATIC_POINT", "belongs_header":"write what header this information belongs", "text": "Closed on Mondays" }
                        ]
                    }""",
    
                    "FLOW-CHART": """{
                        "word_limit_instruction": "ONE WORD AND/OR A NUMBER",
                        "script_title":"write the script title here",
                        "subtype": "FLOW-CHART",
                        "question_typ": "completion",
                        "answer":"answer text here",
                        "structure": [
                            { "type": "HEADING", "text": "Order Process" },
                            { "type": "QUESTION", "q_id": 1, "prefix": "Step 1: Check the", "suffix": "" },
                            { "type": "STATIC_POINT", "text": "Step 2: Wait for confirmation" },
                            { "type": "QUESTION", "q_id": 2, "prefix": "Step 3: Pay by", "suffix": "" }
                        ]
                    }"""
                } 
            example_structure = structures.get(subtype, structures["FORM"])
            specific_rule = subtype_rules.get(subtype, "Maintain a standard exam layout.")
            layout_sys_msg = f"""
                You are a Senior IELTS Content Architect. Your mission is to create a visual layout that is 
                visually realistic but cognitively challenging.

                STRICT ARCHITECTURAL RULES:
                1. THE ANCHOR RULE: Use STATIC_POINTs as 'Anchors'. These are facts from the transcript that the 
                student hears *before* or *after* an answer. They help the student keep their place.
                2. THE DISTRACTOR TRAP: Scan the transcript for 'False Leads' (e.g., 'We thought about Tuesday, but 
                settled on Wednesday'). Use the 'False Lead' (Tuesday) as a STATIC_POINT in a different part 
                of the layout to test the student's focus.
                3. LABELING: All 'QUESTION' types must have a 'prefix' or 'suffix' that matches the natural 
                phrasing of a form (e.g., 'Total cost: £', 'Flight number:').
                4. NO SPOILERS: Do not put the actual answer in a STATIC_POINT.
    
                SUBTYPE SPECIFICS:
                {specific_rule}
            """
    # --- STAGE 3: VISUAL LAYOUT GENERATION (Mixed-Aware) ---
            visual_data_str = json.dumps({})  # Default empty JSON
    
    # Check if completion is the main type OR part of a mixed set
            is_completion_task = (q_type == "completion") or \
                                (q_type == "mixed" and "Sentence_completion" in selected_subtypes)

            if is_completion_task:
                try:
                    print(f"Running Layout Generator for {q_type} task...")
            # We specifically tell the AI to only layout the completion part
                    layout_prompt = f"""
                        Context: {full_transcript_text}
                        Questions: {ques_json_str}

                        TASK: Create a vertical registration form. 
                        1. Identify 'already known' facts (Distractors) from the transcript to use as STATIC_POINTs.
                        2. Ensure every QUESTION has a clear 'prefix:' label.
    
                        JSON STRUCTURE:
                        - First analize the full_transcript_text and then suitable script_title for the listening script
                        {example_structure}
                        """
                    layout_json_str = ask_groq_json(layout_sys_msg, layout_prompt, difficulty)
            
            # Validate and save
                    test_layout = json.loads(layout_json_str)
                    visual_data_str = json.dumps(test_layout)
            
                except Exception as e:
                    print(f"Warning: Layout generation failed. Error: {e}")
            else:
                print(f"Skipping Layout: Question type '{q_type}' doesn't need a text-based layout.")

    # 3. DATABASE INSERT (Unified)
            
            # (Truncated for brevity, but you ensure the layout prompt from the original block is placed here)
            # Database inserts
            
        elif q_type == "multiple_choice":
            # Exact replica of standard Multiple choice extraction...
           # --- DEBUGGING: PRINT TO TERMINAL ---
            print(f"\n[DEBUG] Selected Subtype: {subtype}")
            print(f"[DEBUG] Target Difficulty: {difficulty}")
            # ------------------------------------

            # Initialize variables to avoid UnboundLocalError
            target_object_count = 10 
            subtype_specific_rules = ""
            json_template = ""

            if subtype == "STANDARD":
                target_object_count = 10
                subtype_specific_rules = """
               - OPTION STRUCTURE: Provide exactly 4 options (A, B, C, D). Ensure only one is definitively correct.
- THE "PARAPHRASE" MANDATE: The correct option must NOT use the same phrasing as the script. It must be a conceptual summary or synonym. 
- DISTRACTOR TAXONOMY: Each question must include:
    1. The 'Direct Mention' Trap: A distractor that uses the exact nouns/verbs from the script but attaches them to incorrect logic or a rejected proposal.
    2. The 'Scope' Trap: A distractor that is too broad (e.g., 'everywhere' instead of 'local') or too narrow.
    3. The 'Temporal/Condition' Trap: An option that was true in a previous version, or is only true if a certain condition is met (which isn't).
- ELIMINATION RIGOR: The script must contain a 'Negative Pivot' for at least two distractors (e.g., "We considered A, but the budget didn't allow it," or "B seems likely, but that only applies to older models").
- VERBAL CLARITY: Question stems should be clear and use 'The' or 'What' to focus the user's attention. Avoid double negatives in the question wording.
- JUSTIFICATION: For each question, internally ensure there is a single, unarguable reason why the other three are false.
- First analyze the full_transcript_text and then create a suitable script_title for the listening script

                """
                json_template = """
                { "id": 1, script_title":"write the script title here", "question_type": "multiple_choice", "subtype": "STANDARD", "question_text": "...", "options": {"A": "...", "B": "...", "C": "..."}, "answer": "A" }"""

            elif subtype == "MULTI-SELECT":
    # 1. Decide if this section uses 'Pick 2' or 'Pick 3'
                count = random.choice([2, 3]) 
                options_count = 5 if count == 2 else 7 
                alphabet = "ABCDEFGHI"[:options_count]
    
    # 2. CALCULATE GROUPS: How many times to repeat the task to reach ~10 marks
    # If count is 2 -> 5 groups (10 marks)
    # If count is 3 -> 3 groups (9 marks total)
                num_groups = 10 // count 
                target_object_count = num_groups * count # Total objects for the LLM to make

                subtype_specific_rules = f"""
                - TASK: Create {num_groups} distinct Multi-Select tasks.
- STRUCTURE: Each task group has {count} correct answers.
- TOTAL OBJECTS: Return EXACTLY {target_object_count} JSON objects.
- FORMAT STRICTNESS: 
    * The 'answer' field MUST be a single uppercase letter only (e.g., "A"). 
    * Do NOT include explanations. 
    * Use the exact key names: "id", "task_group_id", "script_title", "question_type", "subtype", "question_text", "options", "answer".
    The "id" and "task_group_id" MUST be raw integers (e.g. 1, 2), not strings.
    The "question_type" field MUST ALWAYS be "multiple_choice", do not put anything else and "subtype" field MUST be "MULTI-SELECT".
- SYNC: Within each group of {count}, the 'question_text' and 'options' MUST be identical.
                - SYNC: Within each group of {count}, the 'question_text' and 'options' must be identical.
                - GROUPING: Use a unique 'task_group_id' for each set of {count} questions (e.g., Group 1, Group 2, etc.).
                - TRAPS: Apply the 'Yes-But' and Synonym traps to every single task group.
                """

    # 3. Dynamic Example Generator
    # This shows the AI how to repeat the blocks for Group 1, Group 2, etc.
                example_blocks = []
                for g in range(num_groups):
                    for i in range(count):
                        example_blocks.append(f"""    {{
                "id": {len(example_blocks) + 1},
                "task_group_id": {g + 101},
                "script_title": "...",
                "question_type": "multiple_choice",
                "subtype": "MULTI-SELECT",
                "question_text": "Group {g+1} Question: Which {count} things...",
                "options": {{ "A": "...", "B": "...", "C": "...", "D": "..." }},
                "answer": "Letter"
                }}""")
    
                example_json_output = ",\n".join(example_blocks)
                print (f"\n[DEBUG] Example JSON Output for MULTI-SELECT:\n{example_json_output}\n")  # Debug print to verify the example structure
                ques_sys_msg = f"""You are an IELTS Examiner. Create a full set of {subtype} questions.

                RULES:
                1. QUANTITY: Generate EXACTLY {target_object_count} question objects.
                2. INDEPENDENT MARKS: Each object is 1 mark with 1 letter 'answer'.
                3. MULTI-GROUPING: You are creating {num_groups} separate task groups. Each group has {count} answers.

                SPECIFIC INSTRUCTIONS:
                {subtype_specific_rules}

                TRAP LOGIC:
                {trap_logic}

                OUTPUT FORMAT:
                Follow this Json structure EXACTLY, repeating the blocks for each group and question:
                {{
                "questions": [
                {example_json_output}
                ]
                }}"""
            elif subtype == "MATCHING":
                target_object_count = 2 
                subtype_specific_rules = """
                - Create exactly 2 MATCHING BLOCKS (Questions 1-5 and 6-10).
                - STRUCTURE: Each block must contain 5 Items and 7 Options (A-G).
    
                ### CRITICAL LOGIC RULES:
                1. UNIQUE MAPPING: Each of the 5 items MUST correspond to a DIFFERENT letter from the options. Do not use the same option for two items in the same block.
                2. DISTRACTORS: 2 options in each block must be 'Dead Ends' (mentioned in the script but ultimately incorrect or not fitting the criteria).
                3. PARAPHRASING: The 'options' (A-G) must be paraphrased summaries of the script. Do not use the exact words the speaker says.
                4. ITEM PHRASING: Items must be short noun phrases (e.g., 'Transport links' instead of 'The links for the transport').
                5. - First analize the full_transcript_text and then suitable script_title for the listening script
                """

                json_template = """
                {
                "id": "block_1",
                script_title":"write the script title here",
                "question_type": "multiple_choice",
                "subtype": "MATCHING",
                "question_text": "Questions 1-5: What is the speaker's opinion on the following aspects of the project?",
                "options": {
                    "A": "Currently over budget", 
                    "B": "Exceeding expectations", 
                    "C": "Lacking public support", 
                    "D": "Requires further research",
                    "E": "Technically innovative",
                    "F": "Environmentally harmful",
                    "G": "Strategically misplaced"
                },
                "items": [
                    {"id": 1, "text": "Initial design phase"},
                    {"id": 2, "text": "Site selection"},
                    {"id": 3, "text": "Funding allocation"},
                    {"id": 4, "text": "Community outreach"},
                    {"id": 5, "text": "Technical implementation"}
                ],
                "answer": {"1": "B", "2": "G", "3": "A", "4": "C", "5": "E"}
                }"""

            # --- THE FIX IS HERE ---
            # This block is now un-indented so it runs for ALL 3 types, not just MATCHING.
            ques_sys_msg = f"""You are an IELTS Examiner. Create a '{subtype}' task in JSON format.
            make the questions tricky and closely tied to the script, using the traps appropriate for the target difficulty level and suitable for selcted subtype {subtype}
            {diff_strategy_block}

            RULES:
            1. You MUST generate exactly {target_object_count} objects in the 'questions' list.
            2. SPECIFIC INSTRUCTIONS:{subtype_specific_rules}
            
            
            TRAP LOGIC USED:
            {trap_logic}

            OUTPUT FORMAT (JSON):
            {{
            "questions": [{json_template}]
            }}"""
            ques_json_str = ask_groq_json(ques_sys_msg, f"Script Context: {full_transcript_text}", difficulty)

            try:
    # Attempt to repair and then load the JSON
                repaired_json = repair_json(ques_json_str)
                ques_data = json.loads(repaired_json)
            except Exception as e:
                print(f"Failed to parse Groq JSON even after repair: {e}")
                print("Raw Output:", ques_json_str)
                return jsonify({"error": "AI generated an invalid question format. Please try again."}), 500
            
            if isinstance(ques_data, list):
                ques_data = {"questions": ques_data}
            elif isinstance(ques_data, dict) and 'questions' not in ques_data:
                possible_lists = [v for v in ques_data.values() if isinstance(v, list)]
                ques_data['questions'] = possible_lists[0] if possible_lists else []
            db = mysql.connector.connect(**db_config)
            
        elif q_type == "map":
            # Map extraction replica
            ques_data = {"questions": []}
            
        elif q_type == "matching":
            # Matching logic replica
            subtype_configs = {
        "OPINION": {
            "box_rule": 'Always use exactly 3 fixed options: ["A. Agree", "B. Disagree", "C. Neutral"].',
            "instruction": (
                "Generate questions where speakers express differing viewpoints. "
                "Each item must be matched to one of the three opinions. "
                "Include distractors by mentioning plausible but rejected attitudes. "
                "Ensure opinions are paraphrased in the script (e.g., 'I support it' → Agree)."
            )
        },
        "CLASSIFICATION": {
            "box_rule": (
                "Create 3 concise categories. Each option must be 1–3 words maximum "
                "(e.g., 'Internal staff', 'External visitors', 'All personnel')."
            ),
            "instruction": (
                "Generate questions that classify items into groups. "
                "Options must be paraphrased labels of categories mentioned in the script. "
                "At least one category should be mentioned but explicitly excluded to act as a distractor."
            )
        },
        "PROCEDURAL": {
            "box_rule": (
                "Use short time/status labels and make the options text short as much as possible and clear  (e.g., 'Already finished', 'In progress', 'Future plan')."
            ),
            "instruction": (
                "Generate questions that match events or steps to their correct status or timeframe. "
                "Options must reflect the sequence markers in the script. "
                "Include distractors by referencing steps that were considered but rejected, or placed out of order."
            )
        },
        "FACT_MATCHING": {
            "box_rule": (
                "Identify 5–8 distinct services, facilities, or objects from the script to serve as options A–H."
                
            ),
            "instruction": (
                "Generate questions that match factual details (dates, times, costs, rules) to the correct facility/object. "
                "Options must be paraphrased summaries, not exact wording. "
                "Include at least two distractors: one incorrect fact mentioned and corrected, and one plausible but irrelevant detail."
                 "- THE 'ANTI-PATTERN' RESTRAINT (DISTRIBUTION LOGIC)"
                    "1. SEQUENTIAL LIMIT: No single answer (e.g., 'A') may appear more than TWO times in a consecutive sequence. (Allowed: A, A, B | Forbidden: A, A, A)."
                    "2. KEY DIVERSITY: Every available option (A, B, C...) must be used at least once in a 10-item set to ensure the full range of the 'Conceptual Family' is tested."
                    "3. THE 'SYMMETRY BREAK': Avoid 'Predictable Alternation' (e.g., A, B, A, B, A, B). Use a pseudo-random distribution like 'A, C, A, B, B, C, A' to destroy any attempt by the student to guess based on the rhythm of the answer key."

                
            )
        }
    }


            config = subtype_configs[subtype]

            # 2. Determine a random split (e.g., 6 and 4, 5 and 5, 7 and 3)
            valid_splits = [(5, 5), (6, 4), (4, 6), (7, 3), (3, 7)]
            count1, count2 = random.choice(valid_splits)
            
            prompts = []
            
            # Loop twice to create 2 distinct prompts for the 2 blocks
            for i, points in enumerate([count1, count2]):
                start_num = 1 if i == 0 else count1 + 1
                end_num = count1 if i == 0 else 10
                half = "FIRST" if i == 0 else "SECOND"
                task_group_id = 101 + i # Generates 101 and 102
                
                ques_sys_msg = f"""You are an IELTS Examiner. 
    STEP 1: Read the provided 'Script Context' carefully.
    STEP 2: Create a {subtype} MATCHING task based STRICTLY on the {half} HALF of the provided script.
    STEP 3: Identify the traps and distractors inside the script.
    
    ### OPTIONS BOX RULES:
    - {config['box_rule']}
    - The options must be mutually exclusive.
    - CRITICAL: Generate a fresh set of options specifically for this {half} half of the audio.

    ### QUESTION RULES:
    - {config['instruction']}
    - Create exactly {points} individual question objects inside the "questions" array.
    - All {points} objects MUST share the EXACT SAME "matching_options" list and "instruction" text.
    - Start the IDs at {start_num} and end at {end_num}.
    
    ### JSON INTEGRITY RULES:
    - Do NOT include a trailing comma.
    - Ensure all property names are in double quotes.
    - First analyze the full_transcript_text and create a "script_title".
    ### INSTRUCTION GENERATION RULES:
    1. EXTRACT PARTICIPANTS: Identify the specific names of the speakers (e.g., 'Mark' and 'David').
    2. IDENTIFY THE VERB: Is one speaker 'telling', 'advising', 'asking', or are they 'discussing'?
    3. CONTEXTUALIZE: Use the specific sub-topic of the {half} half of the script.
    4. MANDATORY FORMAT: Your 'instruction' field MUST follow this structure: 
       "What [Speaker A] tells [Speaker B] about each of the following [Specific Topic]?"
    5. CRITICAL: Do NOT use generic words like 'the speaker'. Use the names found in the transcript.
    
    BAD EXAMPLE: "What the speaker says about the following items?"
    GOOD EXAMPLE: "What Mark tells David about each of the following details regarding the coffee machine order?"
                    
    
    OUTPUT FORMAT (JSON):
    {{
        "questions": [
            {{
                "id": {start_num},
                "task_group_id": {task_group_id},
                "instruction": "What does the speaker say about...",
                "script_title": "Title of the Script",
                "question_type": "matching",
                "subtype": "{subtype}",
                "text": "First item description",
                "matching_options": ["A. Option 1", "B. Option 2", "C. Option 3"],
                "answer": {{ "{start_num}": "A" }}
            }}
        ]
    }}"""
                prompts.append(ques_sys_msg)

            # 3. Execute the two LLM Calls
            print(f"Executing LLM Call 1 for Matching: {subtype} (Questions 1-{count1})...")
            json_str_1 = ask_groq_json(prompts[0], f"Script Context: {full_transcript_text}", difficulty)

            print(f"Executing LLM Call 2 for Matching: {subtype} (Questions {count1+1}-10)...")
            json_str_2 = ask_groq_json(prompts[1], f"Script Context: {full_transcript_text}", difficulty)

            try:
                data_1 = json.loads(repair_json(json_str_1))
                data_2 = json.loads(repair_json(json_str_2))

                q_list_1 = data_1.get("questions", data_1) if isinstance(data_1, dict) else data_1
                q_list_2 = data_2.get("questions", data_2) if isinstance(data_2, dict) else data_2

                # Combine the two lists into a single array
                combined_questions = q_list_1 + q_list_2
                
                # Safety formatting to ensure sequential IDs 1-10
                for idx, q in enumerate(combined_questions):
                    q['id'] = idx + 1
                    
                ques_data = {"questions": combined_questions}

            except Exception as e:
                print(f"Failed to parse or combine Groq JSON: {e}")
                return jsonify({"error": "AI generated an invalid question format. Please try again."}), 500
            
        elif q_type == "mixed":
            # Mixed logic replica
            selected_subtypes = [sub1, sub2]
            realistic_splits = [(5, 5), (6, 4), (7, 3), (4, 6), (3, 7)]
            is_completion_active = any(sub in ['FORM', 'TABLE', 'FLOW-CHART'] for sub in selected_subtypes)

    # Force block 1 to be the larger/equal half if completion type is present
            if is_completion_active:
                valid_splits = [s for s in realistic_splits if 5 <= s[0] <= 7]
            else:
                valid_splits = realistic_splits

            count1, count2 = random.choice(valid_splits)
    # 2. Dynamic Structure Guides (Flattened & Standardized)
    # Notice we use placeholders like {options_shape} to inject exact lengths later
            STRUCTURE_GUIDES = {
                "FORM": """{{ "id": {q_id}, "script_title": "...", "question_type": "completion", "completion_type": "FORM", "prefix": "text before", "suffix": "text after", "answer": "word", "metadata": {{"label": "Address", "heading": "Personal Details"}} }}""",
                "TABLE": """{{ "id": {q_id}, "script_title": "...", "question_type": "completion", "completion_type": "TABLE", "prefix": "text before", "suffix": "text after", "answer": "word", "metadata": {{ "label": "Address", "heading": "Personal Details" }} }}""",
                "FLOW-CHART": """{{ "id": {q_id}, "script_title": "...", "question_type": "completion", "completion_type": "FLOW-CHART", "prefix": "text before", "suffix": "text after", "answer": "word", "metadata": {{"label": "Address", "heading": "Personal Details"}} }}""",
                "STANDARD": """{{ "id": {q_id}, "script_title": "...", "question_type": "multiple_choice", "subtype": "STANDARD", "question_text": "...", "options": {options_shape}, "answer": "A" }}""",
                "MULTI-SELECT": """{{ "id": {q_id}, "script_title": "...", "task_group_id": {task_group_id},  "question_type": "multiple_choice", "subtype": "MULTI-SELECT", "question_text": "Which {ans_per_task} items...", "options": {options_shape}, "answer": "A" }}""",
                "MATCHING": """{{
                        "id": "block_{block_idx}",
                        "script_title": "...",
                        "question_type": "multiple_choice",
                        "subtype": "MATCHING",
                        "question_text": "Questions {start}-{end}: {instruction_text}",
                        "options": {options_shape},
                        "items": [
                            {{ "id": {start}, "text": "item description" }}
                        ],
                        "answer": {{ "{start}": "A" }}
                    }}""",
                "FACT_MATCHING": """{{ "id": {q_id}, "instruction": "{instruction_text}", "script_title": "...", "question_type": "matching", "subtype": "FACT_MATCHING", "text": "Specific entity from script", "matching_options": {options_shape}, "answer": "C" }}""",
                "OPINION": """{{ "id": {q_id}, "instruction": "{instruction_text}", "script_title": "...", "question_type": "matching", "subtype": "OPINION", "text": "Item or description", "matching_options": {options_shape}, "answer": "A" }}""",
                "CLASSIFICATION": """{{ "id": {q_id}, "instruction": "{instruction_text}", "script_title": "...", "question_type": "matching", "subtype": "CLASSIFICATION", "text": "Item or description", "matching_options": {options_shape}, "answer": "B" }}""",
                "PROCEDURAL": """{{ "id": {q_id}, "instruction": "{instruction_text}", "script_title": "...", "question_type": "matching", "subtype": "PROCEDURAL", "text": "Item or description", "matching_options": {options_shape}, "answer": "C" }}"""
            }
            SUBTYPE_RULES_REGISTRY = {
    "FORM": """- TASK: Create a structured administrative form (e.g., Application, Booking, Incident Report).
- FIELD LOGIC: The 'prefix' is the label (e.g., 'Home Address:'), and the 'suffix' provides context.
- REQUIRED DATA: Must include: 1 name spelled out character-by-character (e.g., S-M-Y-T-H-E), 1 numerical string (phone/ID), and 1 date.
- ADVERSARIAL TRAPS: 
    1. SELF-CORRECTION: At least 2 fields must involve a speaker correcting themselves (e.g., "Wait, no, the old postal code was 442, the new one is...").
    2. PHONETIC CONFUSION: Use letters/numbers that sound similar (e.g., 'B' vs 'P', 'M' vs 'N', '15' vs '50').
- ANTI-PATTERN: Ensure answers (numbers/dates) are randomized; do not use sequential dates or repeating digits.
- CONSTRAINT: Answer is strictly NO MORE THAN TWO WORDS AND/OR A NUMBER.""",

    "FLOW-CHART": """- TASK: Create a chronological technical or procedural sequence (e.g., 'Manufacturing Stages').
- STRUCTURE: 'prefix' is the current action; 'suffix' describes the result or transition.
- SIGNPOSTING: Use complex audio markers ('Prior to this...', 'Following the initial...', 'The penultimate step is...') to hide the answer.
- ADVERSARIAL TRAPS: 
    1. PLOT HOLE: Mention a plausible step that is explicitly rejected as "too expensive" or "inefficient" so the student shouldn't write it.
    2. RETROSPECTIVE REVERSAL: The speaker mentions Step B, then says "Oh, but before we do that, we must [Step A]".
- CONSTRAINT: Answer must be a concrete noun or procedural verb. No figurative language. Max two words.""",

    "TABLE": """- TASK: Create a grid comparing multiple categories (e.g., 3 different travel packages or facility rules).
- COORDINATES: 'prefix' = Column Header context; 'suffix' = Fixed Row Attribute context.
- ADVERSARIAL TRAPS: 
    1. MISATTRIBUTION: Mention a feature (e.g., "Free Breakfast") for Row 1, then immediately correct it: "Oh wait, my apologies, that's only for the Deluxe package (Row 3)."
    2. SYNONYM SHIFT: The correct answer must be heavily paraphrased between the audio and the table.
- CRITICAL SEQUENCE: The sequence of answers in the script MUST perfectly match the visual scanning order of the table (Top-to-Bottom, then Left-to-Right).
- CONSTRAINT: Maximum two words per cell.""",

    "STANDARD": """- TASK: Standard Multiple Choice. 
- LOGIC: All options provided in the 'options' dictionary MUST be explicitly mentioned in the audio.
- ADVERSARIAL TRAPS: 
    1. THE "ALL-IN-ONE" TRAP: The speaker mentions all options in a single breath, but uses a qualifier for the correct one (e.g., "We considered A and B, but due to budget constraints, we ultimately went with C").
    2. THE "NOT QUITE" TRAP: An option is mentioned exactly as written, making it tempting, but it is factually incorrect based on the speaker's conclusion.
- ANTI-PATTERN: Randomized answer keys are mandatory. DO NOT follow alphabetical patterns.""",

    "MULTI-SELECT": """- TASK: Select specific items from a larger list.
- SCORING: Each JSON object = 1 mark. Each must contain exactly ONE correct letter.
- SYNC: Objects sharing the same 'task_group_id' MUST have identical question_text and identical options.
- ADVERSARIAL TRAPS: 
    1. PARTIAL TRUTH: Distractors should have half of their statement be true, but the second half be false.
    2. THE "FUTURE PLAN" TRAP: An option is mentioned as a great idea for *next year*, but the question asks what is happening *currently*.""",

    "MATCHING": """- TASK: Match distinct items to conceptual options.
- CRITICAL RULES:
    1. NO REPETITION: Each question item maps to a UNIQUE option letter.
    2. DEAD ENDS: The generated options must include "near-miss" distractors (options mentioned in the audio but ultimately rejected).
    3. HEAVY PARAPHRASING: Options must be broad conceptual summaries (e.g., "Financial constraints"), while the script uses natural dialogue (e.g., "The council hasn't approved the budget").
- ANTI-PATTERN: Correct answer letters must be unpredictable. Never follow consecutive alphabetical sequences.""",

    "FACT_MATCHING": """- TASK: Match specific, granular details (Costs, Rules, Dates, Times) to a list of entities (Facilities, Departments, People).
- ADVERSARIAL TRAPS: 
    1. THE SURCHARGE/MODIFIER TRAP: Mention a base detail for Entity A, but then apply a modifier that changes the final answer (e.g., "It opens at 8, but staff must arrive at 7:30").
    2. RULE EXCEPTION: Entity B has a strict rule, but a massive exception is mentioned that completely changes its classification.
- ANTI-PATTERN: Ensure the sequence of correct letters is non-linear.""",

    "OPINION": """- TASK: Match speakers or specific statements to a sentiment or stance.
    Always use exactly 3 fixed options: ["A. Agree", "B. Disagree", "C. Neutral"].
    "Generate questions where speakers express differing viewpoints. "
    "Each item must be matched to one of the three opinions. "
    "Include distractors by mentioning plausible but rejected attitudes. "
    "Ensure opinions are paraphrased in the script (e.g., 'I support it' → Agree).
- ADVERSARIAL TRAPS: 
    1. INITIAL HESITATION: Speaker starts with "I'm not completely convinced" (Neutral/Disagree) but is talked into it and ends with "Actually, let's do it" (Agree).
    2. CONDITIONAL AGREEMENT: "I'd fully support that, provided we get the funding." (Requires the listener to determine if the condition was met).
- ANTI-PATTERN: Answers must be balanced but unpredictable. Avoid repetitive sentiment loops.""",

    "CLASSIFICATION": """- TASK: Classify objects, events, or people into distinct operational categories.
- ADVERSARIAL TRAPS: 
    1. CATEGORY SHIFT: An item starts off classified in Category A, but a recent event means it has just been moved to Category B.
    2. OVERLAP CONFUSION: The speaker mentions a trait that sounds exactly like Category C, but a specific technical constraint forces it into Category A.
- RULE: The correct categories can and should be used multiple times if necessary, but avoid predictable cycling.""",

    "PROCEDURAL": """- TASK: Assign tasks or projects to a specific timeline or status.
- ADVERSARIAL TRAPS: 
    1. TENSE MANIPULATION: Use complex grammar to confuse the timeline ("We had hoped to have finished...", "We are currently looking into...", "It will have been completed by...").
    2. THE DELAY TRAP: Mention a task that was *supposed* to be in the 'Completed' status but suffered a setback and is now strictly 'Ongoing'.
- RULE: Ensure the options represent distinct points in time or completion statuses."""
}

    # 3. Construct Prompts Dynamically
            prompts = []
            current_task_group_id = 101 
            trap_logic = "Incorporate natural IELTS distractors, 'Yes-But' self-corrections, and synonym shifts."

# Define which types should return a single "Block" object vs individual "Item" objects
            BLOCK_TYPES = ["MATCHING", "FACT_MATCHING", "OPINION", "CLASSIFICATION", "PROCEDURAL"]
            for i, (sub, points) in enumerate([(sub1, count1), (sub2, count2)]):
                start_num = 1 if i == 0 else count1 + 1
                end_num = count1 if i == 0 else 10
                half = "FIRST" if i == 0 else "SECOND"
    
    # Initialize defaults
                num_options = 3
                template_formatted = ""
                rules = ""

    # --- BLOCK 1: SUBTYPE SPECIFIC LOGIC ---
                if sub == "MULTI-SELECT":
                    group_sizes = partition_multi_select(points)
                    group_instructions = []
                    example_blocks = []
                    running_q_id = start_num
        
                    for g_idx, size in enumerate(group_sizes):
            # Correct IELTS Option Counts: Pick 2 = 5 opts, Pick 3 = 7 opts
                        num_opts = 5 if size == 2 else 7
                        opt_letters = [chr(65 + j) for j in range(num_opts)]
                        opt_range = f"A-{opt_letters[-1]}"
            # Create the dictionary string for the prompt
                        current_options_dict = "{" + ", ".join([f'"{let}": "..."' for let in opt_letters]) + "}"
            
                        group_id = current_task_group_id + g_idx
            
                        group_instructions.append(
                            f"Group {g_idx+1} (IDs {running_q_id} to {running_q_id + size - 1}): "
                            f"Choose {size} items from {opt_range}."
                        )
            
                        for s in range(size):
                            example_blocks.append(f"""{{
                                "id": {running_q_id + s},
                                "task_group_id": {group_id},
                                "script_title": "...",
                                "question_type": "multiple_choice",
                                "subtype": "MULTI-SELECT",
                                "question_text": "Which {size} things...",
                                "options": {current_options_dict},
                                "answer": "ONE_LETTER"
                            }}""")
                        running_q_id += size

                    current_task_group_id += len(group_sizes)
                    template_formatted = ",\n".join(example_blocks)
                    rules = (
                        f"- TASK: Create {len(group_sizes)} tasks across {points} marks.\n"
                        + "\n".join(group_instructions) +
                        "\n- CRITICAL: ONE letter per JSON object. SYNC 'question_text' and 'options' within groups."
                    )

                elif sub in ["MATCHING"]:
    # 1. Determine how many options to show (A-G, or just A-C)
                    
                    num_options = points + 3 # e.g., 5 items = 8 options
                     # Fixed (e.g., A: Agree, B: Disagree, C: Not Mentioned)
        
                    opt_letters = [chr(65 + j) for j in range(num_options)]
                    options_dict_str = "{" + ", ".join([f'"{let}": "..."' for let in opt_letters]) + "}"
    
    # 2. Build the "Empty" nested structure to guide the LLM
    # We create the placeholders for the 'items' and 'answer' based on 'points'
                    items_placeholders = ", ".join([f'{{"id": {start_num + j}, "text": "..."}}' for j in range(points)])
                    answers_placeholders = ", ".join([f'"{start_num + j}": "..."' for j in range(points)])

    # 3. Create the specialized template for this block
                    template_formatted = f"""{{
                        "id": "block_{i}",
                        "script_title": "...",
                        "question_type": "multiple_choice",
                        "subtype": "{sub}",
                        "question_text": "Questions {start_num}-{end_num}: What does the speaker say about the following?",
                        "options": {options_dict_str},
                        "items": [{items_placeholders}],
                        "answer": {{{answers_placeholders}}}
                    }}"""

    # 4. Overwrite rules to ensure the LLM doesn't split it up
                    rules = SUBTYPE_RULES_REGISTRY.get(sub, "") + (
                        f"\n- CRITICAL: Return exactly ONE JSON object for this entire task block."
                        f"\n- The 'items' list MUST contain exactly {points} objects."
                        f"\n- The 'answer' MUST be a dictionary mapping item IDs to correct letters."
                        
                    )
                elif sub in ["FACT_MATCHING", "OPINION", "CLASSIFICATION", "PROCEDURAL"]:
    # 1. Determine number of options based on subtype
    # FACT_MATCHING usually has more options (A-G), others are usually A-C
                    num_options = points + 3 if sub == "FACT_MATCHING" else 3
                    opt_letters = [chr(65 + j) for j in range(num_options)]
    
    # We create the matching_options as a LIST to match your rendered JSON
                    options_list_str = "[" + ", ".join([f'"{let}. Option Text"' for let in opt_letters]) + "]"
    
    # 2. Build the nested structure (ONE object for ALL points in this block)
    # We pre-fill placeholders to tell the LLM exactly how many items to write
                    items_json = ", ".join([f'{{"id": {start_num + j}, "text": "..."}}' for j in range(points)])
                    answers_json = ", ".join([f'"{start_num + j}": "..."' for j in range(points)])

                    template_formatted = f"""{{
                        "id": "block_{i}",
                        "instruction": "[GENERATE_DYNAMIC_INSTRUCTION]",
                        "script_title": "...",
                        "question_type": "matching",
                        "subtype": "{sub}",
                        "matching_options": {options_list_str},
                        "items": [{items_json}],
                        "answer": {{{answers_json}}}
                    }}"""

                    rules = SUBTYPE_RULES_REGISTRY.get(sub, "") + (
                        f"\n- CRITICAL: Return exactly ONE JSON object for this block."
                        f"\n- Provide exactly {points} items in the 'items' list."
                        f"\n- The 'answer' must be a dictionary mapping item IDs to correct letters."
                        f"\n- INSTRUCTION FORMAT: You MUST generate a context-specific question for the 'instruction' field."
                        f"\n- FORMAT: 'What [Speaker A] says/tells [Speaker B] about each of the following [Main Topic]?'"
                        f"\n- EXAMPLE: 'What does Professor Morgan tell the student about the following urban regeneration features?'"
                    )
                else:
        # Standard, Form, Table, Flow-chart
                    num_options = 3 if sub == "STANDARD" else 0
                    opt_letters = [chr(65 + j) for j in range(num_options)] if num_options > 0 else []
                    options_dict_str = "{" + ", ".join([f'"{let}": "..."' for let in opt_letters]) + "}" if num_options > 0 else "{}"
        
                    template_raw = STRUCTURE_GUIDES.get(sub, "{}")
                    template_formatted = template_raw.replace("{q_id}", "X").replace("{options_shape}", options_dict_str)
                    rules = SUBTYPE_RULES_REGISTRY.get(sub, "Follow standard IELTS logic.")

    # --- BLOCK 2: PROMPT ASSEMBLY ---
    # We use the template_formatted and rules already set above
                if sub in BLOCK_TYPES:
                    target_count_phrase = "exactly ONE task block object that encompasses all items"
                elif sub == "MULTI-SELECT":
        # Multi-select template_formatted already contains multiple objects joined by commas
                    target_count_phrase = f"exactly {points} individual question objects"
                else:
                    target_count_phrase = f"exactly {points} independent question objects"

                msg = f"""You are a Senior IELTS Content Architect. Generate {target_count_phrase} in strict JSON format.

                - SUBTYPE: {sub}
                - TARGET QUESTIONS: {start_num} through {end_num}
                - SOURCE MATERIAL: Use strictly the {half} HALF of the transcript.

                - RULES: 
                {rules}
                - TRAP LOGIC: {trap_logic}

                - JSON STRUCTURE MANDATE (Return ONLY this format):
                {{
                    "questions": [
                        {template_formatted}
                    ]
                }}"""
    
                prompts.append(msg)
    # 4. Execute Calls Safely (Your existing execution logic)
            print(f"Executing LLM Call 1 for Mixed Type: {sub1} (Questions 1-{count1})...")
            json_str_1 = ask_groq_json(prompts[0], f"Script Context: {full_transcript_text}", difficulty)

            print(f"Executing LLM Call 2 for Mixed Type: {sub2} (Questions {count1+1}-10)...")
            json_str_2 = ask_groq_json(prompts[1], f"Script Context: {full_transcript_text}", difficulty)

            try:
                data_1 = json.loads(json_str_1)
                data_2 = json.loads(json_str_2)

                q_list_1 = data_1.get("questions", data_1) if isinstance(data_1, dict) else data_1
                q_list_2 = data_2.get("questions", data_2) if isinstance(data_2, dict) else data_2

                if not isinstance(q_list_1, list): q_list_1 = [q_list_1]
                if not isinstance(q_list_2, list): q_list_2 = [q_list_2]

        # Combine and force sequential IDs (1 through 10)
                combined_questions = q_list_1 + q_list_2
                for idx, q in enumerate(combined_questions):
                    q['id'] = idx + 1 
    
                ques_data = {"questions": combined_questions}

            except Exception as e:
                print(f"ERROR combining JSON in mixed type: {e}")
                ques_data = {"questions": []}

    # 5. Visual Layout Generation (If Needed)
            
                
            # Set a flag to bypass the global LLM call below
            subtype_rules = {
            "FORM": """
                ROLE: Expert Registrar.
                FORMAT: Vertical, single-column.
                DISTRACTOR LOGIC: Identify 'rejected' info in the transcript (e.g., an old phone number or a previous address). 
                Insert these as STATIC_POINTs nearby to create context. 
                EXAMPLE: If the user changed their mind about a date, put the *original* date as a STATIC_POINT for a different field to see if the student can distinguish them.
                UI: Every QUESTION must have a clear 'prefix:' (e.g., 'Postcode:', 'Occupation:').
            """,
    
            "TABLE": """
            ROLE: Data Analyst.
            FORMAT: Horizontal Grid. 
            HEADER LOGIC: Identify the logical columns for this table (usually 2 or 3 columns total). 
            Generate a 'table_headers' array of strings and the headers should not be empty, they should all include information . 
            Make sure that "belongs_header" names should be identical to the "table_headers"
            Example: ['Venue', 'Event Type', 'Price'] or ['Role', 'Location', 'Responsibilities'].
            DISTRACTOR LOGIC: Use 'Comparison' distractors...
            UI: Group items under SUB_HEADINGs which act as the first column's value.
            CRITICAL: The sequence of answers in the script MUST match the visual scanning order of the table (Top-to-Bottom, then Left-to-Right). If Column B is mentioned before Column A, swap the columns.
            """,
    
            "FLOW-CHART": """
                ROLE: Process Engineer.
                FORMAT: Chronological sequence. 
                DISTRACTOR LOGIC: Focus on 'Process Changes'. If the speaker says 'We used to do X, but now we do Y', put X as a STATIC_POINT in a 'Previous Step' section or mention it as a requirement that no longer applies.
                UI: Ensure a logical flow. Every QUESTION should have a verb-based prefix (e.g., 'Sign the...', 'Pay the...').
            """
            };      
            structures = {
                "FORM": """{
                    "word_limit_instruction": "ONE WORD ONLY",
                    "subtype": "FORM",
                    "question_typ": "completion",
                    "answer":"answer text here",
                    "structure": [
                        { "type": "HEADING", "text": "Registration Form" },
                        { "type": "STATIC_POINT", "text": "Date of birth: 12/05/1998" },
                        { "type": "QUESTION", "q_id": 1, "prefix": "Occupation:", "suffix": "" }
                    ]
                }""",
    
                "TABLE": """{
                    "word_limit_instruction": "NO MORE THAN TWO WORDS",
                    "subtype": "TABLE",
                    "question_typ": "completion",
                    "answer":"answer text here",
                    "table_headers": ["Category", "Location", "Notes"],
                    "structure": [
                        { "type": "SUB_HEADING", "belongs_header":"write what header this information belongs", "text": "Library" },
                        { "type": "QUESTION", "belongs_header":"write what header this information belongs", "q_id": 1, "prefix": "", "suffix": "Square" },
                        { "type": "STATIC_POINT", "belongs_header":"write what header this information belongs", "text": "Closed on Mondays" }
                    ]
                }""",
    
                "FLOW-CHART": """{
                    "word_limit_instruction": "ONE WORD AND/OR A NUMBER",
                    "subtype": "FLOW-CHART",
                    "question_typ": "completion",
                    "answer":"answer text here",
                    "structure": [
                        { "type": "HEADING", "text": "Order Process" },
                        { "type": "QUESTION", "q_id": 1, "prefix": "Step 1: Check the", "suffix": "" },
                        { "type": "STATIC_POINT", "text": "Step 2: Wait for confirmation" },
                        { "type": "QUESTION", "q_id": 2, "prefix": "Step 3: Pay by", "suffix": "" }
                    ]
                }"""
            }

# Select the correct example based on the subtype
            if sub1 in ['FORM', 'TABLE', 'FLOW-CHART']:
                subtype = sub1
            elif sub2 in ['FORM', 'TABLE', 'FLOW-CHART']:
                subtype = sub2
            example_structure = structures.get(subtype, structures["FORM"])
            specific_rule = subtype_rules.get(subtype, "Maintain a standard exam layout.")
            layout_sys_msg = f"""
                You are a Senior IELTS Content Architect. Your mission is to create a visual layout that is 
                visually realistic but cognitively challenging.

                STRICT ARCHITECTURAL RULES:
                1. THE ANCHOR RULE: Use STATIC_POINTs as 'Anchors'. These are facts from the transcript that the 
                student hears *before* or *after* an answer. They help the student keep their place.
                2. THE DISTRACTOR TRAP: Scan the transcript for 'False Leads' (e.g., 'We thought about Tuesday, but 
                settled on Wednesday'). Use the 'False Lead' (Tuesday) as a STATIC_POINT in a different part 
                of the layout to test the student's focus.
                3. LABELING: All 'QUESTION' types must have a 'prefix' or 'suffix' that matches the natural 
                phrasing of a form (e.g., 'Total cost: £', 'Flight number:').
                4. NO SPOILERS: Do not put the actual answer in a STATIC_POINT.
    
                SUBTYPE SPECIFICS:
                {specific_rule}
            """
    # --- STAGE 3: VISUAL LAYOUT GENERATION (Mixed-Aware) ---
            visual_data_str = json.dumps({})  # Default empty JSON
    
    # Check if completion is the main type OR part of a mixed set
            # --- FIX 1: Update the logic check to look for the actual subtypes ---
            is_completion_task = (q_type == "mixed" and any(sub in ['FORM', 'TABLE', 'FLOW-CHART'] for sub in selected_subtypes))
                                
            completion_list = None
            multiple_choice_list = None

# Safely check the first element of q_list_1
            if q_list_1 and q_list_1[0].get("question_type") == "completion":
                completion_list = q_list_1
                print("q_list_1 is the Completion type.")
            else:
                completion_list = q_list_2
                print("q_list_2 is the Completion type.")
            if is_completion_task:
                try:
                    print(f"Running Layout Generator for {q_type} task...")
            # We specifically tell the AI to only layout the completion part
                    layout_prompt = f"""
                        Context: {full_transcript_text}
                        Questions: {completion_list}

                        TASK: Create a vertical registration form. 
                        1. Identify 'already known' facts (Distractors) from the transcript to use as STATIC_POINTs.
                        2. Ensure every QUESTION has a clear 'prefix:' label.
    
                        JSON STRUCTURE:
                        {example_structure}
                        """
                    layout_json_str = ask_groq_json(layout_sys_msg, layout_prompt, difficulty)
            
            # Validate and save
                    test_layout = json.loads(layout_json_str)
                    visual_data_str = json.dumps(test_layout)
            
                except Exception as e:
                    print(f"Warning: Layout generation failed. Error: {e}")
            else:
                print(f"Skipping Layout: Question type '{q_type}' doesn't need a text-based layout.")


        # FINAL DATABASE INSERT
        self.update_state(state='PROGRESS', meta={'progress': 90, 'status': 'Finalizing test package...'})
        db = mysql.connector.connect(**db_config)
        cursor = db.cursor()

        audio_path_for_db = f"static/{filename}"
        audio_url_for_frontend = f"{base_url}/static/{filename}"
        
        sql = """INSERT INTO practice_sessions 
                (user_id, topic, section_number, question_type, audio_url, transcript, 
                questions, word_limit_rule, visual_data, map_image_url, map_svg, layout_frontent) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""
    
        final_script = script_data.get('script', script_data) if isinstance(script_data, dict) else script_data
        db_questions = ques_data.get('questions', [])

        params = (
            user_id, raw_topic, section, q_type, audio_path_for_db,
            json.dumps(final_script), json.dumps(db_questions), spec['rule'],
            visual_data_str, map_url, map_svg_str, visual_data_str
        )

        cursor.execute(sql, params)
        db.commit()
        #deduction_successful = deduct_user_credits(user_id, required_credits)

        #if not deduction_successful:
    # Optional: You might want to rollback the DB insert here if they somehow ran out of credits mid-generation
            #print("Warning: Failed to deduct credits after generation.")

        #cursor.execute("SELECT balance FROM credits WHERE user_id = %s", (user_id,))
        #new_balance_row = cursor.fetchone()
        #new_balance = new_balance_row[0] if new_balance_row else 0

        # Return a DICTIONARY instead of a Flask Response
        return {
            "status": "success",
            "audio_url": audio_url_for_frontend,
            "script": final_script,
            "questions": db_questions,
            "word_limit_rule": spec['rule'],
            "form_title": ques_data.get('form_title', "") if isinstance(ques_data, dict) else "",
            "visual_data": visual_data_str,
            "map_svg": map_svg_str,
            "map_image_url": map_url,
        }

    except Exception as e:
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
    finally:
        if db and db.is_connected(): 
            db.close()

