<?php
/*
 * Self and Peer Evaluation (SPEval) Moodle Plugin
 * View Page - main entry point for students to access the evaluation form.
 */
 
require(__DIR__.'/../../config.php');
 
use mod_speval\local\util;
use mod_speval\local\form_handler;
 
$id         = required_param('id', PARAM_INT);
$cm         = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$course     = get_course($cm->course);
$context    = context_module::instance($cm->id);
$speval     = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);
 
require_login($course, false, $cm);
require_capability('mod/speval:view', $context);
 
$PAGE->set_cm($cm);
$PAGE->set_url(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]));
$PAGE->requires->css(new moodle_url('/mod/speval/styles.css'));
$PAGE->activityheader->disable();
 
$renderer = $PAGE->get_renderer('mod_speval');
 
$start = optional_param('start', 0, PARAM_INT);
 
// Check if a final submission exists
$submission = $DB->record_exists('speval_eval', [
    'userid' => $USER->id,
    'spevalid' => $speval->id
]);
 
$studentHasGrade = $DB->record_exists('speval_grades', [
    'userid' => $USER->id,
    'spevalid' => $speval->id
]);
 
/**
 * Helper: read drafts for this user/activity into a prefill array.
 * Note: Must match fields saved in form_handler::save_draft
 */
function speval_load_prefill_from_drafts(\moodle_database $DB, int $spevalid, int $userid): array {
    $records = $DB->get_records('speval_draft', ['spevalid' => $spevalid, 'userid' => $userid]);
 
    $prefill = [
        'criteria1' => [], 'criteria2' => [], 'criteria3' => [],
        'criteria4' => [], 'criteria5' => [],
        'comment1'  => [], 'comment2'  => []
    ];
 
    foreach ($records as $rec) {
        $peerid = (int)$rec->peerid;
 
        for ($i = 1; $i <= 5; $i++) {
            $field = "criteria{$i}";
            if (property_exists($rec, $field) && $rec->{$field} !== null) {
                $prefill[$field][$peerid] = (int)$rec->{$field};
            }
        }
 
        // comment1
        if (isset($rec->comment1) && $rec->comment1 !== null) {
            $prefill['comment1'][$peerid] = (string)$rec->comment1;
        }
 
        // comment2
        if (isset($rec->comment2) && $rec->comment2 !== null) {
            $prefill['comment2'][$peerid] = (string)$rec->comment2;
        }
    }
 
    return $prefill;
}
 
/**
 * NEW HELPER: Generates a formatted string of the saved data for display.
 */
function speval_format_saved_data(int $userid, array $c_data, array $c1_data, array $c2_data, \moodle_database $DB): string {
    global $USER;
   
    $output = html_writer::start_div('speval-draft-content');
 
    // 1. Get all peer IDs involved in the save request
    $all_peerids = array_merge(array_keys($c1_data), array_keys($c2_data), array_keys($c_data));
    $peerids = array_unique($all_peerids);
 
    // 2. Loop through each peer's evaluation
    foreach ($peerids as $peerid) {
        $is_self = ($peerid == $USER->id);
        $peer_user = $is_self ? $USER : $DB->get_record('user', ['id' => $peerid]);
 
        if (!$peer_user) continue;
 
        $name = $is_self ? 'Self Evaluation' : fullname($peer_user);
        $output .= html_writer::tag('h4', 'Draft for: ' . $name);
       
        $output .= html_writer::start_ul();
       
        // Criteria
        for ($i = 1; $i <= 5; $i++) {
            $field = "criteria_text{$i}";
            if (isset($c_data[$field][$peerid])) {
                $output .= html_writer::tag('li', "Criteria {$i}: **{$c_data[$field][$peerid]}**");
            }
        }
 
        // Comment 1
        if (isset($c1_data[$peerid]) && !empty(trim($c1_data[$peerid]))) {
            $output .= html_writer::tag('li', 'Comment 1: ' . html_writer::tag('blockquote', s(trim($c1_data[$peerid]))));
        }
 
        // Comment 2 (Self only)
        if ($is_self && isset($c2_data[$peerid]) && !empty(trim($c2_data[$peerid]))) {
             $output .= html_writer::tag('li', 'Comment 2 (Self): ' . html_writer::tag('blockquote', s(trim($c2_data[$peerid]))));
        }
       
        $output .= html_writer::end_ul();
    }
   
    $output .= html_writer::end_div();
   
    // Add a final status message
    $output = html_writer::tag('p', 'Your draft has been successfully saved. You can continue or submit later.') . $output;
   
    return $output;
}
 
// Handle final submission BEFORE any output to allow redirect
if (!$submission && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $savedraft = optional_param('savedraft', 0, PARAM_INT);
    $savedraft_flag = optional_param('savedraft_flag', 0, PARAM_INT);
   
    // Only redirect for final submissions (not drafts)
    if ($savedraft_flag != 1 && $savedraft != 1) {
        form_handler::process_submission($course->id, $USER, $speval);
       
        // Redirect after successful submission (Post/Redirect/Get pattern)
        redirect(
            new moodle_url('/mod/speval/view.php', ['id' => $cm->id, 'submitted' => 1])
        );
    }
}
 
echo $OUTPUT->header();
 
// Show success notification if just submitted
$submitted = optional_param('submitted', 0, PARAM_INT);
if ($submitted == 1) {
    echo $OUTPUT->notification(
        get_string('submissionsuccess', 'mod_speval'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}
 
// --- Display open and close time information ---
$info = [];
 
if (!empty($speval->timeopen)) {
    $info[] = get_string('openson', 'mod_speval', userdate($speval->timeopen));
}
if (!empty($speval->timeclose)) {
    $info[] = get_string('closeson', 'mod_speval', userdate($speval->timeclose));
}
 
// If neither is set, just show "always open"
if (empty($info)) {
    $info[] = get_string('alwaysopen', 'mod_speval');
}
 
echo $OUTPUT->notification(implode('<br>', $info), 'info');
 
 
// Submission
if ($submission){
    echo $renderer->display_grade_for_student($USER, $speval, $cm);
 
} else {
    // Time
    $now = time();
 
    if (!empty($speval->timeopen) && $now < $speval->timeopen) {
        echo $OUTPUT->notification("This activity is not yet open.", 'warning');
        echo $OUTPUT->footer();
        return;
    }
 
    if (!empty($speval->timeclose) && $now > $speval->timeclose) {
        echo $OUTPUT->notification("This activiy already has been closed", 'warning');
        echo $OUTPUT->footer();
        return;
    }
 
 
    // Student needs to evaluate (either POSTing or displaying form)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
 
        // Check the 'savedraft' field, which is set to 1 by the JS when the draft button is pressed.
        $savedraft = optional_param('savedraft', 0, PARAM_INT);
        $savedraft_flag = optional_param('savedraft_flag', 0, PARAM_INT);
 
        if ($savedraft_flag == 1 || $savedraft == 1) {
            // 1. DRAFT SAVE PATH
           
            // Gather arrays for draft save (must include all potential fields)
            $c1_arr = optional_param_array('criteria_text1', [], PARAM_INT);
            $c2_arr = optional_param_array('criteria_text2', [], PARAM_INT);
            $c3_arr = optional_param_array('criteria_text3', [], PARAM_INT);
            $c4_arr = optional_param_array('criteria_text4', [], PARAM_INT);
            $c5_arr = optional_param_array('criteria_text5', [], PARAM_INT);
            $comments1 = optional_param_array('comment', [], PARAM_RAW);
            $comments2 = optional_param_array('comment2', [], PARAM_RAW);
 
            $ok = form_handler::save_draft($speval->id, $USER, $c1_arr, $c2_arr, $c3_arr, $c4_arr, $c5_arr, $comments1, $comments2);
           
            // Consolidated criteria data for easy display
            $criteria_data = [
                'criteria_text1' => $c1_arr, 'criteria_text2' => $c2_arr, 'criteria_text3' => $c3_arr,
                'criteria_text4' => $c4_arr, 'criteria_text5' => $c5_arr
            ];
           
            // --- MODIFIED NOTIFICATION SECTION ---
            if ($ok) {
                echo $OUTPUT->notification('Draft Saved.', \core\output\notification::NOTIFY_SUCCESS);
            } else {
                echo $OUTPUT->notification('Could not save draft. Please try again.', \core\output\notification::NOTIFY_ERROR);
            }
            // -------------------------------------
 
            // Re-render form with latest draft prefilled
            $studentsInGroup = util::get_students_in_same_groups($speval->id, $USER);
            if (empty($studentsInGroup)) {
                echo $renderer->no_peers_message();
            } else {
                $prefill = speval_load_prefill_from_drafts($DB, $speval->id, $USER->id);
                // Note: The draft notification is already displayed above, so we don't need draft_loaded_notification here.
                echo $renderer->evaluation_form($speval, $studentsInGroup, $cm, $prefill);
            }
 
        }
 
    } else if (!$start) {
        // Landing page (before starting the evaluation)
       
        echo $renderer->student_landing_page($cm, $speval);
 
    } else {
        // Display the form (prefill from existing drafts, if any)
        $studentsInGroup = util::get_students_in_same_groups($speval->id, $USER);
 
        if (empty($studentsInGroup)) {
            echo $renderer->no_peers_message();
        } else {
            $prefill = speval_load_prefill_from_drafts($DB, $speval->id, $USER->id);
            if (!empty($prefill['criteria1']) || !empty($prefill['comment1']) || !empty($prefill['comment2'])) {
                echo $renderer->draft_loaded_notification();
            }
            echo $renderer->evaluation_form($speval, $studentsInGroup, $cm, $prefill);
        }
    }
}
 
echo $OUTPUT->footer();