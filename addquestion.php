<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT); // Course module ID.
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/speval:manage', $context);

$PAGE->set_url(new moodle_url('/mod/speval/addquestion.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('addquestion', 'mod_speval'));
$PAGE->set_heading(get_string('addquestion', 'mod_speval'));


// Navigation tabs
// if (has_capability('mod/speval:manage', $context)) {
$tabs = [
	new tabobject('spe', new moodle_url('/mod/speval/view.php', ['id' => $cm->id]), get_string('pluginname', 'mod_speval')),
	new tabobject('settings', new moodle_url('/mod/speval/edit.php', ['id' => $cm->id]), get_string('settings')),
	new tabobject('addquestion', new moodle_url('/mod/speval/addquestion.php', ['id' => $cm->id]), get_string('addquestion', 'mod_speval')),
	new tabobject('results', new moodle_url('/mod/speval/results.php', ['id' => $cm->id]), get_string('results', 'mod_speval')),
	new tabobject('more', '#', get_string('more')), // Placeholder for dropdown
];
$selected = 'addquestion';
echo print_tabs([$tabs], $selected, null, null, true);
// }

echo $OUTPUT->header();
echo '<h2>' . get_string('addquestion', 'mod_speval') . '</h2>';
// ...existing code for add question form...
echo $OUTPUT->footer();
