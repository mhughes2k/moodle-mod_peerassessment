<?php
require ('../../config.php');
require_once("lib.php");
require_once($CFG->dirroot."/lib/tablelib.php");    

$id = required_param('id',PARAM_INT);	//course module id;
$groupid = optional_param('selectedgroup',false,PARAM_INT);

if($id) {
    if (!$cm = get_coursemodule_from_id('peerassessment', $id)) {
        error("Course Module ID was incorrect");
    }

    if (!$course = get_record('course', 'id', $cm->course)) {
        error("Course is misconfigured");
    }
    if (!$peerassessment = get_record(PA_TABLE, 'id', $cm->instance)) {
        error("Course module is incorrect");
    }
    
}

require_course_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

$ratings = get_records('peerassessment_ratings','peerassessment',$peerassessment->id);

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
$displaygroups= array();
  //display a list of groups to display
$groups = groups_get_activity_allowed_groups($assignment_cm);
foreach($groups as $g) {
  $displaygroups[$g->id] =$g->name;
}


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

print_heading(get_string('peerassessmentreportheading','peerassessment',$peerassessment));
echo '<div class="reportlink">';
echo '<form><p>Display Group:';
choose_from_menu($displaygroups,'selectedgroup',$groupid);
echo "<input type='hidden' name='id' value='{$id}'/><input type='submit' value='Select'/></p>";
echo '</form></div>';

if ($groupid) {

//print_r($peerassessment);
switch ($peerassessment->frequency) {
  case PA_FREQ_ONCE:
    $table = peerassessment_get_table_single_frequency($peerassessment,$members);
    break;
  case PA_FREQ_WEEKLY:
    $overview_table = new Stdclass;
    $overview_table->head[] = '';
    $a = array(); // average rating
    $b = array(); //average given rating
    $a[] = 'Average Rating Recieved';
    $b[] = 'Average Rating Given';
    foreach($members as $m) {
      $overview_table->head[] = $m->lastname . ', '.$m->firstname;
      $a[] = get_average_rating_for_student($peerassessment,$m->id);
      $b[] = get_average_rating_by_student($peerassessment,$m->id);                                                                    
    }
    //$a[] = '&nbsp;';
    $overview_table->data[] = $a;
    $overview_table->data[] = $b;
    print_heading("Overview");
    print_table($overview_table);
    $table = peerassessment_get_table_weekly_frequency($peerassessment,$members);
    print_heading("Details");    
    break;  
  case PA_FREQ_UNLIMITED:
    $table = peerassessment_get_table_unlimited_frequency($peerassessment,$members);
    break; 
  	
	break;
}


print_table($table);
     
}
else {
  print_box("Please choose a group to display.");
}     