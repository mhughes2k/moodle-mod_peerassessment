<?php
require('../../config.php');
require_once("lib.php");
require_once($CFG->dirroot."/lib/tablelib.php");

$id = required_param('id', PARAM_INT);

if ($id) {
    if (!$course = $DB->get_record('course', array('id'=>$id))) {
        error("Course is misconfigured");
    }
}

require_course_login($course, true);


$strpas = get_string('modulenameplural', 'peerassessment');
$strpa  = get_string('modulename', 'peerassessment');
/* output stuff now */

$PAGE->set_url(new moodle_url('/mod/peerassessessment', array('id' => $id)));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'peerassessment'));

echo $OUTPUT->box_start();
$table = new html_table();

$table->head = array('Peer Assessment Activity', 'Frequency');
if ( $activities = $DB->get_records('peerassessment', array('course'=>$course->id))) {

    foreach ($activities as $a) {
        $e = array();
        $viewreport = false;

        if ($cm = get_coursemodule_from_instance('peerassessment', $a->id)) {
            $context = context_module::instance($cm->id);
            $viewreport = has_capability('mod/peerassessment:viewreport', $context);
        }
        $e[]= "<A href='{$CFG->wwwroot}/mod/peerassessment/view.php?p={$a->id}'>$a->name</a>";

        switch($a->frequency) {
            case PA_FREQ_WEEKLY:
                $e[] = 'Weekly';
                break;
            case PA_FREQ_UNLIMITED:
                $e[] = 'Unlimited';
                break;
            case PA_FREQ_ONCE:
                $e[] = 'Once';
                break;
        }
        if ($viewreport) {
            $e[] = "<a href='{$CFG->wwwroot}/mod/peerassessment/report.php?id={$cm->id}'>View Report</a>";
        }
        $table->data[] = $e;
    }
    echo html_writer::table($table);
} else {
    p('No activities found');
}
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
