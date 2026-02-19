<?php
/*
    * TODO: Document purpose of file
*/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_login();


$id = required_param('id', PARAM_INT); // Course module ID.
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);

require_capability('mod/speval:manage', $context);

// Setup page.
$PAGE->set_url(new moodle_url('/mod/speval/edit.php', ['id' => $id]));
$PAGE->set_title(get_string('editquestions', 'mod_speval'));
$PAGE->set_heading($course->fullname);

// Navigation tabs for teachers only
// if (has_capability('mod/speval:manage', $context)) {
$tabs = [
    new tabobject('spe', new moodle_url('/mod/speval/view.php', ['id' => $cm->id]), get_string('pluginname', 'mod_speval')),
    new tabobject('settings', new moodle_url('/mod/speval/edit.php', ['id' => $cm->id]), get_string('settings')),
    new tabobject('addquestion', new moodle_url('/mod/speval/addquestion.php', ['id' => $cm->id]), get_string('addquestion', 'mod_speval')),
    new tabobject('results', new moodle_url('/mod/speval/results.php', ['id' => $cm->id]), get_string('results', 'mod_speval')),
    new tabobject('more', '#', get_string('more')), // Placeholder for dropdown
];
$selected = 'settings';
echo print_tabs([$tabs], $selected, null, null, true);
// }

// Define the form for editing evaluation questions.
class speval_edit_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Allow teacher to define up to 5 questions (you can raise this).
        for ($i = 1; $i <= 5; $i++) {
            $mform->addElement('text', 'question' . $i, get_string('questionlabel', 'mod_speval', $i));
            $mform->setType('question' . $i, PARAM_TEXT);
        }

        // Checkbox: enable self / peer evaluation.
        $mform->addElement('advcheckbox', 'enableself', get_string('enableself', 'mod_speval'));
        $mform->setDefault('enableself', 1);

        $mform->addElement('advcheckbox', 'enablepeer', get_string('enablepeer', 'mod_speval'));
        $mform->setDefault('enablepeer', 1);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

// Get existing config (from DB).
$instance = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);

$form = new speval_edit_form(null, ['instance' => $instance]);

// If form was submitted.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    // Save back into DB.
    $update = new stdClass();
    $update->id = $instance->id;

    for ($i = 1; $i <= 5; $i++) {
        $field = 'question' . $i;
        $update->$field = $data->$field;
    }
    $update->enableself = $data->enableself;
    $update->enablepeer = $data->enablepeer;

    $DB->update_record('speval', $update);

    redirect(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]),
        get_string('changessaved'));
}

// Output.
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
