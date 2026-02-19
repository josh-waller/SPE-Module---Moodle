<?php
require_once('../../config.php');
require_once($CFG->libdir . '/gradelib.php'); // Needed for grade_update()
require_sesskey();
global $DB;

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('speval', $cmid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// 1. Calculate and update internal speval_grades only (AI runs on submission, not here)
\mod_speval\local\grade_service::calculate_spe_grade($cm, $cm->course);

// 2. Fetch all grades for this activity
$grades = $DB->get_records('speval_grades', ['spevalid' => $cm->instance]);

// 3. Prepare gradebook structure
$grade_items = [];
foreach ($grades as $g) {
    $grade_items[$g->userid] = [
        'userid' => $g->userid,
        'rawgrade' => $g->finalgrade,
    ];
}



// 4. Publish to Moodle gradebook
$grade_update_result = grade_update(
    'mod/speval',              // component
    $cm->course,               // course id
    'mod',                     // itemtype
    'speval',                  // itemmodule (same as plugin name)
    $cm->instance,             // iteminstance (activity instance id)
    0,                         // itemnumber
    $grade_items               // actual grades
);

// 5. Redirect back with success or error message
$resultsurl = new moodle_url('/mod/speval/results.php', ['id' => $cmid]);

if ($grade_update_result === GRADE_UPDATE_OK) {
    redirect($resultsurl, get_string('gradesuccess', 'mod_speval'), 2, \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($resultsurl, 'Failed to publish grades to Moodle gradebook.', 3, \core\output\notification::NOTIFY_ERROR);
}
