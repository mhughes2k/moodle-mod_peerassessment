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

$navlinks = array();
$navlinks[] = array('name' => $strpas, 'link' => '', 'type' => 'activity');
$navigation = build_navigation($navlinks);
print_header_simple('', '', $navigation, '', '', true, '', navmenu($course));

echo $OUTPUT->heading(get_string('modulenameplural', 'peerassessment'));

echo $OUTPUT->box_start();
$table=new stdClass;
$table->head = array('Peer Assessment Activity', 'Associated Assignment', 'Frequency');
if ( $activities = $DB->get_records('peerassessment', array('course'=>$course->id))) {

    foreach ($activities as $a) {
        $e = array();
        $viewreport = false;

        if ($cm = get_coursemodule_from_instance('peerassessment', $a->id)) {
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
            $viewreport = has_capability('mod/peerassessment:viewreport', $context);
        }
        $e[]= "<A href='{$CFG->wwwroot}/mod/peerassessment/view.php?p={$a->id}'>$a->name</a>";
        if ($a->assignment) {
            $ass_cm = get_coursemodule_from_id('assignment', $a->assignment);

            if (!$ass_cm) {
                $table->data[]= array("Could not get course module from id {$a->id}");
            }
            $ass = get_record('assignment', 'id', $ass_cm->instance);
            if (
              $ass_cm
            &&
              $ass
            ) {
                $e[] = $ass->name;
            }
        } else {
            $e[] ='No Associated Assignment';
        }
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
    print_table($table);
} else {
    p('No activities found');
}
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
