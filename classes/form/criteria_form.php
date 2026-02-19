<?php
namespace mod_speval\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class criteria_form extends \moodleform {

    public function definition() {
        global $DB;
        global $COURSE;
        $mform = $this->_form;

        // Get existing criteria from customdata -
        $criteriaData = $this->_customdata['criteriaData'] ?? null;             // criteriaData is obtained from section 5 of criteria.php
        $n_criteria = $criteriaData->n_criteria;                                   
        $n_openquestion = $criteriaData->n_openquestion;                                   

        // Get options from the bank once
        $closedquestionBankOptions = $this->get_closed_questions_bank_options($COURSE);
        $openquestionBankOptions = $this->get_open_questions_bank_options($COURSE);
        

        // ----------------------------------------------------------------------------------------------------------------------------------
        // Loop through criteria
        for ($i = 1; $i <= $n_criteria; $i++) {
            $fieldname = "predefined_criteria{$i}";
            $customfield = "custom_criteria{$i}";

            // Dropdown from bank
            $mform->addElement('select', $fieldname, get_string("criteria{$i}", 'mod_speval'), $closedquestionBankOptions);
            
            // Custom criteria
            $mform->addElement('text', $customfield, "   ", ['size' => 120]);
            $mform->setType($customfield, PARAM_TEXT);
            $mform->hideIf($customfield, $fieldname, 'neq', 0);

            // Pre-fill values if available
            if (!empty($criteriaData->{$fieldname})) {
                $mform->setDefault($fieldname, $criteriaData->{$fieldname});
            }
            
            if (!empty($criteriaData->$customfield)) {
                $mform->setDefault($customfield, $criteriaData->{$customfield});
            }

            $mform->addElement('html', '<hr style="width:100%; margin: 20px 0; border-top: 1px solid #4c41adff;">');
        }
        

        // ----------------------------------------------------------------------------------------------------------------------------------
        // Loop through open questions
        for ($j = 1; $j <= $n_openquestion; $j++) {
            $oq_fieldname = "predefined_openquestion{$j}";
            $oq_customfield = "custom_openquestion{$j}";

            // Dropdown from bank
            $mform->addElement('select', $oq_fieldname, "open question {$j}", $openquestionBankOptions);
            
            // Custom criteria
            $mform->addElement('text', $oq_customfield, "   ", ['size' => 120]);
            $mform->setType($oq_customfield, PARAM_TEXT);
            $mform->hideIf($oq_customfield, $oq_fieldname, 'neq', 0);

            // Pre-fill values if available
            if (!empty($criteriaData->{$oq_fieldname})) {
                $mform->setDefault($oq_fieldname, $criteriaData->{$oq_fieldname});
            }
            
            if (!empty($criteriaData->$oq_customfield)) {
                $mform->setDefault($oq_customfield, $criteriaData->{$oq_customfield});
            }

            $mform->addElement('html', '<hr style="width:100%; margin: 20px 0; border-top: 1px solid #4c41adff;">');
        }


        // Submit buttons
        $this->add_action_buttons(true, get_string('savechanges'));
    }


    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $seen = [];
        $n_criteria = $this->_customdata['criteriaData']->n_criteria ?? 1;

        for ($i = 1; $i <= $n_criteria; $i++) {
            $fieldname = "predefined_criteria{$i}";
            $value = $data[$fieldname] ?? 0;

            if ($value != 0) { // ignore "Other"
                if (in_array($value, $seen)) {
                    $errors[$fieldname] = get_string('duplicatecriteria', 'mod_speval');
                } else {
                    $seen[] = $value;
                }
            }
        }

        return $errors;
    }


    
    /**
     * Get options from criteria bank
     */
    protected function get_closed_questions_bank_options($course) {
        global $DB;
        
        $records = $DB->get_records('speval_bank', ['courseid' => $course->id, 'isopenquestion'=> 0], 'id ASC');
        $options = [0 => get_string('other', 'mod_speval')]; // default first option

        foreach ($records as $record) {
            $options[$record->id] = $record->questiontext;
        }
        
        return $options;
    }


    protected function get_open_questions_bank_options($course) {
        global $DB;
        
        $records = $DB->get_records('speval_bank', ['courseid' => $course->id, 'isopenquestion'=> 1], 'id ASC');
        $options = [0 => get_string('other', 'mod_speval')]; // default first option

        foreach ($records as $record) {
            $options[$record->id] = $record->questiontext;
        }
        
        return $options;
    }
}