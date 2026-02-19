<?php
require('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.
$course = get_course($id);
require_login($course);

$activities = get_all_instances_in_course('speval', $course);
echo $OUTPUT->header();
echo $OUTPUT->heading("All SPEval activities");

foreach ($activities as $a) {
    echo html_writer::link(
        new moodle_url('/mod/speval/view.php', ['id' => $a->coursemodule]),
        $a->name
    ) . "<br>";
}

echo $OUTPUT->footer();