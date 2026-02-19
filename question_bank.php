<?php
require_once('../../config.php');
require_once('question_bank_form.php');
require_once($CFG->libdir.'/tablelib.php');

global $DB;

// -----------------------------------------------------------------
// 1. Get course/module/context/plugin instance
$id = required_param('id', PARAM_INT); // course module id
$cm = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);
$courseid = $cm->course;
$context = context_module::instance($cm->id);
$course = get_course($courseid);
$PAGE->set_url(new moodle_url('/mod/speval/question_bank.php', ['id' => $cm->id]));

require_login($course, false, $cm);
require_capability('mod/speval:addinstance', $context);

// -----------------------------------------------------------------
// 2. Check for action parameter (delete)
$action = optional_param('action', '', PARAM_ALPHA);
$deleteid = optional_param('deleteid', 0, PARAM_INT);

if ($action === 'delete' && $deleteid) {
    // Check if the user confirmed deletion
    $confirm = optional_param('confirm', 0, PARAM_BOOL);

    if (!$confirm) {
        // First time: show confirmation message
        $question = $DB->get_record('speval_bank', ['id' => $deleteid], '*', MUST_EXIST);
        $message = get_string('confirmdeletequestion', 'mod_speval', format_string($question->questiontext));

        $PAGE->activityheader->disable();
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('deletequestion', 'mod_speval'));
        echo $OUTPUT->notification($message, 'warning');

        $yesurl = new moodle_url('/mod/speval/question_bank.php', [
            'id' => $id,
            'action' => 'delete',
            'deleteid' => $deleteid,
            'confirm' => 1,
            'sesskey' => sesskey()
        ]);
        $nourl = new moodle_url('/mod/speval/question_bank.php', ['id' => $id]);

        echo $OUTPUT->confirm(get_string('deletewarning', 'mod_speval'), $yesurl, $nourl);
        echo $OUTPUT->footer();
        exit;
    }

    // If confirmed, proceed to delete
    require_sesskey();
    $DB->delete_records('speval_bank', ['id' => $deleteid]);

    redirect(
        new moodle_url('/mod/speval/question_bank.php', ['id' => $id]),
        get_string('questiondeleted', 'mod_speval'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// -----------------------------------------------------------------
// // 4. Handle form submission
$form = new \mod_speval\form\question_bank_form(null, ['courseid' => $courseid, 'cmid' => $id]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));

} else if ($data = $form->get_data()) {
    global $DB, $USER;

    // If the user clicked the Import CSV button
    if (!empty($data->importcsv)) {

        // Retrieve the draft item ID directly from the submitted data
        $draftitemid = $data->importfile;

        // Access the file storage
        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);

        // Get uploaded files from user draft area
        $files = $fs->get_area_files($context->id, 'user', 'draft', $draftitemid, 'id DESC', false);

        if (empty($files)) {
            print_error('nocsvfile', 'mod_speval', '', null, 'No CSV file was uploaded.');
        }

        // Use the first file only
        $file = reset($files);
        $content = $file->get_content();

        // Parse CSV
        $lines = array_map('str_getcsv', explode("\n", trim($content)));
        if (empty($lines)) {
            print_error('invalidcsv', 'mod_speval', '', null, 'CSV file appears empty.');
        }

        $header = array_map('trim', $lines[0]);
        unset($lines[0]);

        foreach ($lines as $line) {
            if (count($line) < 2) continue;
            [$questiontext, $isopenquestion] = $line;
            if (empty(trim($questiontext))) continue;

            $record = new \stdClass();
            $record->questiontext = trim($questiontext);
            $record->isopenquestion = (int)trim($isopenquestion);
            $record->courseid = $courseid;

            $DB->insert_record('speval_bank', $record);
        }

        redirect(
            new \moodle_url('/mod/speval/question_bank.php', ['id' => $id]),
            get_string('csvimportsuccess', 'mod_speval'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else {
        // Normal question submission
        $record = new \stdClass();
        $record->questiontext = $data->questiontext;
        $record->isopenquestion = $data->isopenquestion;
        $record->courseid = $data->courseid;

        $DB->insert_record('speval_bank', $record);
        redirect(
            new moodle_url('/mod/speval/question_bank.php', ['id' => $id]),
            get_string('questionsaved', 'mod_speval'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}



// -----------------------------------------------------------------
// 3. Set up page
$PAGE->set_url('/mod/speval/question_bank.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('questionbank', 'mod_speval'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('questionbank', 'mod_speval'));



// -----------------------------------------------------------------
// 5. Display form
$form->display();

// -----------------------------------------------------------------
// 6. Display existing questions in a table
$questions = $DB->get_records('speval_bank', ['courseid' => $courseid], 'id ASC');

if ($questions) {
    echo $OUTPUT->heading('Question Bank');
    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');
    // echo html_writer::tag('th', 'ID');
    echo html_writer::tag('th', 'Question Text');
    echo html_writer::tag('th', 'Open Question?');
    echo html_writer::tag('th', 'Actions');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($questions as $q) {
        $deleteurl = new moodle_url('/mod/speval/question_bank.php', [
            'id' => $cm->id, 
            'action' => 'delete',
            'deleteid' => $q->id
        ]);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', format_string($q->questiontext));
        echo html_writer::tag('td', $q->isopenquestion ? 'Yes' : 'No');
        echo html_writer::tag('td', html_writer::link($deleteurl, get_string('delete', 'mod_speval')));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();