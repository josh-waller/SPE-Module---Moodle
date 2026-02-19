<?php
namespace mod_speval\local;


defined('MOODLE_INTERNAL') || die();

class grade_service {
    public static function calculate_spe_grade($cm, $courseid) {
    global $DB;

    // 1. Get all submissions for this activity
    $submissions = $DB->get_records('speval_eval', ['spevalid' => $cm->instance]);

    if (!$submissions) {
        // Ensure a grades row exists for every enrolled student with 0s
        error_log('mod_speval: No submissions found for spevalid '.$cm->instance);

        $enrolled_students = $DB->get_records_sql("
            SELECT u.id
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid = :courseid
        ", ['courseid' => $courseid]);

        foreach ($enrolled_students as $student) {
            $DB->delete_records('speval_grades', ['spevalid' => $cm->instance, 'userid' => $student->id]);
            $DB->insert_record('speval_grades', [
                'userid' => $student->id, 'spevalid' => $cm->instance,
                'criteria1' => 0, 'criteria2' => 0, 'criteria3' => 0, 'criteria4' => 0, 'criteria5' => 0,
                'finalgrade' => 0
            ]);
        }
        return;
    }

    $processed_students = [];

    // 2. Aggregate grades per student (peerid)
    foreach ($submissions as $s) {
        $studentid = $s->userid;

        // Skip if already processed
        if (isset($processed_students[$studentid])) {
            continue;
        }

        // Initialize totals
        $totals = [0,0,0,0,0];
        $counts = [0,0,0,0,0];

        // Loop again to collect all peer evaluations for this student
        foreach ($submissions as $sub) {
            if ($sub->peerid == $studentid) {
                // Criteria 1-5
                for ($i=1; $i<=5; $i++) {
                    $field = "criteria$i";
                    if ($sub->$field !== null) {
                        $totals[$i-1] += $sub->$field;
                        $counts[$i-1]++;
                    }
                }
            }
        }

        // Compute average per criteria (skip nulls)
        $avg = [];
        $sum_total = 0;
        $sum_count = 0;
        for ($i=0; $i<5; $i++) {
            $avg[$i] = $counts[$i] > 0 ? $totals[$i]/$counts[$i] : 0;
            $sum_total += $totals[$i];
            $sum_count += $counts[$i];
        }

        $finalgrade = $sum_count > 0 ? $sum_total / $sum_count : 0;

        // 3. Insert/update grade
        $DB->delete_records('speval_grades', [
            'spevalid' => $cm->instance,
            'userid' => $studentid
        ]);

        $DB->insert_record('speval_grades', [
            'userid'     => $studentid,
            'spevalid' => $cm->instance,
            'criteria1'  => $avg[0],
            'criteria2'  => $avg[1],
            'criteria3'  => $avg[2],
            'criteria4'  => $avg[3],
            'criteria5'  => $avg[4],
            'finalgrade' => $finalgrade
        ]);

        $processed_students[$studentid] = true;
    }

    // 4. Assign 0 to students who did not receive any peer evaluations
    $enrolled_students = $DB->get_records_sql("
        SELECT u.id
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        WHERE e.courseid = :courseid
    ", ['courseid' => $courseid]);

    foreach ($enrolled_students as $student) {
        if (!isset($processed_students[$student->id])) {
            $DB->delete_records('speval_grades', [
                'spevalid' => $cm->instance,
                'userid' => $student->id
            ]);
            $DB->insert_record('speval_grades', [
                'userid'     => $student->id,
                'spevalid' => $cm->instance,
                'criteria1'  => 0,
                'criteria2'  => 0,
                'criteria3'  => 0,
                'criteria4'  => 0,
                'criteria5'  => 0,
                'finalgrade' => 0
            ]);
        }
    }
}

 /**
     * Calculate grades and run AI analysis
     * @param object $cm Course module
     * @param int $courseid Course ID
     * @return array AI analysis results
     */
    public static function calculate_spe_grade_with_ai($cm, $courseid) {
        // Calculate grades first
        self::calculate_spe_grade($cm, $courseid);
        
       // Run AI analysis only if grades exist for this activity
        $ai_results = [];
        global $DB;
        if ($DB->record_exists('speval_grades', ['spevalid' => $cm->instance])) {
            try {
                $ai_results = \mod_speval\local\ai_service::analyze_evaluations($cm->instance);
            } catch (\moodle_exception $e) {
                error_log('mod_speval: AI analysis skipped: ' . $e->getMessage());
                $ai_results = [];
            }
        }
        return $ai_results;
    }

}
