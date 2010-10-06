<?php

require ('../../config.php');
require_once("lib.php");        
require_once('pagelib.php');

require_once($CFG->dirroot.'/lib/grouplib.php');

$id = optional_param('id',0,PARAM_INT);	  //course module id; 
$p = optional_param('p',0,PARAM_INT);     /*  allows this page to work if we get 
                                              the peer assessment id rather than 
                                              cmid.
                                          */

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
/*
    if (!$group_cm = get_coursemodule_from_id('')) {
      
    }
*/  
} 
else {

    if (! $peerassessment = get_record('peerassessment', 'id', $p)) {
        error('Course module is incorrect');
    }
    if (! $course = get_record('course', 'id', $peerassessment->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('peerassessment', $peerassessment->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
    $id=$cm->id;
}

require_course_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
        // show some info for guests
if (isguestuser()) {
    $navigation = build_navigation('', $cm);
    print_header_simple(format_string($peerassessment->name), '', $navigation,
                  '', '', true, '', navmenu($course, $cm));
    $wwwroot = $CFG->wwwroot.'/login/index.php';
    if (!empty($CFG->loginhttps)) {
        $wwwroot = str_replace('http:','https:', $wwwroot);
    }

    notice_yesno(get_string('noguests', 'chat').'<br /><br />'.get_string('liketologin'),
            $wwwroot, $CFG->wwwroot.'/course/view.php?id='.$course->id);

    print_footer($course);
    exit;

}

$alreadyCompleted = false;

$compareTime = time();
switch($peerassessment->frequency) {
  case PA_FREQ_ONCE:
    //find out if the user has completed this acitivy AT ALL
    //echo '1';
    if (
$ratings = get_records_select('peerassessment_ratings',"ratedby = {$USER->id} AND peerassessment={$peerassessment->id}") 
      ) {
      $alreadyCompleted = PA_COMPLETED;  
      //notice(get_string('alreadycompleted','peerassessment'));
    }
    //print_r($ratings);
    break;
  case PA_FREQ_WEEKLY:
    //echo 'wkly';
    $oneWeekAgo =$compareTime - PA_ONE_WEEK;
    //find out if the user has completed this acitivy within the last week
    if($ratings = get_records_select('peerassessment_ratings',"timemodified>{$oneWeekAgo} AND peerassessment={$peerassessment->id}")) {
      //print_r($ratings);
      //we've got a rating record(s) that are were modified more recenly than a week ago
      $alreadyCompleted =PA_COMPLETED_THIS_WEEK;
      //notice(get_string('notenoughtimepassed','peerassessment'));
    }

    break;
  case PA_FREQ_UNLIMITED:
   // echo 'unlim';
    //just display the thing
    break;
}


$data = data_submitted();
if ($data) {
//print_r($data);
  if ($data->cancel) {
    //user clicked on the cancel button;
    redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);
    exit();
  }
//die();
  if ($alreadyCompleted && $peerassessment->canedit) 
  {
    //we probably have to do an update on each of the existing ratings
    $comments = $data->comments;                  
    $submittime = time();
    foreach((array)$data as $name=>$value) {
      
      if (substr(strtolower($name),0,7) =='rating_') {
        //have a user rating
        //fetch the existing rating
        $userid = substr($name,7);
        
        $select = "SELECT * FROM {$CFG->prefix}peerassessment_ratings WHERE peerassessment = {$peerassessment->id} AND ratedby = {$USER->id} AND userid={$userid}";
        $ratings = get_records_sql($select);
        //print_r($ratings);        
        foreach($ratings as $rating) {
          
          $ins = new stdClass;
          $ins->id = $rating->id;
          $ins->rating = $value;
          $ins->timemodified = $submittime;
          //$result = insert_record('peerassessment_ratings',$ins);
          //print_object($ins);
         // $ins->studentcomment  = $comments;
          $result = update_record('peerassessment_ratings',$ins);
        }
     
        /*$success = $success & $result;
        if (!$result) {
             //die("{$success} Failed to record rating of {$value} for user {$userid}.");
        }*/
        
      }
    }
    $co = get_record('peerassessment_comments','userid',$USER->id,'peerassessment',$peerassessment->id);
    $co->timemodified= $submittime;
    $co->studentcomment=$comments; 
    update_record('peerassessment_comments',$co);   
    //die();       
  }
  else if (!$alreadyCompleted) {
    $comments = $data->comments;            //TODO WE NEED SAVE THIS
    $success = true;
    $submittime = time();// so the ratings are all at the same time
    foreach((array)$data as $name=>$value) {
      
      if (substr(strtolower($name),0,7) =='rating_') {
        //have a user rating
        
        $userid = substr($name,7);
        
        $ins = new stdClass;
        $ins->ratedby = $USER->id;
        $ins->peerassessment = $peerassessment->id;
        $ins->userid=$userid;
        $ins->rating = $value;
        $ins->timemodified = $submittime;
        //$ins->studentcomment  = $comments;  //this will overwrite        
        $result = insert_record('peerassessment_ratings',$ins);
        
        //print_object($ins);
        /*$success = $success & $result;
        if (!$result) {
             //die("{$success} Failed to record rating of {$value} for user {$userid}.");
        }*/
      }
    }
    $co = new stdClass;
    $co->userid=$USER->id;
    $co->peerassessment=$peerassessment->id;
    $co->timecreated= $submittime;
    $co->timemodified= $submittime;
    $co->studentcomment=$comments;
    $co_result = insert_record('peerassessment_comments',$co); 
  }
  else {
    //we are already completed but can't edit
    ///we really shoulnd't do anything 
  
  }
  
  //if (!$success) {
      //die('Unable to save whole peer assessment!');
  //}

//print_r($data);
  redirect($CFG->wwwroot."/course/view.php?id={$course->id}");
  exit();
}




$PAGE       = page_create_instance($peerassessment->id);
$pageblocks = blocks_setup($PAGE);
$blocks_preferred_width = bounded_number(180, blocks_preferred_width($pageblocks[BLOCK_POS_LEFT]), 210);


$PAGE->print_header($course->shortname.': %fullname%');

//check what frequency this is running at and if it should be displayed for the user.
$ratings = false;


//die();

add_to_log($course->id, 'peerassessment', 'view', "view.php?id=$cm->id", $peerassessment->id, $cm->id);

// Initialize $PAGE, compute blocks


//print_r($cm);
$assignment_cm = false;
$group = false;
$groupmode = false;
$group_context=false;
//print_object($peerassessment);
if($peerassessment->assignment) {
  //use the underlying activities
  //echo 'Has assignment';
  if(!$assignment_cm = get_coursemodule_from_id('assignment',$peerassessment->assignment)){//,$peerassessment->course)) {
    die('Couldn\'t get cm for assignment');
  }
 // print_r($assignment_cm);
  $groupmode = groups_get_activity_groupmode($assignment_cm);

  $groupid = groups_get_activity_group($assignment_cm,true);
  
  $group_context = get_context_instance(CONTEXT_MODULE, $assignment_cm->id);
}
else {
  //echo 'doesn\'t have assignment';
  //we should use the peer assessment's group
  $groupmode = groups_get_activity_groupmode($cm);
  $groupid = groups_get_activity_group($cm,true);
  $group_context = get_context_instance(CONTEXT_MODULE, $cm->id);

}

$members = groups_get_members($groupid);

if (!$group = groups_get_group($groupid) ) {
  if (has_capability('moodle/course:manageactivities',$context)) {
    $a = new stdClass;
    $a->id = $cm->id;
    notice(get_string('mustbestudent','peerassessment',$cm->id));
    exit();
  }
  else {
    notice(get_string('nogroup','peerassessment'));
    exit();
  }
}


$a = new stdClass;
$a->peerassessmentname = $peerassessment->name;
if (substr(strtolower($group->name),0,6) == 'group ') {
  $a->groupname =substr($group->name,6);
}
else {
  $a->groupname =$group->name;
}
print_heading(get_string('peerassessmentactivityheadingforgroup','peerassessment',$a));


echo '<table id="layout-table"><tr>';
$lt = (empty($THEME->layouttable)) ? array('left', 'middle', 'right') : $THEME->layouttable;
foreach ($lt as $column) {
    switch ($column) {
        case 'left':
          
          break;
        case 'middle':
          {
            if (has_capability('mod/peerassessment:viewreport',$context)) {
              echo '<div class="reportlink">';
              echo "<a href=\"report.php?id=$cm->id&gid={$groupid}\">".get_string('viewreport', 'peerassessment').'</a>';
              echo '</div>';
            }
            
            //echo "Completed: ". (int)$alreadyCompleted;
            //if ($alreadyCompleted) {
           // echo ("Aready completed: ".(int)$alreadyCompleted);
            
             $editResponses = $alreadyCompleted && $peerassessment->canedit;
           // echo ("Responses Can be Edited: ".(int)$editResponses);
             switch($alreadyCompleted) {
                case PA_COMPLETED:
                  if ($peerassessment->canedit) {
                    print_box(get_string('alreadycompleted','peerassessment'));
                  }
                  else {
                    notice(get_string('alreadycompleted','peerassessment'));
                  }
                  break;
                case PA_COMPLETED_THIS_WEEK:
                  if ($peerassessment->canedit) {
                    print_box(get_string('notenoughtimepassed','peerassessment'));
                  }                  
                  else {
                    notice(get_string('notenoughtimepassed','peerassessment'));
                  }
                  break;
              }
              
            //}
            //else {
           
            if (!$alreadyCompleted | $editResponses) {
            //check that the opening / due times are still OK
              $ctime = time();
              if ($peerassessment->timeavailable!=0 &&
                  $peerassessment->timeavailable > time() &&
                  !has_capability('mod/peerassessment:viewreport',$context)
              ) {
                //and !has_capability('mod/assignment:grade', $this->context)      // grading user can see it anytime
                //and $this->assignment->var3) {                                   // force hiding before available date
                  print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
                  print_string('notavailableyet', 'peerassessment');
                  print_simple_box_end();
              }
              else if ($peerassessment->timedue!=0 
                && $peerassessment->timedue < time() 
                && !has_capability('mod/peerassessment:viewreport',$context)
              ) {
                  print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
                  print_string('expired', 'peerassessment');
                  print_simple_box_end();
              
              }
              else {
                print_container_start();
                //get a list of the all the members of the group that this user is in for the underlying a
                // assignment
                //if ($chatusers = chat_get_users($chat->id, $currentgroup, $cm->groupingid)) {
                echo '<form  method="post">';
                echo "<input type='hidden' name='cmid' value='{$cm->id}'/>";
                echo '<table id="members">';
                echo "<tr><th>Name</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr>";
                if ($members){
                foreach ($members as $user) {
                  if (!has_capability('mod/peerassessment:recordrating',$context,$user->id)) {
                    continue;
                  }
                    echo '<tr>';
                    echo '<td>';
                    echo "{$user->lastname}, {$user->firstname}";
                    if ($user->id == $USER->id) {
                      echo ' (You)';
                    }
                    echo '</td>';
                    echo "<td><input type='radio' name='rating_{$user->id}' value='1'></td>";
                    echo "<td><input type='radio' name='rating_{$user->id}' value='2'></td>";
                    echo "<td><input type='radio' name='rating_{$user->id}' value='3'></td>";
                    echo "<td><input type='radio' name='rating_{$user->id}' value='4'></td>";
                    echo "<td><input type='radio' name='rating_{$user->id}' value='5'></td>";
                    echo '</tr>'  ;
                } 
              }
              else {
                  echo "<tr><td>".get_string('nomembersfound','peerassessment').'</td></tr>';
              }
              echo "<tr><th colspan='6'>Comments</th></tr>";
              echo "<tr><td colspan='6'><textarea name='comments' rows='5' columns='40'>";
              //really should display existing comment
              echo "</textarea></td></tr>";
              echo "<tr><th colspan='6'><input type='submit' value='Save'/><input type='submit' name='cancel' value='Cancel'/></td></tr>";
              echo '</table>';
              echo '</form>';
              print_container_end();
            }
          }
           
        }
          break;
        case 'right':
          break;
    }
}
print_footer($course);