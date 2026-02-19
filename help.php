<?php
require('../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_url('/mod/speval/help.php');
$PAGE->set_title(get_string('pluginname', 'mod_speval') . ' ' . get_string('help', 'moodle'));
$PAGE->set_heading('Student Peer Evaluation Help');
$PAGE->navbar->add('Help');

echo $OUTPUT->header();
?>

<style>
    .spe-help-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 0 20px;
        font-family: "Inter", "Segoe UI", Roboto, sans-serif;
        color: #2e2e2e;
        line-height: 1.6;
    }

    .spe-section {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px 28px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .spe-section:hover {
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        transform: translateY(-2px);
    }

    .spe-section h2 {
        font-size: 1.4rem;
        color: #2563eb;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 6px;
        margin-bottom: 16px;
        font-weight: 600;
    }

    .spe-section ul, .spe-section ol {
        margin: 0 0 0 1.25rem;
        padding: 0;
    }

    .spe-section li {
        margin-bottom: 8px;
    }

    .spe-highlight {
        background-color: #eff6ff;
        border-left: 4px solid #2563eb;
        padding: 12px 18px;
        border-radius: 8px;
        font-size: 0.95rem;
    }

    .spe-footer-note {
        font-size: 0.85rem;
        color: #777;
        text-align: center;
        margin-top: 40px;
        border-top: 1px solid #e5e7eb;
        padding-top: 16px;
    }

    a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
    }

    a:hover {
        text-decoration: underline;
    }
</style>


<div class="spe-help-container">

    <div class="spe-section">
        <h2><i class="icon fa fa-info-circle text-primary"></i> About Student Peer Evaluation (SPE)</h2>
        <p>
            The <strong>Student Peer Evaluation (SPE)</strong> activity enables students to evaluate their peers
            based on a set of defined criteria. The system automatically calculates and publishes final grades
            to the Moodle Gradebook once evaluations are complete.
        </p>
        <div class="spe-highlight">
            <strong>Purpose:</strong> To promote reflective assessment and teamwork while providing instructors
            with transparent grading data.
        </div>
    </div>

    <div class="spe-section">
        <h2><i class="icon fa fa-cogs text-primary"></i> How It Works</h2>
        <ol>
            <li>Students are assigned peers within their group.</li>
            <li>Each student completes an evaluation form based on five criteria.</li>
            <li>The system aggregates and averages all received evaluations.</li>
            <li>Final grades are calculated automatically and can be published to the gradebook.</li>
            <li>Any potential cases of discrepency or misconduct are flagged by the system to be viewed by the Unit Coordinator</li>
        </ol>
    </div>

    <div class="spe-section">
        <h2><i class="icon fa fa-star text-primary"></i> Features</h2>
        <ul>
            <li>Automatic grade calculation and Moodle gradebook integration.</li>
            <li>AI-based evaluation insights.</li>
            <li>Group-level progress bars are provided for insight into student submissions.</li>
            <li>Supports both manual and automatic group assignments.</li>
        </ul>
    </div>

    <div class="spe-section">
        <h2><i class="icon fa fa-lightbulb text-primary"></i> Instructor Tips</h2>
        <ul>
            <li>Ensure that groups are created or imported before evaluations start.</li>
            <li>Use the <em>Results</em> page to review peer submissions and publish final grades.</li>
            <li>Monitor participation via the <em>Progress</em> tab for quick insights.</li>
            <li>Questions can be dynamically changed in the <em>criteria</em> tab view</li>
            <li>Open ended questions are comments used to gain insight into the group workings.</li>
            <li>Opened ended question 2 will only appear to self evaluation</li>
        </ul>
    </div>

    <div class="spe-section">
        <h2><i class="icon fa fa-life-ring text-primary"></i> About the developers</h2>
        <p>
            This Module was created as a part of ICT302 project IT07 created by the following people:
            <br/><br/>
            Abhijeet Sodhi<br/>
            Arogya Badal<br/>
            Carlos Pereda Mieses<br/>
            Hasan Bin Rehan<br/>
            James Kuang<br/>
            Joshua Weller<br/>
            <br/>
            All copy rights to this module belong to Murdoch University. Special thanks to our
            clients and supervisors Peter Cole and Megan Cole.
        </p>
    </div>

    <p class="spe-footer-note">
        © <?= date('Y') ?> Student Peer Evaluation (SPE) Module — Murdoch University
    </p>
</div>

<?php
echo $OUTPUT->footer();
