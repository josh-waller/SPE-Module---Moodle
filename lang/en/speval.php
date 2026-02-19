<?php

/* 
Note: Changes to this file require Moodle to purge all caches.
You can do so from http://localhost/moodle/admin/purgecaches.php
or click the bookmark in our VM
*/

// ------------------------------------------------------------------------------------------------------
// General terms
$string['pluginname'] = 'Self and Peer Evaluation';
$string['modulename'] = 'Self and Peer Evaluation';
$string['spename'] = 'SPE Name';


// ------------------------------------------------------------------------------------------------------
// Criteria
$string['criteria1'] = 'Criteria 1';
$string['criteria2'] = 'Criteria 2';
$string['criteria3'] = 'Criteria 3';
$string['criteria4'] = 'Criteria 4';
$string['criteria5'] = 'Criteria 5';
$string['criteriasaved'] = 'Criteria saved successfully.';
$string['duplicatecriteria'] = 'Your already selected this question in other criteria';
$string['importcsvbutton'] = 'Import questions';
$string['csvimportsuccess'] = 'CSV questions were imported successfully.';
$string['importcsvnofile'] = 'No CSV file was uploaded.';
$string['questionbankimport'] = 'Import questions from CSV';
$string['errornocsv'] = 'Please upload a CSV file before importing.';

// ------------------------------------------------------------------------------------------------------
// Settings 1-5
$string['linkoption'] = 'Link Option'; 
$string['standalone'] = 'Standalone Activity'; 
$string['linktoassignment'] = 'Link to Assignment';
$string['linkedassign'] = 'Linked Assignment';
$string['modulenameplural'] = 'Self and Peer Evaluations';
$string['speval:addinstance'] = 'Add a new SPEval activity';
$string['speval:view'] = 'View SPEval';
$string['speval:submit'] = 'Submit SPEval';
$string['results'] = 'Results';
$string['criteria'] = 'Criteria';
$string['gradeall'] = 'Grade All Evaluations';
$string['speval:grade'] = 'Grade submissions in SPEVAL';
$string['nogrades'] = 'No Grades yet';
$string['editgrade'] = 'Edit Grade';
$string['gradesuccess'] = 'Grade updated successfully';
$string['gradesnotcalculated'] = 'AI analysis is unavailable because grades are not calculated yet.';
$string['progress'] = 'Progress of Submission';
$string['mustselectgrouping'] = 'You must select a grouping';
$string['mustselectassign'] = 'You must select an assignment';
$string['questiondeleted'] = 'Question deleted sucessfully';
$string['questionsaved'] = 'Question saved sucessfully';
$string['pluginname']         = 'Self & Peer Evaluation';
$string['questionbank']       = 'Question Bank';
$string['questiontext']       = 'Question Text';
$string['openquestion']       = 'Open Question?';
$string['savequestion']       = 'Save Question';
$string['questionadded']      = 'Question added!';
$string['edit']               = 'Edit';
$string['delete']             = 'Delete';
$string['criteriasaved']      = 'Criteria saved successfully';
$string['deleteconfirm']      = 'are you sure you want to delete this submission? you will need to inform student that their submission is deleted';

$string['other'] = 'Other';
$string['selectcriteria'] = 'Select Criteria';

$string['timeopen'] = 'Open time';
$string['timeclose'] = 'Close time';
$string['overduehandling'] = 'Late submission handling';
$string['overdue_prevent'] = 'Prevent late submissions';
$string['overdue_allow'] = 'Allow late submissions';
$string['overdue_marklate'] = 'Accept but mark as late';

$string['pluginadministration'] = "Admin";

$string['deleteconfirm'] = 'Are you sure you want to delete this submission?';


$string['deletedsub'] = 'Submission deleted sucessfully.';
$string['submissionsuccess'] = 'Your evaluation has been submitted successfully.';


$string['modulename_help'] = 'The Student Peer Evaluation activity enables students to 
assess their peers based on defined criteria. It supports transparent grading, automatic result calculation, 
and AI-assisted analysis to identify discrepancies and ensure fair evaluation.

For a detailed guide on how to use this activity, visit the 
<a href="http://localhost/moodle/mod/speval/help.php" target="_blank">Student Peer Evaluation Help Page</a>.
';

$string['defaultintro'] = '<div><strong>Please note:</strong> Everything you put into this form will be kept strictly confidential by the unit coordinator.</div><div> </div>
<div><strong>Contribution Ratings:</strong></div>
<div> </div>
<div>
  <ol>
    <li><strong>Very Poor:</strong> Very poor, or even obstructive, contribution
      to the project process</li>
    <li><strong>Poor:</strong> Poor contribution to the project process</li>
    <li><strong>Average:</strong> Acceptable contribution to the project
      process.</li>
    <li><strong>Good:</strong> Good contribution to the project process.</li>
    <li><strong>Excellent:</strong> Excellent contribution to the project
      process</li>
  </ol>
</div>
<div><strong>Using the assessment scales above, evaluate your peers.</strong>
</div>';

$string['nogroup'] = 'No group';
$string['finalgrade'] = 'Final grade';
$string['markdiscrepancy'] = 'Mark discrepancy';
$string['commentdiscrepancy'] = 'Comment discrepancy';
$string['quicksubmissiondiscrepancy'] = 'Quick submission discrepancy';
$string['misbehaviour'] = 'Misbehaviour';
$string['noresults'] = 'No results to display.';
$string['nousersfound'] = 'No enrolled users found.';
$string['id'] = 'ID';

// ----
// Timing
$string['openson'] = 'Activity opens on: {$a}';
$string['closeson'] = 'Activity closes on: {$a}';
$string['alwaysopen'] = 'This activity is always open.';
$string['deletequestion'] = 'Delete Question';

$string['confirmdeletequestion'] = 'Are you sure you want to delete the question "<b>{$a}</b>"?';
$string['deletewarning'] = 'Deleting this question might affect Speval activities that exist within this unit. Please make sure you do not have any SPE current activities using this question';

// ------------------------------------------------------------------------------------------------------
// Misbehaviour labels (1..6)
$string['misbehaviour_1'] = 'Normal or positive teamwork behaviour';
$string['misbehaviour_2'] = 'Aggressive or hostile behaviour';
$string['misbehaviour_3'] = 'Uncooperative or ignoring messages behaviour';
$string['misbehaviour_4'] = 'Irresponsible or unreliable behaviour';
$string['misbehaviour_5'] = 'Harassment or inappropriate comments behaviour';
$string['misbehaviour_6'] = 'Dishonest or plagiarism behaviour';
$string['-']='-';