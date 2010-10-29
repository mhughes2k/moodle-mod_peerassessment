<?php
require ('../../config.php');
require_once("lib.php");
require_once($CFG->dirroot."/lib/tablelib.php");    

$id = required_param('id',PARAM_INT);	//course module id;
//$groupid = optional_param('selectedgroup',false,PARAM_INT);

if($id) {
    
    if (!$course = get_record('course', 'id', $id)) {
        error("Course is misconfigured");
    }
   
}

require_course_login($course, true);//, $cm);

//$context = get_context_instance(CONTEXT_MODULE, $cm->id);
$strpas = get_string('modulenameplural', 'peerassessment');
$strpa  = get_string('modulename', 'peerassessment');
/* output stuff now */

//print_header_simple($course->shortname.': ' .$course->fullname);
$navlinks = array();
$navlinks[] = array('name' => $strpas, 'link' => '', 'type' => 'activity');
$navigation = build_navigation($navlinks);
print_header_simple('', '', $navigation,'', '', true, '', navmenu($course));

print_heading(get_string('modulenameplural','peerassessment'));

print_box_start();
$table=new stdClass;
$table->head = array('Peer Assessment Activity','Associated Assignment','Frequency');       
if ( $activities = get_records('peerassessment','course',$course->id)) {

foreach($activities as $a) {
    $e = array();
    //print_r($a);
    $viewReport = false;
  
    if ($cm = get_coursemodule_from_instance('peerassessment', $a->id)) {
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $viewReport = has_capability('mod/peerassessment:viewreport',$context);
    }
    else {
        //echo 'couldnt get cm';
    }
  
  $e[]= "<A href='{$CFG->wwwroot}/mod/peerassessment/view.php?p={$a->id}'>$a->name</a>";
  //print_r($a);
  if ($a->assignment) {
    $ass_cm = get_coursemodule_from_id('assignment', $a->assignment);
   
    if (!$ass_cm) {
      $table->data[]= array("Could not get course module from id",print_r($a,true));
      //continue;
    }
    else {
        //$context = get_context_instance(CONTEXT_MODULE, $cm->id);
       //$viewReport = has_capability('mod/peerassessment:viewreport',$context);
    }
    $ass = get_record('assignment','id',$ass_cm->instance);
    if (
      $ass_cm  
    && 
      $ass
    ) {
      $e[] = $ass->name;
    }
  }
  else {
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
  if ($viewReport) {
    $e[] = "<a href='{$CFG->wwwroot}/mod/peerassessment/report.php?id={$a->id}'>View Report</a>";
  }
 // $e[] =print_r($a,true);
  $table->data[] = $e;
}
print_table($table);
}
else {
  p('No activities found');
}
     print_box_end();
print_footer();