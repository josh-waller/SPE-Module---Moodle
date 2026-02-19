<?php
/*
 * Renderer for the speval module.
 * All HTML generation functions should be placed here.
 */

namespace mod_speval\output;

defined('MOODLE_INTERNAL') || die();

use mod_speval\local\util;
use \plugin_renderer_base;
use \moodle_url;
use \html_writer;

class renderer extends plugin_renderer_base {

    public function student_landing_page($cm, $speval){
        $html = $this->output->heading(format_string($cm->name));

        if (isset($speval->grade)) {
            $html .= html_writer::tag('p', '<b>Maximum grade:</b> ' . (float)$speval->grade);
        }

        $html .= html_writer::start_div('speval-container');
            $html .= html_writer::tag('h2', 'Self & Peer Evaluation');
            $html .= $this->output->box(format_module_intro('speval', $speval, $cm->id), 'generalbox');

            $starturl = new moodle_url('/mod/speval/view.php', ['id' => $cm->id, 'start' => 1]);
            $html .= html_writer::start_tag('form', ['method' => 'get', 'action' => $starturl, 'style' => 'display:inline-block;']);
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'start', 'value' => 1]);
            $html .= html_writer::tag('button', 'Start Self and Peer Evaluation', ['type' => 'submit', 'class' => 'spebutton']);
            $html .= html_writer::end_tag('form');
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render the evaluation form.
     *
     * @param \stdClass $speval
     * @param array     $studentsInGroup list of user records (peers + self)
     * @param array     $prefill associative arrays keyed by field => [peerid => value]
     * expected keys: criteria1..criteria5, comment1, comment2
     */
    public function evaluation_form($speval, $studentsInGroup, $cm, array $prefill = []) {
        global $USER;

        $html  = html_writer::start_div('speval-container');
        $html .= html_writer::tag('h2', 'Self & Peer Evaluation');
        $html .= $this->output->box(format_module_intro('speval', $speval, $cm->id), 'generalbox');


        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => '',
            'id'     => 'speval-form'
        ]);

        // Sesskey
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ]);

        // Optional spevalid (handy if needed later)
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'spevalid',
            'value' => $speval->id
        ]);

        // Start time (for quick-submission flagging)
        $starttime = time();
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'starttime',
            'value' => $starttime
        ]);

        
        // Show self evaluation first
        $html .= $this->peer_fields($speval, $USER, true, $prefill);

        foreach ($studentsInGroup as $student) {
            $is_self = ($student->id == $USER->id);
            if (!$is_self){
                $html .= $this->peer_fields($speval, $student, $is_self, $prefill);
            }
        }

        // Small note + action buttons
        $html .= html_writer::div(
            html_writer::span('You can save a draft at any time and submit later.', 'speval-draft-note'),
            'speval-draft-help'
        );

        $html .= html_writer::start_div('form-actions');

        // Save draft: CHANGED TYPE TO 'button' and ADDED 'id' for JS handling
        $html .= html_writer::tag('button', 'Save draft', [
            'type' => 'button', 
            'name' => 'savedraft', // Retained for POST data identification
            'value' => '1',
            'id' => 'speval-draft-btn', 
            'class' => 'btn btn-secondary',
            'formnovalidate' => 'formnovalidate'
        ]);

        $html .= ' ';

        // Final submit
        $html .= html_writer::tag('button', 'Submit All Evaluations', [
            'type' => 'submit',
            'id' => 'speval-submit-btn',
            'class' => 'btn btn-primary'
        ]);

        $html .= html_writer::end_div(); // .form-actions

        $html .= html_writer::end_tag('form');

        $html .= $this->add_comment_validation_js();
        $html .= html_writer::end_div(); // .speval-container

        return $html;
    }

    /*
    * Renders a form for a single peer (or self).
    *
    * @param \stdClass $speval
    * @param \stdClass $student user record
    * @param bool      $is_self boolean, true if this block is for the current user evaluating themself.
    * @param array     $prefill array of previously saved values (criteria1..5, comment1, comment2)
    */
    public function peer_fields($speval, $student, $is_self = false, array $prefill = []) {
        $studentid = $student->id;
        $criteria_data = util::get_criteria_data($speval);

        // Display student name (if student is themselves, diplsay "Self Evaluation")
        $html = html_writer::empty_tag('hr');
        $html .= html_writer::start_tag('fieldset', ['class' => 'speval-peer', 'data-peerid' => $studentid]);
        $html .= html_writer::tag('legend', $is_self ? 'Self Evaluation' : s(fullname($student)), ['class' => 'peer-name']);

        // For now same number of criteria; if you later want asymmetry, adjust here.
        $num_questions = $criteria_data->n_criteria;

        // Loop through each criterion and display them
        for ($i = 1; $i <= $num_questions; $i++) {
            $criteriatext = $criteria_data->{"criteria_text$i"};
            $saved = isset($prefill["criteria{$i}"][$studentid]) ? (int)$prefill["criteria{$i}"][$studentid] : null;
            $html .= $this->criteria_row("criteria_text{$i}", $i . ". " . $criteriatext, $studentid, $saved);
        }

        // Open Question 1 (Comment 1)
        $openquestiontext = $criteria_data->{"openquestion_text1"};

        if ($is_self){
            $openquestiontext = str_replace('this person', 'you', $openquestiontext);
        }
            
            
        // Comment 1 Textarea (Mandatory for all evaluations)
        $saved1 = isset($prefill['comment1'][$studentid]) ? $prefill['comment1'][$studentid] : '';
        $html .= html_writer::start_div('form-row');
        $html .= html_writer::label($openquestiontext, "comment_{$studentid}", false, ['style' => 'font-weight: bold;']);
        $html .= html_writer::tag('textarea', s($saved1), [
            'name' => "comment[{$studentid}]",
            'id'   => "comment_{$studentid}",
            'rows' => 4,
            'cols' => 160
        ]);
        
        $html .= '<br>';
        $html .= '<br>';
        
        // Open Question 2 (Comment 2, for self evaluation only)
        if ($is_self){
            $openquestiontext2 = $criteria_data->{"openquestion_text2"};
            $saved2 = isset($prefill['comment2'][$studentid]) ? $prefill['comment2'][$studentid] : ''; 

            $html .= html_writer::label($openquestiontext2, "comment2_{$studentid}", false, ['style' => 'font-weight: bold;']);
            $html .= html_writer::tag('textarea', s($saved2), [ 
                'name' => "comment2[{$studentid}]",
                'id'   => "comment2_{$studentid}",
                'rows' => 4,
                'cols' => 160
            ]);
        }

        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('fieldset');
        return $html;
    }

    /**
     * Criteria radio row with optional preselected value.
     *
     * @param string   $name        e.g., 'criteria_text1'
     * @param string   $criteriaText
     * @param int      $studentid
     * @param int|null $selected    1..5 or null
     */
    private function criteria_row($name, $criteriaText, $studentid, $selected = null){
        $criteria_labels = [
            1 => 'Very Poor',
            2 => 'Poor',
            3 => 'Average',
            4 => 'Good',
            5 => 'Excellent'
        ];

        $html = html_writer::start_div('form-row');
        $html .= html_writer::label($criteriaText, "{$name}_{$studentid}", ["class" => "criteria-text"]);
        $html .= '<br>';
        $html .= html_writer::start_tag('span', ['id' => "{$name}_{$studentid}"]);

        foreach ($criteria_labels as $value => $text) {
            $attrs = [
                'type' => 'radio',
                'name' => "{$name}[{$studentid}]",
                'value' => $value,
                'required' => 'required'
            ];
            if ($selected !== null && (int)$selected === (int)$value) {
                $attrs['checked'] = 'checked';
            }
            $input = html_writer::empty_tag('input', $attrs);
            $html .= html_writer::tag('label', $input . ' ' . $text, ['class' => "scale-value"]);
        }

        $html .= html_writer::end_tag('span');
        $html .= html_writer::end_div();

        return $html;
    }

    public function no_peers_message() {
        return html_writer::tag('p', 'You are not in a group with other students. Please contact your unit coordinator.', ['class' => 'no-peers']);
    }

    public function submission_success_notification(){
        return $this->notification(
            "All evaluations submitted! Grades updated for assessed members.",
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    public function draft_loaded_notification(){
        return $this->notification(
            "Draft loaded from your previous save.",
            \core\output\notification::NOTIFY_INFO
        );
    }

    public function display_grade_for_student($user, $speval, $cm){
        global $DB;

        $html = html_writer::div(
            html_writer::tag('p', 'You have already submitted your evaluation. Thank you!', ['class' => 'alert alert-info'])
        );

        // Add intro description of the activity
        $html .= $this->output->box(format_module_intro('speval', $speval, $cm->id), 'generalbox');

        $html .= html_writer::tag('h3', 'Your Grade');

        $grade = $DB->get_record('speval_grades', [
            'userid' => $user->id,
            'spevalid' => $speval->id
        ]);

        if ($grade) {
            $html .= html_writer::tag('p', "Final Grade: $grade->finalgrade / 5.0");
        } else {
            $html .= html_writer::tag('p', "No grade available yet.");
        }

        return $html;
    }


/**
 * Adds JavaScript to handle comment validation for final submit and 
 * manual form submission for the 'Save draft' button.
 */
private function add_comment_validation_js() {
    return html_writer::tag('script', "
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('speval-form');
            const draftButton = document.getElementById('speval-draft-btn');
            if (!form || !draftButton) return;

            // 1. Create a hidden input to mimic 'savedraft' being set in the POST data
            // (We keep the original 'savedraft' input with value '1' from PHP for simplicity 
            // if we were to keep the button type='submit', but since it's now type='button', 
            // we'll rely on the existing name attribute being sent by the button itself 
            // OR use a hidden field to control the logic flow more clearly.)
            
            // Let's create a hidden input for control flow
            let savedraftInput = form.querySelector('input[name=\"savedraft_flag\"]');
            if (!savedraftInput) {
                savedraftInput = document.createElement('input');
                savedraftInput.type = 'hidden';
                savedraftInput.name = 'savedraft_flag';
                savedraftInput.value = '0';
                form.appendChild(savedraftInput);
            }

            // 2. Add click handler to the 'Save draft' button (now type='button')
            draftButton.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default button action
                
                // Temporarily set the hidden input value to trigger the draft saving logic in PHP
                savedraftInput.value = '1'; 
                
                // Ensure the 'savedraft' button's value is included in the POST data
                draftButton.name = 'savedraft';
                draftButton.value = '1';

                // Submit the form manually
                form.submit();
                
                // After submission, reset flag for next submit type
                savedraftInput.value = '0';
            });


            // --- FINAL SUBMISSION VALIDATION LOGIC ---

            // Attach validation to onsubmit (only runs for final submit)
            form.onsubmit = function(e) {
                
                // If the draft button was clicked and the hidden flag is set, skip validation
                if (savedraftInput.value === '1') {
                    // This case should be handled by the click handler above, 
                    // but we ensure no validation runs if the flag is still set.
                    return true;
                }
                
                // Original Validation logic (for final submit)
                const textareas = form.querySelectorAll('textarea');
                let valid = true;

                textareas.forEach(area => {
                    const words = area.value.trim().split(/\\s+/).filter(Boolean);
                    const existingWarning = area.nextElementSibling && area.nextElementSibling.classList.contains('word-warning')
                        ? area.nextElementSibling
                        : null;

                    if (existingWarning) existingWarning.remove();

                    // Check for required word count (20 words) for comments
                    if (area.name.startsWith('comment') && words.length < 20) {
                        valid = false;
                        area.style.border = '2px solid red';
                        const msg = document.createElement('div');
                        msg.textContent = 'Please write at least 20 words.';
                        msg.classList.add('word-warning');
                        msg.style.color = 'red';
                        msg.style.fontSize = '0.9em';
                        msg.style.marginTop = '4px';
                        area.insertAdjacentElement('afterend', msg);
                    } else {
                        area.style.border = '';
                    }
                });

                // Returning false prevents form submission
                if (!valid) {
                    e.preventDefault();
                }
                return valid;
            };
        });
    ");
}
}