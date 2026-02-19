import argparse
import json
import re
import time
import sys
import os
import numpy as np
import pandas as pd
import torch
from transformers import pipeline
from typing import Dict, List, Tuple, Optional

# ======= Configuration =======
DEVICE = 0 if torch.cuda.is_available() else -1
SENTIMENT_MODEL = "cardiffnlp/twitter-roberta-base-sentiment-latest"
ZS_MODEL = "facebook/bart-large-mnli"

# Clause handling: favor the final judgement after contrast words
CONTRAST_SPLIT = re.compile(r"\b(?:but|however|although|though|yet|nevertheless|nonetheless)\b", re.I)
W_BEFORE, W_AFTER = 0.7, 0.3

# Thresholds (configurable via environment or config file)
CONF_THRESHOLD = float(os.getenv('SPEVAL_CONF_THRESHOLD', '0.20'))
GAP_TOLERANCE = float(os.getenv('SPEVAL_GAP_TOLERANCE', '0.70'))
MIS_WARN_MIN = float(os.getenv('SPEVAL_MIS_WARN_MIN', '0.30'))

# Misbehaviour labels for display/reporting
MIS_LABELS = [
    "Normal or positive teamwork behaviour",
    "Aggressive or hostile behaviour",
    "Uncooperative or ignoring messages behaviour",
    "Irresponsible or unreliable behaviour",
    "Harassment or inappropriate comments behaviour",
    "Dishonest or plagiarism behaviour",
]
NORMAL_LABEL = MIS_LABELS[0]

# Candidate label phrases given to the zero-shot model (more explicit for better separation)
# We map model outputs back to the concise display labels above.
MIS_LABEL_CANDIDATES = {
    MIS_LABELS[0]: "Normal, respectful, helpful teamwork behaviour",
    MIS_LABELS[1]: "Aggressive or hostile behaviour (yelling, insults, threats)",
    MIS_LABELS[2]: "Uncooperative behaviour (ignores messages or instructions, refuses to respond)",
    MIS_LABELS[3]: "Irresponsible or unreliable behaviour (misses deadlines, fails to complete assigned tasks)",
    MIS_LABELS[4]: "Harassment or inappropriate comments (offensive or discriminatory remarks)",
    MIS_LABELS[5]: "Dishonest behaviour such as plagiarism or cheating",
}

CRITERIA = ["criteria1", "criteria2", "criteria3", "criteria4", "criteria5"]

# Global pipelines (lazy loading)
_sentiment = None
_zeroshot = None

def sentiment_pipe():
    global _sentiment
    if _sentiment is None:
        try:
            _sentiment = pipeline("sentiment-analysis", model=SENTIMENT_MODEL,
                                return_all_scores=True, device=DEVICE)
        except Exception as e:
            print(f"Error loading sentiment model: {e}", file=sys.stderr)
            raise
    return _sentiment

def zs_pipe():
    global _zeroshot
    if _zeroshot is None:
        try:
            _zeroshot = pipeline("zero-shot-classification", model=ZS_MODEL, device=DEVICE)
        except Exception as e:
            print(f"Error loading zero-shot model: {e}", file=sys.stderr)
            raise
    return _zeroshot

# ======= Helper Functions =======
def clean_text(s: str) -> str:
    """Clean and normalize text input."""
    if pd.isna(s) or s is None:
        return ""
    s = str(s).strip()
    return re.sub(r"\s+", " ", s)

def avg_mark(row: Dict) -> float:
    """Calculate average mark from criteria ratings."""
    vals = []
    for c in CRITERIA:
        v = pd.to_numeric(row.get(c, np.nan), errors="coerce")
        if pd.notna(v):
            vals.append(float(v))
    return float(np.mean(vals)) if vals else 3.0  # neutral if missing

def mark_to_norm(mark_1_to_5: float) -> float:
    """Convert 1-5 scale to -1 to +1 normalized scale."""
    return float((float(mark_1_to_5) - 3.0) / 2.0)

def clause_aware_sentiment(text: str) -> Tuple[float, float]:
    """
    Return (sent_norm in [-1,1], conf in [0,1]).
    sentiment_norm = -1*Pneg + 0*Pneu + 1*Ppos (clause-weighted)
    confidence = weighted max(prob) over clauses
    """
    t = clean_text(text)
    if not t:
        return 0.0, 0.0

    try:
        clauses = re.split(CONTRAST_SPLIT, t)
        n = len(clauses)
        weights = np.linspace(W_BEFORE, W_AFTER, n)
        weights = (weights / weights.sum()).astype(np.float32)

        res_list = sentiment_pipe()(clauses)
        pols, confs = [], []
        
        for res in res_list:
            sc = {r["label"].lower(): float(r["score"]) for r in res}
            pneg = sc.get("negative", 0.0)
            pneu = sc.get("neutral", 0.0) 
            ppos = sc.get("positive", 0.0)
            pols.append(-1.0 * pneg + 1.0 * ppos)
            confs.append(max(pneg, pneu, ppos))

        pols = np.array(pols, dtype=np.float32)
        confs = np.array(confs, dtype=np.float32)
        w = weights[:len(pols)]
        w = w / w.sum()
        
        return float((pols * w).sum()), float((confs * w).sum())
    except Exception as e:
        print(f"Error in sentiment analysis: {e}", file=sys.stderr)
        return 0.0, 0.0

def comment_discrepancy_flag(comment_text: str, avg_mark_val: float) -> Tuple[bool, float, float]:
    """
    True if comment sentiment conflicts with the avg numeric mark beyond tolerance.
    Returns: (is_discrepancy, sentiment_norm, confidence)
    """
    s_norm, conf = clause_aware_sentiment(comment_text)
    if conf < CONF_THRESHOLD:
        return False, s_norm, conf
    
    diff = abs(s_norm - mark_to_norm(avg_mark_val))
    return bool(diff >= GAP_TOLERANCE), s_norm, conf

def misbehaviour_flag(text: str) -> Tuple[bool, str, float]:
    """
    Detect misbehaviour in text using zero-shot classification.
    Returns: (is_misbehaviour, label, confidence)
    """
    t = clean_text(text)
    if not t:
        return False, NORMAL_LABEL, 0.0
    
    try:
        # Use more explicit candidate phrases and a task-specific hypothesis template
        candidate_labels = list(MIS_LABEL_CANDIDATES.values())
        out = zs_pipe()(t, candidate_labels=candidate_labels, multi_label=False, hypothesis_template="In a team project, this behaviour is {}.")
        label = out["labels"][0]
        score = float(out["scores"][0])
        
        # Map model label back to our display labels
        mapped_label = None
        for base_label, cand in MIS_LABEL_CANDIDATES.items():
            if label == cand:
                mapped_label = base_label
                break
        if mapped_label is None and label in MIS_LABELS:
            mapped_label = label
        if mapped_label is None:
            mapped_label = NORMAL_LABEL

        if mapped_label == NORMAL_LABEL:
            return False, label, score
        return bool(score >= MIS_WARN_MIN), mapped_label, score
    except Exception as e:
        print(f"Error in misbehaviour detection: {e}", file=sys.stderr)
        return False, NORMAL_LABEL, 0.0

# ======= Core Analysis Functions =======
def analyze_single_evaluation(eval_data: Dict) -> Dict:
    """
    Analyze a single evaluation record.
    Input: Dictionary with evaluation data
    Output: Analysis results dictionary
    """
    # Combine all comments for analysis
    comments = []
    for i in [1]:  # only comment1, ignore comment2 per requirements
        comment = clean_text(eval_data.get(f'comment{i}', ''))
        if comment:
            comments.append(comment)
    
    combined_comment = ' '.join(comments)
    
    # Calculate average mark from criteria only (ignore any provided finalgrade)
    avg_mark_val = avg_mark(eval_data)
    
    # Analyze misbehaviour
    misbehaviour_detected, misbehaviour_label, misbehaviour_confidence = misbehaviour_flag(combined_comment)
    
    # Analyze comment discrepancy
    comment_discrepancy_detected, sentiment_norm, sentiment_confidence = comment_discrepancy_flag(combined_comment, avg_mark_val)
    
    # Mark discrepancy (placeholder for future implementation)
    mark_discrepancy_detected = False
    
    # Generate explanation
    explanation_parts = []
    if misbehaviour_detected:
        explanation_parts.append(f"Misbehaviour detected: {misbehaviour_label} (confidence: {misbehaviour_confidence:.2f})")
    if comment_discrepancy_detected:
        explanation_parts.append(f"Comment-mark discrepancy: sentiment={sentiment_norm:.2f}, mark_norm={mark_to_norm(avg_mark_val):.2f} (confidence: {sentiment_confidence:.2f})")
    
    explanation = "; ".join(explanation_parts) if explanation_parts else "No issues detected"
    
    # Determine misbehaviour category index (1-6) based on predicted label
    mis_cat_index = 1
    try:
        if misbehaviour_label in MIS_LABELS:
            mis_cat_index = int(MIS_LABELS.index(misbehaviour_label)) + 1
        else:
            mis_cat_index = 1
    except Exception:
        mis_cat_index = 1

    return {
        'evaluation_id': eval_data.get('id'),
        'peer_id': eval_data.get('peerid'),
        'evaluator_id': eval_data.get('userid'),
        'activity_id': eval_data.get('spevalid'),
        'misbehaviour_detected': misbehaviour_detected,
        'misbehaviour_label': misbehaviour_label,
        'misbehaviour_confidence': misbehaviour_confidence,
        'misbehaviour_category_index': mis_cat_index,
        'mark_discrepancy_detected': mark_discrepancy_detected,
        'comment_discrepancy_detected': comment_discrepancy_detected,
        'sentiment_norm': sentiment_norm,
        'sentiment_confidence': sentiment_confidence,
        'average_mark': avg_mark_val,
        'explanation': explanation,
        'analysis_timestamp': int(time.time())
    }

def analyze_evaluations_batch(evaluations_data: List[Dict]) -> List[Dict]:
    """
    Analyze a batch of evaluations.
    Input: List of evaluation dictionaries
    Output: List of analysis results
    """
    results = []
    
    for eval_data in evaluations_data:
        try:
            result = analyze_single_evaluation(eval_data)
            results.append(result)
        except Exception as e:
            print(f"Error analyzing evaluation {eval_data.get('id', 'unknown')}: {e}", file=sys.stderr)
            # Add error result
            results.append({
                'evaluation_id': eval_data.get('id'),
                'peer_id': eval_data.get('peerid'),
                'evaluator_id': eval_data.get('userid'),
                'activity_id': eval_data.get('spevalid'),
                'error': str(e),
                'analysis_timestamp': int(time.time())
            })
    
    return results

# ======= Main API Functions =======
def process_json_input(json_data: str) -> str:
    """
    Process JSON input from Moodle and return JSON results.
    This is the main entry point for Moodle integration.
    """
    try:
        data = json.loads(json_data)
        
        if isinstance(data, list):
            # Batch processing
            results = analyze_evaluations_batch(data)
        elif isinstance(data, dict):
            # Single evaluation
            results = [analyze_single_evaluation(data)]
        else:
            raise ValueError("Invalid input format")
        
        return json.dumps({
            'status': 'success',
            'results': results,
            'processed_count': len(results),
            'timestamp': int(time.time())
        })
    
    except Exception as e:
        error_response = {
            'status': 'error',
            'error': str(e),
            'timestamp': int(time.time())
        }
        return json.dumps(error_response)

def evaluate_ai_module_csv(input_csv: str, output_csv: str = "output_dataset.csv", 
                          default_activityid: int = 3) -> pd.DataFrame:
    """
    Legacy CSV processing function (for backward compatibility).
    """
    try:
        df = pd.read_csv(input_csv)
        
        # Ensure minimum columns exist
        required_columns = ["id", "userid", "peerid", "spevalid", "comment1", "timecreated"] + CRITERIA
        for c in required_columns:
            if c not in df.columns:
                df[c] = np.nan
        
        # Default spevalid if missing
        if df["spevalid"].isna().all():
            df["spevalid"] = default_activityid
        
        results = []
        for _, row in df.iterrows():
            eval_data = row.to_dict()
            analysis = analyze_single_evaluation(eval_data)
            
            # Convert to output format
            output_row = {
                "id": eval_data.get("id"),
                "spevalid": int(eval_data.get("spevalid", default_activityid)),
                "groupingid": None,
                "groupid": None,
                "misbehaviourflag": analysis['misbehaviour_detected'],
                "markdiscrepancyflag": analysis['mark_discrepancy_detected'],
                "commentdiscrepancyflag": analysis['comment_discrepancy_detected'],
                "notes": analysis['explanation'],
                "timecreated": int(time.time()),
            }
            results.append(output_row)
        
        out_df = pd.DataFrame(results)
        out_df.to_csv(output_csv, index=False)
        return out_df
        
    except Exception as e:
        print(f"Error processing CSV: {e}", file=sys.stderr)
        raise

# ======= CLI Interface =======
if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="SmartSPE – AI Module for Moodle Integration")
    parser.add_argument("--input", help="Path to input file (CSV or JSON)")
    parser.add_argument("--output", help="Path to output file (CSV)")
    parser.add_argument("--json", action="store_true", help="Process JSON input from stdin")
    parser.add_argument("--spevalid", type=int, default=3, help="Fallback activity id")
    
    args = parser.parse_args()
    
    if args.json:
        # JSON mode for Moodle integration
        input_data = sys.stdin.read()
        result = process_json_input(input_data)
        print(result)
    elif args.input:
        # CSV mode for testing
        output_file = args.output or "output_dataset.csv"
        evaluate_ai_module_csv(args.input, output_file, args.spevalid)
        print(f"Results saved to: {output_file}")
    else:
        parser.print_help()