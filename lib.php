<?php
// Add a navigation tab for 'Add Question' in the activity navigation
function speval_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE: return MOD_ARCHETYPE_OTHER;
        case FEATURE_BACKUP_MOODLE2: return true;
        default: return null;
    }
}


function speval_add_instance(stdClass $speval, mod_speval_mod_form $mform = null) {
    global $DB;

    $speval->timemodified = time();

    // Ensure mutual exclusivity
    if ($speval->linkoption == 0) { // Standalone mode
        $speval->linkedassign = null;
    } else if ($speval->linkoption == 1) { // Linked to assignment
        $speval->grouping = null;
    }
    
    $id = $DB->insert_record('speval', $speval);
    return $id;
}


function speval_delete_instance($id) {
    die("SPEVAL DELETE INSTANCE CALLED with id=$id");
    global $DB;

    $DB->delete_records('speval', ['id' => $id]);
    $DB->delete_records('speval_eval', ['spevalid' => $id]);
    $DB->delete_records('speval_grades', ['spevalid' => $id]);
    $DB->delete_records('speval_flag', ['spevalid' => $id]);

    return true;
}


function speval_update_instance(stdClass $speval, mod_speval_mod_form $mform = null) {
    global $DB;

    $speval->timemodified = time();
    $speval->id = $speval->instance;
    
    // Ensure mutual exclusivity
    if ($speval->linkoption == 0) { // Standalone mode
        $speval->linkedassign = null;
    } else if ($speval->linkoption == 1) { // Linked to assignment
        $speval->grouping = null;
    }

    return $DB->update_record('speval', $speval);
}



function speval_extend_settings_navigation(settings_navigation $settings, navigation_node $spevalnode) {
    global $PAGE;
    global $USER;
    global $COURSE;


    // Only add tabs if the activity node exists
    if (!empty($spevalnode)) {

        // Ensure only teachers can see this page (Stuednts should not see tabs. There are no moodle activity that allow this).
        if (has_capability('mod/speval:addinstance', $PAGE->cm->context)) {              
            
            // Add a 'Results' tab
            $resultsUrl = new moodle_url('/mod/speval/results.php', ['id' => $PAGE->cm->id]);   
            $spevalnode->add(get_string('results', 'speval'), $resultsUrl, navigation_node::TYPE_SETTING, null, 'spevalresults');

            // Add a 'Criteria' tab
            $criteriaUrl = new moodle_url('/mod/speval/criteria.php', ['id' => $PAGE->cm->id]);
            $spevalnode->add(get_string('criteria', 'mod_speval'), $criteriaUrl, navigation_node::TYPE_SETTING, null, 'spevalcriteria');

            // Add a new 'Progress' tab
            $progressUrl = new moodle_url('/mod/speval/progress.php', ['id' => $PAGE->cm->id]);
            $spevalnode->add(get_string('progress', 'mod_speval'), $progressUrl, navigation_node::TYPE_SETTING, null, 'spevalprogress');

            // Add a 'Question bank' tab
            $questionbankUrl = new moodle_url('/mod/speval/question_bank.php', ['id' => $PAGE->cm->id, 'courseid' => $COURSE->id]);
            $spevalnode->add('Question Bank', $questionbankUrl, navigation_node::TYPE_SETTING, null, 'spevalquestionbank');
        }
    }
}