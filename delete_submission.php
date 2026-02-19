<?php
// /mod/speval/delete_submission.php
require_once('../../config.php');

global $DB, $OUTPUT;

// 1. Get required parameters
$id = required_param('id', PARAM_INT);       // course module id
$userid = required_param('userid', PARAM_INT); // ID of the user whose submission to delete
$confirm = optional_param('confirm', 0, PARAM_INT); // Simple confirmation flag

// 2. Load context and module
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// 3. Security: Check capability and sesskey
require_capability('mod/speval:addinstance', $context); // Only teachers (or those with addinstance) can delete
require_sesskey(); // Check for the security token

// 4. Perform Deletion
$deleted = $DB->delete_records('speval_eval', [
    'spevalid' => $cm->instance,
    'userid' => $userid
]);

$DB->delete_records('speval_flag', [
    'spevalid' => $cm->instance,
    'userid' => $userid
]);

$DB->delete_records('speval_grades', [
    'spevalid' => $cm->instance,
    'userid' => $userid
]);

// 5. Redirect and notify
if ($deleted) {
    // Notify success
    $message = get_string('deletedsub', 'speval', $userid); // Define this string in lang file
    \core\notification::success($message);
} else {
    // Notify failure (e.g., submission not found)
    \core\notification::error(get_string('notfoundsub', 'speval')); // Define this string in lang file
}

// Redirect back to the progress page
redirect(new moodle_url('/mod/speval/progress.php', ['id' => $id]));
?>