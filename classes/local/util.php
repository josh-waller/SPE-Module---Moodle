<?php
/*
    * Utility functions for the speval module.
    * All utility functions that don't fit elsewhere should go here.    
*/

namespace mod_speval\local;


defined('MOODLE_INTERNAL') || die();

class util {
    public const MAX_CRITERIA = 5;
    public const MAX_OPENQUESTION = 2;

    public static function get_students_in_same_groups($spevalid, \stdClass $user) {
        /* 
        * Get all students in the same groups as the given user within the given course.
        * If no groups exist in the course, or the user is not in any groups, return just the user.
        * 
        * @param int $spevalid The SPE activity id.
        * @param \stdClass $user The current user.
        * @return array User objects indexed by id.
         */
        global $DB;
        global $COURSE;

        $speval = $DB->get_record('speval', ['id' => $spevalid], '*', MUST_EXIST);                  // Get the SPEval activity record from the DB
        $studentids = [$user->id];                                                                  // Start with the user themselves

        // If linked to an assignment.
        if (!empty($speval->linkedassign)) {
            $assign = $DB->get_record('assign', ['id' => $speval->linkedassign]);                   // Get the linked assignment record

            $groupingid = $assign->teamsubmissiongroupingid ?? 0;                                   // Get the grouping id from assignment (0 if none)
            $groups = groups_get_user_groups($assign->course, $user->id);                           // Get all groups the user belongs to in this course
        } else {
            $groupingid = $speval->grouping ?? 0;                                                    // Get the grouping id from speval activity (0 if none)
            $groups = groups_get_user_groups($speval->course, $user->id);
        }

        // Defensive: ensure $groups[$groupingid] is an array before using array_values
        $groupids = [];
        if (isset($groups[$groupingid]) && is_array($groups[$groupingid])) {
            $groupids = array_values($groups[$groupingid]);
        }
        $groupid = $groupids[0] ?? null;
        $members = [];
        if (!empty($groupid)) {
            $members = groups_get_members($groupid, 'u.id', 'u.id');
            $studentids = array_unique(array_merge($studentids, array_keys($members)));
        }

        list($in_sql, $params) = $DB->get_in_or_equal($studentids);                                 // Get the SQL and params for all member ids

        return $DB->get_records_select(
            'user',
            "id $in_sql",
            $params,
            'lastname, firstname',
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
    }


    public static function get_criteria_data($speval) {
        /* 
        * Used by criteria.php 
        * This function creates a criteriaObject that has the following properties:
        * $criteriaObject->n_criteria
        * $criteriaObject->custom_criteria1         // The text written by the teacher stored in $customfield in criteria_form.php
        * $criteriaObject->custom_criteria2
        * $criteriaObject->custom_criteria3
        * $criteriaObject->custom_criteria4
        * $criteriaObject->custom_criteria5
        * $criteriaObject->predefined_criteria1     // The element chosen by the teacher stored in $fieldname in criteria_form.php 
        * $criteriaObject->predefined_criteria2
        * $criteriaObject->predefined_criteria3
        * $criteriaObject->predefined_criteria4
        * $criteriaObject->predefined_criteria5
        * $criteriaObject->criteria_text1           // The final text shown to studends
        * $criteriaObject->criteria_text2
        * $criteriaObject->criteria_text3
        * $criteriaObject->criteria_text4
        * $criteriaObject->criteria_text5
        * $criteriaObject->custom_openquestion1
        * $criteriaObject->custom_openquestion2
        * $criteriaObject->predefined_openquestion1
        * $criteriaObject->predefined_openquestion2
        * $criteriaObject->openquestion_text1            // The final text for the openquestion
        * $criteriaObject->openquestion_text2           // The final text for the second openquestion (meant to be only for self evaluation)
        */
        global $DB;

        // -------------------------------------------------------------------------------------------------------------------------------
        // Closed criteria questions
        $criteriaRecords = $DB->get_records('speval_criteria', ['spevalid' => $speval->id], 'sortorder ASC');
        $BankRecords = $DB->get_records_menu('speval_bank', null, '', 'id, questiontext');

        $criteriaObject = new \stdClass();

        $i = 0;


        foreach ($criteriaRecords as $criteria) {
            $i++;

            // If questiontext not empty in the DB, store this value in the property {"custom_criteria{$i}"}
            if (!empty($criteria->questiontext)){
                $criteriaObject->{"custom_criteria{$i}"} = $criteria->questiontext;
                $criteriaObject->{"criteria_text{$i}"} = $criteria->questiontext;

            // If questionbankid not NULL and not 0 in the DB, store this value in the property {"predefined_criteria{$i}"}
            } else if  (!empty($criteria->questionbankid)){
                $criteriaObject->{"predefined_criteria{$i}"} = $criteria->questionbankid ?? 0;
                $criteriaObject->{"criteria_text{$i}"} = $BankRecords[$criteria->questionbankid] ?? '';
            
            // If both questiontext and questionbankid have empty values, set a default for criteriaObject
            } else {
                $criteriaObject->{"criteria_text{$i}"} = "";
            }            
        }

        while ($i < self::MAX_CRITERIA){
            $i++;
            $criteriaObject->{"predefined_criteria{$i}"} = NULL;
            $criteriaObject->{"custom_criteria{$i}"} = NULL;
            $criteriaObject->{"criteria_text{$i}"} = "";
        }

        $criteriaObject->n_criteria = $i;

        // -------------------------------------------------------------------------------------------------------------------------------
        // Open questions
        $openquestionsRecords = $DB->get_records('speval_openquestion', ['spevalid' => $speval->id], 'sortorder ASC');

        $j = 0;
        foreach ($openquestionsRecords as $question) {
            $j++;

            // If questiontext not empty in the DB, store this value in the property {"openquestion{$i}"}
            if (!empty($question->questiontext)){
                $criteriaObject->{"custom_openquestion{$j}"} = $question->questiontext;
                $criteriaObject->{"openquestion_text{$j}"} = $question->questiontext;
            

            // If questionbankid not NULL and not 0 in the DB, store this value in the property {"predefined_criteria{$i}"}
            } else if  (!empty($question->questionbankid)){
                $criteriaObject->{"predefined_openquestion{$j}"} = $question->questionbankid ?? 0;
                $criteriaObject->{"openquestion_text{$j}"} = $BankRecords[$question->questionbankid] ?? '';
            } 
            
            // If both questiontext and questionbankid have empty values, set a default for criteriaObject
            else {
                $criteriaObject->{"openquestion_text{$j}"} = "";
            }
        }
    
        while ($j < self::MAX_OPENQUESTION){
            $j++;
            $criteriaObject->{"predefined_openquestion{$j}"} = NULL;
            $criteriaObject->{"custom_openquestion{$j}"} = NULL;
            $criteriaObject->{"openquestion_text{$j}"} = "";
        }

        $criteriaObject->n_openquestion = $j;

        return $criteriaObject;
}


    public static function save_criteria($spevalid, $data) {
        /* 
        * Used by criteria.php 
        */
        global $DB;
        for ($i = 1; $i <= self::MAX_CRITERIA; $i++) {
            $existing = $DB->get_record('speval_criteria', ['spevalid' => $spevalid, 'sortorder' => $i]);
            if (!$existing) {
                $newcriteria = new \stdClass();
                $newcriteria->spevalid = $spevalid;
                $newcriteria->sortorder = $i;

                if ($data->{"predefined_criteria$i"} > 0){                                      // The predefined is not "other"
                    $newcriteria->questiontext = NULL;
                    $newcriteria->questionbankid = $data->{"predefined_criteria$i"};
                } else {                                                                        // The predefined is "other"
                    $newcriteria->questiontext = $data->{"custom_criteria$i"};
                    $newcriteria->questionbankid = 0;
                }

                $DB->insert_record('speval_criteria', $newcriteria);

            } else {
                if ($data->{"predefined_criteria$i"} > 0){                                      // The predefined is not "other"
                    $existing->questiontext   = NULL;
                    $existing->questionbankid = $data->{"predefined_criteria$i"};
                } else {                                                                        // The predefined is "other"
                    $existing->questiontext   = $data->{"custom_criteria$i"};
                    $existing->questionbankid = 0;
                }

                $DB->update_record('speval_criteria', $existing);
            }
        }

        for ($i = 1; $i <= self::MAX_OPENQUESTION; $i++) {
            $existing = $DB->get_record('speval_openquestion', ['spevalid' => $spevalid, 'sortorder' => $i]);
            if (!$existing) {
                $newoq = new \stdClass();
                $newoq->spevalid = $spevalid;
                $newoq->sortorder = $i;
                
                if ($data->{"predefined_openquestion$i"} > 0){                                      // The predefined is not "other"
                    $newoq->questiontext = NULL;
                    $newoq->questionbankid = $data->{"predefined_openquestion$i"};
                } else {                                                                        // The predefined is "other"
                    $newoq->questiontext = $data->{"custom_openquestion$i"};
                    $newoq->questionbankid = 0;
                }    
                $DB->insert_record('speval_openquestion', $newoq);
            } else {

                if ($data->{"predefined_openquestion$i"} > 0){                                      // The predefined is not "other"
                    $existing->questiontext   = NULL;
                    $existing->questionbankid = $data->{"predefined_openquestion$i"};
                } else {                                                                        // The predefined is "other"
                    $existing->questiontext   = $data->{"custom_openquestion$i"};
                    $existing->questionbankid = 0;
                }

                // var_dump($existing);
                // exit;                
                $DB->update_record('speval_openquestion', $existing);

            }
        }
    }
}