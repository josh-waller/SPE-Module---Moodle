<?php
require_once('../../config.php');

$gradeid = required_param('gradeid', PARAM_INT);
$cmid = required_param('id', PARAM_INT);

$grade = $DB->get_record('speval_grades', ['id' => $gradeid], '*', MUST_EXIST);
$cm = get_coursemodule_from_id('speval', $cmid, 0, false, MUST_EXIST);

require_login();

// Set page context and URL
$context = context_module::instance($cm->id);
$PAGE->set_context($context);
$PAGE->set_url('/mod/speval/edit_grade.php', ['id'=>$cmid, 'gradeid'=>$gradeid]);
$PAGE->set_title(get_string('editgrade', 'mod_speval'));
$PAGE->set_heading(get_string('editgrade', 'mod_speval'));
$PAGE->set_cm($cm);

// Handle form submission
if (data_submitted() && confirm_sesskey()) {
    $grade->finalgrade = required_param('finalgrade', PARAM_FLOAT);

    $DB->update_record('speval_grades', $grade);

    redirect(new moodle_url('/mod/speval/view.php', ['id'=>$cmid]), get_string('gradesuccess','mod_speval'));
}

// Output the form
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editgrade', 'mod_speval'));

echo html_writer::start_tag('form', ['method'=>'post']);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'gradeid','value'=>$gradeid]);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'id','value'=>$cmid]);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);

// Get max grade from speval table
$speval = $DB->get_record('speval', ['id'=>$grade->spevalid]);
$maxgrade = isset($speval->grade) ? $speval->grade : 5; // fallback if not set

// Final grade input only, hide arrows
echo html_writer::label("Final Grade:", "finalgrade");
echo html_writer::empty_tag('input', [
    'type'  => 'number',
    'name'  => "finalgrade",
    'id'    => "finalgrade",
    'value' => $grade->finalgrade,
    'min'   => 0,
    'max'   => $maxgrade,
    'step'  => 0.01,
    'style' => '
        width:80px;
        -moz-appearance: textfield; /* Firefox */
    '
]);

// Additional CSS to hide arrows in Webkit browsers
echo html_writer::tag('style', '
    #finalgrade::-webkit-outer-spin-button,
    #finalgrade::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
');

echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', ['type'=>'submit','value'=>get_string('savechanges','moodle')]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
