import os
import re
import json
import jsonify
import random
import json_repair
import docx
import pdfplumber
import mysql.connector
from google import genai
from celery_tasks.celery_config import celery_app
import requests
import uuid
from collections import defaultdict
import spacy
import concurrent.futures
from dotenv import load_dotenv

# --- CELERY CONFIGURATION ---from dotenv import load_dotenv
load_dotenv()
nlp = spacy.load("en_core_web_sm")
# ==========================================
# 1. CONFIGURATION & PROMPTS
# ==================================


# Rules for how to construct each question type
QUESTION_RULES = {
    "multiple_choice": (
        "Construct 4-option questions. "
        "The question MUST test meaning, inference, or comparison, "
        "not direct factual recall. "
        "Correct answers must be supported by the passage but NOT explicitly stated in one sentence. "
        "Distractors must be partially correct but wrong in scope, cause, implication, or emphasis."
    ),

    "true_false_not_given": (
        "Test factual accuracy against the passage. "
        "TRUE = the statement agrees with the passage. "
        "FALSE = the statement clearly contradicts the passage. "
        "NOT_GIVEN = the passage does not provide enough information. "
        "Statements must be paraphrased and may combine ideas from multiple sentences. "
        "Ensure a balanced distribution of TRUE, FALSE, and NOT_GIVEN."
    ),

    "yes_no_not_given": (
        "Test the writer’s opinions, claims, or evaluations. "
        "YES = the writer clearly agrees or supports the statement. "
        "NO = the writer clearly disagrees or rejects it. "
        "NOT_GIVEN = the writer’s opinion is not stated or cannot be inferred. "
        "Do NOT use purely factual statements."
    ),

    "matching_headings": (
        "Create concise paragraph headings that reflect the MAIN IDEA, "
        "not specific details. "
        "Provide more headings than paragraphs, including close distractors "
        "that focus on minor points or examples rather than the central theme."
    ),

    "matching_information": (
        "Match specific information (examples, explanations, comparisons, implications) "
        "to paragraph letters. "
        "Information statements must be paraphrased and may require scanning and inference."
    ),

    "sentence_completion": (
        "Create gap-fill sentences that test understanding of meaning, "
        "not simple copying. "
        "The answer must be exact words taken from the passage. "
        "MAXIMUM TWO WORDS."
    ),

    "summary_completion": (
        "Create a summary of a specific section of the passage. "
        "Gaps must test key ideas, relationships, or conclusions. "
        "Answers must be exact words from the passage. "
        "MAXIMUM TWO WORDS."
    )
}


# Rules for Passage Generation (Phase 1)
PASSAGE_DIFFICULTY_RULES = {
    "Beginner": (
    "CEFR A2–B1. "
    "Simple sentence structures, limited subordination. "
    "Concrete topics, explicit explanations. "
    "High-frequency vocabulary, minimal academic terms. "
    "Explicit connectors (first, next, finally). "
    "Minimal abstraction or evaluation. "
    "Low trap density: mostly factual recall."
),

"Intermediate": (
    "CEFR B2. "
    "Mix of simple and complex sentences. "
    "Clear paragraph organization with some implicit meaning. "
    "Moderate academic vocabulary and collocations. "
    "Mix of explicit and implicit connectors (however, in contrast). "
    "Limited evaluation or comparison of ideas. "
    "Medium trap density: qualifier shifts, half-truths."
),

"Advanced": (
    "CEFR C1–C2. "
    "Frequent complex and embedded clauses. "
    "Abstract concepts, evaluation, and cautious or hedged claims. "
    "Low-frequency academic vocabulary, discipline-specific terms. "
    "Subtle cohesion, implicit logical flow. "
    "Multiple viewpoints or unresolved debates may be presented. "
    "Conclusions may be implicit rather than explicitly stated. "
    "High trap density: inference required, multiple plausible distractors."
)

}

# Rules for Question Complexity (Phase 2)
QUESTION_COMPLEXITY_RULES = {
    "Beginner": (
        "Questions must be literal and directly answerable from a single sentence. "
        "Avoid traps, inference, or close distractors."
    ),

    "Intermediate": (
        "Questions must require paraphrase recognition or understanding of cause and effect. "
        "Distractors should be plausible and related but clearly incorrect."
    ),

    "Advanced": (
        "Questions must require logical inference, synthesis of ideas across sentences or paragraphs, "
        "or identification of subtle distinctions in meaning or emphasis. "
        "Correct answers must NOT be directly stated. "
        "Distractors should be highly deceptive and partially supported by the text."
    )
}

ques_diff_rules = {
    "easy": (
        "STRATEGY: EXPLICIT CORRESPONDENCE (A1-B1 Level)\n"
        "- COGNITIVE TASK: Literal matching and surface-level identification.\n"
        "- LINGUISTIC ALIGNMENT: Use high-frequency 'Anchor Words' (names, numbers, specific nouns) that appear identically in the text.\n"
        "- CONTEXTUAL PROXIMITY: Ensure the evidence for the answer is physically close to the keywords in the text.\n"
        "- TRANSPARENCY: Avoid any distracting information or overlapping vocabulary that could lead to a 'wrong' choice."
    ),
    "medium": (
        "STRATEGY: LEXICAL TRANSFORMATION (B2 Level)\n"
        "- COGNITIVE TASK: Recognition of synonyms and parallel expressions.\n"
        "- LINGUISTIC ALIGNMENT: Systematically replace text keywords with synonyms or shift the grammar (e.g., changing an action to a result).\n"
        "- CONTEXTUAL PROXIMITY: Place the correct answer in the vicinity of 'Thematic Lures'—sentences that look relevant but contain conflicting facts.\n"
        "- DISTRACTION LOGIC: Distractors should represent common misconceptions or 'half-truths' found in the same paragraph."
    ),
    "hard": (
        "STRATEGY: CONCEPTUAL SYNTHESIS & ABSTRACTION (C1-C2 Level)\n"
        "- COGNITIVE TASK: Global understanding, inference of authorial tone, and logical deduction.\n"
        "- LINGUISTIC ALIGNMENT: Use entirely 'Zero-Overlap' phrasing. Summarize complex arguments or abstract concepts without using any technical vocabulary from the source.\n"
        "- CONTEXTUAL PROXIMITY: Evidence must be synthesized from multiple sentences or different paragraphs. The answer should require 'connecting the dots'.\n"
        "- ADVERSARIAL LOGIC: Use 'Negative Constraints' (identifying what is NOT true) and 'Nuance Traps' (focusing on words like 'scarcely', 'ostensibly', or 'hitherto' to flip meaning)."
    )
}
# ==========================================
# 2. UTILITY FUNCTIONS
# ==========================================


def get_subtype_traps(active_subtype, trap_metadata):
    """
    Filters trap anchors to only include those specifically generated 
    for the current subtype.
    """
    anchors = trap_metadata.get("anchors", [])
    
    # 1. Filter anchors where the 'subtype' key matches active_subtype
    # We use .upper() and .strip() to ensure matching isn't broken by casing or spaces
    subtype_specific_anchors = [
        a for a in anchors 
        if str(a.get("subtype", "")).upper().strip() == str(active_subtype).upper().strip()
    ]
    
    # 2. Prioritize high-quality traps within that specific list
    valid_anchors = [a for a in subtype_specific_anchors if a.get("trap_rank") == "High"] or subtype_specific_anchors
    
    if not valid_anchors:
        return "\n(No specific trap anchors identified for this subtype. Use general IELTS adversarial logic.)\n"

    block = f"\n### MANDATORY TRAP ANCHORS FOR {active_subtype.upper()}:\n"
    for i, anchor in enumerate(valid_anchors[:5]):
        block += f"ANCHOR {i+1}:\n"
        block += f"   - Logic to implement: {anchor.get('trap_logic', '')}\n"
        block += f"   - Source Text to reference: \"{anchor.get('original_text', '')}\"\n"
        block += f"   - Distractor Example: \"{anchor.get('distractor_example', '')}\"\n\n"
        
    return block
def clean_text(text):
    """Normalizes text and ensures consistent paragraph spacing."""
    if not text: return ""
    text = re.sub(r'\n\s*\n+', '||PAR||', text)
    text = text.replace('\n', ' ')
    text = text.replace('||PAR||', '\n\n')
    return re.sub(r' +', ' ', text).strip()

def label_paragraphs(text):
    """Adds [A], [B] labels to paragraphs."""
    paragraphs = [p.strip() for p in text.split('\n\n') if p.strip()]
    labeled = []
    for i, p in enumerate(paragraphs):
        label = chr(65 + i)
        labeled.append(f"[{label}] {p}")
    return "\n\n".join(labeled)

def extract_text_from_file(file_path, file_type=None):
    """Extracts text from PDF, DOCX, or TXT."""
    if not file_path: return None
    if file_type:
        ext = "." + file_type.lower().replace(".", "")
    else:
        ext = os.path.splitext(file_path)[1].lower()

    try:
        if "pdf" in ext:
            with pdfplumber.open(file_path) as pdf:
                return "\n\n".join([p.extract_text() or "" for p in pdf.pages])
        elif "docx" in ext:
            doc = docx.Document(file_path)
            return "\n\n".join([p.text for p in doc.paragraphs if p.text.strip()])
        elif "txt" in ext:
            with open(file_path, "r", encoding="utf-8") as f:
                return f.read()
    except Exception as e:
        print(f"File extraction error: {e}")
        return None
    return None

def shuffle_options(question_obj):
    subtype = question_obj.get("subtype", "")
    if subtype in ["MCQ-MULTI", "MATCHING-HEADINGS", "MATCHING-INFO", "MATCHING-FEATURES"]:
        return question_obj

    options = question_obj.get("metadata", {}).get("options_box", []) or question_obj.get("options", [])
    if not options or len(options) != 4:
        return question_obj

    # 1. IDENTIFY THE CORRECT TEXT BY LABEL
    # Map 'A', 'B', 'C', 'D' to index 0, 1, 2, 3
    original_answer_label = str(question_obj.get("answer", "A")).upper()[0]
    label_to_idx = {"A": 0, "B": 1, "C": 2, "D": 3}
    
    try:
        idx = label_to_idx[original_answer_label]
        # Get the full string (e.g., "A: The early methodological...")
        full_correct_str = options[idx]
        # Clean it to get just the text
        correct_text = full_correct_str.split(':', 1)[-1].strip() if ':' in full_correct_str else full_correct_str.split(')', 1)[-1].strip() if ')' in full_correct_str else full_correct_str
    except (KeyError, IndexError):
        return question_obj # Abort if answer key is invalid

    # 2. CLEAN ALL OPTIONS
    clean_opts = []
    for opt in options:
        clean = opt.split(':', 1)[-1].strip() if ':' in opt else opt.split(')', 1)[-1].strip() if ')' in opt else opt
        clean_opts.append(clean)

    # 3. SHUFFLE
    random.shuffle(clean_opts)

    # 4. RE-LABEL AND ASSIGN
    labels = ["A", "B", "C", "D"]
    new_options = []
    new_answer = "A" 

    for i, opt_text in enumerate(clean_opts):
        new_options.append(f"{labels[i]}: {opt_text}")
        if opt_text == correct_text:
            new_answer = labels[i] 

    if "metadata" in question_obj:
        question_obj["metadata"]["options_box"] = new_options
    else:
        question_obj["options"] = new_options
        
    question_obj["answer"] = new_answer
    return question_obj

# 1. Initialize the Client once (outside the function is better for performance)
client = genai.Client(api_key=os.getenv('GEMINI_API_KEY'))

def call_llm(system_prompt, user_prompt, json_mode=False, difficulty='intermediate', user_choice='sentence_completion'):
    print(f"LLM CALL | Difficulty: {difficulty} | User Choice: {user_choice}")
    try:
        PREMIUM_MODEL = 'gemini-3.1-pro-preview'
        
        # 1. Provide a fallback default model name
        model_name = "gemini-2.5-pro" 

        if difficulty == 'beginner':
            model_name = PREMIUM_MODEL if user_choice == 'mixed' else "gemini-1.5-pro"
        elif difficulty == 'intermediate' or difficulty == 'intermediate': # Handle your default case
            model_name = PREMIUM_MODEL if user_choice == 'mixed' else "gemini-2.5-pro"
        elif difficulty == 'advanced':
            model_name = PREMIUM_MODEL
        
        # 2. Safety check: ensure model_name is never empty
        if not model_name:
            model_name = "gemini-2.5-pro"

        # ... rest of your config and generate_content call
        # 3. Configuration
        # The new SDK uses 'response_mime_type' inside a GenerateConfig object
        config = {
            "temperature": 1.0,
            "top_p": 0.95,
            "max_output_tokens": 8192,
            "system_instruction": system_prompt,
            "response_mime_type": "application/json" if json_mode else "text/plain",
        }

        # 4. Generate Content
        response = client.models.generate_content(
            model=model_name,
            contents=user_prompt,
            config=config
        )

        content = response.text
        
        if json_mode:
            try:
                # json_repair is great for fixing potential markdown backticks or truncations
                return json_repair.loads(content)
            except Exception as e:
                print(f"JSON Parsing Error: {e}")
                print(f"RAW CONTENT: {content[:100]}...")
                return None
                
        return content

    except Exception as e:
        print(f"Gemini API Error: {e}")
        return None

def get_reading_trap_instructions(difficulty):
    # --- LEVEL 1: EASY (Direct & Linear) ---
    if difficulty.lower() == "easy":
        return """### TRAP INSTRUCTIONS (EASY):
        - NO DISTRACTORS: The answer should be the only logical fit for the keyword.
        - PROXIMITY: Place the answer in the same sentence as the main keyword.
        - FORBIDDEN: Do not use negative qualifiers (e.g., 'not', 'rarely') that change the meaning of the keyword."""

    # --- LEVEL 2: MEDIUM (Paraphrasing & Proximity) ---intermediate
    elif difficulty.lower() == "intermediate":
        traps = [
            "NEARBY DISTRACTOR: Include a word from the same category as the answer (e.g., another date or name) in the adjacent sentence.",
            "SYNONYM CLUSTERING: Use synonyms for the question's keywords, but place the actual answer next to a different synonym to test scanning.",
            "MODIFIER TRAP: Use words like 'most', 'all', or 'some' to qualify a statement, making a general answer incorrect.",
            "THEMATIC DISTRACTOR: Use a keyword from the question in a paragraph that discusses a completely different topic."
        ]
        selected = random.sample(traps, 4)
        return "### TRAP INSTRUCTIONS (MEDIUM):\n" + "\n".join([f"- {t}" for t in selected])

    # --- LEVEL 3: HARD (Logic, Syntax & Referencing) ---
    else: # Hard
        traps = [
            "FALSE CORRESPONDENCE: Mention the exact keyword from the question, but link it to an incorrect fact or result.",
            "PRONOUN REFERENCING: Use 'this', 'it', or 'latter' to refer to the answer located in the previous sentence, forcing the user to track the subject.",
            "CONTRASTIVE LOGIC: Use 'however', 'despite', or 'although' to provide an answer that contradicts the initial premise of the paragraph.",
            "MULTI-SENTENCE SYNTHESIS: The information needed to fill the gap is split across two sentences (e.g., Sentence A introduces the cause, Sentence B the effect).",
            "SPECIFICITY TRAP: Include a general term and a specific term; the question asks for one but the text emphasizes the other."
        ]
        selected = random.sample(traps, 5)
        return "### TRAP INSTRUCTIONS (HARD - C1 Level):\n" + "\n".join([f"- {t}" for t in selected])


def get_passage_trap_seeds(difficulty):
    """
    Return a detailed, ready-to-use passage-trap seed string for the requested difficulty.
    Each returned string includes:
      - Purpose and goals for the passage
      - Concrete trap types to include (names + short descriptions)
      - Placement guidance (where to put each trap)
      - Simple automated validation checks to run after generation
      - Short prompt snippets to inject into a passage generator
      - Span-tagging and auditing guidance
      - Fairness and pilot checklist
    """

    d = difficulty.lower()
    if d == "beginner" or d == "easy":
        return """
### PASSAGE STRUCTURE (EASY / BEGINNER)
**Goal**
- Produce clear, fair passages that test basic comprehension and scanning without unfair ambiguity.

**Recommended Passage-Level Traps (Easy)**
1. DIRECT CORRESPONDENCE
   - Description: Each paragraph contains one clear main fact or idea.
   - Placement: Topic sentence first; supporting sentence(s) follow.
2. EXPLICIT LINKS
   - Description: Use simple connectors (and, but, because, therefore) to make logical relations explicit.
   - Placement: Between sentences to show cause/effect or contrast.
3. SINGLE NUMERIC REFERENCE
   - Description: If numbers appear, use one per paragraph and label units clearly.
   - Placement: In the same sentence as the fact it supports.
4. NAMED ANCHOR
   - Description: Introduce one person/place per paragraph and use that name consistently.
   - Placement: First mention in the paragraph; reuse the same name rather than pronouns.
5. NO RED HERRINGS
   - Description: Avoid irrelevant dates, technical details, or side examples that are not central.
   - Placement: Do not include peripheral facts.

**How to inject (writer prompt snippet)**
- "Keep paragraphs short (3–5 sentences). Each paragraph must state one main fact in the first sentence. Use one clear example and one explicit transition word. If you include a number, label its unit."

**Validation checks (automated heuristics)**
- Ensure each paragraph contains exactly one main noun phrase/topic sentence.
- Confirm no paragraph has more than one numeric token.
- Verify transition words present where cause/contrast is intended.

**Span tagging guidance**
- Tag the sentence index of the topic sentence for each paragraph: e.g., {"trap":"direct_correspondence","paragraph":2,"sentence":1}.

**Fairness & Pilot checklist**
- Limit traps to 0–1 per paragraph.
- Ensure all facts are resolvable from the passage alone (no outside knowledge).
- Pilot with a small beginner group; check that >80% of correct answers come from direct text matches.
"""

    elif d == "intermediate" or d == "medium":
        return """
### PASSAGE STRUCTURE (INTERMEDIATE / MEDIUM)
**Goal**
- Increase scanning and inference demands while remaining resolvable from the text.

**Recommended Passage-Level Traps (Intermediate)**
1. SYNONYM CLUSTERING
   - Description: Use 2–3 synonyms for a concept within a paragraph (e.g., agriculture / farming / cultivation).
   - Placement: Spread across the paragraph to require scanning.
2. DATA CLUSTERING
   - Description: Place two related numbers or dates in the same sentence to require unit and context awareness.
   - Placement: Single sentence containing both numeric tokens.
3. ATTRIBUTION CONTRAST
   - Description: Include a reported claim (X 'claimed') and a later qualifier or corrective statement.
   - Placement: Claim in one sentence; qualifier 1–2 sentences later.
4. LOCAL RED HERRING
   - Description: Add one plausible but irrelevant sentence near the correct evidence to lure skimmers.
   - Placement: Immediately before or after the evidence sentence.
5. PRONOUN LINK
   - Description: Use a pronoun that must be resolved to an antecedent in the previous sentence.
   - Placement: Pronoun in sentence N, antecedent in sentence N-1.
6. MINOR TECHNICAL DETAIL
   - Description: Insert a short technical phrase that is plausible but not central to the main point.
   - Placement: As a subordinate clause or parenthetical.

**How to inject (writer prompt snippet)**
- "Include a reported claim and a later qualifier. Place two numbers in one sentence (with units). Add one plausible but irrelevant sentence near the evidence. Use one pronoun that links to the previous sentence."

**Validation checks (automated heuristics)**
- Confirm presence of at least one claim + qualifier pair.
- Verify two numeric tokens appear in the same sentence and have units.
- Check pronoun antecedent is resolvable within one prior sentence.
- Ensure the red herring sentence does not contain the exact answer span.

**Span tagging guidance**
- Tag claim and qualifier sentence indices: e.g., {"trap":"attribution_contrast","sentences":[3,5]}.
- Tag numeric sentence index and units: {"trap":"data_clustering","sentence":4,"numbers":["1920","1924"],"units":["year","year"]}.

**Fairness & Pilot checklist**
- Limit traps to 1–2 per paragraph and 2–4 per passage.
- Avoid cultural or domain knowledge dependencies.
- Pilot with intermediate readers; check item discrimination and revise red herrings that cause >30% random guessing.
"""

    else:  # advanced / hard
        return """
### PASSAGE STRUCTURE (ADVANCED / HARD - C1/C2)
**Goal**
- Create passages that require synthesis, inference, and careful handling of nuance; suitable for advanced readers.

**Recommended Passage-Level Traps (Advanced)**
1. LOGIC LIMITERS (THE 'LOGIC FLIP')
   - Description: Use temporal or conditional qualifiers (formerly, hitherto, provided that) to limit the truth of a statement.
   - Placement: In the claim sentence; follow with qualification or pivot.
2. NARRATIVE PIVOTS
   - Description: Insert a mid-paragraph pivot (however, despite this, conversely) that reverses or significantly qualifies the initial claim.
   - Placement: Mid-paragraph; pivot sentence should change the direction of the argument.
3. DETAILED RED HERRING
   - Description: Include a technically rich but peripheral example that mimics the main argument’s language.
   - Placement: As a long subordinate clause or separate sentence near the main claim.
4. PRONOUN OVERLOAD
   - Description: Use multiple referencing expressions ('the former', 'this phenomenon', 'it') that require back-referencing across sentences.
   - Placement: Spread pronouns across 2–3 sentences to force lookback.
5. CONTRASTED EVIDENCE
   - Description: Present an experimental claim in one paragraph and a large-scale counteranalysis in another.
   - Placement: Claim in paragraph N; counteranalysis in paragraph N+1 or N+2.
6. NUMERIC UNIT CONFLICT
   - Description: Reuse the same numeral in different paragraphs with different units (percent vs count) to tempt unit confusion.
   - Placement: Numeral A in paragraph 1 with unit X; same numeral in paragraph 3 with unit Y.

**How to inject (writer prompt snippet)**
- "Write a paragraph that asserts X, then pivot with 'however' to limit X. Include a detailed but peripheral example that uses similar vocabulary to the main claim. Use at least one pronoun that refers back two sentences. Reuse a numeral elsewhere with a different unit. Add a contrasting large-scale analysis in a later paragraph."

**Validation checks (automated heuristics)**
- Detect pivot words (however, despite, conversely) and ensure the following sentence qualifies or contradicts the prior claim.
- Verify at least one pronoun requires a two-sentence lookback to resolve.
- Confirm numeral reuse with differing units across paragraphs.
- Ensure the red herring contains plausible vocabulary but does not itself provide the correct answer span.

**Span tagging guidance**
- Tag pivot and qualification sentences: {"trap":"narrative_pivot","sentences":[2,3]}.
- Tag pronoun-heavy spans and their antecedents: {"trap":"pronoun_overload","spans":[(4,5),(5,6)]}.
- Tag numeric reuse: {"trap":"numeric_unit_conflict","occurrences":[{"paragraph":1,"number":"30","unit":"%"},{"paragraph":3,"number":"30","unit":"people"}]}.

**Fairness & Pilot checklist**
- Limit traps to 2–4 per passage for advanced level.
- Ensure every trap is resolvable using only passage text; do not rely on outside knowledge.
- Pilot with advanced readers; check that high-ability readers consistently choose correct answers and that traps discriminate (high discrimination index).
- If any trap causes >40% of advanced readers to answer incorrectly in a way that suggests ambiguity, revise or remove it.

**Integration & Usage Notes**
- When generating the passage, record sentence indices for each trap so question generation can reference or exploit them.
- Use the validation checks to automatically flag and regenerate passages that fail heuristics.
- Keep a log of trap placements and types for later item analysis and to ensure balanced distribution across test forms.
"""




def get_traps_for_question(difficulty, question_type):
    traps_library = {
    "sentence_completion": {
        "easy": [
            {
                "name": "word_spotting",
                "description": "WORD-SPOTTING LURE: Place a high-frequency keyword from the text in the question prefix/suffix.",
                "how_to_use": "Ensure the actual answer is a synonym found elsewhere in the text to punish simple word-matching."
            },
            {
                "name": "negation_trap",
                "description": "NEGATION TRAP: Use a negative qualifier in the text (hardly, seldom, scarcely) to flip meaning.",
                "how_to_use": "Ask for the status so the student must detect the negative nuance."
            },
            {
                "name": "surface_synonym",
                "description": "SURFACE SYNONYM: Provide a distractor that looks like a synonym but doesn’t fit the context.",
                "how_to_use": "Use common synonyms that are grammatically correct but semantically wrong."
            },
            {
                "name": "punctuation_confusion",
                "description": "PUNCTUATION CONFUSION: Distractor relies on misreading commas or clauses.",
                "how_to_use": "Split sentences so the wrong clause looks like the answer."
            },
            {
                "name": "false_friend",
                "description": "FALSE FRIEND: Use a word that looks similar to the correct answer but has a different meaning.",
                "how_to_use": "Insert homonyms or look-alike words to lure skimmers."
            },
            {
                "name": "article_confusion",
                "description": "ARTICLE CONFUSION: Trap based on 'a/an/the' usage differences.",
                "how_to_use": "Make distractors that differ only by article to test precision."
            }
        ],
        "intermediate": [
            {
                "name": "syntactic_flip",
                "description": "SYNTACTIC FLIP: The answer requires a grammar shift (noun → adjective, verb → noun).",
                "how_to_use": "Structure the question so only the shifted form fits grammatically."
            },
            {
                "name": "redundancy_trap",
                "description": "REDUNDANCY TRAP: Mention two similar objects/dates; question describes one but expects the other.",
                "how_to_use": "Design distractors around near-duplicate details to test precision."
            },
            {
                "name": "context_shift",
                "description": "CONTEXT SHIFT: Distractor is correct in another context but not in the one asked.",
                "how_to_use": "Use words that appear in text but belong to a different sentence or meaning."
            },
            {
                "name": "collocation_trap",
                "description": "COLLOCATION TRAP: Distractor fits grammatically but is not the natural collocation.",
                "how_to_use": "Insert words that don’t naturally pair with the surrounding phrase."
            },
            {
                "name": "partial_quote",
                "description": "PARTIAL QUOTE: Distractor copies part of the text but omits the key qualifier.",
                "how_to_use": "Use truncated phrases that change meaning."
            },
            {
                "name": "anaphora_trap",
                "description": "ANAPHORA TRAP: Distractor depends on resolving a pronoun to the wrong antecedent.",
                "how_to_use": "Place pronouns in nearby sentences to create plausible but incorrect antecedents."
            }
        ],
        "advanced": [
            {
                "name": "distance_trap",
                "description": "DISTANCE TRAP: Separate clue and answer far apart in the text.",
                "how_to_use": "Anchor with a name early in the paragraph, but place the answer at the end."
            },
            {
                "name": "multi_clause_confusion",
                "description": "MULTI-CLAUSE CONFUSION: Distractor comes from a different clause in a complex sentence.",
                "how_to_use": "Force scanning across clauses to identify the right one."
            },
            {
                "name": "semantic_inversion",
                "description": "SEMANTIC INVERSION: Distractor reverses the meaning of a subtle phrase.",
                "how_to_use": "Flip implied meaning without changing grammar to trap inference errors."
            },
            {
                "name": "cross_paragraph",
                "description": "CROSS-PARAGRAPH: Distractor pulls info from another paragraph.",
                "how_to_use": "Mix details across sections to mislead readers who skim locally."
            },
            {
                "name": "rare_word_trap",
                "description": "RARE WORD TRAP: Distractor uses obscure vocabulary that appears in the text.",
                "how_to_use": "Insert rare words that appear but don’t fit the blank semantically."
            },
            {
                "name": "pronoun_resolution",
                "description": "PRONOUN RESOLUTION: Distractor requires resolving ambiguous pronouns across sentences.",
                "how_to_use": "Design the blank so correct resolution needs cross-sentence reasoning."
            }
        ]
    },

    "multiple_choice": {
        "easy": [
            {
                "name": "keyword_match_lure",
                "description": "KEYWORD MATCH LURE: Distractor uses exact phrasing from the text but misrepresents context.",
                "how_to_use": "Copy terminology but alter meaning so it looks right at first glance."
            },
            {
                "name": "qualifier_shift",
                "description": "QUALIFIER SHIFT: Change 'some/most' into 'all/only/always'.",
                "how_to_use": "Flip qualifiers to make the statement false."
            },
            {
                "name": "literal_misread",
                "description": "LITERAL MISREAD: Distractor interprets figurative language literally.",
                "how_to_use": "Take metaphors or idioms as literal facts to trap surface readers."
            },
            {
                "name": "obvious_extreme",
                "description": "OBVIOUS EXTREME: Distractor exaggerates the claim.",
                "how_to_use": "Turn moderate statements into extreme ones to be easily rejected by careful readers."
            },
            {
                "name": "false_comparison",
                "description": "FALSE COMPARISON: Distractor introduces a comparison not in the text.",
                "how_to_use": "Add comparative language that wasn’t present to lure skimmers."
            },
            {
                "name": "antonym_trap",
                "description": "ANTONYM TRAP: Distractor uses an antonym that superficially fits the sentence.",
                "how_to_use": "Offer the opposite term that looks plausible but contradicts the passage."
            }
        ],
        "intermediate": [
            {
                "name": "half_right_trap",
                "description": "HALF-RIGHT TRAP: First part true, second part false.",
                "how_to_use": "Combine a correct detail with a wrong relationship or date."
            },
            {
                "name": "irrelevant_truth",
                "description": "IRRELEVANT TRUTH: Factually correct but irrelevant to the question.",
                "how_to_use": "Insert a true statement that doesn’t answer the stem."
            },
            {
                "name": "scope_confusion",
                "description": "SCOPE CONFUSION: Distractor applies detail universally when it’s local.",
                "how_to_use": "Expand subgroup detail to a global claim to test scope awareness."
            },
            {
                "name": "time_shift",
                "description": "TIME SHIFT: Distractor changes the time reference (past ↔ present/future).",
                "how_to_use": "Replace past tense facts with present/future to mislead."
            },
            {
                "name": "actor_swap",
                "description": "ACTOR SWAP: Distractor attributes action to the wrong person/group.",
                "how_to_use": "Swap subjects or agents to create plausible but incorrect options."
            },
            {
                "name": "implication_trap",
                "description": "IMPLICATION TRAP: Distractor states an implication not supported by the text.",
                "how_to_use": "Use plausible inference that the passage does not actually support."
            }
        ],
        "advanced": [
            {
                "name": "data_misalignment",
                "description": "DATA MISALIGNMENT: Use numbers/percentages but attach them to the wrong category/time.",
                "how_to_use": "Keep digits identical but misattribute them to a different subject or period."
            },
            {
                "name": "order_of_events_shuffle",
                "description": "ORDER OF EVENTS: Reverse or shuffle sequence of events.",
                "how_to_use": "Suggest B caused A or happened first when text says A then B."
            },
            {
                "name": "subjectivity_vs_fact",
                "description": "SUBJECTIVITY VS FACT: Convert opinion/claim into proven fact.",
                "how_to_use": "Turn reported claims into absolute statements to test distinction between claim and evidence."
            },
            {
                "name": "cross_reference_error",
                "description": "CROSS REFERENCE ERROR: Distractor pulls info from another paragraph or figure.",
                "how_to_use": "Mix details across sections to mislead those who don’t verify context."
            },
            {
                "name": "multi_factor_confusion",
                "description": "MULTI-FACTOR CONFUSION: Distractor combines two unrelated factors into one statement.",
                "how_to_use": "Merge details from different contexts to create a plausible-sounding but incorrect option."
            },
            {
                "name": "statistical_significance_trap",
                "description": "STATISTICAL SIGNIFICANCE TRAP: Distractor implies results are statistically significant when text only reports trends.",
                "how_to_use": "Use statistical language (significant, proven) to overstate tentative findings."
            }
        ]
    },

    "multi_select": {
        "easy": [
            {
                "name": "modifier_trap",
                "description": "MODIFIER TRAP: Alter a qualifier (some → all, often → always).",
                "how_to_use": "Flip qualifiers to make distractor incorrect."
            },
            {
                "name": "negation_trap",
                "description": "NEGATION TRAP: Flip polarity (not allowed → allowed).",
                "how_to_use": "Reverse the text’s stated permission/ban."
            },
            {
                "name": "keyword_surface",
                "description": "KEYWORD SURFACE: Distractor repeats a text keyword but shifts meaning.",
                "how_to_use": "Use the same word in a different context to lure skimmers."
            },
            {
                "name": "extreme_statement",
                "description": "EXTREME STATEMENT: Distractor exaggerates a claim into an absolute.",
                "how_to_use": "Turn moderate claims into absolutes to be rejected by careful readers."
            },
            {
                "name": "false_equivalence",
                "description": "FALSE EQUIVALENCE: Distractor equates two unrelated ideas.",
                "how_to_use": "Suggest equivalence not supported by the passage."
            },
            {
                "name": "simple_negation",
                "description": "SIMPLE NEGATION: Distractor is the direct negation of a true statement.",
                "how_to_use": "Use straightforward opposites to trap inattentive readers."
            }
        ],
        "intermediate": [
            {
                "name": "true_but_wrong",
                "description": "TRUE BUT WRONG: Factually correct but doesn’t answer the question.",
                "how_to_use": "Use a cause when the stem asks for an effect or vice versa."
            },
            {
                "name": "scope_trap",
                "description": "SCOPE TRAP: True for a subgroup but presented as universal.",
                "how_to_use": "Expand local detail into a global claim."
            },
            {
                "name": "temporal_trap",
                "description": "TEMPORAL TRAP: Shift timeline (past decade → currently/future).",
                "how_to_use": "Change time reference to mislead."
            },
            {
                "name": "redundancy_trap",
                "description": "REDUNDANCY TRAP: Confuse two similar details.",
                "how_to_use": "Reference the wrong but similar fact as a distractor."
            },
            {
                "name": "partial_overlap",
                "description": "PARTIAL OVERLAP: Distractor shares some elements with correct options but omits a key qualifier.",
                "how_to_use": "Make distractors that look correct until the missing qualifier is noticed."
            },
            {
                "name": "attribution_swap",
                "description": "ATTRIBUTION SWAP: Distractor attributes a finding to the wrong study or author.",
                "how_to_use": "Swap researcher names or study contexts to create plausible errors."
            }
        ],
        "advanced": [
            {
                "name": "number_vs_percentage",
                "description": "NUMBER VS PERCENTAGE: Swap units (30% → 30 people).",
                "how_to_use": "Keep digits identical but change unit type to trap unit-skimming."
            },
            {
                "name": "causality_trap",
                "description": "CAUSALITY TRAP: Reverse cause and effect.",
                "how_to_use": "Claim Y caused X when text says X caused Y."
            },
            {
                "name": "subjectivity_vs_fact",
                "description": "SUBJECTIVITY VS FACT: Convert opinion/claim into proven fact.",
                "how_to_use": "Turn 'researchers suggested' into 'it is proven' to test claim vs evidence."
            },
            {
                "name": "absent_truth",
                "description": "ABSENT TRUTH: Insert a well-known fact not mentioned in the text.",
                "how_to_use": "Add plausible but absent information to lure those relying on world knowledge."
            },
            {
                "name": "data_misalignment",
                "description": "DATA MISALIGNMENT: Misattribute numbers/percentages to wrong categories or times.",
                "how_to_use": "Attach correct digits to the wrong subject or period."
            },
            {
                "name": "counterfactual_trap",
                "description": "COUNTERFACTUAL TRAP: Distractor states a plausible 'what if' scenario as if it were fact.",
                "how_to_use": "Frame hypothetical outcomes as actual results to mislead."
            }
        ]
    },

    "summary_bank": {
        "easy": [
            {
                "name": "semantic_overlap",
                "description": "SEMANTIC OVERLAP: Include 3 similar words, only one fits.",
                "how_to_use": "Distractors are synonyms but contextually wrong."
            },
            {
                "name": "grammatical_uniformity",
                "description": "GRAMMATICAL UNIFORMITY: All distractors same part of speech.",
                "how_to_use": "Force grammar fit but only one is semantically correct."
            },
            {
                "name": "distractor_length",
                "description": "DISTRACTOR LENGTH: Make distractors longer or shorter to lure skimmers.",
                "how_to_use": "Use length differences to create surface appeal."
            },
            {
                "name": "synonym_pool",
                "description": "SYNONYM POOL: Provide several near-synonyms where only one matches nuance.",
                "how_to_use": "Test fine-grained vocabulary choice."
            },
            {
                "name": "distractor_order",
                "description": "DISTRACTOR ORDER: Place distractors in an order that mimics the text sequence.",
                "how_to_use": "Make the wrong option appear to match the passage flow."
            }
        ],
        "intermediate": [
            {
                "name": "textual_trap",
                "description": "TEXTUAL TRAP: Word appears in text but is wrong for summary.",
                "how_to_use": "Insert text words that don’t logically complete the summary."
            },
            {
                "name": "cohesion_trap",
                "description": "COHESION TRAP: Distractor breaks logical cohesion when inserted.",
                "how_to_use": "Choose words that disrupt the summary’s flow despite surface fit."
            },
            {
                "name": "pronoun_antecedent",
                "description": "PRONOUN ANTECEDENT: Distractor depends on resolving ambiguous pronouns.",
                "how_to_use": "Require correct antecedent resolution to select the right word."
            },
            {
                "name": "summary_scope",
                "description": "SUMMARY SCOPE: Distractor narrows or widens the scope incorrectly.",
                "how_to_use": "Offer options that change the intended scope of the summary."
            },
            {
                "name": "paraphrase_trap",
                "description": "PARAPHRASE TRAP: Distractor is a plausible paraphrase that subtly changes emphasis.",
                "how_to_use": "Test ability to detect nuance in paraphrasing."
            }
        ],
        "advanced": [
            {
                "name": "synthesis_trap",
                "description": "SYNTHESIS TRAP: Correct answer requires combining ideas across sentences; distractors use single-sentence facts.",
                "how_to_use": "Make the right choice require synthesis, distractors appear locally correct."
            },
            {
                "name": "inference_required",
                "description": "INFERENCE REQUIRED: Summary completion needs an inference not explicitly stated.",
                "how_to_use": "Design distractors that are literal but fail the inference test."
            },
            {
                "name": "paraphrase_precision",
                "description": "PARAPHRASE PRECISION: Distractors are accurate paraphrases that miss a subtle qualifier.",
                "how_to_use": "Require precise paraphrase matching to the passage nuance."
            },
            {
                "name": "nuance_loss",
                "description": "NUANCE LOSS: Distractor removes an important nuance (e.g., conditionality).",
                "how_to_use": "Offer options that lose conditional or modal meaning."
            },
            {
                "name": "omitted_detail_trap",
                "description": "OMITTED DETAIL TRAP: Distractor omits a critical detail that changes meaning.",
                "how_to_use": "Make the correct option include the small but decisive detail."
            },
            {
                "name": "compression_trap",
                "description": "COMPRESSION TRAP: Distractor compresses two ideas into one oversimplified phrase.",
                "how_to_use": "Test ability to preserve complexity in summaries."
            }
        ]
    },
    "matching_headings": {
        "easy": [
            {
                "name": "first_sentence_echo",
                "description": "OPENING ECHO: The heading matches keywords in the first sentence but ignores the rest of the paragraph.",
                "how_to_use": "Use a heading that reflects the 'hook' or introduction rather than the actual main idea."
            },
            {
                "name": "single_detail_elevation",
                "description": "DETAIL ELEVATION: A heading that accurately describes one small example or name mentioned in the text.",
                "how_to_use": "Elevate a minor statistic or a proper noun to a heading status to lure those who don't distinguish theme from detail."
            }
        ],
        "intermediate": [
            {
                "name": "pivot_trap",
                "description": "PIVOT TRAP: The paragraph starts with one topic but shifts after 'however' or 'but'.",
                "how_to_use": "Provide a heading for the initial topic (the 'concession') to trap students who stop reading halfway."
            },
            {
                "name": "synonym_mismatch",
                "description": "SYNONYM MISMATCH: The heading uses a synonym that has a slightly different scope (e.g., 'financial' vs 'commercial').",
                "how_to_use": "Use near-synonyms that fit the general field but fail the specific nuance of the paragraph's argument."
            }
        ],
        "advanced": [
            {
                "name": "unfulfilled_promise",
                "description": "UNFULFILLED PROMISE: A heading that sounds like a logical next step for the topic but is never actually discussed.",
                "how_to_use": "Create a highly plausible 'academic' heading that uses text vocabulary but describes a concept the author didn't cover."
            },
            {
                "name": "summary_vs_description",
                "description": "SUMMARY VS DESCRIPTION: Distinguishing between a heading that describes the *topic* and one that summarizes the *argument*.",
                "how_to_use": "Provide one heading that is a vague label (e.g., 'Ship construction') and another that is a specific summary (e.g., 'A new method for hull design')."
            }
        ]
    },

    "matching_information": {
        "easy": [
            {
                "name": "keyword_clutter",
                "description": "KEYWORD CLUTTER: Multiple paragraphs contain the same nouns/dates found in the prompt.",
                "how_to_use": "Spread the target nouns across Paragraphs A, B, and C, but only one paragraph contains the specific *action* described."
            }
        ],
        "intermediate": [
            {
                "name": "implicit_location",
                "description": "IMPLICIT LOCATION: The information is an example of a concept mentioned, not the concept itself.",
                "how_to_use": "Prompt for 'a reference to financial loss'; text mentions 'the company went bankrupt' without using the word 'financial'."
            },
            {
                "name": "category_overlap",
                "description": "CATEGORY OVERLAP: Two paragraphs discuss similar categories (e.g., two different types of ships).",
                "how_to_use": "Ensure the student must identify the specific attribute that differentiates the two categories."
            }
        ],
        "advanced": [
            {
                "name": "nb_rule_trap",
                "description": "NB RULE TRAP: One paragraph contains two pieces of information.",
                "how_to_use": "Design the text so one paragraph is very dense, serving as the answer for two separate prompts, forcing students to re-evaluate their 'one-per-paragraph' assumption."
            },
            {
                "name": "chronological_bait",
                "description": "CHRONOLOGICAL BAIT: Prompt asks for a 'future prediction', but the only paragraph with dates discusses the 'past'.",
                "how_to_use": "Trap students who scan for 'time-related words' without checking the tense or modal verbs (will vs. used to)."
            }
        ]
    },

    "matching_features": {
        "easy": [
            {
                "name": "proximity_lure",
                "description": "PROXIMITY LURE: Mentioning the correct keyword immediately next to the wrong person's name.",
                "how_to_use": "Write: 'While Smith studied [Keyword], it was Jones who discovered...' to trap those who stop at the first name they see."
            }
        ],
        "intermediate": [
            {
                "name": "viewpoint_evolution",
                "description": "VIEWPOINT EVOLUTION: A person changed their mind over time.",
                "how_to_use": "Prompt for a person's 'final conclusion'; text mentions their 'early theory' which was later debunked."
            },
            {
                "name": "indirect_attribution",
                "description": "INDIRECT ATTRIBUTION: The text says 'Smith agrees with the theory proposed by Jones'.",
                "how_to_use": "Ask who proposed the theory; the student might pick Smith because his name is closer to the verb 'agrees'."
            }
        ],
        "advanced": [
            {
                "name": "collective_vs_individual",
                "description": "COLLECTIVE VS INDIVIDUAL: Distinguishing between a group consensus and a specific researcher's claim.",
                "how_to_use": "Attribute a finding to 'the team' in the text, but use a specific researcher's name as a distractor."
            },
            {
                "name": "paraphrase_barrier",
                "description": "PARAPHRASE BARRIER: The feature is an exact match (Name), but the statement is a high-level conceptual paraphrase.",
                "how_to_use": "Make the statement so abstract that the student cannot find a single matching word except the name."
            }
        ]
    },

    "truth_logic": { # Covers TRUE/FALSE/NOT GIVEN and YES/NO/NOT GIVEN
        "easy": [
            {
                "name": "direct_antonym",
                "description": "DIRECT ANTONYM: The statement uses the exact opposite verb or adjective.",
                "how_to_use": "Text: 'The population rose'; Statement: 'The population declined' (FALSE/NO)."
            },
            {
                "name": "absent_noun",
                "description": "ABSENT NOUN: The statement introduces a subject never mentioned in the text.",
                "how_to_use": "Statement mentions 'local government' where the text only discusses 'international bodies' (NOT GIVEN)."
            }
        ],
        "intermediate": [
            {
                "name": "modality_shift",
                "description": "MODALITY SHIFT: Changing 'could' or 'might' into 'will' or 'must'.",
                "how_to_use": "Text: 'It is possible that X happened'; Statement: 'X definitely happened' (FALSE/NO)."
            },
            {
                "name": "comparison_trap",
                "description": "COMPARATIVE TRAP: The text mentions A and B, but the statement compares them.",
                "how_to_use": "Text: 'A is fast. B is cheap.' Statement: 'A is faster than B' (NOT GIVEN)."
            }
        ],
        "advanced": [
            {
                "name": "unwarranted_deduction",
                "description": "UNWARRANTED DEDUCTION: A statement that is common sense or highly likely, but not in the text.",
                "how_to_use": "Statement: 'The merchants became wealthy because of the trade' (NOT GIVEN if the text only mentions the *volume* of trade but never their bank accounts)."
            },
            {
                "name": "claim_vs_fact_trap",
                "description": "CLAIM VS FACT: Mistaking a character's opinion for the author's fact.",
                "how_to_use": "Text: 'Schildt argued that X is true.' Statement: 'X is true.' (NOT GIVEN/NO if the author later expresses doubt about Schildt)."
            }
        ]
    }
}



    difficulty = difficulty.lower()
    available_traps = traps_library.get(question_type, {})

    if not available_traps:
        return f"### TRAP INSTRUCTIONS:\n- No specific traps defined for {question_type}."

    # Get the list of traps for the chosen difficulty
    trap_pool = available_traps.get(difficulty, [])

    if not trap_pool:
        return f"### TRAP INSTRUCTIONS:\n- No traps available for {question_type} at {difficulty} level."

    # Decide how many traps to select
    if difficulty == "easy":
        num_to_select = min(3, len(trap_pool))
    elif difficulty == "intermediate":
        num_to_select = min(4, len(trap_pool))
    else:  # advanced/hard
        num_to_select = len(trap_pool)

    # Randomly select traps
    selected_traps = random.sample(trap_pool, num_to_select)

    # Format output nicely
    output_lines = []
    for trap in selected_traps:
        output_lines.append(
            f"{trap['name'].upper()}: {trap['description']} | How to use: {trap['how_to_use']}"
        )

    return f"### MANDATORY {question_type.upper()} ADVERSARIAL TRAPS ({difficulty.upper()}):\n- " + "\n- ".join(output_lines)


# ==========================================
# 3. MAIN LOGIC (SPLIT PHASE)
# ==========================================


# --- STEP 1: Preprocessing ---
def preprocess_passage(passage_text):

    try:
        doc = nlp(passage_text)
    except Exception as e:
        print("NLP error:", e)
        return []
    results = []
    for sent in doc.sents:
        features = {
            "text": sent.text,
            "modality": [tok.text for tok in sent if tok.tag_ == "MD"],
            "negation": [tok.text for tok in sent if tok.dep_ == "neg"],
            "causality": [tok.text for tok in sent if tok.dep_ in ["advcl","mark"] and tok.text.lower() in ["because","since","therefore","thus"]],
            "contrast": [tok.text for tok in sent if tok.text.lower() in ["however","although","yet","but"]],
            "attribution": [ent.text for ent in sent.ents if ent.label_ in ["PERSON","ORG","GPE"]],
            "quantifiers": [tok.text for tok in sent if tok.text.lower() in ["many","few","several","all","none"]],
            "conditionals": [tok.text for tok in sent if tok.text.lower() in ["if","unless","provided"]],
            "temporal": [ent.text for ent in sent.ents if ent.label_ == "DATE"] + [tok.text for tok in sent if tok.text.lower() in ["initially","later","eventually","by"]],
            "comparatives": [tok.text for tok in sent if tok.tag_ in ["JJR","JJS","RBR","RBS"]],
        }  # as above
        # normalize
        for k,v in features.items():
            if isinstance(v,list):
                features[k] = list(set([t.lower() for t in v]))
        features["trap_flags"] = [k for k,v in features.items() if v]
        results.append(features)
    return results

# --- STEP 2: Difficulty Calibration ---
def calibrate_features(features, difficulty):
    if difficulty.lower() == "easy":
        return [f for f in features if f["modality"] or f["negation"] or f["quantifiers"]]
    elif difficulty.lower() == "medium":
        return [f for f in features if f["attribution"] or f["contrast"] or f["temporal"]]
    else:  # Hard
        return [f for f in features if f["causality"] or f["conditionals"] or f["comparatives"] or f["temporal"]]

# --- STEP 3: Placeholder Anchor (for padding) ---
def generate_placeholder_anchor(difficulty):
    return {
        "original_text": "",
        "paragraph": "",
        "context": "",
        "contrast_marker": "",
        "type": [],
        "trap_logic": "Placeholder trap.",
        "question_idea": "Placeholder question.",
        "synonyms": [],
        "confidence": 0.7,
        "difficulty_level": difficulty,
        "question_type": "T/F/NG",
        "subtype_instr": "Placeholder subtype.",
        "distractor_example": "Placeholder distractor.",
        "trap_rank": "Low"
    }
TRAP_CATEGORIES = {
    "sentence_completion": ["LEXICAL SUBSTITUTION", "NEIGHBORHOOD NOUNS", "GRAMMAR FIT", "TEMPORAL", "CAUSALITY"],
    "multiple_choice": ["ATTRIBUTION", "CAUSALITY", "COMPARATIVE", "IMPLICIT", "TEMPORAL"],
    "matching_headings": ["SCOPE & QUANTITY", "TEMPORAL", "CONTRAST", "CAUSALITY"],
    "true_false_not_given": ["NEGATIVE INVERSION", "CONDITIONAL LOGIC", "ATTRIBUTION", "TEMPORAL"],
    "yes_no_not_given": ["NEGATIVE INVERSION", "CONDITIONAL LOGIC", "ATTRIBUTION", "CAUSALITY"]
}


# --- STEP 4: Main Function ---
def identify_trap_anchors(passage_text, user_choice, difficulty, num_questions, subtype, mixed_subtypes, selected_categories):
    # --- STEP 1: CLEANUP FIRST ---
    if selected_categories and isinstance(selected_categories, list) and len(selected_categories) > 0:
        if isinstance(selected_categories[0], dict):
            selected_categories = [item.get('type') for item in selected_categories]

    if mixed_subtypes and isinstance(mixed_subtypes, list) and len(mixed_subtypes) > 0:
        if isinstance(mixed_subtypes[0], dict):
            mixed_subtypes = [item.get('subtype') for item in mixed_subtypes]

    # --- STEP 2: RECURSIVE MIXED LOGIC (NEW INTERCEPT) ---
    # Intercept 'mixed' requests immediately before building prompts
    if user_choice == "mixed":
        # 1. Define how subtypes map back to their parent user_choice
        SUBTYPE_POOL = {
            "sentence_completion": ["SUMMARY-BANK", "STANDARD"],
            "multiple_choice": ["MCQ-SINGLE", "MCQ-MULTI", "MCQ-GLOBAL"],
            "matching_headings": ["MATCHING-HEADINGS", "MATCHING-INFO", "MATCHING-FEATURES"],
            "true_false_not_given": ["TRUE/FALSE/NOT GIVEN"],
            "yes_no_not_given": ["YES/NO/NOT GIVEN"]
        }

        def get_parent_choice(st):
            for parent, kids in SUBTYPE_POOL.items():
                if st in kids: 
                    return parent
            return "sentence_completion" # Safe fallback

        # 2. Handle list of subtypes
        if isinstance(mixed_subtypes, list) and len(mixed_subtypes) >= 1:
            subtypes_to_process = mixed_subtypes
        else:
            subtypes_to_process = ["STANDARD", "MCQ-SINGLE"] # Default fallback

        # 3. Distribute the question count across the subtypes
        num_subtypes = len(subtypes_to_process)
        base_count = num_questions // num_subtypes
        remainder = num_questions % num_subtypes

        all_anchors = []

        # 4. Trigger individual LLM calls per subtype
        for i, current_subtype in enumerate(subtypes_to_process):
            # Distribute remainder to the first few subtypes so total matches exactly
            current_count = base_count + (1 if i < remainder else 0)
            if current_count == 0:
                continue

            parent_choice = get_parent_choice(current_subtype)
            print(f"--- Mixed Mode: Generating traps for {current_subtype} ({parent_choice}) ---")

            # Recursive call: pass mixed_subtypes=None to ensure it hits the specific elif blocks
            res = identify_trap_anchors(
                passage_text=passage_text,
                user_choice=parent_choice,
                difficulty=difficulty,
                num_questions=current_count,
                subtype=current_subtype,
                mixed_subtypes=None, 
                selected_categories=selected_categories
            )

            if "anchors" in res:
                all_anchors.extend(res["anchors"])

        # 5. Return aggregated results immediately. Bypass single LLM call below.
        return {"anchors": all_anchors}


    # --- STEP 3: STANDARD LOGIC (For single subtypes or recursive calls) ---
    subtype_instruction = ""
    features = preprocess_passage(passage_text)
    calibrated = calibrate_features(features, difficulty)
    
    # This line is now safe because selected_categories is a list of strings
    categories = TRAP_CATEGORIES.get(user_choice, ["Modality & Frequency", "Causal Reversal"])
    
    if selected_categories:
        categories = [c for c in categories if c in selected_categories]
        
    sys_p = (
        "You are an expert IELTS Item Writer for Cambridge Assessment. "
        "Perform a 'Deep Logic Scan' of the text to find points of vulnerability "
        "where a student might misinterpret meaning. Highlight subtle traps and "
        "generate distractor ideas in authentic IELTS style."
    )
    
    if user_choice == "sentence_completion" and subtype == "SUMMARY-BANK":
        total_traps_needed = num_questions * 2 
        subtype_instruction = f"""
        SUMMARY-BANK (Sentence Completion with Options):
        - Target exactly {total_traps_needed} anchors to populate a summary word bank.
        - FOCUS 1: "Near-Miss Synonyms" - Words that are topically related but change the nuance.
        - FOCUS 2: "Grammatical Fit / Semantic Fail" - Distractors that fit the part of speech but distort meaning.
        - FOCUS 3: "Frequency/Modality Flips" - Change 'often' to 'always', or 'can' to 'must'.
        """
        num_questions = total_traps_needed

    elif user_choice == "sentence_completion" and subtype == "STANDARD":
        total_traps_needed = num_questions
        subtype_instruction = f"""
        STANDARD (Sentence Completion from Text):
        - Target exactly {total_traps_needed} anchors where a student must extract a word.
        - FOCUS 1: "Proximity Traps" - Identify a tempting, highly visible noun/verb near the correct answer.
        - FOCUS 2: "Context Shift" - Spot words in the text that match the prompt's vocabulary but belong to a different clause.
        """
        num_questions = total_traps_needed

    elif user_choice == "multiple_choice" and subtype == "MCQ-SINGLE":
        total_traps_needed = num_questions * 3
        subtype_instruction = f"""
        MCQ-SINGLE (4-Option Multiple Choice):
        - Identify exactly 3 distinct trap anchors for every 1 intended question. Total anchors: {total_traps_needed}.
        - CREATE THESE 3 DISTRACTOR TYPES:
            1. "True but Irrelevant": Factually correct according to the text, but doesn't answer the prompt.
            2. "Opposite / Inverted": Uses exact vocabulary but reverses meaning.
            3. "Plausible Fabrication": Combines two separate facts into a non-existent relationship.
        """
        num_questions = total_traps_needed

    elif user_choice == "multiple_choice" and subtype == "MCQ-MULTI":
        total_traps_needed = num_questions * 4
        subtype_instruction = f"""
        MCQ-MULTI (Choose Multiple Correct Options):
        - Target exactly {total_traps_needed} anchors.
        - FOCUS 1: "Half-Truths" - First half of the sentence is true, second half is false/not given.
        - FOCUS 2: "Conditional Logic" - Ignore an 'if' or 'unless' from the text.
        - FOCUS 3: "Category Merging" - Attribute a characteristic of Subject A to Subject B.
        """
        num_questions = total_traps_needed

    elif user_choice == "multiple_choice" and subtype == "MCQ-GLOBAL":
        total_traps_needed = num_questions * 3
        subtype_instruction = f"""
        MCQ-GLOBAL (Passage Main Idea / Title):
        - Target exactly {total_traps_needed} anchors.
        - FOCUS 1: "Too Narrow" - Summarizes one paragraph but ignores the rest.
        - FOCUS 2: "Too Broad" - Goes beyond the scope of the passage.
        - FOCUS 3: "Inaccurate Tone" - Frames neutral analysis as angry critique or overly optimistic projection.
        """
        num_questions = total_traps_needed

    elif user_choice == "matching_headings":
        total_traps_needed = num_questions + 3
        subtype = "MATCHING-HEADINGS" if subtype == "MATCHING-HEADINGS" else subtype
        subtype_instruction = f"""
        MATCHING-HEADINGS (Paragraph Themes):
        - Target exactly {total_traps_needed} anchors (includes {num_questions} correct themes + 3 distractors).
        - FOCUS 1: "The Introduction Trap" - Matches the first sentence, ignores the 'however' pivot.
        - FOCUS 2: "Detail Disguised as Theme" - Elevates a minor example into a heading.
        - FOCUS 3: "The Unfulfilled Promise" - Sounds highly relevant but isn't actually discussed.
        """
        num_questions = total_traps_needed

    elif user_choice == "matching_info" or (user_choice == "matching_headings" and subtype == "MATCHING-INFO"):
        total_traps_needed = num_questions + 2
        subtype_instruction = f"""
        MATCHING-INFO (Which paragraph contains the following information):
        - Target exactly {total_traps_needed} anchors.
        - FOCUS 1: "Keyword Repetition" - Paragraphs that repeat searched keywords but lack the specific action.
        - FOCUS 2: "Future vs. Past" - Mixes up a paragraph about 'past attempts' with 'future predictions'.
        """
        num_questions = total_traps_needed

    elif user_choice == "matching_features" or (user_choice == "matching_headings" and subtype == "MATCHING-FEATURES"):
        total_traps_needed = num_questions + 3
        subtype_instruction = f"""
        MATCHING-FEATURES (Matching Statements to Names/Categories):
        - Target exactly {total_traps_needed} anchors.
        - FOCUS 1: "The Proximity Trap" - Two experts in the same sentence; misattribute claims.
        - FOCUS 2: "The Temporal Shift" - Distinguish between 'previously believed' vs 'current findings'.
        - FOCUS 3: "The Collaborative Trap" - Findings attributed to a 'group' vs a 'specific individual'.
        """
        num_questions = total_traps_needed

    elif user_choice == "true_false_not_given":
        total_traps_needed = num_questions
        subtype_instruction = f"""
        TRUE/FALSE/NOT GIVEN (Factual Falsifiability):
        - Target exactly {total_traps_needed} distinct logic anchors.
        - FOR FALSE: "Direct Contradiction" - Alter a modifier (change 'some' to 'all').
        - FOR NOT GIVEN: "Unwarranted Deduction" - Highly plausible assumption never confirmed.
        - FOR NOT GIVEN: "Wrong Comparison" - Mentions two items, but never compares them.
        """
        num_questions = total_traps_needed

    elif user_choice == "yes_no_not_given":
        total_traps_needed = num_questions
        subtype_instruction = f"""
        YES/NO/NOT GIVEN (Writer's Claims / Opinions):
        - Target exactly {total_traps_needed} distinct logic anchors.
        - FOR NO: "Opinion Reversal" - Text expresses skepticism, trap expresses certainty.
        - FOR NOT GIVEN: "External Knowledge" - True in real world, but writer never mentions it.
        - FOR NOT GIVEN: "Attribution Shift" - Stating something as writer's opinion when it was a quote.
        """
        num_questions = total_traps_needed

    # Build the final Prompt
    user_p = f"""
    Analyze the passage for Trap Anchors.
    Return exactly {num_questions} anchors (no more, no less).
    Traps should be suitable for {user_choice} questions. The selected subtype is {subtype} at {difficulty} difficulty.

    Use these preprocessed features:
    {calibrated}

    Subtype Instructions:
    {subtype_instruction}

    ### Relevant Trap Categories for {user_choice}:
    {categories}

    ### Difficulty Calibration
    - Easy: obvious qualifiers (may, often).
    - Medium: attribution traps (historians suggest).
    - Hard: multi-layered logic (conditional + causal + attribution).

    ### JSON Output
    {{
      "anchors": [
        {{
          "original_text": "...",
          "paragraph": "Auto-detected paragraph",
          "context": "Surrounding sentence",
          "contrast_marker": "however/although/etc",
          "type": ["QUALIFIER","CAUSALITY","ATTRIBUTION","TEMPORAL","COMPARATIVE","IMPLICIT"],
          "trap_logic": "Explain why this is a trap.",
          "question_idea": "Draft a T/F/NG or MCQ distractor idea.",
          "synonyms": ["...", "..."],
          "confidence": 0.6-1.0,
          "difficulty_level": "{difficulty}",
          "question_type": "{subtype}",          
          "distractor_example": "Sample misleading statement.",
          "trap_rank": "High/Medium/Low"
        }}
      ]
    }}
    Ensure exactly {num_questions} anchors.
    """

    raw_response = call_llm(sys_p, user_p, difficulty=difficulty, user_choice=user_choice)
    print("RAW RESPONSE:", raw_response)
    
    if not raw_response:
        return {"anchors": [], "error": "LLM returned no response"}

    try:
        q_data = json_repair.loads(raw_response)

        if not isinstance(q_data, dict):
            print("Error: Repaired JSON is not a dictionary object")
            return {"anchors": [], "error": "Invalid JSON structure"}

        anchors = q_data.get("anchors", [])
        
        if not anchors:
            potential_keys = ["traps", "trap_anchors", "questions"]
            for k in potential_keys:
                if q_data.get(k):
                    anchors = q_data.get(k)
                    break
            
            if not anchors:
                return {"anchors": [], "error": "No anchors found in JSON"}

        # Enforce count
        if len(anchors) > num_questions:
            anchors = anchors[:num_questions]
        elif len(anchors) < num_questions:
            # Ensure generate_placeholder_anchor exists in your script
            anchors += [generate_placeholder_anchor(difficulty)] * (num_questions - len(anchors))

        # Validate quality
        for anchor in anchors:
            if not isinstance(anchor, dict): continue
            if not anchor.get("trap_logic"):
                anchor["trap_logic"] = "Trap logic missing — auto-filled."
            if not anchor.get("confidence"):
                anchor["confidence"] = 0.7
            if anchor.get("trap_rank") not in ["High", "Medium", "Low"]:
                anchor["trap_rank"] = "Medium"
            # Ensure the correct subtype is attached to the final output
            if not anchor.get("question_type") or anchor.get("question_type") == "T/F/NG, Matching, MCQ, Sentence Completion":
                anchor["question_type"] = subtype

        return {"anchors": anchors}

    except Exception as e:
        print(f"CRITICAL ERROR: Failed to repair/parse JSON: {e}")
        print(f"Raw Snippet: {raw_response[:100]}...") 
        return {"anchors": [], "error": f"Parsing failed: {str(e)}"}


db_config = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'aireadinggenerator')
}

def internal_save_to_db(result_to_save):
    """Function defined inside ai_backend to handle its own saving"""
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        sql = """
            INSERT INTO reading_tests 
            (user_id, topic, difficulty, passage, questions, answers, language)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """
        values = (
            result_to_save.get("user_id"),
            result_to_save.get('topic', 'Unknown'),
            result_to_save.get('difficulty', 'Intermediate'),
            result_to_save.get('passage', ''),
            json.dumps(result_to_save.get("questions", []), ensure_ascii=False),
            json.dumps(result_to_save.get("answers", []), ensure_ascii=False),
            result_to_save.get("language", "English")
        )
        cursor.execute(sql, values)
        conn.commit()
        return {"status": "success", "insert_id": cursor.lastrowid}
    except Exception as e:
        print(f"CRITICAL DB ERROR: {e}") # Log this to your Celery console
        return {"status": "error", "message": str(e)}
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()
def check_user_balance(user_id, user_choice):
    required_amount = 2 if user_choice == 'mixed' else 1
    
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
def get_user_plan(user_id):
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT plan_type FROM users WHERE id = %s", (user_id,))
        user = cursor.fetchone()
        cursor.close()
        conn.close()
        return user['plan_type'] if user else 'free'
    except Exception as e:
        print(f"DB Error: {e}")
        return 'free'



def process_file_content(text, num_questions):
    # 1. Word Count Check
    words = text.split()
    count = len(words)
    
    # 2. Capacity Check (100:1 Ratio)
    # We set a floor of 200 to keep it from being too thin
    min_required = max(200, num_questions * 100)
    
    if count < min_required:
        
        return {
            "status": "error", "type": "VALIDATION",
            "message": f"This text is too short for a {num_questions}-question set."
                       f"Please provide at least {min_required} words, but only {count} were found."
        }
    
    # 3. Smart Trimming (1200 Word Ceiling)
    if count > 1200:
        # Take the first 1200 words
        truncated_text = " ".join(words[:1200])
        
        # Clean up: Ensure we don't end in the middle of a sentence
        # Find the last occurrence of . ! or ?
        last_punctuation = max(
            truncated_text.rfind('.'), 
            truncated_text.rfind('!'), 
            truncated_text.rfind('?')
        )
        
        if last_punctuation != -1:
            text = truncated_text[:last_punctuation + 1]
        else:
            text = truncated_text
            
    return {"status": "success", "processed_text": text}
@celery_app.task(bind=True, name='generate_test')
def generate_test(self, **kwargs):  # Use self first, then kwargs
    # 1. Extract values from kwargs with .get() to avoid KeyErrors
    self.update_state(state='PROGRESS', meta={'progress': 10, 'status': 'Started cooking your order...'})
    user_id = kwargs.get("user_id")
    user_choice = kwargs.get('user_choice', 'multiple_choice')
    difficulty = kwargs.get('difficulty', 'Intermediate')
    mixed_types = kwargs.get('mixed_types', [])
    topic = kwargs.get('topic', 'General Knowledge')
    num_questions = kwargs.get('num_questions', 5)
    word_count = kwargs.get('word_count', 200)
    file_path = kwargs.get('file_path', None)
    file_type = kwargs.get('file_type', None)
    final_questions = []
    final_answers = {}
    mixed_subtypes = []
    selected_categories = []
    subtype = ""

    print(f"Received request: User ID={user_id}, Choice={user_choice}, Difficulty={difficulty}, Num Questions={num_questions}, Mixed Types={mixed_types}, File Path={file_path}")


    if not num_questions or num_questions < 1: num_questions = 5
    if user_choice == 'mixed':
        required_credits = 2
    else:
        required_credits = 1
    # 2. Check balance BEFORE calling LLM or Audio APIs
    has_balance, balance, message = check_user_balance(user_id, user_choice)

    if not has_balance:
        # Return an error dict (Acts as a 403 Forbidden in Celery context)
        return {"error": message, "current_balance": balance}
    # --- PHASE 1: GET TEXT ---
    passage_text = ""
    # 1. PRE-DETERMINE SUBTYPE (Move this up!)
    subtype = None
    if user_choice == "sentence_completion":
        subtype = random.choice(["STANDARD", "SUMMARY-BANK"]) # Default to standard for simplicity
        if subtype == "STANDARD":
            squestion_traps = get_traps_for_question(difficulty, "sentence_completion")
        else:
            squestion_traps = get_traps_for_question(difficulty, "summary_bank")    
    elif user_choice == "multiple_choice":
        subtype = random.choice(["MCQ-SINGLE", "MCQ-GLOBAL", "MCQ-MULTI"]) # Default to single-answer MCQ for simplicity
        if subtype == "MCQ-SINGLE" :
            mquestion_traps = get_traps_for_question(difficulty, "multiple_choice")
        elif subtype == "MCQ-GLOBAL":
             mquestion_traps = get_traps_for_question(difficulty, "mcq_global")
        else:
            mquestion_traps = get_traps_for_question(difficulty, "multi_select")
    elif user_choice == "matching_headings":
        subtype = random.choice(["MATCHING-INFO", "MATCHING-FEATURES", "MATCHING-HEADINGS"])    

        if subtype == "MATCHING-HEADINGS":
            maquestion_traps = get_traps_for_question(difficulty, "matching_headings")
        elif subtype == "MATCHING-INFO":
            maquestion_traps = get_traps_for_question(difficulty, "matching_information")
        elif subtype == "MATCHING-FEATURES":
            maquestion_traps = get_traps_for_question(difficulty, "matching_features")
    elif user_choice == "true_false_not_given":
        subtype = "True/false/not given"  # Placeholder for future expansion
        if subtype == "True/false/not given":
            tquestion_traps = get_traps_for_question(difficulty, "truth_logic")
    elif user_choice == "yes_no_not_given":
        subtype = "Yes/no/not given"
        if subtype == "Yes/no/not given":
            yquestion_traps = get_traps_for_question(difficulty, "truth_logic")
    elif user_choice == "mixed":
        # 1. Use the actual list sent by the user checkboxes
        # If mixed_types is empty/None, provide a fallback to avoid errors
        selected_categories = mixed_types if mixed_types else ["sentence_completion", "true_false_not_given"]
        
        # 2. Initialize the mixed_subtypes list
        mixed_subtypes = []
        
        # 3. Map each USER SELECTED category to a random internal style
        for t in selected_categories:
            if t == "sentence_completion":
                mixed_subtypes.append({"type": t, "subtype": random.choice(["SUMMARY-BANK", "STANDARD"])})
            elif t == "multiple_choice":
                mixed_subtypes.append({"type": t, "subtype": random.choice(["MCQ-SINGLE", "MCQ-GLOBAL", "MCQ-MULTI"])})
            elif t == "matching_headings":
                mixed_subtypes.append({"type": t, "subtype": random.choice(["MATCHING-HEADINGS", "MATCHING-INFO", "MATCHING-FEATURES"])})
            elif t == "true_false_not_given":
                mixed_subtypes.append({"type": t, "subtype": "True/False/Not Given"})
            elif t == "yes_no_not_given":
                mixed_subtypes.append({"type": t, "subtype": "Yes/No/Not Given"})
            # Add other mappings for matching_information, matching_features, etc.# Placeholder for future expansion
    if file_path:
    # 1. SERVER-SIDE SECURITY CHECK
        actual_plan = get_user_plan(user_id)
    
    # We use a try-finally block to ensure the file is DELETED no matter what happens
        try:
            if actual_plan != 'pro':
                print(f"Unauthorized upload attempt by User ID: {user_id}")
                return {
            "status": "error", "type": "VALIDATION",
            "message": "File uploads are only allowed for Pro users. Please upgrade your plan to access this feature." 
        }

        # 2. PRO-ONLY PROCESSING
            print("Extracting raw text from uploaded file...")
            raw_dirty_text = extract_text_from_file(file_path, file_type)

            if not raw_dirty_text or len(raw_dirty_text.strip()) < 50:
                return {
            "status": "error", "type": "VALIDATION",
            "message": "The extracted text is too short or empty. Please upload a valid document with sufficient content." 
        }
            # --- NEW: WORD COUNT TRIMMING LOGIC ---
            num_questions = int(kwargs.get('num_questions', 5))
            result = process_file_content(raw_dirty_text, num_questions)

        # CORRECTED ERROR CHECK
            if result.get("status") == "error":
            # This returns the logical error to your 'Universal Route'
                print(f"Validation failed: {result['message']}")
                return {"error": result["message"]}
        # 3. REFINEMENT PHASE
            clean_source_text = result["processed_text"]
            print("Refining noisy PDF text into an academic passage...")
            refine_sys_p = "You are an expert IELTS Content Editor."
            refine_user_p = f"""
            YOUR TASK:
            1. Clean the text: Remove all page numbers, website names (like www.onislam.net), chapter headers, and redundant titles.
            2. Academic Tone: Smooth out any broken sentences caused by page breaks. 
            3. Maintain Content: Keep all the core facts, dates, and arguments.
            4. Structure: Reorganize the content into 5-8 clear, logical paragraphs.
            5. Formatting: 
                - Label each paragraph [A], [B], [C], etc.
                - Use double newlines between paragraphs.
                - Output ONLY the cleaned passage.
    
            RAW TEXT TO REFINE: {clean_source_text[:10000]}  # Cap text to prevent token overflow
            """
            passage_text = call_llm(refine_sys_p, refine_user_p, json_mode=False, difficulty=difficulty, user_choice=user_choice)

        finally:
        # CRITICAL: Delete the file even if the user is 'free' or if the LLM fails
            if file_path and os.path.exists(file_path):
                os.remove(file_path)
                print(f"Security Cleanup: Temp file {file_path} removed.")
    else:
        # 1B: Generate New Text
        # --- PHASE 1: GENERATE NEW TEXT (Updated) ---
        passage_seeds = get_passage_trap_seeds(difficulty)
        if topic == "AI's Choice: Random Topic" or not topic.strip():
            # Instructions to help the AI pick a high-scoring IELTS topic
            random_salt = str(uuid.uuid4())[:8]
            topic_logic = (
                f"Choose a UNIQUE academic topic (Seed: {random_salt}). "
                "Choose a high-level, academic topic suitable for IELTS Reading. Do not use one topic repeatedly. "                
            )
            print("User requested AI Topic Choice. Delegating topic selection to LLM...")
        else:
            topic_logic = f"The specific topic is: {topic}"
        print(f"Generating new {difficulty} passage...{topic}")

        # New: Add context about the questions
        question_context = f"This passage will be used for a '{user_choice}' task and try to make the passage suitable for that question type."
        
        if user_choice == "sentence_completion":
            question_context += f" Specifically, the '{subtype}' format and these traps: {squestion_traps}"
        elif user_choice == "multiple_choice":
            question_context += f" Specifically, the '{subtype}' format and these traps: Distractors {mquestion_traps}. "    
        elif user_choice == "matching_headings":
            question_context += f" Specifically, the '{subtype}' format and these traps: {maquestion_traps}."
        elif user_choice == "true_false_not_given":
            question_context += f" Specifically, the '{subtype}' format and these traps: {tquestion_traps}."
        elif user_choice == "yes_no_not_given":
            question_context += f" Specifically, the '{subtype}' format and these traps: {yquestion_traps}."
        elif user_choice == "mixed":
            # 1. Use the actual 'mixed_subtypes' list we built from the user's checkboxes
            descriptions = [f"{item['type']} ({item['subtype']})" for item in mixed_subtypes]
            mix_details = ", ".join(descriptions)
            
            question_context = f" This is a MIXED-TYPE task involving: {mix_details}."
            
            # 2. Add specific 'Hooks' for the types the user actually selected
            # This tells the AI exactly what features the text needs
            hooks = []
            selected_types = [item['type'] for item in mixed_subtypes]
            
            if "matching_headings" in selected_types:
                hooks.append("clear, distinct topic sentences for each paragraph")
            if "true_false_not_given" in selected_types or "yes_no_not_given" in selected_types:
                hooks.append("specific factual claims, dates, and names for verification")
            if "multiple_choice" in selected_types:
                hooks.append("complex arguments and contrasting viewpoints for inference")
            if "sentence_completion" in selected_types or "summary_completion" in selected_types:
                hooks.append("strong academic synonyms and paraphrasing opportunities")

            if hooks:
                question_context += " To support these, the text MUST include: " + "; ".join(hooks) + "."
                
        pass_rules = PASSAGE_DIFFICULTY_RULES.get(difficulty, PASSAGE_DIFFICULTY_RULES["Intermediate"])

        sys_p = "You are an expert academic writer for IELTS."
        user_p = f"""
        Write an IELTS Reading Passage.
        TOPIC GUIDELINE: {topic_logic}
        MANDATORY: Do not use the same topic as the previous session. 
        Ensure the topic is distinct from common environmental themes unless specifically asked.
        DIFFICULTY: {difficulty}
        GUIDELINES: {pass_rules}
        INTENT: {question_context} 
        LENGTH: Approx {word_count} words.
        TRAP SEEDS TO INCORPORATE:
         - Follow the passage trap structure guidelines below to embed 'reading traps' that increase complexity and test careful reading.
         - Take the logic seriously to ensure the passage contains the necessary elements to create challenging questions.
         - Tke the main logic of the traps and weave them naturally into the passage.
         - Do not blindly list facts; ensure the traps are meaningfully integrated.
            {passage_seeds}
        FORMAT:
        - Separate paragraphs with double newlines.
        - Label paragraphs [A], [B], [C] etc.
        - STRICT CLEANLINESS: Output ONLY the passage text. Do not include introductory remarks, "Here is your passage," or concluding notes.
        """
        
        self.update_state(state='PROGRESS', meta={'progress': 30, 'status': 'Generating passage...'})
        passage_text = call_llm(sys_p, user_p, json_mode=False, difficulty=difficulty, user_choice=user_choice)
    if not passage_text: 
        print('DEBUG: LLM returned empty result')
        return {
            "status": "error", 
            "type": "VALIDATION", 
            "error": "The AI failed to generate a passage."
        }
    if isinstance(passage_text, dict) and passage_text.get("status") == "error":
    # Forward the existing error but tag it as VALIDATION
        return {
            "status": "error",
            "type": "VALIDATION",
            "error": passage_text.get("message", "An unknown content error occurred.")
        }
    print(f"{passage_text[:60]}...")
    # --- NEW PHASE 1.5: IDENTIFY TRAPS ---
    print("Analyzing passage for potential traps...")
    self.update_state(state='PROGRESS', meta={'progress': 50, 'status': 'Implementing logical structure...'})
    trap_metadata = identify_trap_anchors(passage_text, user_choice, difficulty, num_questions, subtype, mixed_subtypes,selected_categories)

# CHECK: Stop if trap_metadata is empty, None, or has an error
    if not trap_metadata or "error" in trap_metadata or not trap_metadata.get("anchors"):
        print("CRITICAL: Trap analysis failed.")
    
    # Check if it was a logical failure (e.g., text too simple)
        if isinstance(trap_metadata, dict) and "error" in trap_metadata:
         error_msg = trap_metadata["error"]
        else:
            error_msg = "Could not identify logical traps in this text. "

        return {
            "status": "error", 
            "type": "VALIDATION", 
            "error": error_msg
        }
    trap_context_block = ""
    if trap_metadata and "anchors" in trap_metadata:
        # 1. Filter for high-quality traps first
        valid_anchors = [a for a in trap_metadata["anchors"] if a.get("trap_rank") == "High"]
        
        # 2. Fallback if no high-rank traps exist
        if not valid_anchors:
            valid_anchors = trap_metadata["anchors"]
            
        # 3. Format as a clear list for the LLM
        trap_context_block += "\n### AVAILABLE TRAP ANCHORS (Use these to build your questions):\n"
        for i, anchor in enumerate(valid_anchors[:8]): # Limit to top 8 to save context
            trap_context_block += f"ANCHOR {i+1}:\n"
            trap_context_block += f"   - Source Text: \"{anchor.get('original_text', '')}\"\n"
            trap_context_block += f"   - Trap Logic: {anchor.get('trap_logic', '')}\n"
            trap_context_block += f"   - Suggested Distractor: \"{anchor.get('distractor_example', '')}\"\n\n"
    else:
        trap_context_block = "\n(No specific trap anchors identified. Use general IELTS logic.)\n"
    # --- PHASE 2: GENERATE QUESTIONS ---
   
    print(f"Trap Analysis Complete: {trap_context_block}")
    
    # --- PHASE 2: GENERATE QUESTIONS ---
    
    print("Generating questions based on text...")
    
    # Calculate distribution
    selected_types = []
    if user_choice == "Mixed":
        selected_types = mixed_types if mixed_types else ["multiple_choice", "true_false_not_given"]
    else:
        selected_types = [user_choice]
        
    # Build instructions string
    type_instructions = ""
    if selected_types:
        base_count = num_questions // len(selected_types)
        remainder = num_questions % len(selected_types)
        
        for i, qtype in enumerate(selected_types):
            count = base_count + (1 if i < remainder else 0)
            rule = QUESTION_RULES.get(qtype, "Standard Rule")
            type_instructions += f"\n- Generate {count} x {qtype} questions.\n  RULE: {rule}"

    trap_logic = get_reading_trap_instructions(difficulty)
    print(f"Using trap logic:\n{trap_logic}")
    current_ques_diff = ques_diff_rules.get(difficulty, ques_diff_rules["medium"])
    diff_strategy_block = f"### TARGET DIFFICULTY: {difficulty.upper()}\n### EXECUTION RULES:\n{current_ques_diff}" 
    self.update_state(state='PROGRESS', meta={'msg': 'Drafting questions and answers...'})       
    cot_instructions = """
    ### CRITICAL PROCESS (CHAIN OF THOUGHT):
    Before generating the JSON, you must internally perform these steps for EVERY question:
    1. LOCATE ANSWER: Find the target sentence in the text.
    2. SCAN FOR DISTRACTORS: Look at DIFFERENT paragraphs to find:
       - Numbers/Dates that represent different events.
       - Names of experts who hold OPPOSITE views.
       - Keywords that look similar but mean something different.
    3. FORMULATE OPTIONS: 
       - Correct Answer: PARAPHRASE the text (Synonyms).
       - Distractors: Use EXACT WORDS/NUMBERS from the "Scan" step (Red Herrings).
    """      
    self.update_state(state='PROGRESS', meta={'progress': 70, 'status': 'Generating custom questions...'})
    subtype_instr=""
    if user_choice == "sentence_completion":

    # Base System Message Parts
      
        
    
    # --- SUBTYPE 1: STANDARD (Individual Sentences) ---
        if subtype == "STANDARD":
            subtype_instr = f"""
            ### CORE MANDATE: IELTS SENTENCE COMPLETION (STANDARD)
1. SEQUENTIAL INTEGRITY: Questions must appear in the exact order that the answers appear in the passage.
2. VERBATIM EXTRACTION: The 'answer' MUST be lifted exactly from the text. No changes to plurals, tenses, or word forms. (Strict 1-3 words).
3. GRAMMATICAL SYNERGY: When the answer is placed into the blank, the resulting sentence must be 100% grammatically perfect. 
    - Pay extreme attention to articles (a/an/the), prepositions (in/of/to), and subject-verb agreement.
4. ZERO-MATCH PARAPHRASING: 
    - Do not use the same unique adjectives or verbs found in the original sentence of the text.
    - RESTRUCTURE the logic: If the text is Active Voice, make the question Passive. If the text lists a cause, make the question focus on the effect.
    - Avoid "Word-Spotting": A student should not be able to find the answer by simply looking for a matching keyword in the question stem.
5. LOGICAL UNIQUENESS: The question must be specific enough that only ONE phrase from the entire text can satisfy the logic. Eliminate any "false positives."
            6. DATA INTEGRITY:
                - 'options_box' must be an empty list [].

            ### ADVERSARIAL TRAP FRAMEWORK (MANDATORY)
            {trap_context_block}


            """
            q_count_instruction = f"Create exactly {num_questions} questions for an IELTS Reading test."
            json_example = """{
                "id": 1,
                "subtype": "STANDARD",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "The WHO suggests that in 2019, nearly seven million people suffered",
                "suffix": "as a result of poor air quality.",
                "answer": "premature deaths",
                "metadata": {
                    "options_box": [],
                    "paragraph_context": "[A]",
                    "original_sentence": "...responsible for approximately 7 million premature deaths worldwide..."
                }
            }"""

    # --- SUBTYPE 2: MATCHING-ENDINGS ---
        
    # --- SUBTYPE 3: SUMMARY-BANK (Gap fill with synonym list) ---
       # --- SUBTYPE 3: SUMMARY-BANK (Gap fill with synonym list) ---
        elif subtype == "SUMMARY-BANK":
            subtype_instr = f"""
            1. MASTER TEMPLATE PERSISTENCE: 
                - Generate exactly ONE summary paragraph (80-150 words). 
                - Use placeholders [1] through [{num_questions}] as static tokens.
                - CRITICAL: Place the full summary in a SINGLE 'summary_text' key at the root of the JSON. Do not repeat it inside individual question objects.
                - SCHEMA RULE: Create ONLY ONE object in the "questions" array. Inside that one object, expand 'questions_options' to contain all {num_questions} gaps.
            2. ADVERSARIAL WORD BANK: 
                - Provide {num_questions + 4} options in the 'options_box' array. 
                - RULE: If a word appears in the Source Text, it should be a DISTRACTOR in the bank. 
                - RULE: Correct answers in the bank must be synonyms (e.g., if text says 'rapid', bank says 'swift').
            3. ADVERSARIAL TRAP INSTRUCTIONS:
                - Take the main logic from these traps and apply them creatively to each question.
                {trap_context_block}
            4. GRAMMATICAL HOMOGENEITY: 
                - For every gap, ensure the bank contains at least 3 other words of the same Part of Speech (POS). 
            5. DEEP PARAPHRASING: 
                - Shift the sentence structure. If the text is 'A caused B', the summary should be 'B was a result of A'.
            6. VERIFIABILITY: 
                 - For every correct answer, provide a 'mapping_evidence' field.
            """
            q_count_instruction = f"Create exactly ONE question object that contains {num_questions} gaps inside its 'questions_options' array."
            json_example = """{
            "id": 1,
            "subtype": "SUMMARY-BANK",
            'passage_title' : "write the title of the entire passage here",
            "summary_text": "Text with [1], [2], etc...",
            "options_box": ["A: word1", "B: word2", "C: word3", "D: word4", "E: word5", "F: word6", "G: word7", "H: word8", "I: word9"],
            "questions_options": [
                { "id": 1, "gap_number": 1, "answer": "A", "correct_word": "word1", "mapping_evidence": "..." },
                { "id": 2, "gap_number": 2, "answer": "B", "correct_word": "word2", "mapping_evidence": "..." }
            ]
        }"""
       # --- 3. Generation Loop ---
        ques_sys_msg = f"""
        You are a Senior IELTS Test Constructor. {q_count_instruction}.
        {diff_strategy_block}        

        SPECIFIC SUBTYPE RULES:
        {subtype_instr}
        ### PRE-GENERATION CHECKLIST (CHAIN OF THOUGHT):
For each question, the model must internally:
1. IDENTIFY TARGET: Find the exact sentence and the 1-2 word "Answer Key."
2. IDENTIFY LURE: Locate a "Neighbor Noun"—a word nearby that fits the grammar but is logically incorrect.
3. DESIGN THE STEM: 
    - Write the Prefix/Suffix using synonyms. 
    - Check: Does the answer fit the grammar of the prefix/suffix perfectly?
    - Check: Does the stem contain a "Keyword Match" that would make it too easy? If yes, re-write.
4. VERIFY ORDER: Ensure this answer appears AFTER the previous question's answer in the text.
        OUTPUT FORMAT:
        Return ONLY a raw JSON object. Do not include markdown formatting (```json).
        Structure:
        {{
        "questions": [ {json_example} ]
        }}"""
 # IMPORTANT: You MUST pass the passage_text in the user prompt!
 # NEW
        user_prompt = f"""
        PASSAGE TO USE:
        {passage_text}

        MANDATORY VALIDATION CHECK:
        1. Verify that every single number, date, or proper noun used in your 'options_box' EXISTS in the text above.
        2. If you want to use "5%" as a distractor, "5%" MUST appear in the text.
        3. If the text does not have enough numbers, use plausible synonyms, but DO NOT invent data.
        """

        # ... (inside your generation logic)
        q_data = call_llm(ques_sys_msg, user_prompt, json_mode=True, difficulty=difficulty, user_choice=user_choice)
        print("RAW QUESTION DATA:", q_data)
        if not q_data or "questions" not in q_data:
            print("DEBUG: Question data is missing or malformed.")
    
    # Check if q_data contains a more specific error message from a previous step
            if isinstance(q_data, dict) and "error" in q_data:
                error_msg = q_data["error"]
            else:
                error_msg = "Question generation failed. Try again later"

            return {
                "status": "error", 
                "type": "VALIDATION", 
                "error": error_msg
            }

# --- PHASE 3: POST-PROCESSING ---
            # --- PHASE 3: POST-PROCESSING ---
        raw_questions = q_data.get("questions", [])
        final_answers = {}

        for i, q in enumerate(raw_questions):
    # Ensure q is a dictionary to avoid errors if the LLM returned a string/list
            if not isinstance(q, dict):
                continue

    # 1. HANDLE SUMMARY-BANK (Nested Structure)
            if q.get("subtype") == "SUMMARY-BANK":
        # Ensure the block itself has an ID
                if "id" not in q:
                    q["id"] = 100 + i # Fallback block ID
            
                gaps = q.get("questions_options", [])
                for gap in gaps:
            # SAFETY: Check for 'id' OR 'gap_number' to prevent crash
                    gap_id = gap.get("id") or gap.get("gap_number")
                    if gap_id:
                        final_answers[str(gap_id)] = gap.get("answer", "")
        
                final_questions.append(q)

    # 2. HANDLE STANDARD / OTHER TYPES (Flat Structure)
            else:
        # SAFETY: Use .get("id") and a fallback to prevent 'Server Error: id'
                q_id = q.get("id")
                if q_id is None:
                    q_id = i + 1  # Generate a fallback ID if missing
                    q["id"] = q_id
            
                final_questions.append(q)
                final_answers[str(q_id)] = q.get("answer", "")

        
    elif user_choice == "multiple_choice":
        
    # --- SUBTYPE 1: MCQ-SINGLE (Choose 1 of 4) ---
        if subtype == "MCQ-SINGLE":
            subtype_instr = f"""
            ### ROLE: ELITE ACADEMIC EXAMINER (IELTS SECTION 3 SPECIALIST)
Your goal is to generate {num_questions} high-complexity, sequential Multiple Choice Questions (MCQs) based on the provided text. You must punish superficial "keyword matching" and reward deep logical synthesis.

---

### I. QUESTION ARCHITECTURE
1. **Sequential Flow:** Questions must follow the chronological order of information in the passage.
2. **Cognitive Split:** - 60% Literal/Paraphrase: Testing precise understanding of complex academic phrasing.
   - 40% Inferential/Logical: Asking about the author's purpose, underlying assumptions, or the "why" behind a stated fact.
3. **The "Sanitized" Correct Answer:**
   - **STRICT PROHIBITION:** The correct option MUST NOT use the primary nouns or verbs from the source sentence. 
   - **THE GOAL:** Transform the concept. (e.g., If text says "diagenesis of hydroxyapatite," the answer might say "mineral changes in skeletal structures").

---

### II. ADVERSARIAL DISTRACTOR LOGIC (The Traps)
For every question, you MUST incorporate at least one specific trap from the following taxonomy:
{trap_context_block}

**MANDATORY TRAP TYPES:**
- **The Temporal Inverter:** Reversing the "before/after" or "cause/effect" relationship.
- **The Scope/Scale Blur:** Taking a specific statistic (e.g., 15%) and applying it to a general category, or misattributing a local finding to a global context.
- **The Referential Swap:** Attributing an action to the wrong subject (e.g., "Novice excavators" vs. "Contemporary scholars").
- **The Pseudo-Synonym:** Using a word that sounds scientific but subtly alters the meaning (e.g., "confirmed" vs. "suggested").

---

### III. RIGID EXECUTION CONSTRAINTS
1. **Logical Singularity:** Only ONE option can be factually defensible. Review all distractors to ensure they are demonstrably false based *strictly* on the text.
2. **Independence:** Question 3 must not give away the answer to Question 4.
3. **No Safety Nets:** Do not use "All/None of the above" or "Both A and B."
4. **Answer Distribution:** You must balance the correct answers across A, B, C, and D. If a student chooses "C" for every answer, they should fail.
5. **No Hallucinations:** All distractors must be grounded in actual details from the text, just misapplied or distorted. Never invent outside information.

---

### IV. OUTPUT FORMAT
Return the output as a valid JSON array of objects. Each object must include:
- `id`: Sequential number.
- `question_text`: High-level academic phrasing.
- `options`: A, B, C, D.
- `answer`: The correct letter.
- `logic_check`: A one-sentence internal justification explaining why the correct answer is a valid paraphrase and which "trap" was used for the primary distractor.                 
                """
        
            json_example = """{
                "id": 1,
                "question_type": "multiple_choice",
                "subtype": "MCQ-SINGLE",
                'passage_title' : "write the title of the entire passage here",
                "question_text": "place the question text here...",
                "answer": "B",
                "metadata": {
                    "options_box": [
                        "A: It is a relatively new invention.", 
                        "B: It now produces more than half of all consumed fish.", 
                        "C: It has caused a decline in traditional fishing.", 
                        "D: It is primarily managed by the UN."
                    ],
                    "paragraph_context": ""
                }
            }"""
                
    # --- SUBTYPE 2: MCQ-MULTI (Choose 2 of 5) ---
        elif subtype == "MCQ-MULTI":
            num_to_select = random.choice([2, 3])
            box_size = 5 if num_to_select == 2 else 7
    
            subtype_instr = f"""
           ### ROLE: SENIOR PSYCHOMETRICIAN (IELTS ASSESSMENT DESIGN)
Your task is to create a high-difficulty MCQ-MULTI{num_questions} questions. 
Goal: Select {num_to_select} correct options from a list of {box_size}.

---

### I. THE ANCHORING PROTOCOL
You must use the 'TRAP ANCHORS' provided below as your exclusive raw material.
[INJECTION POINT]: 
{trap_context_block}

**HOW TO CONSTRUCT THE OPTIONS:**
1. **The Correct Set ({num_to_select} options):**
   - Pick the most nuanced 'Source Texts' from the anchors.
   - **TRANSFORM:** You MUST apply "Conceptual Obfuscation." Replace technical keywords with high-level academic synonyms (e.g., replace 'cortical activation' with 'neural metabolic surges').
   - The meaning must remain 100% factually accurate to the text, but the wording must be 0% similar.

2. **The Distractor Set ({box_size} - {num_to_select} options):**
   - Use the 'Suggested Distractors' from the remaining anchors.
   - **REFINEMENT:** Do not just copy-paste the suggestion. Adjust the tone so it matches the length and complexity of your correct options exactly.
   - **STRICT ADHERENCE:** Ensure the specific 'Trap Logic' (Temporal Inversion, Scope Blur, etc.) is the only reason the option is false.

---

### II. ARCHITECTURAL CONSTRAINTS
- **Stylistic Uniformity:** All options (A through {chr(64 + box_size)}) must be approximately the same word count and use the same grammatical structure (e.g., all starting with a gerund or a noun phrase).
- **Logical Mutual Exclusivity:** Ensure no two options could both be interpreted as correct.
- **Plausibility Density:** Every distractor must contain at least one "True" noun from the text to bait the student into a false sense of familiarity.

---
            """

        
            json_example = """{
                "id": 1,
                "question_type": "multiple_choice",
                "subtype": "MCQ-MULTI",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "Which TWO of the following challenges are mentioned in Paragraph E?",
                "answer": "A, E",
                "metadata": {
                    "options_box": [
                        "A: Plastic waste from gear", 
                        "B: Lack of government funding", 
                        "C: Seasonal migration changes", 
                        "D: Competition from land farming", 
                        "E: Agricultural runoff pollution"
                    ],
                    "paragraph_context": ""
                }
            }"""
    # --- SUBTYPE 3: MCQ-GLOBAL (Title/Main Purpose) ---
        elif subtype == "MCQ-GLOBAL":
            subtype_instr = f"""
          ### ROLE: CHIEF ACADEMIC EDITOR (IELTS TEST SPECIFICATION)
Your task is to generate EXACTLY {num_questions} high-level MCQ-GLOBAL questions that evaluates the student’s ability to synthesize the entire passage.

---

### I. THE FOUR-PILLAR OPTION ARCHITECTURE
You must construct the four options (A, B, C, D) using the following strict archetypes:

1. **THE UNIVERSAL SYNTHESIS (Correct Answer):**
   - **Requirement:** It must bridge the transition from historical assumptions (Paragraph A/B) to modern methodological shifts and conflicts (Paragraph C/D/E).
   - **Constraint:** It must be a "Goldilocks" title: neither too narrow nor too broad. It must encompass the "Methodological Frictions" or "Theoretical Evolutions" mentioned throughout the text.

2. **THE 'DETAIL BAIT' (The Scope Trap):**
   - **Requirement:** Select a factually 100% true statement from a single paragraph (e.g., the findings of the 2022 study or the 18% statistics).
   - **Adversarial Goal:** To catch students who suffer from "Recency Bias" (remembering only the last thing they read) or who focus on specific data points rather than themes.

3. **THE 'MACRO-GENERALIST' (The Breadth Trap):**
   - **Requirement:** A title that covers a domain much larger than the text (e.g., "The Evolution of Human Civilization" or "A History of Global Art").
   - **Adversarial Goal:** To tempt students who understand the general topic but fail to recognize the specific academic boundaries of the passage.

4. **THE 'AUTHORIAL MISALIGNMENT' (The Tone Trap):**
   - **Requirement:** Use primary keywords from the text but assign them an incorrect intent (e.g., calling a neutral survey a "Scathing Critique" or a "Final Resolution" of a problem the text says is "Indeterminate").
   - **Adversarial Goal:** To punish students who ignore modal verbs (might, suggest, could) and assume the author is being definitive.

---

### II. TRAP INTEGRATION 
[INJECTION POINT]: 
{trap_context_block}

**INSTRUCTION:** You MUST pull keywords and concepts from these Anchors to populate the 'Detail Bait' and 'Authorial Misalignment' options.
            """

    # VITAL: Update the json_example specifically for GLOBAL to ensure the AI uses 'prefix' 
    # and doesn't hallucinate paragraph-specific metadata.
            json_example = """{
                "id": 7,
                "question_type": "multiple_choice",
                "subtype": "MCQ-GLOBAL",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "What would be the most appropriate title for this text?",
                "answer": "D",
                "metadata": {
                    "options_box": [
                        "A: [Detail Trap] - A specific point mentioned in Paragraph B", 
                        "B: [Too Broad Trap] - A generalized topic that exceeds the text scope", 
                        "C: [Tone Trap] - An inaccurate description of the author's purpose", 
                        "D: [Correct] - The most comprehensive title for the entire passage"
                    ],
                    "paragraph_context": "Entire Passage"
                }
            }"""
        ques_sys_msg = f"""
        ### ROLE: LEAD PSYCHOMETRICIAN & IELTS TEST ARCHITECT
You are responsible for engineering high-fidelity IELTS Reading assessments. Your objective is to create {num_questions} questions that strictly adhere to the {difficulty} difficulty tier.

---

### I. DIFFICULTY CALIBRATION PROTOCOL
- **EASY:** Focus on explicit synonym matching within a single sentence. Distractors are unrelated to the target sentence.
- **MEDIUM:** Requires sentence-level paraphrasing. Distractors use keywords from neighboring sentences to test basic scanning skills.
- **HARD (Adversarial):** Requires **cross-sentence synthesis** or **logical deduction**. 
    - Correct answers must use "Conceptual Mapping" (transforming the idea, not just the words).
    - Distractors MUST be "Evidence-Based Traps" (they look correct because they use real words from the text, but the logic is flawed).

---

### II. TRAP EXECUTION & LOGIC
{subtype_instr}

**THE ADVERSARIAL RULE:** For every question, you must be able to point to a specific "Anchor" in the text that justifies the correct answer AND a specific "Trap" from the {diff_strategy_block} that invalidates the distractors. 

---

### III. SYSTEM CONSTRAINTS
1. **The 'No-Overlap' Rule:** Ensure that if a distractor is true, it only applies to a different context or scale than the question asks.
2. **The 'Independence' Rule:** No question should provide a "logical bridge" to the next.
3. **JSON Integrity:** Output must be a strictly valid JSON object. No conversational filler.

{cot_instructions}
            
        OUTPUT FORMAT:
        Structure:
        {{
        "questions": [ {json_example} ]
        }}"""
 # IMPORTANT: You MUST pass the passage_text in the user prompt!
 # NEW
        user_prompt = f"""
        ### TASK: PASSAGE ANALYSIS & ITEM GENERATION
PASSAGE TO EVALUATE:
{passage_text}

---

### MANDATORY GENERATION STEPS:
1. **Phase 1: Deep Scan.** Locate {num_questions} distinct logical segments in the passage that can support a {difficulty} level question.
2. **Phase 2: Evidence Mapping.** For each segment, identify:
    - The "Evidence Sentence" (for the correct answer).
    - The "Trap Sentence" (to build distractors).
3. **Phase 3: Paraphrase Engineering.** Rewrite the correct answer so it contains ZERO primary keywords from the Evidence Sentence while maintaining 100% semantic fidelity.
4. **Phase 4: Distractor Integration.** Apply the provided trap logic ({trap_context_block}) to create the three incorrect options.

---


       
        """
        print(ques_sys_msg,user_prompt)
        q_data = call_llm(ques_sys_msg, user_prompt, json_mode=True, difficulty=difficulty, user_choice=user_choice)
        if not q_data or "questions" not in q_data:
            print("DEBUG: Question data is missing or malformed.")
    
    # Check if q_data contains a more specific error message from a previous step
            if isinstance(q_data, dict) and "error" in q_data:
                error_msg = q_data["error"]
            else:
                error_msg = "Question generation failed. Try again later"

            return {
                "status": "error", 
                "type": "VALIDATION", 
                "error": error_msg
            }

 # --- PHASE 3: POST-PROCESSING ---
        final_questions = q_data["questions"]

        for i, q in enumerate(final_questions):
            q["id"] = i + 1
            if q.get("question_type") == "multiple_choice":
                final_questions[i] = shuffle_options(q)

 
 # FIX: Use "question_type" instead of "type"
        if q.get("question_type") == "multiple_choice":
            q = shuffle_options(q)
        final_answers = {str(q.get("id", i+1)): q.get("answer", "Error: Missing Answer") for i, q in enumerate(final_questions)}
        
    elif user_choice == "matching_headings":
        if subtype == "MATCHING-HEADINGS":
            subtype_instr = f"""
    TASK: MATCHING-HEADINGS (Conceptual Synthesis)
    
    STEP 1: ANCHOR INGESTION
    - Review the provided ANCHOR TRAPS: 
    {trap_context_block}
    - You MUST use at least two of these specific logic patterns to build your 'options_box' distractors.
    
    STEP 2: HEADING ARCHITECTURE
    - 'answer': Map {{"Paragraph A": "iv", ...}}.
    - 'options_box': List of N + {random.choice([3, 4])} headings.
    - NOMINALIZATION RULE: Convert verbs to abstract nouns (e.g., "failed to corroborate" becomes "The absence of corroborative evidence").
    
    STEP 3: ADVERSARIAL TRAP MAPPING
    - SLOT i: Use the 'Suggested Distractor' from an ANCHOR as your first option.
    - SLOT ii: Use 'The Detail Magnet' trap (a true detail used as a false heading).
    - SLOT iii-N: The correct answers and remaining distractors.
    
    STEP 4: EXCLUSIVITY CHECK
    - Ensure no heading is "partially correct" for another paragraph. If Paragraph D mentions Mediterranean data, Paragraph A's heading MUST NOT mention Mediterranean data.
    """

            json_example = """{
        "id": 1,
        "question_type": "matching",
        "subtype": "MATCHING-HEADINGS",
        'passage_title' : "write the title of the entire passage here",
        "prefix": "Choose the correct heading for Paragraphs A-E from the list of headings below.",
        "answer": {
            "Paragraph A": "iii",
            "Paragraph B": "v",
            "Paragraph C": "i",
            "Paragraph D": "viii",
            "Paragraph E": "ii"
        },
        "metadata": {
            "options_box": [
                "i: The geological impact on magnetic flux",
                "ii: Future implications for geodynamic modeling",
                "iii: A shift in traditional geophysical paradigms",
                "iv: The relationship between heat and isotope decay",
                "v: Anomalous intensity in the Iron Age Levant",
                "vi: The role of anticyclonic vortices in global weather",
                "vii: Disproved theories regarding core-mantle boundaries",
                "viii: Global datasets vs localized experimental findings"
            ],
            "paragraph_context": "Full Passage (A-E)"
        }
    }"""
            additional_instructions = """
### CRITICAL PROCESS (PARAGRAPH BLUEPRINTING):
    For MATCHING-HEADINGS, perform this internal check:
    1. UNDERSTAND THE CORE: For Paragraph A, ask: "If I had to delete everything except one sentence, which one defines the purpose?" That is your heading.
    2. CREATE THE LURE: Take a technical noun from Paragraph A (e.g., 'Anticyclonic vortex') and create a heading for Paragraph B that uses that noun but is incorrect.
    3. CHECK EXCLUSIVITY: Ensure that the correct heading for Paragraph A DOES NOT fit Paragraph B. If there is overlap, the question is broken. Refine the wording to make it specific.
    4. COUNT VERIFICATION: Count Paragraphs. Count Headings. Verify Headings = Paragraphs + 3 (or 4).
    
    ### ANTI-PATTERN REQUIREMENTS:
- You MUST ensure the correct headings are NOT in sequential order. 
- Example of a BAD mapping: A:i, B:ii, C:iii.
- Example of a GOOD mapping: A:v, B:ix, C:ii, D:iv.
- Strategic Placement: Place the correct heading for Paragraph A somewhere in the middle of the options_box (e.g., slot 'v' or 'vi').
- DISTRACTOR PLACEMENT: Place your most "tempting" distractors (the Shadow Traps) in the first two slots (i and ii) to test if the student is rushing."""
        elif subtype == "MATCHING-INFO":    
            subtype_instr = f"""
             TASK: MATCHING-INFO (Evidence Location)
    - Create {num_questions} information statements.
    - 'prefix': "Which paragraph contains the following information? NB: You may use any letter more than once."
    
    STEP 1: USE ANCHOR LOGIC
    - Utilize the following ANCHORS to design your questions:
    {trap_context_block}
    - If an ANCHOR mentions 'archaeomagnetic data aggregation', create a statement like: "a reference to the process of combining diverse geographical datasets."
    
    STEP 2: THE "SHADOW" REQUIREMENT
    - For every correct answer in Paragraph X, you MUST place a "keyword lure" (from the same ANCHOR) in Paragraph Y. 
    - Purpose: Punish students who scan for nouns without checking the "Action" of the sentence.
    
    STEP 3: NB RULE ENFORCEMENT
    - Ensure one paragraph is the correct answer for at least two different statements.
            """
            additional_instructions = """
            ### CRITICAL PROCESS (FEATURE-STATEMENT MAPPING):
    For MATCHING-FEATURES, perform this internal check:
    1. THE PARAPHRASE PIVOT: Do not use the expert's exact words. If the text says "Dr. Aris suggests the ice caps will melt by 2050," the statement should be "A prediction regarding the timeline of glacial dissolution."
    2. THE "MULTIPLE MENTION" TRAP: Ensure that a specific 'Feature' (e.g., a person's name) appears in at least two different paragraphs, but only one mention contains the answer. This forces the student to read the whole text, not just stop at the first name they see.
    3. THE COLLECTIVE FALLACY: Create one statement that sounds like the general "consensus" of the whole passage, but ensure it is actually attributed to only one specific person.
    4. OPTION BOX LOGIC: Ensure there are more statements than features, OR use the "NB: You may use any letter more than once" rule to ensure students cannot use a simple process of elimination.

### ADVERSARIAL DESIGN REQUIREMENTS:
- DISTRACTOR PLACEMENT (THE "FIRST NAME" TRAP): In the options_box, place the name that appears most frequently in Paragraph A at the bottom of the list, while making it the correct answer for the hardest statement.
- SYNTACTIC SHIFTING: If the text uses an 'Active' voice ("Smith discovered..."), the statement MUST use a 'Passive' voice ("The discovery of... was attributed to Smith").
- THE "NEAR-MISS" STATEMENT: Create a statement that uses 90% of the keywords found in Paragraph C, but make the correct answer a person mentioned in Paragraph E. This punishes "keyword spotting."
            """
            json_example = """{
                "id": 1,
                "subtype": "MATCHING-INFO",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "Which paragraph contains the following information?",
                "answer": "C",
                "metadata": {
                    "question_statement": "a reference to the amount of energy used by traditional cooling systems",
                    "options_box": ["Paragraph A", "Paragraph B", "Paragraph C", "Paragraph D"],
                    "paragraph_context": "The answer is in Paragraph C, but Paragraph B mentions 'energy' in a different context."
                }
            }"""        
        # --- SUBTYPE 3: MATCHING-FEATURES ---
        elif subtype == "MATCHING-FEATURES":
            subtype_instr = f"""
            TASK: MATCHING-FEATURES (Categorical Attribution)
    - Match {num_questions} statements to {num_questions-1} Features (e.g., Expert A, Expert B).
    
    STEP 1: ANCHOR INTEGRATION
    - Review these logical anchors:
    {trap_context_block}
    - Use 'The Unfulfilled Promise' (Anchor 5) to create a statement about a specific expert's skepticism or a "yet-to-be-resolved" issue.
    
    STEP 2: VOICE-SHIFTING
    - Do not use quotes. Use conceptual summaries. 
    - Text: "Expert A says the field is erratic." 
    - Statement: "An observation concerning the inconsistent nature of geomagnetic patterns."
    
    STEP 3: THE PROXIMITY LURE
    - Place the name of Expert A in a sentence that describes the work of Expert B. The statement should target Expert B’s work, testing if the student attributes the idea to the correct person.
            """
            additional_instructions = """
### CRITICAL PROCESS (FEATURE-LOGIC BLUEPRINTING):
    For MATCHING-FEATURES, perform this internal check:
    1. THE "VOICE-SHIFT" TEST: If the text uses a direct quote ("The results were inconclusive," said Dr. Aris), the statement MUST use a conceptual summary ("a skeptical perspective regarding the finality of research outcomes").
    2. THE "N-1" FEATURE RATIO: Ensure there are fewer Features (Names) than Questions. This forces the use of the "NB Rule" where one person is the answer for multiple statements.
    3. THE SHADOW PROXIMITY TRAP: Find Paragraph X where Dr. Aris is mentioned. In the same paragraph, describe a concept that actually belongs to Professor Vance (who is mentioned in Paragraph Y). This punishes students who match by proximity rather than meaning.
    4. CHRONOLOGICAL DISRUPTION: Do not list the experts in the order they appear in the text. If Professor Vance is in Para A, list her as Option C in the options_box.

### ADVERSARIAL DESIGN REQUIREMENTS:
- THE "COLLECTIVE VS INDIVIDUAL" TRAP: Create a statement about a general theory. The text should mention that many scientists support it, but attribute the *specific wording* in the statement to only one person.
- CONTRASTING PAIRS: Ensure two features (experts) discuss the same topic but have opposing views. One statement should target the "critique" while the other targets the "support."
- META-DATA CLARITY: In 'paragraph_context', explicitly state which other experts were mentioned nearby to explain why the distractor is "tempting."""

            json_example = """{
                "id": 1,
                "subtype": "MATCHING-FEATURES",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "Match each statement with the correct scientist (A, B, or C).",
                "answer": "B",
                "question_statement": "The belief that urban expansion is irreversible.",
                "metadata": {                    
                    "options_box": [
                        "A: Dr. Aris Thorne",
                        "B: Professor Elena Vance",
                        "C: Marcus Sterling"
                    ],
                    "paragraph_context": "Vance's view is in Para D; Thorne is mentioned in the same para but disagrees."
                }
            }"""       
        ques_sys_msg = f"""
       You are a Senior IELTS Test Constructor. Your objective is to create {num_questions} questions that are indistinguishable from official Cambridge materials.

SUBTYPE-SPECIFIC RULES:
{subtype_instr}

LOGICAL CONSTRAINTS (THE 10/10 BAR):
1. NO KEYWORD SYMMETRY: If a question statement and a paragraph share more than 2 consecutive words, the question is too easy. REWRITE it using synonyms or nominalization.
2. ANCHOR UTILIZATION: The following ANCHORS must guide the "wrong" options to ensure they are 'attractively incorrect':
{trap_context_block}
3. DISTRACTOR PARALLELISM: All options in the 'options_box' must share the same grammatical structure (e.g., all starting with "The [Noun] of...").
{additional_instructions}
OUTPUT FORMAT:
Return ONLY a raw JSON object. No markdown. No prose.
Structure:
{{
  "questions": [ {json_example} ]
}}"""
 # IMPORTANT: You MUST pass the passage_text in the user prompt!
 # NEW
        user_prompt = f"""
        PASSAGE TO USE:
        {passage_text}

        MANDATORY VALIDATION CHECK:
        1. Verify that every single number, date, or proper noun used in your 'options_box' EXISTS in the text above.
        2. If you want to use "5%" as a distractor, "5%" MUST appear in the text.
        3. If the text does not have enough numbers, use plausible synonyms, but DO NOT invent data.
        """

        q_data = call_llm(ques_sys_msg, user_prompt, json_mode=True, difficulty=difficulty, user_choice=user_choice)
        if not q_data or "questions" not in q_data:
            print("DEBUG: Question data is missing or malformed.")
    
    # Check if q_data contains a more specific error message from a previous step
            if isinstance(q_data, dict) and "error" in q_data:
                error_msg = q_data["error"]
            else:
                error_msg = "Question generation failed. Try again later"

            return {
                "status": "error", 
                "type": "VALIDATION", 
                "error": error_msg
            }

 # --- PHASE 3: POST-PROCESSING ---
        final_questions = q_data["questions"]

        for i, q in enumerate(final_questions):
            q["id"] = i + 1
 
 # FIX: Use "question_type" instead of "type"
        if q.get("question_type") == "multiple_choice":
            q = shuffle_options(q)
        final_answers = {str(q["id"]): q["answer"] for q in final_questions}
        
    elif user_choice == "true_false_not_given":
        words_amount = ""
        if difficulty == "easy":
            words_amount = "12-18"
        elif difficulty == "intermediate":
            words_amount = "18-22"
        elif difficulty == "advanced":
            words_amount = "20-25"
        subtype_instr = f"""
        TASK: TRUE/FALSE/NOT GIVEN (Standard IELTS Logic)
    - Create {num_questions} sequential statements.
    - 'prefix': "Do the following statements agree with the information given in the text?"
    - 'answer': One of "TRUE", "FALSE", or "NOT GIVEN".
    - When generating the 'question_statement' try to not to use easily identifiable keywords from the text. Instead, use abstract nouns and paraphrasing (e.g., 'an explanation of', 'a comparison between', 'the reason for') to hide keywords and force deeper understanding.
    - Keep the 'question_statement' between {words_amount} words. 
    ### TRAP EXECUTION RULES:
    1. TRUE (The Paraphrase):
       - Statement must match the text’s meaning but use 0% of the same content words.
       - Example: text says "fuel consumption decreased"; statement says "energy use dropped".

    2. FALSE (The Direct Contradiction):
       - Use a Quantity Flip (most → all, few → none).
       - Use a Directional Flip (increase → decrease).
       - Use a Fact Clash (1998 → 1996).
       - Distractors must look plausible but directly oppose the text.

    3. NOT GIVEN (The Shadow Information):
       - Topic exists in the text, but the specific claim does not.
       - Similarity Trap: statement reuses text words but adds a comparison or evaluation not in the passage.
       - Future Trap: statement makes a definitive prediction when the text only expresses possibility.

    ### TRAP ANCHORS (Use these to design statements and distractors):
    {trap_context_block}

    ### Difficulty Calibration
    - Easy: obvious qualifiers (may, often).
    - Medium: attribution traps (historians suggest, critics argue).
    - Hard: multi-layered logic (conditional + causal + attribution combined).
        """

        json_example = """{
        "id": 1,
        "subtype": "TFNG",
        'passage_title' : "write the title of the entire passage here",
        "prefix": "Do the following statements agree with the information given in the text?",
        "answer": "NOT GIVEN",
        "metadata": {
            "question_statement": "Google's founders initially intended to settle in Menlo Park permanently.",
            "logic_explanation": "The text mentions they operated from a garage in Menlo Park (Paragraph A), but it never discusses their long-term residency intentions.",
            "paragraph_context": "A"
        }
    }"""
        ques_sys_msg = f"""
        You are a Senior IELTS Test Constructor. Create exatly {num_questions} questions for an IELTS Reading test.
        {diff_strategy_block}

        {cot_instructions}
        ### RANDOMIZATION & DISTRIBUTION RULES:
        1. DO NOT follow a predictable pattern (e.g., T-F-NG-T-F). 
        2. RANDOMIZE the order of answers. The first question should not always be 'TRUE'.
        3. Aim for a balanced but irregular distribution. For 5 questions, use a mix like (2x TRUE, 1x FALSE, 2x NOT GIVEN) or (1x TRUE, 2x FALSE, 2x NOT GIVEN).
        4. NEVER have more than two of the same answer in a row (e.g., TRUE, TRUE, TRUE is forbidden).
        SPECIFIC SUBTYPE RULES:
        {subtype_instr}
 
        OUTPUT FORMAT:
        Return ONLY a raw JSON object. Do not include markdown formatting (```json).
        Structure:
        {{
        "questions": [ {json_example} ]
        }}"""
 # IMPORTANT: You MUST pass the passage_text in the user prompt!
 # NEW
        user_prompt = f"""
        PASSAGE TO USE:
        {passage_text}
        
        """

        q_data = call_llm(ques_sys_msg, user_prompt, json_mode=True, difficulty=difficulty, user_choice=user_choice)
        if not q_data or "questions" not in q_data:
            print("DEBUG: Question data is missing or malformed.")
    
    # Check if q_data contains a more specific error message from a previous step
            if isinstance(q_data, dict) and "error" in q_data:
                error_msg = q_data["error"]
            else:
                error_msg = "Question generation failed. Try again later"

            return {
                "status": "error", 
                "type": "VALIDATION", 
                "error": error_msg
            }

 # --- PHASE 3: POST-PROCESSING ---
        final_questions = q_data["questions"]

        for i, q in enumerate(final_questions):
            q["id"] = i + 1
 
 # FIX: Use "question_type" instead of "type"
        if q.get("question_type") == "multiple_choice":
            q = shuffle_options(q)
        final_answers = {str(q["id"]): q["answer"] for q in final_questions}
        
    elif user_choice == "yes_no_not_given":
        
        if subtype == "Yes/no/not given":    
            subtype_instr = f"""
             TASK: YES/NO/NOT GIVEN (Writer's Claims & Opinions)
            - Create {num_questions} sequential statements.
            - 'prefix': "Do the following statements agree with the claims of the writer in the text?"
            - 'answer': One of "YES", "NO", or "NOT GIVEN".

            ### TRAP EXECUTION RULES:
            1. YES (The Agreement):
            - Statement must match the writer’s view but use paraphrased evaluative language.
            - Example: text says "groundbreaking"; statement says "innovative".
            - Look for adjectives/adverbs that signal opinion (important, misguided, revolutionary).

            2. NO (The Disagreement):
            - Tone Flip: text is optimistic, statement says writer is fearful.
            - Strength Flip: text says "possible", statement says "certain".
            - Direct Refutation: text says "benefit", statement says "drawback".
            - Distractors must sound plausible but invert the writer’s stance.

            3. NOT GIVEN (The Silent Opinion):
            - Subject is in the text, but the writer does not express the specific opinion.
            - Moral/Value Trap: statement asks if something is "good/bad" when the text only describes "how" it works.
            - Future Trap: statement makes a definitive prediction when the text only expresses possibility.
            - Similarity Trap: statement reuses text words but adds a judgment not present.

            ### TRAP ANCHORS (Use these to design statements and distractors):
            {trap_context_block}

            ### Difficulty Calibration
            - Easy: straightforward paraphrase of writer’s evaluative claim.
            - Medium: tone or strength flips requiring careful inference.
            - Hard: multi-layered traps combining attribution + moral/value + temporal logic.
            """

            json_example = """{
            "id": 1,
            "subtype": "YNNG",
            "passage_title": "write the title of the passage here",
            "prefix": "Do the following statements agree with the claims of the writer in the text?",
            "answer": "NO",
            "metadata": {
                "question_statement": "The writer believes that Google's data collection methods are primarily harmful to the user experience.",
                "logic_explanation": "The text states that Google 'claims its collection improves user experience' (Paragraph E), contradicting the idea that the writer sees them as 'primarily harmful'.",
                "paragraph_context": "E"
                }
            }"""
            ques_sys_msg = f"""
            You are a Senior IELTS Test Constructor. Create exatly {num_questions} questions for an IELTS Reading test.
            {diff_strategy_block}

            {cot_instructions}

            SPECIFIC SUBTYPE RULES:
            {subtype_instr}
 
            OUTPUT FORMAT:
            Return ONLY a raw JSON object. Do not include markdown formatting (```json).
            Structure:
            {{
            "questions": [ {json_example} ]
            }}"""
 # IMPORTANT: You MUST pass the passage_text in the user prompt!
 # NEW
            user_prompt = f"""
            PASSAGE TO USE:
            {passage_text}

            MANDATORY VALIDATION CHECK:
            1. Verify that every single number, date, or proper noun used in your 'options_box' EXISTS in the text above.
            2. If you want to use "5%" as a distractor, "5%" MUST appear in the text.
            3. If the text does not have enough numbers, use plausible synonyms, but DO NOT invent data.
            """

            q_data = call_llm(ques_sys_msg, user_prompt, json_mode=True, difficulty=difficulty, user_choice=user_choice)
            if not q_data or "questions" not in q_data:
                print("DEBUG: Question data is missing or malformed.")
    
    # Check if q_data contains a more specific error message from a previous step
                if isinstance(q_data, dict) and "error" in q_data:
                    error_msg = q_data["error"]
                else:
                    error_msg = "Question generation failed. Try again later"

                return {
                    "status": "error", 
                    "type": "VALIDATION", 
                    "error": error_msg
                }

 # --- PHASE 3: POST-PROCESSING ---
            final_questions = q_data["questions"]
            

            for i, q in enumerate(final_questions):
                q["id"] = i + 1
 
 # FIX: Use "question_type" instead of "type"
            if q.get("question_type") == "multiple_choice":
                q = shuffle_options(q)
            final_answers = {str(q["id"]): q["answer"] for q in final_questions}
            
       
    # 1. SETUP TASK LIST (Before Generation)
    elif user_choice == "mixed":
        tasks_to_generate = mixed_subtypes 
        num_to_select = random.choice([2, 3])
        box_size = 5 if num_to_select == 2 else 7
        print("Generating mixed type questions:", tasks_to_generate)
        num_to_select = random.choice([2, 3])
        box_size = 5 if num_to_select == 2 else 7
    # 1. DEFINE REGISTRIES
    # Note: Use strictly valid JSON strings for the LLM to emulate.
        STRUCTURE_GUIDES = {
            "SENTENCE-STANDARD": """{   "subtype": "STANDARD",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "The WHO suggests that in 2019, nearly seven million people suffered",
                "suffix": "as a result of poor air quality.",
                "answer": "premature deaths",
                "metadata": {
                    "options_box": [],
                    "paragraph_context": "[A]",
                    "original_sentence": "...responsible for approximately 7 million premature deaths worldwide..."
                }
            }""",
            "SUMMARY-BANK": """{
            "subtype": "SUMMARY-BANK",
            'passage_title' : "write the title of the entire passage here",
            "summary_text": "Text with [1], [2], etc...",
            "options_box": ["A: word1", "B: word2", "C: word3", "D: word4", "E: word5", "F: word6", "G: word7", "H: word8", "I: word9"],
            "questions_options": [
                { "id": 1, "gap_number": 1, "answer": "A", "correct_word": "word1", "mapping_evidence": "..." },
                { "id": 2, "gap_number": 2, "answer": "B", "correct_word": "word2", "mapping_evidence": "..." }
            ]
        }""",
            "MCQ-SINGLE": """{ "question_type": "multiple_choice", "subtype": "MCQ-SINGLE","passage_title": "Title here", "question_text": "...", "answer": "B", "metadata": {"options_box": ["A: ...", "B: ...", "C: ...", "D: ..."], "paragraph_context": ""} }""",
            "MCQ-MULTI": """{
                "question_type": "multiple_choice",
                "subtype": "MCQ-MULTI",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "Which TWO of the following challenges are mentioned in Paragraph E?",
                "answer": "A, E",
                "metadata": {
                    "options_box": [
                        "A: Plastic waste from gear", 
                        "B: Lack of government funding", 
                        "C: Seasonal migration changes", 
                        "D: Competition from land farming", 
                        "E: Agricultural runoff pollution"
                    ],
                    "paragraph_context": ""
                }
            }""",
            "MCQ-GLOBAL": """{
                "question_type": "multiple_choice",
                "subtype": "MCQ-GLOBAL",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "What would be the most appropriate title for this text?",
                "answer": "D",
                "metadata": {
                    "options_box": [
                        "A: [Detail Trap] - A specific point mentioned in Paragraph B", 
                        "B: [Too Broad Trap] - A generalized topic that exceeds the text scope", 
                        "C: [Tone Trap] - An inaccurate description of the author's purpose", 
                        "D: [Correct] - The most comprehensive title for the entire passage"
                    ],
                    "paragraph_context": "Entire Passage"
                }
            }""",
            "MATCHING-HEADINGS": """{ "question_type": "matching",
        "subtype": "MATCHING-HEADINGS",
        'passage_title' : "write the title of the entire passage here",
        "prefix": "Choose the correct heading for Paragraphs A-E from the list of headings below.",
        "answer": {
            "Paragraph A": "iii",
            "Paragraph B": "v",
            "Paragraph C": "i",
            "Paragraph D": "viii",
            "Paragraph E": "ii"
        },
        "metadata": {
            "options_box": [
                "i: The geological impact on magnetic flux",
                "ii: Future implications for geodynamic modeling",
                "iii: A shift in traditional geophysical paradigms",
                "iv: The relationship between heat and isotope decay",
                "v: Anomalous intensity in the Iron Age Levant",
                "vi: The role of anticyclonic vortices in global weather",
                "vii: Disproved theories regarding core-mantle boundaries",
                "viii: Global datasets vs localized experimental findings"
            ],
            "paragraph_context": "Full Passage (A-E)"
        }
    }""",
            "MATCHING-INFO": """{ "subtype": "MATCHING-INFO",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "Which paragraph contains the following information?",
                "answer": "C",
                "metadata": {
                    "question_statement": "a reference to the amount of energy used by traditional cooling systems",
                    "options_box": ["Paragraph A", "Paragraph B", "Paragraph C", "Paragraph D"],
                    "paragraph_context": "The answer is in Paragraph C, but Paragraph B mentions 'energy' in a different context."
                }
            }""",
            "MATCHING-FEATURES": """{  "subtype": "MATCHING-FEATURES",
                'passage_title' : "write the title of the entire passage here",
                "prefix": "Match each statement with the correct scientist (A, B, or C).",
                "answer": "B",
                "question_statement": "The belief that urban expansion is irreversible.",
                "metadata": {                    
                    "options_box": [
                        "A: Dr. Aris Thorne",
                        "B: Professor Elena Vance",
                        "C: Marcus Sterling"
                    ],
                    "paragraph_context": "Vance's view is in Para D; Thorne is mentioned in the same para but disagrees."
                }
            }""",
            "TRUE/FALSE/NOT GIVEN": """{ "subtype": "TFNG",
        'passage_title' : "write the title of the entire passage here",
        "prefix": "Do the following statements agree with the information given in the text?",
        "answer": "NOT GIVEN",
        "metadata": {
            "question_statement": "Google's founders initially intended to settle in Menlo Park permanently.",
            "logic_explanation": "The text mentions they operated from a garage in Menlo Park (Paragraph A), but it never discusses their long-term residency intentions.",
            "paragraph_context": "A"
        }
    }""",
            "YES/NO/NOT GIVEN": """{ "subtype": "YNNG",
            "passage_title": "write the title of the passage here",
            "prefix": "Do the following statements agree with the claims of the writer in the text?",
            "answer": "NO",
            "metadata": {
                "question_statement": "The writer believes that Google's data collection methods are primarily harmful to the user experience.",
                "logic_explanation": "The text states that Google 'claims its collection improves user experience' (Paragraph E), contradicting the idea that the writer sees them as 'primarily harmful'.",
                "paragraph_context": "E"
                }
            }"""
        }
        
        SUBTYPE_RULES_REGISTRY = {
    "SENTENCE-STANDARD": """
       ### CORE MANDATE: IELTS SENTENCE COMPLETION (STANDARD)
1. SEQUENTIAL INTEGRITY: Questions must appear in the exact order that the answers appear in the passage.
2. VERBATIM EXTRACTION: The 'answer' MUST be lifted exactly from the text. No changes to plurals, tenses, or word forms. (Strict 1-3 words) and answers can be maximum 3 words and can include one number or just numbers.
3. Word Limit: inside the "word_limit" key, specify the exact number of words in the answer (e.g., "No more than three words") and if there is a number inside the answer include it (e.g., "No more than three words/And one number").
4. GRAMMATICAL SYNERGY: When the answer is placed into the blank, the resulting sentence must be 100% grammatically perfect. 
    - Pay extreme attention to articles (a/an/the), prepositions (in/of/to), and subject-verb agreement.
5. ZERO-MATCH PARAPHRASING: 
    - Do not use the same unique adjectives or verbs found in the original sentence of the text.
    - RESTRUCTURE the logic: If the text is Active Voice, make the question Passive. If the text lists a cause, make the question focus on the effect.
    - Avoid "Word-Spotting": A student should not be able to find the answer by simply looking for a matching keyword in the question stem.
6. LOGICAL UNIQUENESS: The question must be specific enough that only ONE phrase from the entire text can satisfy the logic. Eliminate any "false positives."
            7. DATA INTEGRITY:
                - 'options_box' must be an empty list [].

            ### ADVERSARIAL TRAP FRAMEWORK (MANDATORY)
            {specific_traps}

 ### PRE-GENERATION CHECKLIST (CHAIN OF THOUGHT):
For each question, the model must internally:
1. IDENTIFY TARGET: Find the exact sentence and the 1-2 word "Answer Key."
2. IDENTIFY LURE: Locate a "Neighbor Noun"—a word nearby that fits the grammar but is logically incorrect.
3. DESIGN THE STEM: 
    - Write the Prefix/Suffix using synonyms. 
    - Check: Does the answer fit the grammar of the prefix/suffix perfectly?
    - Check: Does the stem contain a "Keyword Match" that would make it too easy? If yes, re-write.
4. VERIFY ORDER: Ensure this answer appears AFTER the previous question's answer in the text.
    """,
    
    "SUMMARY-BANK": """
        1. MASTER TEMPLATE PERSISTENCE: 
        - Generate exactly ONE summary paragraph (80-150 words). 
        - Use placeholders [1] through [{current_num}] as static tokens.
        - CRITICAL: Place the full summary in a SINGLE 'summary_text' key at the root of the JSON. 
    
    2. ADVERSARIAL WORD BANK (STRICT GRAMMAR RULE): 
        - Provide exactly {current_num + 4} options in the 'options_box' array. 
        - RULE: Every correct answer in the bank must be a SYNONYM of the source text.
        - **HOMOGENEITY RULE**: The total 'options_box' must be composed of only ONE or TWO parts of speech. 
        - If Gap [1] is an ADJECTIVE, the 'options_box' must contain at least 4 other ADJECTIVES as distractors. 
        - DO NOT mix nouns, verbs, and adverbs in a way that makes the correct answer the only grammatically viable choice. 
        - Example: If the gap is "The ___ process", all options in the bank should be adjectives (e.g., 'rapid', 'stochastic', 'manual', 'intricate').

    3. ADVERSARIAL TRAP INSTRUCTIONS:
        - Use synonyms for correct answers. Use words directly from the text as WRONG distractors.
        {specific_traps}

    4. DEEP PARAPHRASING: 
        - Flip the voice (Active to Passive). 
        - If the text is 'A caused B', the summary should be 'B was a result of A'.

    5. VERIFIABILITY: 
        - Provide 'mapping_evidence' for every gap explaining why the synonym was chosen.

    6.MANDANTORY: Make sure that answer is not following a pattern like A,B,C,D. Choose the correct letter randomly 
    """,
    
    "MCQ-SINGLE": """
        ### ROLE: ELITE ACADEMIC EXAMINER (IELTS SECTION 3 SPECIALIST)
Your goal is to generate {current_num} high-complexity, sequential Multiple Choice Questions (MCQs) based on the provided text. You must punish superficial "keyword matching" and reward deep logical synthesis.

---

### I. QUESTION ARCHITECTURE
1. **Sequential Flow:** Questions must follow the chronological order of information in the passage.
2. **Cognitive Split:** - 60% Literal/Paraphrase: Testing precise understanding of complex academic phrasing.
   - 40% Inferential/Logical: Asking about the author's purpose, underlying assumptions, or the "why" behind a stated fact.
3. **The "Sanitized" Correct Answer:**
   - **STRICT PROHIBITION:** The correct option MUST NOT use the primary nouns or verbs from the source sentence. 
   - **THE GOAL:** Transform the concept. (e.g., If text says "diagenesis of hydroxyapatite," the answer might say "mineral changes in skeletal structures").

---

### II. ADVERSARIAL DISTRACTOR LOGIC (The Traps)
For every question, you MUST incorporate at least one specific trap from the following taxonomy:
{specific_traps}

**MANDATORY TRAP TYPES:**
- **The Temporal Inverter:** Reversing the "before/after" or "cause/effect" relationship.
- **The Scope/Scale Blur:** Taking a specific statistic (e.g., 15%) and applying it to a general category, or misattributing a local finding to a global context.
- **The Referential Swap:** Attributing an action to the wrong subject (e.g., "Novice excavators" vs. "Contemporary scholars").
- **The Pseudo-Synonym:** Using a word that sounds scientific but subtly alters the meaning (e.g., "confirmed" vs. "suggested").

---

### III. RIGID EXECUTION CONSTRAINTS
1. **Logical Singularity:** Only ONE option can be factually defensible. Review all distractors to ensure they are demonstrably false based *strictly* on the text.
2. **Independence:** Question 3 must not give away the answer to Question 4.
3. **No Safety Nets:** Do not use "All/None of the above" or "Both A and B."
4. **Answer Distribution:** You must balance the correct answers across A, B, C, and D. If a student chooses "C" for every answer, they should fail.
5. **No Hallucinations:** All distractors must be grounded in actual details from the text, just misapplied or distorted. Never invent outside information.

---

### IV. OUTPUT FORMAT
Return the output as a valid JSON array of objects. Each object must include:
- `id`: Sequential number.
- `question_text`: High-level academic phrasing.
- `options`: A, B, C, D.
- `answer`: The correct letter.
    """,
    
    "MCQ-MULTI": """
        ### ROLE: SENIOR PSYCHOMETRICIAN (IELTS ASSESSMENT DESIGN)
Your task is to create a high-difficulty MCQ-MULTI question. 
Goal: Select {current_num} correct options from a list of {box_size}.

---

### I. THE ANCHORING PROTOCOL
You must use the 'TRAP ANCHORS' provided below as your exclusive raw material.
[INJECTION POINT]: 
{specific_traps}

**HOW TO CONSTRUCT THE OPTIONS:**
1. **The Correct Set ({num_to_select} options):**
   - Pick the most nuanced 'Source Texts' from the anchors.
   - **TRANSFORM:** You MUST apply "Conceptual Obfuscation." Replace technical keywords with high-level academic synonyms (e.g., replace 'cortical activation' with 'neural metabolic surges').
   - The meaning must remain 100% factually accurate to the text, but the wording must be 0% similar.

2. **The Distractor Set ({box_size} - {num_to_select} options):**
   - Use the 'Suggested Distractors' from the remaining anchors.
   - **REFINEMENT:** Do not just copy-paste the suggestion. Adjust the tone so it matches the length and complexity of your correct options exactly.
   - **STRICT ADHERENCE:** Ensure the specific 'Trap Logic' (Temporal Inversion, Scope Blur, etc.) is the only reason the option is false.

---

### II. ARCHITECTURAL CONSTRAINTS
- **Stylistic Uniformity:** All options (A through {chr(64 + box_size)}) must be approximately the same word count and use the same grammatical structure (e.g., all starting with a gerund or a noun phrase).
- **Logical Mutual Exclusivity:** Ensure no two options could both be interpreted as correct.
- **Plausibility Density:** Every distractor must contain at least one "True" noun from the text to bait the student into a false sense of familiarity.

---
    """,
    
    "MCQ-GLOBAL": """
        ### ROLE: CHIEF ACADEMIC EDITOR (IELTS TEST SPECIFICATION)
Your task is to generate EXACTLY {current_num} high-level MCQ-GLOBAL questions that evaluates the student’s ability to synthesize the entire passage.

---

### I. THE FOUR-PILLAR OPTION ARCHITECTURE
You must construct the four options (A, B, C, D) using the following strict archetypes:

1. **THE UNIVERSAL SYNTHESIS (Correct Answer):**
   - **Requirement:** It must bridge the transition from historical assumptions (Paragraph A/B) to modern methodological shifts and conflicts (Paragraph C/D/E).
   - **Constraint:** It must be a "Goldilocks" title: neither too narrow nor too broad. It must encompass the "Methodological Frictions" or "Theoretical Evolutions" mentioned throughout the text.

2. **THE 'DETAIL BAIT' (The Scope Trap):**
   - **Requirement:** Select a factually 100% true statement from a single paragraph (e.g., the findings of the 2022 study or the 18% statistics).
   - **Adversarial Goal:** To catch students who suffer from "Recency Bias" (remembering only the last thing they read) or who focus on specific data points rather than themes.

3. **THE 'MACRO-GENERALIST' (The Breadth Trap):**
   - **Requirement:** A title that covers a domain much larger than the text (e.g., "The Evolution of Human Civilization" or "A History of Global Art").
   - **Adversarial Goal:** To tempt students who understand the general topic but fail to recognize the specific academic boundaries of the passage.

4. **THE 'AUTHORIAL MISALIGNMENT' (The Tone Trap):**
   - **Requirement:** Use primary keywords from the text but assign them an incorrect intent (e.g., calling a neutral survey a "Scathing Critique" or a "Final Resolution" of a problem the text says is "Indeterminate").
   - **Adversarial Goal:** To punish students who ignore modal verbs (might, suggest, could) and assume the author is being definitive.

---

### II. TRAP INTEGRATION 
[INJECTION POINT]: 
{specific_traps}

**INSTRUCTION:** You MUST pull keywords and concepts from these Anchors to populate the 'Detail Bait' and 'Authorial Misalignment' options.
    """,
    
    "MATCHING-HEADINGS": """
        TASK: MATCHING-HEADINGS (Conceptual Synthesis)
    
    STEP 1: ANCHOR INGESTION
    - Review the provided ANCHOR TRAPS: 
    {specific_traps}
    - You MUST use at least two of these specific logic patterns to build your 'options_box' distractors.
    
    STEP 2: HEADING ARCHITECTURE
    - 'answer': Map {{"Paragraph A": "iv", ...}}.
    - 'options_box': List of N + {random.choice([3, 4])} headings.
    - NOMINALIZATION RULE: Convert verbs to abstract nouns (e.g., "failed to corroborate" becomes "The absence of corroborative evidence").
    
    STEP 3: ADVERSARIAL TRAP MAPPING
    - SLOT i: Use the 'Suggested Distractor' from an ANCHOR as your first option.
    - SLOT ii: Use 'The Detail Magnet' trap (a true detail used as a false heading).
    - SLOT iii-N: The correct answers and remaining distractors.
    
    STEP 4: EXCLUSIVITY CHECK
    - Ensure no heading is "partially correct" for another paragraph. If Paragraph D mentions Mediterranean data, Paragraph A's heading MUST NOT mention Mediterranean data.


    ## CRITICAL PROCESS (PARAGRAPH BLUEPRINTING):
    For MATCHING-HEADINGS, perform this internal check:
    1. UNDERSTAND THE CORE: For Paragraph A, ask: "If I had to delete everything except one sentence, which one defines the purpose?" That is your heading.
    2. CREATE THE LURE: Take a technical noun from Paragraph A (e.g., 'Anticyclonic vortex') and create a heading for Paragraph B that uses that noun but is incorrect.
    3. CHECK EXCLUSIVITY: Ensure that the correct heading for Paragraph A DOES NOT fit Paragraph B. If there is overlap, the question is broken. Refine the wording to make it specific.
    4. COUNT VERIFICATION: Count Paragraphs. Count Headings. Verify Headings = Paragraphs + 3 (or 4).
    
    ### ANTI-PATTERN REQUIREMENTS:
- You MUST ensure the correct headings are NOT in sequential order. 
- Example of a BAD mapping: A:i, B:ii, C:iii.
- Example of a GOOD mapping: A:v, B:ix, C:ii, D:iv.
- Strategic Placement: Place the correct heading for Paragraph A somewhere in the middle of the options_box (e.g., slot 'v' or 'vi').
- DISTRACTOR PLACEMENT: Place your most "tempting" distractors (the Shadow Traps) in the first two slots (i and ii) to test if the student is rushing.
    """,
    
    "MATCHING-INFO": """
        TASK: MATCHING-INFO (Evidence Location)
    - Create {current_num} information statements.
    - 'prefix': "Which paragraph contains the following information? NB: You may use any letter more than once."
    
    STEP 1: USE ANCHOR LOGIC
    - Utilize the following ANCHORS to design your questions:
    {specific_traps}
    - If an ANCHOR mentions 'archaeomagnetic data aggregation', create a statement like: "a reference to the process of combining diverse geographical datasets."
    
    STEP 2: THE "SHADOW" REQUIREMENT
    - For every correct answer in Paragraph X, you MUST place a "keyword lure" (from the same ANCHOR) in Paragraph Y. 
    - Purpose: Punish students who scan for nouns without checking the "Action" of the sentence.
    
    STEP 3: NB RULE ENFORCEMENT
    - Ensure one paragraph is the correct answer for at least two different statements.

    ### CRITICAL PROCESS (FEATURE-STATEMENT MAPPING):
    For MATCHING-FEATURES, perform this internal check:
    1. THE PARAPHRASE PIVOT: Do not use the expert's exact words. If the text says "Dr. Aris suggests the ice caps will melt by 2050," the statement should be "A prediction regarding the timeline of glacial dissolution."
    2. THE "MULTIPLE MENTION" TRAP: Ensure that a specific 'Feature' (e.g., a person's name) appears in at least two different paragraphs, but only one mention contains the answer. This forces the student to read the whole text, not just stop at the first name they see.
    3. THE COLLECTIVE FALLACY: Create one statement that sounds like the general "consensus" of the whole passage, but ensure it is actually attributed to only one specific person.
    4. OPTION BOX LOGIC: Ensure there are more statements than features, OR use the "NB: You may use any letter more than once" rule to ensure students cannot use a simple process of elimination.

### ADVERSARIAL DESIGN REQUIREMENTS:
- DISTRACTOR PLACEMENT (THE "FIRST NAME" TRAP): In the options_box, place the name that appears most frequently in Paragraph A at the bottom of the list, while making it the correct answer for the hardest statement.
- SYNTACTIC SHIFTING: If the text uses an 'Active' voice ("Smith discovered..."), the statement MUST use a 'Passive' voice ("The discovery of... was attributed to Smith").
- THE "NEAR-MISS" STATEMENT: Create a statement that uses 90% of the keywords found in Paragraph C, but make the correct answer a person mentioned in Paragraph E. This punishes "keyword spotting."
    """,
    
    "MATCHING-FEATURES": """
       TASK: MATCHING-FEATURES (Categorical Attribution)
    - Match {current_num} statements to {current_num-1} Features (e.g., Expert A, Expert B).
    
    STEP 1: ANCHOR INTEGRATION
    - Review these logical anchors:

    {specific_traps}

    
    STEP 2: VOICE-SHIFTING
    - Do not use quotes. Use conceptual summaries. 
    - Text: "Expert A says the field is erratic." 
    - Statement: "An observation concerning the inconsistent nature of geomagnetic patterns."
    
    STEP 3: THE PROXIMITY LURE
    - Place the name of Expert A in a sentence that describes the work of Expert B. The statement should target Expert B’s work, testing if the student attributes the idea to the correct person.
### CRITICAL PROCESS (FEATURE-LOGIC BLUEPRINTING):
    For MATCHING-FEATURES, perform this internal check:
    1. THE "VOICE-SHIFT" TEST: If the text uses a direct quote ("The results were inconclusive," said Dr. Aris), the statement MUST use a conceptual summary ("a skeptical perspective regarding the finality of research outcomes").
    2. THE "N-1" FEATURE RATIO: Ensure there are fewer Features (Names) than Questions. This forces the use of the "NB Rule" where one person is the answer for multiple statements.
    3. THE SHADOW PROXIMITY TRAP: Find Paragraph X where Dr. Aris is mentioned. In the same paragraph, describe a concept that actually belongs to Professor Vance (who is mentioned in Paragraph Y). This punishes students who match by proximity rather than meaning.
    4. CHRONOLOGICAL DISRUPTION: Do not list the experts in the order they appear in the text. If Professor Vance is in Para A, list her as Option C in the options_box.

### ADVERSARIAL DESIGN REQUIREMENTS:
- THE "COLLECTIVE VS INDIVIDUAL" TRAP: Create a statement about a general theory. The text should mention that many scientists support it, but attribute the *specific wording* in the statement to only one person.
- CONTRASTING PAIRS: Ensure two features (experts) discuss the same topic but have opposing views. One statement should target the "critique" while the other targets the "support."
- META-DATA CLARITY: In 'paragraph_context', explicitly state which other experts were mentioned nearby to explain why the distractor is "tempting.
    """,
    
    "TRUE/FALSE/NOT GIVEN": """
        - 'prefix': "Do the following statements agree with the information given in the text?"
    - 'answer': One of "TRUE", "FALSE", or "NOT GIVEN".
    - When generating the 'question_statement' try to not to use easily identifiable keywords from the text. Instead, use abstract nouns and paraphrasing (e.g., 'an explanation of', 'a comparison between', 'the reason for') to hide keywords and force deeper understanding.
    - Keep the 'question_statement' between {words_amount} words. 
    ### TRAP EXECUTION RULES:
    1. TRUE (The Paraphrase):
       - Statement must match the text’s meaning but use 0% of the same content words.
       - Example: text says "fuel consumption decreased"; statement says "energy use dropped".

    2. FALSE (The Direct Contradiction):
       - Use a Quantity Flip (most → all, few → none).
       - Use a Directional Flip (increase → decrease).
       - Use a Fact Clash (1998 → 1996).
       - Distractors must look plausible but directly oppose the text.

    3. NOT GIVEN (The Shadow Information):
       - Topic exists in the text, but the specific claim does not.
       - Similarity Trap: statement reuses text words but adds a comparison or evaluation not in the passage.
       - Future Trap: statement makes a definitive prediction when the text only expresses possibility.
    MANDANTORY; Do not follow ant pattern when deciding the answers, choose the correct answer randomly(good example: Not given, True,True,False. Bad example; True, True,False,False)
    ### TRAP ANCHORS (Use these to design statements and distractors):
    {specific_traps}

    ### Difficulty Calibration
    - Easy: obvious qualifiers (may, often).
    - Medium: attribution traps (historians suggest, critics argue).
    - Hard: multi-layered logic (conditional + causal + attribution combined).
    """,
    
    "YES/NO/NOT GIVEN": """
         TASK: YES/NO/NOT GIVEN (Writer's Claims & Opinions)
            - 'prefix': "Do the following statements agree with the claims of the writer in the text?"
            - 'answer': One of "YES", "NO", or "NOT GIVEN".

            ### TRAP EXECUTION RULES:
            1. YES (The Agreement):
            - Statement must match the writer’s view but use paraphrased evaluative language.
            - Example: text says "groundbreaking"; statement says "innovative".
            - Look for adjectives/adverbs that signal opinion (important, misguided, revolutionary).

            2. NO (The Disagreement):
            - Tone Flip: text is optimistic, statement says writer is fearful.
            - Strength Flip: text says "possible", statement says "certain".
            - Direct Refutation: text says "benefit", statement says "drawback".
            - Distractors must sound plausible but invert the writer’s stance.

            3. NOT GIVEN (The Silent Opinion):
            - Subject is in the text, but the writer does not express the specific opinion.
            - Moral/Value Trap: statement asks if something is "good/bad" when the text only describes "how" it works.
            - Future Trap: statement makes a definitive prediction when the text only expresses possibility.
            - Similarity Trap: statement reuses text words but adds a judgment not present.

            ### TRAP ANCHORS (Use these to design statements and distractors):
            {specific_traps}

            ### Difficulty Calibration
            - Easy: straightforward paraphrase of writer’s evaluative claim.
            - Medium: tone or strength flips requiring careful inference.
            - Hard: multi-layered traps combining attribution + moral/value + temporal logic.
    """
}
        # 2. THE REFACTORED GENERATION LOOP
        words_amount = "10-12" if difficulty == "easy" else "13-17" if difficulty == "intermediate" else "18-20"
    
    # 2. PREPARE TASKS FOR PARALLEL EXECUTION
        total_tasks = len(tasks_to_generate)
        remaining_questions = num_questions
        llm_tasks = []

        for i, task in enumerate(tasks_to_generate):
            active_type = task["type"]
            active_subtype = task["subtype"]
        
        # Distribute question counts
            current_num = remaining_questions if i == total_tasks - 1 else num_questions // total_tasks
            remaining_questions -= current_num

            if current_num == 0:
                continue

            specific_traps = get_subtype_traps(active_subtype, trap_metadata)
        
            lookup_key = str(active_subtype).upper()
            if active_type == "sentence_completion" and lookup_key == "STANDARD":
                lookup_key = "SENTENCE-STANDARD"

            rules = SUBTYPE_RULES_REGISTRY.get(lookup_key, "Follow standard IELTS logic.")
            template = STRUCTURE_GUIDES.get(lookup_key, "{}")

        # Safely format rules without breaking JSON brackets
            formatted_rules = rules.replace("{num_questions}", str(current_num))\
                                .replace("{specific_traps}", str(specific_traps))\
                                .replace("{num_to_select}", str(num_to_select))\
                                .replace("{box_size}", str(box_size))\
                                .replace("{words_amount}", words_amount)

        # Build highly strict, JSON-enforced prompt
            current_ques_sys_msg = f"""
            ROLE: Senior IELTS Item Writer & Psychometrician.
            OBJECTIVE: Construct exactly {current_num} ultra-high-fidelity questions for the {active_type} ({lookup_key}) format.

            ### LOGICAL ARCHITECTURE:
            1. SEMANTIC SHIELDING: Strictly forbid 'keyword matching.' Use deep paraphrasing.
            2. THE "SINGLE-TRUTH" PRINCIPLE: Every question must have exactly one logically defensible answer.
            3. DISTRACTOR PARITY: Wrong options must be grammatically/contextually plausible based on passage details.

            ### TASK SPECIFICATIONS:
            {formatted_rules}

            ### OUTPUT ARCHITECTURE (MANDATORY):
            - You MUST return a strictly valid JSON object.
            - The JSON must contain a single root key called "questions" which contains an array of objects.
            - Emulate this exact JSON structure for your objects: {template}
            - Do not include markdown formatting like ```json. Just output the raw JSON.
            """
        
            llm_tasks.append({
                "sys_msg": current_ques_sys_msg,
                "user_msg": f"PASSAGE:\n{passage_text}",
                "type": active_type,
                "subtype": lookup_key
            })

    # 3. EXECUTE LLM CALLS CONCURRENTLY
        all_raw_chunks = []
    
        def fetch_chunk(task_data):
            print(f"Requesting {task_data['subtype']}...")
            response = call_llm(task_data["sys_msg"], task_data["user_msg"], json_mode=True, difficulty=difficulty, user_choice=user_choice)
            return response, task_data["type"]
        
    # This runs all subtype generation requests at the exact same time
        with concurrent.futures.ThreadPoolExecutor(max_workers=total_tasks) as executor:
            futures = [executor.submit(fetch_chunk, t) for t in llm_tasks]
            for future in concurrent.futures.as_completed(futures):
                try:
                    raw_response, q_type = future.result()
                
                # Robust JSON Parsing
                    if isinstance(raw_response, (dict, list)):
                        q_data = raw_response
                    else:
                        q_data = json_repair.loads(raw_response)

                    if q_data and isinstance(q_data, dict):
                        chunk = q_data.get("questions", q_data.get("anchors", []))
                    elif isinstance(q_data, list):
                        chunk = q_data
                    else:
                        chunk = []

                    if chunk:
                    # Optional: Shuffle MCQ options if your setup requires it
                        for q in chunk:
                            if q_type == "multiple_choice" and isinstance(q, dict) and "shuffle_options" in globals():
                                q = shuffle_options(q)
                        all_raw_chunks.extend(chunk)

                except Exception as e:
                    print(f"Thread failed to process chunk: {e}")

    # 4. POST-PROCESS IDs AND FINALIZE (Ensures perfect sequential alignment)
        final_questions = []
        final_answers = {}
        current_id = 1

        for q in all_raw_chunks:
            if not isinstance(q, dict): continue
        
            if q.get("subtype") == "SUMMARY-BANK":
                q["id"] = current_id
    # Get the list of gaps
                gaps = q.get("questions_options", [])
    
                if isinstance(gaps, list):
                    for gap in gaps:
            # Check if gap is a dictionary before assigning ID
                        if isinstance(gap, dict):
                            gap["id"] = current_id
                            final_answers[str(current_id)] = gap.get("answer")
                            current_id += 1
                        else:
                # If the LLM returned a list or string, we skip or handle it
                            print(f"Warning: Expected dict for gap, got {type(gap)}")
                # current_id += 1 # Optional: increment anyway to keep numbering
            else:
                q["id"] = current_id
                final_answers[str(current_id)] = q.get("answer")
                current_id += 1
            
            final_questions.append(q)

    result_to_save = {
    "user_id": kwargs.get("user_id"),
    "topic": kwargs.get("topic", "General"),
    "difficulty": kwargs.get("difficulty", "Intermediate"),
    "passage": passage_text,
    "questions": final_questions,  # The fully processed list
    "answers": final_answers,      # The sequential answer key
    "language": kwargs.get("language", "English")
}
    self.update_state(state='PROGRESS', meta={'progress': 90, 'status': 'Wrapping up...'})
    # Call your internal save function (defined at the top of backend.py)
    db_status = internal_save_to_db(result_to_save)
    
    #deduction_successful = deduct_user_credits(user_id, required_credits)

    #if not deduction_successful:
    # Optional: You might want to rollback the DB insert here if they somehow ran out of credits mid-generation
        #print("Warning: Failed to deduct credits after generation.")
    #db = mysql.connector.connect(**db_config)
    #cursor = db.cursor()

    #cursor.execute("SELECT balance FROM credits WHERE user_id = %s", (user_id,))
    #new_balance_row = cursor.fetchone()
    #new_balance = new_balance_row[0] if new_balance_row else 0
    # 5. RETURN TO CELERY
    # We include the db_status so the frontend knows the test_id
    return {
        "test_id": db_status.get("insert_id") if db_status["status"] == "success" else None,
        "passage": passage_text,
        "questions": final_questions,
        "answers": final_answers,
        "db_error": db_status.get("message") if db_status["status"] == "error" else None,
    }