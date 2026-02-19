<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$id = required_param('id', PARAM_INT);                                                  // Get the mdl_course_module id
list($course, $cm) = get_course_and_cm_from_cmid($id, 'speval');                        // Get the course and cm info from the id
$speval = $DB->get_record('speval', ['id' => $cm->instance], '*', MUST_EXIST);          // Get the speval instance record from the DB

$context = context_module::instance($cm->id);                                           // Get the context from the course module
require_capability('mod/speval:grade', $context);                                        // Ensure the user has permission to view this activity

// Correct PAGE setup
$PAGE->set_url(new moodle_url('/mod/speval/results.php', ['id' => $cm->id]));           // Set the URL for this page
$PAGE->set_cm($cm, $course);
$PAGE->set_context($context);
$PAGE->set_title(get_string('results', 'speval'));
$PAGE->set_heading($course->fullname);

// Output starts
$PAGE->activityheader->disable();
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('results', 'speval'));

// Student View was moved to view.php. By convention moodle does not show tabs for students. Access security is now set at line 10 of this file.

// Teacher/manager view

// Compute submission completeness for the Grade All button (left of Export CSV)
$activity = $speval;
$groupingid_btn = $activity->grouping;
if ($groupingid_btn) {
    $groups_btn = groups_get_all_groups($course->id, 0, $groupingid_btn);
} else {
    $groups_btn = groups_get_all_groups($course->id);
}
$missing_btn = [];
if (!empty($groups_btn)) {
    foreach ($groups_btn as $gbtn) {
        $students_btn = groups_get_members($gbtn->id, 'u.id, u.firstname, u.lastname, u.username');
        if (!$students_btn) { continue; }
        foreach ($students_btn as $sbtn) {
            if (!$DB->record_exists('speval_eval', ['spevalid' => $cm->instance, 'userid' => $sbtn->id])) {
                $missing_btn[] = trim($sbtn->firstname . ' ' . $sbtn->lastname) . ' (' . $sbtn->username . ')';
            }
        }
    }
}
$allsubmitted_btn = empty($missing_btn);

// Render Grade All first (left), with Moodle confirm when incomplete
if ($allsubmitted_btn) {
    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/grade_service.php', ['id' => $cm->id]),
        get_string('gradeall', 'speval'),
        'post'
    );
} else {
    $gradeurl = new moodle_url('/mod/speval/grade_service.php', ['id' => $cm->id]);
    echo html_writer::start_tag('form', ['id' => 'gradeallform', 'method' => 'post', 'action' => $gradeurl, 'style' => 'display:inline-block;margin-right:8px;']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::tag('button', get_string('gradeall', 'speval'), ['type' => 'submit', 'id' => 'gradeallbtn', 'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    $confirmmsg = 'Not everyone has submitted their evaluation. Check "Progress of Submission" to see who has not submitted. If you proceed, those students will be graded 0. Do you want to continue?';
    $js = "require(['core/notification'], function(notification) {\n  var btn = document.getElementById('gradeallbtn');\n  if (!btn) { return; }\n  btn.addEventListener('click', function(e){\n    e.preventDefault();\n    notification.confirm('', " . json_encode($confirmmsg) . ", '" . get_string('yes') . "', '" . get_string('no') . "', function(){ document.getElementById('gradeallform').submit(); });\n  });\n});";
    $PAGE->requires->js_init_code($js);
}

// New buttons to export CSV
    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_eval']),
        'Export Eval CSV',
        'get'
    );

    // echo $OUTPUT->single_button(
    //     new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_grades']),
    //     'Export Grades CSV',
    //     'get'
    // );

    // echo $OUTPUT->single_button(
    //     new moodle_url('/mod/speval/export_csv.php', ['id' => $cm->id, 'table' => 'speval_flag']),
    //     'Export AI Flags CSV',
    //     'get'
    // );

    // Publish grades to Moodle gradebook
    echo $OUTPUT->single_button(
        new moodle_url('/mod/speval/grade_service.php', ['id' => $cm->id]),
        'Publish to Gradebook',
        'post'
    );
    // New Import Groups button (NOW THIS IS ON MOD_FORM)
// echo $OUTPUT->single_button(
//     new moodle_url('/mod/speval/groupimport/import.php'),
//     'Import Groups',
//     'get'
// );


// If there are no submissions yet for this activity, show info and stop

$hassubmissions = $DB->record_exists('speval_eval', ['spevalid' => $cm->instance]);
if (!$hassubmissions) {
	$sm = get_string_manager();
	$nosubmsg = 'No submissions have been made for this activity yet.';
	if ($sm->string_exists('nosubmissionsyet', 'mod_speval')) {
		$nosubmsg = get_string('nosubmissionsyet', 'mod_speval');
	} else if ($sm->string_exists('nosubmissionsyet', 'speval')) {
		$nosubmsg = get_string('nosubmissionsyet', 'speval');
	}
	echo $OUTPUT->notification($nosubmsg, 'notifyinfo');
	echo $OUTPUT->footer();
	exit;
}

// Show results: grouped, per-student flags + final grade

// 1) Grades for this activity (may be empty if not yet calculated)
$grades = $DB->get_records('speval_grades', ['spevalid' => $cm->instance], '', 'userid, id, finalgrade, criteria1, criteria2, criteria3, criteria4, criteria5');

// 2) AI flags (individual) for this activity
$flags = $DB->get_records('speval_flag', ['spevalid' => $cm->instance]);

// 3) Aggregate flags per student (as peer)
$peerflags = []; // peerid => aggregated info
foreach ($flags as $f) {
	$peerid = isset($f->peerid) ? $f->peerid : (isset($f->peer) ? $f->peer : 0);
	if (!$peerid) { continue; }
    if (!isset($peerflags[$peerid])) {
        $peerflags[$peerid] = [
            'comment' => false,
            'quick' => false,
            'misbehaviour_categories' => []
        ];
    }
    if (!empty($f->commentdiscrepancy)) {
		$peerflags[$peerid]['comment'] = true;
	}
    if (!empty($f->quicksubmissiondiscrepancy)) {
        $peerflags[$peerid]['quick'] = true;
    }
	// Collect misbehaviour categories if flagged (>1 indicates category beyond baseline)
	if (isset($f->misbehaviorcategory) && (int)$f->misbehaviorcategory >= 1) {
		$peerflags[$peerid]['misbehaviour_categories'][] = (int)$f->misbehaviorcategory;
	}
}

// Misbehaviour labels (1..6) with robust lookup across components, with defaults
$misdefaults = [
    1 => 'Normal or positive teamwork behaviour',
    2 => 'Aggressive or hostile behaviour',
    3 => 'Uncooperative or ignoring messages behaviour',
    4 => 'Irresponsible or unreliable behaviour',
    5 => 'Harassment or inappropriate comments behaviour',
    6 => 'Dishonest or plagiarism behaviour'
];

$stringman = get_string_manager();
$mislabels_map = [];
for ($i = 1; $i <= 6; $i++) {
    $key = 'misbehaviour_' . $i;
    if ($stringman->string_exists($key, 'mod_speval')) {
        $mislabels_map[$i] = get_string($key, 'mod_speval');
    } else if ($stringman->string_exists($key, 'speval')) {
        $mislabels_map[$i] = get_string($key, 'speval');
    } else {
        $mislabels_map[$i] = $misdefaults[$i];
    }
}

// 4) Get groups linked to this activity (grouping) - same as progress.php
$groupingid = $speval->grouping;
if ($groupingid) {
    $groups = groups_get_all_groups($course->id, 0, $groupingid);
} else if ($activity->linkedassign){
    $assignment = $DB->get_record('assign', ['id' => $speval->linkedassign, 'course' => $speval->course], '*', MUST_EXIST);
    $groupingid = $assignment->teamsubmissiongroupingid;
    $groups = groups_get_all_groups($speval->course, 0, $groupingid);
} else {
    $groups = groups_get_all_groups($course->id);
}

if (!$groups) {
    echo $OUTPUT->notification('No groups found for this activity.');
    echo $OUTPUT->footer();
    exit;
}

// 4b) Check submission completeness per student (like progress.php) — for warning and table gating
$missing = [];
foreach ($groups as $group) {
    $students = groups_get_members($group->id, 'u.id, u.firstname, u.lastname, u.username');
    if (!$students) { continue; }
    foreach ($students as $student) {
        $has = $DB->record_exists('speval_eval', ['spevalid' => $cm->instance, 'userid' => $student->id]);
        if (!$has) {
            $missing[] = trim($student->firstname . ' ' . $student->lastname) . ' (' . $student->username . ')';
        }
    }
}
$allsubmitted = empty($missing);

// If not all submitted, warn (the Grade All button at top already shows a confirm modal)
if (!$allsubmitted) {
    echo $OUTPUT->notification('Some students have not submitted yet: ' . s(implode(', ', $missing)), 'warning');
}

// 5) Group students by the activity's groups
$grouped = []; // groupid => list rows
foreach ($groups as $group) {
    $students = groups_get_members($group->id, 'u.id, u.firstname, u.lastname, u.username');
    
    if (!$students) {
        continue;
    }
    
    foreach ($students as $student) {
        $uid = $student->id;

	// Final grade (if exists)
	$final = isset($grades[$uid]) ? (float)$grades[$uid]->finalgrade : 0.0;
    $has_submission_and_grade = isset($grades[$uid]);

    $has_submission_and_grade = isset($grades[$uid]);

	// Discrepancies
    $markdisc = (0 <$final)&&($final < 2.5); // rule provided
    $commentdisc = !empty($peerflags[$uid]['comment']);

	$misdisplay = '-';
    if (!empty($peerflags[$uid]['misbehaviour_categories'])) {
        $cats = array_unique($peerflags[$uid]['misbehaviour_categories']);
        // Remove "Normal" category (1) from display
        $cats = array_values(array_filter($cats, function($c) { return (int)$c !== 1; }));
        if (!empty($cats)) {
            $names = [];
            foreach ($cats as $cat) {
                $names[] = isset($mislabels_map[$cat]) ? $mislabels_map[$cat] : (string)$cat;
            }
            // Render each misbehaviour on a new line
            $misdisplay = implode('<br>', $names);
        }
    }

    $row = [
        'name' => trim($student->firstname . ' ' . $student->lastname),
		'id' => $student->username,
		'final' => format_float($final, 2),
		'markdisc' => $markdisc ? get_string('yes') : '-',
        'quickdisc' => !empty($peerflags[$uid]['quick']) ? get_string('yes') : '-',
		'commentdisc' => $commentdisc ? get_string('yes') : '-',
		'misbehave' => $misdisplay
	];

	if (!isset($grouped[$group->id])) {
		$grouped[$group->id] = [];
	}
	$grouped[$group->id][] = $row;
    }
}

// 6) Render UI: expandable per group (only show table if all submitted)
if ($allsubmitted && !empty($grouped)) {
    $groupindex = 0;
    foreach ($grouped as $gid => $rows) {
        $gname = $gid ? groups_get_group_name($gid) : get_string('nogroup', 'speval');
        $detailsattrs = ['open' => 'open', 'class' => 'speval-group'];
        if ($groupindex > 0) {
            $detailsattrs['style'] = 'margin-top: 24px;';
        }
        echo html_writer::start_tag('details', $detailsattrs);
		echo html_writer::tag('summary', s('Group: ' . ($gname ?: $gid)));

		$table = new html_table();
        // Resolve quick submission label robustly across components
        $qslabel = 'Quick submission discrepancy';
        if ($stringman->string_exists('quicksubmissiondiscrepancy', 'mod_speval')) {
            $qslabel = get_string('quicksubmissiondiscrepancy', 'mod_speval');
        } else if ($stringman->string_exists('quicksubmissiondiscrepancy', 'speval')) {
            $qslabel = get_string('quicksubmissiondiscrepancy', 'speval');
        }

        $table->head = [
            get_string('name'),
            get_string('id', 'speval'),
            get_string('finalgrade', 'speval'),
            get_string('markdiscrepancy', 'speval'),
            $qslabel,
            get_string('commentdiscrepancy', 'speval'),
            get_string('misbehaviour', 'speval')
        ];

		foreach ($rows as $r) {
            // Create cells with conditional highlighting
            $markdisc_cell = s($r['markdisc']);
            $quickdisc_cell = s($r['quickdisc']);
            $commentdisc_cell = s($r['commentdisc']);
            $misbehave_cell = s($r['misbehave']);
            
            // Highlight cells that contain "Yes" (not "-")
            if ($r['markdisc'] !== '-') {
                $markdisc_cell = '<span style="background-color: #ffebee; color: #c62828; padding: 2px 4px; border-radius: 3px;">' . s($r['markdisc']) . '</span>';
            }
            if ($r['quickdisc'] !== '-') {
                $quickdisc_cell = '<span style="background-color: #ffebee; color: #c62828; padding: 2px 4px; border-radius: 3px;">' . s($r['quickdisc']) . '</span>';
            }
            if ($r['commentdisc'] !== '-') {
                $commentdisc_cell = '<span style="background-color: #ffebee; color: #c62828; padding: 2px 4px; border-radius: 3px;">' . s($r['commentdisc']) . '</span>';
            }
            if ($r['misbehave'] !== '-') {
                // Keep highlighting and center the content; allow <br> for multi-line
                $misbehave_cell = '<div style="text-align:center;"><span style="background-color: #ffebee; color: #c62828; padding: 2px 4px; border-radius: 3px; display:inline-block;">' . $r['misbehave'] . '</span></div>';
            } else {
                $misbehave_cell = '<div style="text-align:center;">-</div>';
            }
            
            $table->data[] = [
				s($r['name']),
				s($r['id']),
				s($r['final']),
				$markdisc_cell,
                $quickdisc_cell,
                $commentdisc_cell,
                $misbehave_cell
			];
		}

		echo html_writer::table($table);
		echo html_writer::end_tag('details');
        $groupindex++;
	}   
} else {
	echo $OUTPUT->notification(get_string('noresults', 'mod_speval'), 'warning');
}

// Output end
echo $OUTPUT->footer();
