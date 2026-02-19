<?php
/*
    * Form handler for processing self and peer evaluation submissions.
    * This class contains methods to handle form submissions, validate data,
    * and insert evaluation records into the database.
*/
namespace mod_speval\local;

defined('MOODLE_INTERNAL') || die();

class form_handler {
    public static function process_submission($courseid, $user, $speval) {
        /* * Process the form submission for self and peer evaluations.
        * Inserts evaluation records into the database table speval_eval.
        * * @param int $courseid The course ID
        * @param stdClass $user The user object of the evaluator
        * @param stdClass $speval The speval activity object
        * @return void
        */
        global $DB, $CFG;

        // Safely get arrays
        $c1 = optional_param_array('criteria_text1', [], PARAM_INT);
        $c2 = optional_param_array('criteria_text2', [], PARAM_INT);
        $c3 = optional_param_array('criteria_text3', [], PARAM_INT);
        $c4 = optional_param_array('criteria_text4', [], PARAM_INT);
        $c5 = optional_param_array('criteria_text5', [], PARAM_INT);
        $comments = optional_param_array('comment', [], PARAM_RAW);
        $comment2 = optional_param_array('comment2', [], PARAM_RAW);

        // --- 1. Get Start Time and Calculate Quick Submission Flag ---
        $starttime = required_param('starttime', PARAM_INT);
        $endtime = time();
        $duration_seconds = $endtime - $starttime;
        $MIN_DURATION_SECONDS = 180; // 3 minutes

        $quick_submission_flag = 0;
        if ($duration_seconds < $MIN_DURATION_SECONDS) {
            $quick_submission_flag = 1;
        }
        // ------------------------------------------------------------

        // Only process if at least criteria 1 was submitted.
        if (empty($c1)) {
            return false; // nothing to process
        }

        $allcriteria = [
            'criteria1' => $c1, 
            'criteria2' => $c2, 
            'criteria3' => $c3, 
            'criteria4' => $c4, 
            'criteria5' => $c5
        ];

        $peerids = array_keys($c1);

        global $DB;
        
        // --- CLEANUP DRAFT BEFORE SUBMISSION ---
        // Since the user is submitting, delete any existing draft for this evaluation.
        foreach ($peerids as $peerid) {
            $DB->delete_records('speval_draft', [
                'spevalid' => $speval->id,
                'userid' => $user->id,
                'peerid' => $peerid
            ]);
        }
        // ---------------------------------------

        // Insert new evaluations
        foreach ($peerids as $peerid) {
            // 1. Save Evaluation Record (Final Submission)
            $record = (object)[
                'spevalid'  => $speval->id,
                'userid'      => $user->id,
                'peerid'      => $peerid,
                'comment1'    => $comments[$peerid] ?? '',
                'comment2'    => $comment2[$peerid] ?? '',
                'timecreated' => $endtime, // FIX: Use $endtime for consistency
            ];

            foreach($allcriteria as $fieldname => $values) {
                $record->{$fieldname} = $values[$peerid] ?? 0; // Default to 0 if not set
            }

            $DB->insert_record('speval_eval', $record);

            // 2. Save/Update Flag Record
            $group_info = self::get_peer_group_info($peerid, $speval->id); 
            
            $flag_record = (object)[
                'userid' => $user->id,
                'peerid' => $peerid,
                'spevalid' => $speval->id,
                'grouping' => $group_info['groupingid'] ?? 0, 
                'groupid' => $group_info['groupid'] ?? 0,    
                'commentdiscrepancy' => 0, 
                'markdiscrepancy' => 0,    
                'quicksubmissiondiscrepancy' => $quick_submission_flag, 
                'misbehaviorcategory' => 1, 
                'timecreated' => $endtime
            ];

            // Check if a flag record already exists (Upsert logic)
            $existing_flag = $DB->get_record('speval_flag', [
                'userid' => $user->id,
                'peerid' => $peerid,
                'spevalid' => $speval->id
            ]);

            if ($existing_flag) {
                // If it exists, update only the quicksubmissiondiscrepancy field
                $update_data = (object)[
                    'id' => $existing_flag->id, 
                    'quicksubmissiondiscrepancy' => $quick_submission_flag,
                    'timecreated' => $endtime // Update timecreated to reflect last change
                ];
                $DB->update_record('speval_flag', $update_data);
            } else {
                // Insert a new record
                $DB->insert_record('speval_flag', $flag_record);
            }
        }

        // Calculate grades first, then trigger AI analysis
        // self::calculate_grades_and_trigger_ai($speval, $courseid);
        global $USER;
        try {
            \mod_speval\local\ai_service::analyze_evaluations($speval->id, $USER->id);
        } catch (\Throwable $syncErr) {
            // If synchronous call fails for any reason, fall back to an async adhoc task
            error_log('mod_speval: Synchronous AI analysis failed, queuing task. Error: ' . $syncErr->getMessage());
            self::trigger_ai_analysis($speval->id);
        }

        // Grade update logic could go in a separate grade_service class.
    }

    // =========================================================================
    // === NEW DRAFT SAVING FUNCTIONALITY ===
    // =========================================================================

    /**
     * Saves all evaluations as a draft to the speval_draft table.
     *
     * @param int $spevalid
     * @param \stdClass $user
     * @param array $c1_arr Criteria 1 ratings (peerid => value)
     * @param array $c2_arr Criteria 2 ratings (peerid => value)
     * @param array $c3_arr Criteria 3 ratings (peerid => value)
     * @param array $c4_arr Criteria 4 ratings (peerid => value)
     * @param array $c5_arr Criteria 5 ratings (peerid => value)
     * @param array $comments1 Comment 1 text (peerid => value)
     * @param array $comments2 Comment 2 text (peerid => value)
     * @return bool
     */
    public static function save_draft(int $spevalid, \stdClass $user, array $c1_arr, array $c2_arr, array $c3_arr, array $c4_arr, array $c5_arr, array $comments1, array $comments2): bool {
        global $DB;
        $success = true;

        // 1. Compile a master list of all peer IDs present in the submitted data
        $peerids = array_unique(array_merge(
            array_keys($c1_arr), array_keys($c2_arr), array_keys($c3_arr), 
            array_keys($c4_arr), array_keys($c5_arr), 
            array_keys($comments1), array_keys($comments2)
        ));

        $now = time();

        // 2. Iterate through each peer evaluation submitted

        foreach ($peerids as $peerid) {

            $peerid = (int)$peerid;
            
            // Check if a draft record already exists for this user-peer pair
            $draft = $DB->get_record('speval_draft', ['spevalid' => $spevalid, 'userid' => $user->id, 'peerid' => $peerid]);
            
            // Define data to be saved/updated
            $record = new \stdClass();
            $record->spevalid = $spevalid;
            $record->userid = $user->id;
            $record->peerid = $peerid;
            $record->timemodified = $now;
            
            // --- FIX FOR NULL CRITERIA ---
            // Use array_key_exists() and coalesce operator (??) to ensure a non-NULL integer (0) 
            // is set if the key is missing in the submitted data.
            $record->criteria1 = $c1_arr[$peerid] ?? 0;
            $record->criteria2 = $c2_arr[$peerid] ?? 0;
            $record->criteria3 = $c3_arr[$peerid] ?? 0;
            $record->criteria4 = $c4_arr[$peerid] ?? 0; // <<-- FIX
            $record->criteria5 = $c5_arr[$peerid] ?? 0;
            
            // Comments can be empty string, which is fine
            $record->comment1 = $comments1[$peerid] ?? '';

            // Comment 2 is only for self-evaluation (peerid == userid)
            if ($peerid == $user->id) {
                 $record->comment2 = $comments2[$peerid] ?? '';
            } else {
                // Important: Ensure comment2 is NOT set for peer evaluations if your DB allows NULL
                // If your DB mandates a value (like criteria4), you must set it to a non-NULL default here too.
                // Assuming you've already handled the column setup in the install.xml to allow NULL for comment2/peerid.
                // If comment2 is MANDATORY (NOT NULL) for all, you must set $record->comment2 = '' or 0 here.
            }
            // -----------------------------

            if ($draft) {
                // Update existing draft
                $record->id = $draft->id;
                $update_ok = $DB->update_record('speval_draft', $record);
                if (!$update_ok) {
                    $success = false;
                }
            } else {
                // Insert new draft
                $record->timecreated = $now;
                $insert_id = $DB->insert_record('speval_draft', $record, true);
                if (!$insert_id) {
                    $success = false;
                }
            }
        }

        return $success;
    }
    
    // =========================================================================
    // === HELPER FUNCTIONS ===
    // =========================================================================

    /**
     * Calculate grades and trigger AI analysis
     * @param object $speval The speval activity object
     * @param int $courseid The course ID
     */
    private static function trigger_ai_analysis_only($spevalid) {
        global $USER;
        try {
            \mod_speval\local\ai_service::analyze_evaluations($spevalid, $USER->id);
        } catch (\Throwable $e) {
            error_log('mod_speval: AI analysis failed: ' . $e->getMessage());
            self::trigger_ai_analysis($spevalid);
        }
    }

    /**
    * Trigger AI analysis for new submissions
    * @param int $spevalid
    */
    private static function trigger_ai_analysis($spevalid) {
        try {
            // Try to queue AI analysis task
            $task = new \mod_speval\task\ai_analysis_task();
            // Scope to current evaluator (the logged-in user), so we only analyze their batch
            global $USER;
            $task->set_custom_data(['spevalid' => $spevalid, 'evaluatorid' => $USER->id]);
            \core\task\manager::queue_adhoc_task($task);
            // Silently queued; avoid emitting browser output
        } catch (\Exception $e) { // FIX: Use \Exception
            // Log to server error log instead of browser
            error_log('mod_speval: Failed to queue AI analysis task: ' . $e->getMessage());
            
            // Fallback: Run AI analysis directly (synchronous)
            // Silent fallback run
            try {
                global $USER;
                $results = \mod_speval\local\ai_service::analyze_evaluations($spevalid, $USER->id);
            } catch (\Exception $ai_error) { // FIX: Use \Exception
                error_log('mod_speval: Synchronous AI analysis failed: ' . $ai_error->getMessage());
            }
        }
    }
    
    private static function get_peer_group_info($peerid, $spevalid) {
        global $DB;
        
        // Get the course from the activity
        $speval = $DB->get_record('speval', ['id' => $spevalid]);
        if (!$speval) {
            return ['groupid' => 0, 'groupingid' => 0];
        }
        
        $groups = groups_get_user_groups($speval->course, $peerid);
        
        $groupid = 0;
        $groupingid = 0;
        if (!empty($groups)) {
            foreach ($groups as $grouping => $group_list) {
                if (!empty($group_list)) {
                    // FIX: Use reset() to safely get the first element of the associative array
                    $groupid = reset($group_list); 
                    $groupingid = $grouping;
                    break;
                }
            }
        }
        
        return [
            'groupid' => $groupid,
            'groupingid' => $groupingid
        ];
    }
}