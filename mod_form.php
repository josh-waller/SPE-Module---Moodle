<?php
/*
	* This file relates to the teacher settings tab for adding or modifying an SPE activity.
*/

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once(__DIR__ . '/../../config.php');

class mod_speval_mod_form extends moodleform_mod {
    public function definition() {
		global $USER; 
		global $COURSE;
		global $DB;

		$mform = $this->_form;
		$mform->setDefault('visible', 1);
		
		// -------------------------------------------------------------------------------
		// General
		$mform->addElement('header', 'general', 'General');

		$mform->addElement('text', 'name', get_string('spename', 'mod_speval'));
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', null, 'required', null, 'client');
			
		// Description field
		$this->standard_intro_elements();

		
		// -------------------------------------------------------------------------------
		// Linking
		$mform->addElement('header', 'linking', 'Activity Linking');
		// SPE link option: standalone or linked.
		$linkoptions = [
			0 => get_string('standalone', 'mod_speval'),
			1 => get_string('linktoassignment', 'mod_speval')
		];
		$mform->addElement('select', 'linkoption', get_string('linkoption', 'mod_speval'), $linkoptions);
		$mform->setType('linkoption', PARAM_INT);
		
		// If linked, select assignment from course.
		$assignments = $DB->get_records('assign', ['course' => $COURSE->id, 'teamsubmission' => 1]);
		$assignmentoptions = [0 => "select assignment"];
		foreach ($assignments as $assign) {
			if (!empty($assign->teamsubmissiongroupingid)){ // Do not show unless there is a grouping id set to such assignment
				$assignmentoptions[$assign->id] = format_string($assign->name); // Populate assignmentoptions
			}
		}

		// Linked assignment dropdown (hidden if standalone).
		$mform->addElement('select', 'linkedassign', get_string('linkedassign', 'mod_speval'), $assignmentoptions);
		$mform->setType('linkedassign', PARAM_INT);
		$mform->hideIf('linkedassign', 'linkoption', 'eq', 0);

		// If not linked, select grouping from course.
		$groupings = groups_get_all_groupings($COURSE->id);
		$groupingoptions = [0 => "select grouping"];
		
		foreach($groupings as $grouping){
			$groupingoptions[$grouping->id] = format_string($grouping->name);
		}

		// Linked directly to a grouping.
		$mform->addElement('select', 'grouping', 'Linked Grouping', $groupingoptions);
		$mform->setType('grouping', PARAM_INT);
		$mform->hideIf('grouping', 'linkoption', 'neq', 0);

		// Load settings from current instance
		if (!empty($this->current)) {
			if (!empty($this->current->linkedassign)) {
				$mform->setDefault('linkoption', 1);
				$mform->setDefault('linkedassign', $this->current->linkedassign);
			} else if (!empty($this->current->grouping)){
				$mform->setDefault('linkoption', 0);
				$mform->setDefault('grouping', $this->current->grouping);
			}
		}

		// -------------------------------------------------------------------------------
		// AutoGroup
		$mform->addElement('header', 'autogroup', 'Autogrouping');
		$mform->addElement('html',
			html_writer::link(
				new moodle_url('/mod/speval/groupimport/import.php', ['id' => $COURSE->id]),
				'Import Groups',
				['class' => 'btn btn-secondary',
 				'style' => '
				margin-top: 5px;
				margin-left: 28px;
				margin-bottom: 5px;'
				]
			)
		);

		// -------------------------------------------------------------------------------
		// Timing
		$mform->addElement('header', 'timing', 'Timing');
		$mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'mod_speval'), ['optional' => true]);
		$mform->setType('timeopen', PARAM_INT);
		$mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'mod_speval'), ['optional' => true]);
		$mform->setType('timeclose', PARAM_INT);
		$overdueoptions = [
			'prevent' => get_string('overdue_prevent', 'mod_speval'),
		];
		$mform->addElement('select', 'overduehandling', get_string('overduehandling', 'mod_speval'), $overdueoptions);
		$mform->setType('overduehandling', PARAM_ALPHANUMEXT);
		if (!empty($this->current)) {
			$mform->setDefault('timeopen', $this->current->timeopen ?? 0);
			$mform->setDefault('timeclose', $this->current->timeclose ?? 0);
			$mform->setDefault('overduehandling', $this->current->overduehandling ?? 'prevent');
		}

		// --------------------------------------------------------------------------------
		// Standard elements, common to all modules.
		$this->standard_coursemodule_elements();

		// Action buttons (Save/Cancel).
		$this->add_action_buttons();
	}


	public function validation($data, $files) {
        $errors = parent::validation($data, $files);

		// Obtain fields from the form
    	$linkoption = $data['linkoption'];

		if (isset($data['grouping'])){
			$grouping = $data['grouping'];
		}

		if (isset($data['linkedassign'])){
			$linkedassign = $data['linkedassign'];
		}
		
		// Prevent not linking a grouping
		if ($linkoption == 0 && $grouping == 0){
			$errors['grouping'] = get_string('mustselectgrouping', 'mod_speval');
		}
		
		// Prevent not linking an assignment
		if ($linkoption == 1 && $linkedassign == 0){
			$errors['linkedassign'] = get_string('mustselectassign', 'mod_speval');
		}

        return $errors;
    }


	public function data_preprocessing(&$default_values) {
		parent::data_preprocessing($default_values);

		// Only set a default for new instance - This autoloads the description.
		if (empty($this->current->instance)) {
			$default_values['introeditor']['text'] = get_string('defaultintro', 'mod_speval');
			$default_values['introeditor']['format'] = FORMAT_HTML;
		}
	}


	public function definition_after_data() {
		// This method is required by Moodle's form system
		// It's called after form data is processed
	}



	
}