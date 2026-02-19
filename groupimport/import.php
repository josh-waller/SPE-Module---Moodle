<?php
// This file is a reimplementation of a Moodle file - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once(__DIR__ . '/import_form.php');

// ⚙️ We don’t want this tied to a specific activity or cm.
$courseid = optional_param('id', 1, PARAM_INT); // Default to site course (id=1) if not provided.

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:managegroups', $context);

$PAGE->set_url(new moodle_url('/mod/speval/groupimport/import.php', ['id' => $courseid]));
$PAGE->set_title("Import Groups");
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('admin');

$strimportgroups = get_string('importgroups', 'core_group');
$importform = new groups_import_form(null, ['id' => $courseid]);

$returnurl = new moodle_url('/user/index.php', ['id' => $courseid]);

if ($importform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $importform->get_data()) {
    echo $OUTPUT->header();

    $text = $importform->get_file_content('userfile');
    $text = preg_replace('!\r\n?!', "\n", $text);

    require_once($CFG->libdir . '/csvlib.class.php');
    $importid = csv_import_reader::get_new_iid('groupimport');
    $csvimport = new csv_import_reader($importid, 'groupimport');
    $delimiter = $formdata->delimiter_name;
    $encoding = $formdata->encoding;
    $readcount = $csvimport->load_csv_content($text, $encoding, $delimiter);

    if ($readcount === false) {
        throw new moodle_exception('csvfileerror', 'error', $PAGE->url, $csvimport->get_error());
    } else if ($readcount < 2) {
        throw new moodle_exception('csvnodata', 'error', $PAGE->url);
    }

    $csvimport->init();
    unset($text);

    // --- Valid field setup ---
    $required = ['groupname' => 1];
    $optionaldefaults = ['lang' => 1];
    $optional = [
        'coursename' => 1,
        'idnumber' => 1,
        'groupidnumber' => 1,
        'description' => 1,
        'enrolmentkey' => 1,
        'groupingname' => 1,
        'enablemessaging' => 1,
        'username' => 1, // allow user linking
    ];

    // Handle custom fields (unchanged)
    $customfields = \core_group\customfield\group_handler::create()->get_fields();
    $customfieldnames = [];
    foreach ($customfields as $customfield) {
        $customfieldnames['customfield_' . $customfield->get('shortname')] = 1;
    }

    $customfields = \core_group\customfield\grouping_handler::create()->get_fields();
    $groupingcustomfields = [];
    foreach ($customfields as $customfield) {
        $customfieldnames['grouping_customfield_' . $customfield->get('shortname')] = 1;
        $groupingcustomfields['grouping_customfield_' . $customfield->get('shortname')] =
            'customfield_' . $customfield->get('shortname');
    }

    // --- Validate header ---
    $header = $csvimport->get_columns();
    foreach ($header as $i => $h) {
        $h = trim($h);
        $header[$i] = $h;
        if (!isset($required[$h]) && !isset($optional[$h]) && !isset($customfieldnames[$h])) {
            throw new moodle_exception('invalidfieldname', 'error', $PAGE->url, $h);
        }
        if (isset($required[$h])) {
            $required[$h] = 2;
        }
    }

    foreach ($required as $key => $value) {
        if ($value < 2) {
            throw new moodle_exception('fieldrequired', 'error', $PAGE->url, $key);
        }
    }

    $linenum = 2;

    // --- Process CSV lines ---
    while ($line = $csvimport->next()) {
        $record = [];
        foreach ($line as $key => $value) {
            $record[$header[$key]] = trim($value);
        }

        if (trim(implode('', $record)) === '') continue;

        $newgroup = new stdClass();
        foreach ($optionaldefaults as $key => $value) {
            $newgroup->$key = current_language();
        }

        foreach ($record as $name => $value) {
            if (isset($required[$name]) && !$value) {
                throw new moodle_exception('missingfield', 'error', $PAGE->url, $name);
            } else if ($name == 'groupname') {
                $newgroup->name = $value;
            } else {
                $newgroup->$name = $value;
            }
        }

        // Determine course context
        $newgroup->courseid = $courseid;

        $linenum++;
        $groupname = $newgroup->name;
        $newgrpcoursecontext = context_course::instance($newgroup->courseid);

        if (!has_capability('moodle/course:managegroups', $newgrpcoursecontext)) {
            echo $OUTPUT->notification("No permission to create group '$groupname'.", 'notifyproblem');
            continue;
        }

        // --- Create or update group ---
        $groupid = groups_get_group_by_name($newgroup->courseid, $groupname);
        if ($groupid) {
            // Add user if provided
            if (!empty($newgroup->username) && $DB->record_exists('user', ['username' => $newgroup->username])) {
                $user = $DB->get_record('user', ['username' => $newgroup->username]);
                groups_add_member($groupid, $user->id);
                echo $OUTPUT->notification("User {$newgroup->username} added to existing group '$groupname'.", 'notifysuccess');
            }
        } else if ($groupid = groups_create_group($newgroup)) {
            echo $OUTPUT->notification("Group '$groupname' created.", 'notifysuccess');
            if (!empty($newgroup->username) && $DB->record_exists('user', ['username' => $newgroup->username])) {
                $user = $DB->get_record('user', ['username' => $newgroup->username]);
                groups_add_member($groupid, $user->id);
                echo $OUTPUT->notification("User {$newgroup->username} added to new group '$groupname'.", 'notifysuccess');
            }
        } else {
            echo $OUTPUT->notification("Error adding group '$groupname'.", 'notifyproblem');
            continue;
        }

        // --- Handle grouping assignment ---
        if (!empty($newgroup->groupingname)) {
            $groupingname = $newgroup->groupingname;
            $groupingid = groups_get_grouping_by_name($newgroup->courseid, $groupingname);
            if (!$groupingid) {
                $data = (object)[
                    'courseid' => $newgroup->courseid,
                    'name' => $groupingname
                ];
                $groupingid = groups_create_grouping($data);
                echo $OUTPUT->notification("Grouping '$groupingname' created.", 'notifysuccess');
            }
            groups_assign_grouping($groupingid, $groupid);
            echo $OUTPUT->notification("Group '$groupname' assigned to grouping '$groupingname'.", 'notifysuccess');
        }
    }

    $csvimport->close();
    echo $OUTPUT->single_button($returnurl, get_string('continue'), 'get');
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help($strimportgroups, 'importgroups', 'core_group');
$importform->display();
echo $OUTPUT->footer();
