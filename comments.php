<?php
require ('../../config.php');
require_once("lib.php");
require_once($CFG->dirroot."/lib/tablelib.php");    
$userid= required_param('userid',PARAM_INT);
$id = optional_param('id',false,PARAM_INT);	//course module id;
$groupid = optional_param('selectedgroup',false,PARAM_INT);
$startReportPeriod =optional_param('startperiod',false,PARAM_INT);

$p = optional_param('p',0,PARAM_INT); 
//die("peerassessment id :$p");
if($id) {
    if (!$cm = get_coursemodule_from_id('peerassessment', $id)) {
        error("Course Module ID was incorrect (1)");
    }

    if (!$course = get_record('course', 'id', $cm->course)) {
        error("Course is misconfigured");
    }
    if (!$peerassessment = get_record(PA_TABLE, 'id', $cm->instance)) {
        error("Course module is incorrect");
    }
    
}
else {

    if (! $peerassessment = get_record('peerassessment', 'id', $p)) {
        error('Course module is incorrect');
    }
    if (! $course = get_record('course', 'id', $peerassessment->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('peerassessment', $peerassessment->id, $course->id)) {
        error('Course Module ID was incorrect (2)');
    }
    $id=$cm->id;
}
require_course_login($course, true, $cm);


$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/peerassessment:viewreport',$context);
$ratings = get_records('peerassessment_ratings','peerassessment',$peerassessment->id);

$m = get_record('user','id',$userid);


$select ="userid = {$userid} AND peerassessment={$peerassessment->id}";
$comments = get_records_select('peerassessment_comments',$select);
$name = "{$m->lastname}, {$m->firstname}";// ({$m->id})";

$navigation = build_navigation('', $cm);
print_header_simple(format_string($peerassessment->name), '', $navigation,
                      '', '', true, '', navmenu($course, $cm));

print_heading(get_string('peerassessmentreportheading','peerassessment',$peerassessment));



if($comments) {
      //$c = addslashes($comment->studentcomment);
    $name .="<sup>";
    $table = new stdclass;
    $table->head = array("Comment",'Date');
    foreach($comments as $comment) {
        $table->data[] = array($comment->studentcomment,userdate($comment->timemodified));
        //$table->data[] = $comment->studentcomment;
        //$table->data[] = userdate($comment->timemodified);
    }
    print_table($table);
}

print_footer();