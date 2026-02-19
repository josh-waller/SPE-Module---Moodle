<?php
require_once('../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT); // course module id
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$courseid = $cm->course;

// Set Moodle page properties
$PAGE->set_url('/mod/speval/progress.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->activityheader->disable();

// Only teachers for now
require_capability('mod/speval:addinstance', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading('Progress of Submission');

// Fetch the activity to get its grouping
$activity = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);
$groupingid = $activity->grouping;

// Fetch groups linked to this activity (grouping)
if ($groupingid) {
    $groups = groups_get_all_groups($courseid, 0, $groupingid);
} else if ($activity->linkedassign){
    $assignment = $DB->get_record('assign', ['id' => $activity->linkedassign, 'course' => $courseid], '*', MUST_EXIST);
    $groupingid = $assignment->teamsubmissiongroupingid;
    $groups = groups_get_all_groups($courseid, 0, $groupingid);
} else {
    $groups = groups_get_all_groups($courseid);
}

if (!$groups) {
    echo $OUTPUT->notification('No groups found for this activity.');
    echo $OUTPUT->footer();
    exit;
}

// Loop through each group
foreach ($groups as $group) {
    $students = groups_get_members($group->id, 'u.id, u.firstname, u.lastname');

    if (!$students) {
        continue;
    }

    $total = count($students);
    $submitted = 0;
    $status_list = [];

    foreach ($students as $student) {
        // Check if the student has any submissions for this activity
        $submissions = $DB->get_records('speval_eval', [
            'spevalid' => $cm->instance,
            'userid' => $student->id
        ]);

        $status_html = $student->firstname . ' ' . $student->lastname . ' - ';
        $delete_button = '';

        if ($submissions) {
            $submitted++;
            $status_html .= '<span style="color:green;">Submitted</span>';

            // --- ADD DELETE BUTTON LOGIC HERE ---
            // We use Moodle's sesskey for security and pass the module ID and the user ID
            $delete_url = new moodle_url(
                '/mod/speval/delete_submission.php', 
                [
                    'id'       => $id,         // Course Module ID
                    'userid'   => $student->id,
                    'sesskey'  => sesskey(),
                ]
            );

            // Create a small, danger-style button using html_writer
            $delete_button = html_writer::link(
                $delete_url, 
                get_string('delete', 'moodle'), // Use Moodle's standard 'Delete' string
                [
                    'class'   => 'btn btn-danger btn-sm ml-2', 
                    'onclick' => 'return confirm("' . get_string('deleteconfirm', 'speval') . '");' 
                ]
            );
            // Ensure the button is floated right or in a way that separates it visually
            $status_html .= $delete_button;

        } else {
            $status_html .= '<span style="color:red;">Not Submitted</span>';
        }
        
        $status_list[] = $status_html;
    }

    $percent = round(($submitted / $total) * 100);

    // Group name
    echo html_writer::tag('h3', $group->name);

    // Progress bar
    echo html_writer::start_div('', ['class' => 'progress', 'style' => 'height: 25px; margin-bottom:10px;']);
    echo html_writer::tag('div', $percent . '%', [
        'class' => 'progress-bar',
        'role' => 'progressbar',
        'style' => 'width:' . $percent . '%;',
        'aria-valuenow' => $percent,
        'aria-valuemin' => 0,
        'aria-valuemax' => 100
    ]);
    echo html_writer::end_div();

    // List students and their submission status
    echo html_writer::alist($status_list);
}

echo $OUTPUT->footer();
