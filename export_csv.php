<?php
require_once('../../config.php');
require_login();
 
$id = required_param('id', PARAM_INT); // course module id
$table = required_param('table', PARAM_RAW); // allow underscores
 
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
global $DB;
 
/**
 * Convert misbehaviour category integer to human-readable label.
 * These labels match the mapping in results.php and ai_module.py
 * Categories are stored as 1-indexed (1-6)
 */
function get_misbehaviour_label($category) {
    // 1-indexed mapping (matches results.php lines 152-159)
    $labels = [
        1 => '-',
        2 => 'Aggressive or hostile behaviour',
        3 => 'Uncooperative or ignoring messages behaviour',
        4 => 'Irresponsible or unreliable behaviour',
        5 => 'Harassment or inappropriate comments behaviour',
        6 => 'Dishonest or plagiarism behaviour'
    ];
   
    if (empty($category)) {
        return "-";
    }
   
    // Handle multiple categories (stored as comma-separated integers like "2,4")
    if (is_string($category) && strpos($category, ',') !== false) {
        $cats = explode(',', $category);
        $result = [];
        foreach ($cats as $cat) {
            $cat = (int)trim($cat);
            if (isset($labels[$cat])) {
                $result[] = $labels[$cat];
            }
        }
        return implode('; ', $result);
    }
   
    // Single category
    $cat = (int)$category;
    return isset($labels[$cat]) ? $labels[$cat] : "Unknown";
}
 
// Fetch data
if ($table === 'speval_eval') {
    // Use SQL join to combine eval + flag data for current activity
    $sql = "
        SELECT
            e.id AS evalid,
            e.spevalid,
            e.userid,
            e.peerid,
            e.criteria1,
            e.criteria2,
            e.criteria3,
            e.criteria4,
            e.criteria5,
            e.comment1,
            e.comment2,
            f.commentdiscrepancy,
            f.markdiscrepancy,
            f.quicksubmissiondiscrepancy,
            f.misbehaviorcategory
        FROM {speval_eval} e
        LEFT JOIN {speval_flag} f
            ON e.spevalid = f.spevalid
            AND e.userid = f.userid
            AND e.peerid = f.peerid
        WHERE e.spevalid = :spevalid
        ORDER BY e.userid, e.peerid
    ";
    $records = $DB->get_records_sql($sql, ['spevalid' => $cm->instance]);
} else if ($table === 'speval_flag') {
    $records = $DB->get_records('speval_flag', ['spevalid' => $cm->instance]);
} else {
    die('Invalid table specified.');
}
 
// CSV output headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$table.'_'.date('Ymd').'.csv"');
 
$out = fopen('php://output', 'w');
 
if ($records) {
 
    // =====================================
    // Export for speval_eval (with flags)
    // =====================================
    if ($table === 'speval_eval') {
        $header = [
            'Evaluator Name',
            'Peer Name',
            'criteria1',
            'criteria2',
            'criteria3',
            'criteria4',
            'criteria5',
            'comment1',
            'comment2',
            'commentdiscrepancy',
            'markdiscrepancy',
            'quicksubmissiondiscrepancy',
            'misbehaviorcategory'
        ];
        fputcsv($out, $header);
 
        // Fetch users for evaluator and peer
        $userids = [];
        foreach ($records as $rec) {
            $userids[] = $rec->userid;
            $userids[] = $rec->peerid;
        }
        $userids = array_unique($userids);
        $users = $DB->get_records_list('user', 'id', $userids);
 
        foreach ($records as $rec) {
            $evaluator = isset($users[$rec->userid])
                ? $users[$rec->userid]->firstname . ' ' . $users[$rec->userid]->lastname
                : $rec->userid;
            $peer = isset($users[$rec->peerid])
                ? $users[$rec->peerid]->firstname . ' ' . $users[$rec->peerid]->lastname
                : $rec->peerid;
 
            $row = [
                $evaluator,
                $peer,
                $rec->criteria1,
                $rec->criteria2,
                $rec->criteria3,
                $rec->criteria4,
                $rec->criteria5,
                $rec->comment1,
                $rec->comment2,
                (int)$rec->commentdiscrepancy,
                (int)$rec->markdiscrepancy,
                (int)$rec->quicksubmissiondiscrepancy,
                get_misbehaviour_label($rec->misbehaviorcategory)
            ];
            fputcsv($out, $row);
        }
    }
 
    // =====================================
    // Export for speval_flag
    // =====================================
    else if ($table === 'speval_flag') {
        $header = [
            'Evaluator Name',
            'Peer Name',
            'spevalid',
            'grouping',
            'groupid',
            'commentdiscrepancy',
            'markdiscrepancy',
            'quicksubmissiondiscrepancy',
            'misbehaviorcategory',
            'timecreated'
        ];
        fputcsv($out, $header);
 
        // Fetch users for evaluator and peer
        $userids = [];
        foreach ($records as $rec) {
            $userids[] = $rec->userid;
            $userids[] = $rec->peerid;
        }
        $userids = array_unique($userids);
        $users = $DB->get_records_list('user', 'id', $userids);
 
        foreach ($records as $rec) {
            $evaluator = isset($users[$rec->userid]) ? ($users[$rec->userid]->firstname . ' ' . $users[$rec->userid]->lastname) : $rec->userid;
            $peer = isset($users[$rec->peerid]) ? ($users[$rec->peerid]->firstname . ' ' . $users[$rec->peerid]->lastname) : $rec->peerid;
            $timeformatted = (!empty($rec->timecreated)) ? date('Y-m-d H:i:s', (int)$rec->timecreated) : '';
            $row = [
                $evaluator,
                $peer,
                $rec->spevalid,
                (int)$rec->grouping,
                (int)$rec->groupid,
                (int)$rec->commentdiscrepancy,
                (int)$rec->markdiscrepancy,
                (int)$rec->quicksubmissiondiscrepancy,
                get_misbehaviour_label($rec->misbehaviorcategory),
                $timeformatted
            ];
            fputcsv($out, $row);
        }
    }
}
 
fclose($out);
exit;
 