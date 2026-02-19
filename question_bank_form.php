<?php
namespace mod_speval\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

class question_bank_form extends \moodleform {
    protected $courseid;
    protected $cmid;

    public function __construct($action = null, $customdata = null) {
        if (!empty($customdata['courseid'])) {
            $this->courseid = $customdata['courseid'];
        }
        
        if (!empty($customdata['cmid'])) {
            $this->cmid = $customdata['cmid'];
        }
        
        parent::__construct($action, $customdata);
    }

    public function definition() {
        $mform = $this->_form;

        // --- Manual question fields ---
        $mform->addElement('header', 'manualimport', 'Add a question manually');

        $mform->addElement('text', 'questiontext', get_string('questiontext', 'mod_speval'), ['size' => 120]);
        $mform->setType('questiontext', PARAM_TEXT);

        $mform->addElement('selectyesno', 'isopenquestion', get_string('openquestion', 'mod_speval'));
        $mform->setDefault('isopenquestion', 0);
        
        $mform->addElement('hidden', 'courseid', $this->courseid);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savequestion', 'mod_speval'));

        // --- CSV Import section ---
        $mform->addElement('header', 'autoimport', 'Add a questions with a CSV');
        $mform->addElement('html', '<hr style="width:100%; margin: 20px 0; border-top: 1px solid #4c41adff;">');
        $mform->addElement('filepicker', 'importfile', get_string('questionbankimport', 'mod_speval'), null, [
            'accepted_types' => ['.csv']
        ]);
        $mform->addElement('submit', 'importcsv', get_string('importcsvbutton', 'mod_speval'));

        if (!empty($this->cmid)) {
            $mform->addElement('hidden', 'id', $this->cmid);
            $mform->setType('id', PARAM_INT);
        }
    }

    /**
     * Custom validation logic: require either questiontext or CSV file.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // If "Import CSV" button was clicked, skip text validation.
        if (!empty($data['importcsv'])) {
            $draftid = file_get_submitted_draft_itemid('importfile');
            $fs = get_file_storage();
            $context = \context_user::instance($GLOBALS['USER']->id);
            $uploaded = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id', false);

            if (empty($uploaded)) {
                $errors['importfile'] = get_string('errornocsv', 'mod_speval');
            }

            return $errors;
        }

        // Otherwise (Save button), question text is required
        if (empty(trim($data['questiontext']))) {
            $errors['questiontext'] = get_string('required');
        }

        return $errors;
    }
}