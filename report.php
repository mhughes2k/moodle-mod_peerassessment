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

$table = new stdClass;

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
$table=new stdClass;
$table->head = array();
//$table->head[] ="";
$table->head[] ="Student\Recipient &gt;";
foreach($members as $m2) {
    $table->head[] = "{$m2->lastname}, {$m2->firstname} ({$m2->id})";
}
$recieved_totals = array();
$recieved_counts = array();

foreach($members as $m) {
  $a = array();
  $name = "{$m->lastname}, {$m->firstname} ({$m->id})";
  $a[] = $name;
  $t1 = 0;
  $c=0;
  //$recieved_counts[$m2->id] = 0;
  //$recieved_totals[$m2->id] = 0;             

  foreach($members as $m2) {
    $sql ="SELECT * FROM {$CFG->prefix}peerassessment_ratings WHERE peerassessment={$peerassessment->id} AND ratedby={$m->id} AND userid={$m2->id}";
    //echo $sql;
    $rating = get_record_sql($sql);
    //print_object($rating);
    if ($rating) {
      //we have a ratiing fo r this user
      //$rating = $ratings[0];
      $a[] = $rating->rating;
      $t1 = $t1+$rating->rating;
      $c++;
      if (!isset( $recieved_totals[$m2->id]) ) {
        $recieved_totals[$m2->id] =0;      
      }
      $recieved_totals[$m2->id] = $recieved_totals[$m2->id]+$rating->rating;
      if (!isset( $recieved_counts[$m2->id]) ) {
         $recieved_counts[$m2->id] = 0;
      }
      $recieved_counts[$m2->id] = $recieved_counts[$m2->id]+1;
    }
    else {
      $a[] ='-';
    }
  }
  //now display the average mark that m GAVE
  if ($c>0) {
    $a[] = $t1/$c;
  }
  else {$a[] ='';}
  $table->data[] = $a;
}
//output the average grade received by top of column
$a = array();
$a[] = '';
foreach($members as $m) {
  $recieved_ave=''; 
  if (isset($recieved_counts[$m->id]) && $recieved_counts[$m->id] > 0 ) {
    $recieved_ave = $recieved_totals[$m->id] / $recieved_counts[$m->id];
  }
  else {
    $recieved_ave ='&nbsp;';
  }
  $a[] = $recieved_ave;        //NOTE THIS IS ALSO THE RESULT THAT SHOULD GO TO GRADEBOOK!
}
$a[] = '&nbsp;';
$table->data[] = $a;

print_table($table);
     
}
else {
  print_box("Please choose a group to display.");
}     