<?php
require ('../../config.php');
require_once("lib.php");
require_once($CFG->dirroot."/lib/tablelib.php");    

$id = optional_param('id',false,PARAM_INT);	//course module id;
$groupid = optional_param('selectedgroup',false,PARAM_INT);
$startReportPeriod =optional_param('startperiod',false,PARAM_INT);
$p = optional_param('peerassessment',0,PARAM_INT);
$params = array();
if ($id) {
	$params['id']=$id;
}
if ($groupid) {
	$params['selectedgroup'] = $groupid;
}
if ($startReportPeriod) {
	$params['startReportPeriod'] = $startReportPeriod;
}
if ($p != 0) {
	$params['peerassessment'] = $p;
}
 
if($id) {
    if (!$cm = get_coursemodule_from_id('peerassessment', $id)) {
        error("Course Module ID was incorrect (1)");
    }

    if (!$course = $DB->get_record('course',array('id'=>$cm->course))) {
        error("Course is misconfigured");
    }
    if (!$peerassessment = $DB->get_record(PA_TABLE,array('id'=>$cm->instance))) {
        error("Course module is incorrect");
    }
    
}
else {

    if (! $peerassessment = $DB->get_record('peerassessment',array('id'=>$p))) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id'=>$peerassessment->course))) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('peerassessment', $peerassessment->id, $course->id)) {
        error('Course Module ID was incorrect (2)');
    }
    $id=$cm->id;
}
require_course_login($course, true, $cm);

$PAGE->set_url('/mod/peerassessment/report.php',$params);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/peerassessment:viewreport',$context);
$ratings = $DB->get_records('peerassessment_ratings',array('peerassessment'=>$peerassessment->id));

$group = false;
$groupmode = false;
$group_context=false;
//print_object($peerassessment);
if($peerassessment->assignment) {
  if(!$assignment_cm = get_coursemodule_from_id('assignment',$peerassessment->assignment)){//,$peerassessment->course)) {
    die('Couldn\'t get cm for assignment');
  }
}
else {
  $assignment_cm = $cm;
}

$data = data_submitted();

if ($data) {

  //print_r($data);
  if(!empty($data->delete)) {
    //add_to_log('requested deletion of rating')
    echo "deleting a rating\n";
    if ($rating = $DB->get_records('peerassessment_ratings',array('id'=>$data->ratingid))) {  
      if($rating) {
        //$rating = get_record_select('peerassessment_ratings',"peerassessment={$data->peerassessment} AND timemodified={$data->ratingtime} AND ratedby={$data->userid} ");       
        if(!$DB->delete_records('peerassessment_ratings',array('id'=>$rating->id))) {
          notice("Could not delete rating");
        }
      }      
    } 
    else {
      if ($rating = $DB->get_records_select('peerassessment_ratings',"peerassessment={$data->peerassessment} AND timemodified={$data->ratingtime} AND ratedby={$data->userid} ")) { 
        if(!$DB->delete_records_select('peerassessment_ratings',"peerassessment={$data->peerassessment} AND timemodified={$data->ratingtime} AND ratedby={$data->userid} ")) {
          notice("Could not delete rating");
        }
      }
      else {
        notice("Couldn't locate a rating for specified user");
      }
      
    }
    if (!($DB->delete_records('peerassessment_comments',array('peerassessment'=>$data->peerassessment,'userid'=>$data->userid)))) {
    	notice("Could not delete comment");
    }
    peerassessment_update_grades($peerassessment) ;//update the grade book since we've deleted some entries   
    redirect($CFG->wwwroot."/mod/peerassessment/report.php?selectedgroup={$groupid}&id={$id}");  
  }	
}

$displaygroups= array();
  //display a list of groups to display
$groups = groups_get_activity_allowed_groups($assignment_cm);
foreach($groups as $g) {
  $displaygroups[$g->id] =$g->name;
}
//print_r($displaygroups);
ksort($displaygroups,SORT_STRING);      /*  This is a hack based on the fact that 
                                            groups are created in numeric/
                                            alphabetical order.
                                        */
//print_r($displaygroups);

$members = groups_get_members($groupid);

$group = groups_get_group($groupid);//get_record('groups','id',$groupid);

//$table = new stdClass;

//print_r($peerassessment);

if ($peerassessment->frequency > PA_FREQ_ONCE) {
  // TODO (future) we have to display a list of dates 

}

/* output stuff now */
//print_header_simple($course->shortname.': ' .$course->fullname);
$navigation = build_navigation('', $cm);
print_header_simple(format_string($peerassessment->name), '', $navigation,
                      '', '', true, '', navmenu($course, $cm));

$OUTPUT->heading(get_string('peerassessmentreportheading','peerassessment',$peerassessment));
echo '<div class="reportlink">';
print_string('displaygroup','peerassessment');
print_report_select_form($id,$groups,$groupid);
/*
echo $OUTPUT->single_select(
    $CFG->wwwroot."/mod/peerassessment/report.php?id={$id}&selectedgroup=",
    'reportgroupjump',
    $displaygroups,
    $groupid
);*/

/*popup_form(
    $CFG->wwwroot."/mod/peerassessment/report.php?id={$id}&selectedgroup=",
    $displaygroups,
    'reportgroupjump',
    $groupid
);
*/
/*
echo '<form id="reportgroupjump"><p>' . get_string('displaygroup','peerassessment');
choose_from_menu($displaygroups,'selectedgroup',$groupid);

echo "<input type='hidden' name='id' value='{$id}'/><input type='submit' value='Select'/></p>";
echo '</form>';
*/
echo '</div>';

if ($groupid) {
  
  //print_r($peerassessment);
  switch ($peerassessment->frequency) {
    case PA_FREQ_ONCE:
      $table = peerassessment_get_table_single_frequency($peerassessment,$group);
      break;
    case PA_FREQ_WEEKLY:
      $overview_table = new Stdclass;
      $overview_table->head[] = '';
      $a = array(); // average rating
      $b = array(); //average given rating
      $a[] = get_string('averageratingreceived','peerassessment');//'Average Rating Received';
      $b[] = get_string('averageratinggiven','peerassessment');//'Average Rating Given';
      foreach($members as $m) {
        $overview_table->head[] = $m->lastname . ', '.$m->firstname;
        $a[] = get_average_rating_for_student($peerassessment,$m->id);
        $b[] = get_average_rating_by_student($peerassessment,$m->id);                                                                    
      }
      //$a[] = '&nbsp;';
      $overview_table->data[] = $a;
      $overview_table->data[] = $b;
      print_heading(get_string('overview','peerassessment'));
      print_table($overview_table);
      $table = peerassessment_get_table_weekly_frequency($peerassessment,$group);//$members);
      print_heading(get_string('details','peerassessment'));    
      break;  
    case PA_FREQ_UNLIMITED:
      $table = peerassessment_get_table_unlimited_frequency($peerassessment,$group);//$members);
      break; 
    	
  	break;
  }
  
  
	//print_table($table);
	echo html_writer::table($table);
     
}
else {
  echo $OUTPUT->box_start();
  echo("Please choose a group to display.");
  echo $OUTPUT->single_select(
    $CFG->wwwroot."/mod/peerassessment/report.php?id={$id}&selectedgroup=",
    'reportgroupjump',
    $displaygroups,
    $groupid
);
  /*
popup_form(
    $CFG->wwwroot."/mod/peerassessment/report.php?id={$id}&selectedgroup=",
    $displaygroups,
    'reportgroupjump',
    $groupid
);*/
  echo $OUTPUT->box_end();
}     

//print_object(peerassessment_get_user_grades($peerassessment));

echo $OUTPUT->footer();
