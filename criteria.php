<?php

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 1. Load Moodle and necessary objects
require('../../config.php');
require_once($CFG->libdir.'/formslib.php');                                                 
use mod_speval\local\util;
use mod_speval\form\criteria_form;

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 2. Get course/module/context/plugin instance
$id      = required_param('id', PARAM_INT);                                     // Get the mdl_course_module id
$cm      = get_coursemodule_from_id('speval', $id, 0, false, MUST_EXIST);       // Load the course_module (activity instance wrapper) by id
$course  = get_course($cm->course);                                             // Load the course record from the DB   
$context = context_module::instance($cm->id);                                   // Get the context from the course_module
$speval = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);  // Get the speval instance record from the DB

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 3. Require login and capabilities
require_login($course, false, $cm);                                             // Ensure the user is logged in and has access to this course and activity
require_capability('mod/speval:addinstance', $context);                         // Ensure the user has permission to manage this activity

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 4. Set up page
$PAGE->set_url('/mod/speval/criteria.php', ['id' => $id]);                      // Set the URL for this page          
$PAGE->set_title(get_string('criteria', 'mod_speval'));                         // Set the page title
$PAGE->set_heading($course->fullname);                                          // Set the page heading                
$PAGE->activityheader->disable();                                               // Disable the standard activity header 

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 5. Get criteria and set up criteria form
$criteriaData = util::get_criteria_data($speval);

$mform = new criteria_form(
    new moodle_url('/mod/speval/criteria.php', ['id' => $id]),
    ['cmid' => $id, 'criteriaData' => $criteriaData]
);
$mform->set_data($criteriaData);                                                   // Pre-fill with existing data 


// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 6. Handle form submission / cancellation 
if ($mform->is_cancelled()) {                                                   // Form was cancelled
    redirect(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]));                    
} else if ($data = $mform->get_data()) {                                        // Criteria were submitted - changes are stored in $data
    util::save_criteria($speval->id, $data);

    redirect(new moodle_url('/mod/speval/view.php', ['id' => $cm->id]),
        get_string('criteriasaved', 'mod_speval'));
}

// -------------------------------------------------------------------------------------------------------------------------------------------------------
// 7. Output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('criteria', 'mod_speval'));
$mform->display();
echo $OUTPUT->footer();