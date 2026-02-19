<?php
namespace mod_speval\local;

defined('MOODLE_INTERNAL') || die();

class ai_service {
    
    /**
     * Analyze all evaluations for an activity using AI
     * @param int $spevalid The SPEval activity ID
     * @return array Analysis results
     */
    public static function analyze_evaluations($spevalid, $evaluatorid = null) {
        global $DB;
        
        // Note: Grades should be calculated before calling this method
        // This check is now handled by the calling code

        // Get evaluations for this activity; optionally scope to one evaluator (userid)
        if ($evaluatorid) {
            $evaluations = $DB->get_records('speval_eval', ['spevalid' => $spevalid, 'userid' => $evaluatorid]);
        } else {
            $evaluations = $DB->get_records('speval_eval', ['spevalid' => $spevalid]);
        }
        
        if (empty($evaluations)) {
            return [];
        }
        
        // Prepare data for AI analysis
        $analysis_data = self::prepare_analysis_data($evaluations);
        
        // Call AI module
        $ai_result = self::call_ai_module($analysis_data);
        
        if ($ai_result && isset($ai_result['status']) && $ai_result['status'] === 'success' && !empty($ai_result['results'])) {
            return self::store_analysis_results($spevalid, $ai_result['results']);
        }
        
        return [];
    }
    
    /**
     * Prepare evaluation data for AI analysis
     * @param array $evaluations
     * @return array
     */
    private static function prepare_analysis_data($evaluations) {
        global $DB;
        $data = [];

        foreach ($evaluations as $eval) {
            $data[] = [
                'id' => $eval->id,
                'userid' => $eval->userid,
                'peerid' => $eval->peerid,
                'spevalid' => $eval->spevalid,
                'criteria1' => $eval->criteria1,
                'criteria2' => $eval->criteria2,
                'criteria3' => $eval->criteria3,
                'criteria4' => $eval->criteria4,
                'criteria5' => $eval->criteria5,
                'comment1' => $eval->comment1 ?? '',
                'comment2' => '', // ignore comment2 per requirements
                'timecreated' => $eval->timecreated
            ];
        }
        
        return $data;
    }
    
   /**
 * Call the AI module via HTTP API
 * @param array $data
 * @return array|null
 */
private static function call_ai_module($data) {
    // Get API URL from config or use default
    $api_url = get_config('mod_speval', 'ai_api_url');
    if (empty($api_url)) {
        $api_url = 'http://localhost:8000/analyze';
    }

    try {
        // Prepare JSON data
        $json_data = json_encode($data);

        // VERBOSE LOGGING (temporary)
        error_log('mod_speval: AI request → ' . $api_url);
        error_log('mod_speval: AI payload bytes → ' . strlen($json_data));
        // If payload size is safe for logs, uncomment to log the body:
        // error_log('mod_speval: AI payload body → ' . $json_data);

        // Set up HTTP context
        $options = [
            'http' => [
                'header' => [
                    "Content-Type: application/json",
                    "Content-Length: " . strlen($json_data)
                ],
                'method' => 'POST',
                'content' => $json_data,
                'timeout' => 30
            ]
        ];
        $context = stream_context_create($options);

        // Make the API call
        $result = @file_get_contents($api_url, false, $context);

        // Log HTTP status/headers if available
        if (isset($http_response_header) && is_array($http_response_header)) {
            error_log('mod_speval: AI response headers → ' . implode(' | ', $http_response_header));
        }

        if ($result === false) {
            error_log('mod_speval: AI call failed (file_get_contents returned false) at ' . $api_url);
            return null;
        }

        // Optionally log response body (comment in for deep debugging)
        error_log('mod_speval: AI response body → ' . $result);

        // Parse the response
        $response = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('mod_speval: Invalid JSON from AI API: ' . json_last_error_msg());
            return null;
        }

        return $response;

    } catch (\Exception $e) {
        error_log('mod_speval: Error calling AI API: ' . $e->getMessage());
        return null;
    }
}
    
    /**
     * Store AI analysis results in database
     * @param int $spevalid
     * @param array $results
     * @return array
     */
    private static function store_analysis_results($spevalid, $results) {
        global $DB;
        
        $stored_results = [];
        
        foreach ($results as $result) {
            if (isset($result['error'])) {
                continue; // Skip error results
            }
            // Get group information for this peer
            $group_info = self::get_peer_group_info($result['peer_id'], $spevalid);

            // Map AI result to individual flags record (use peerid consistently)
            $individual = [
                'userid' => $result['evaluator_id'],
                'peerid' => $result['peer_id'],
                'spevalid' => $spevalid,
                'grouping' => $group_info['groupingid'] ?? null,
                'groupid' => $group_info['groupid'] ?? null,
                'commentdiscrepancy' => $result['comment_discrepancy_detected'] ? 1 : 0,
                'markdiscrepancy' => $result['mark_discrepancy_detected'] ? 1 : 0,
                'misbehaviorcategory' => isset($result['misbehaviour_category_index']) ? (int)$result['misbehaviour_category_index'] : 1,
                'timecreated' => $result['analysis_timestamp']
            ];

            // Upsert per unique (userid, peerid, spevalid)
            $existing = $DB->get_record('speval_flag', [
                'userid' => $individual['userid'],
                'peerid' => $individual['peerid'],
                'spevalid' => $spevalid
            ]);

            if ($existing) {
                $individual['id'] = $existing->id;
                $DB->update_record('speval_flag', (object)$individual);
            } else {
                $individual['id'] = $DB->insert_record('speval_flag', (object)$individual);
            }

            $stored_results[] = $individual;
        }
        
        return $stored_results;
    }
    
    /**
     * Get group information for a peer
     * @param int $peerid
     * @param int $spevalid
     * @return array
     */
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
                    // Use reset() to safely retrieve the first element (handles associative arrays)
                    $first = reset($group_list);
                    if ($first !== false) {
                        $groupid = (int)$first;
                        $groupingid = (int)$grouping;
                        break;
                    }
                }
            }
        }
        
        return [
            'groupid' => $groupid,
            'groupingid' => $groupingid
        ];
    }
}