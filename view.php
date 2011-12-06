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

  if (!empty($data->cancel)) {
    //user clicked on the cancel button;
    redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);
    exit();
  }
//die();
  require_capability('mod/peerassessment:recordrating',$context);
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
		if ($ratings === false) {
			//we have a form but for a user that we' haven't got a previous rating for so we need to insert it.
			echo 'Inserting rating for a new member to group.';
			$ins = new stdClass;
			$ins->ratedby = $USER->id;
			$ins->peerassessment = $peerassessment->id;
			$ins->userid=$userid;
			$ins->rating = $value;
			$ins->timemodified = $submittime;
			//$ins->studentcomment  = $comments;  //this will overwrite        
			$result = insert_record('peerassessment_ratings',$ins);
		}
		else {
			//print_object($ratings);
			echo 'Updating existing';
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
		}
     
        /*$success = $success & $result;
        if (!$result) {
             //die("{$success} Failed to record rating of {$value} for user {$userid}.");
        }*/
        
      }
    }
	if ($comments !='') {
		$co = get_record('peerassessment_comments','userid',$USER->id,'peerassessment',$peerassessment->id);
		$co->timemodified= $submittime;
		$co->studentcomment=$comments; 
		update_record('peerassessment_comments',$co);   
	}
          
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
        if ($result) {
         
        }        
        //print_object($ins);
        /*$success = $success & $result;
        if (!$result) {
             //die("{$success} Failed to record rating of {$value} for user {$userid}.");
        }*/
      }
    }
	if ($comments !='') {
		$co = new stdClass;
		$co->userid=$USER->id;
		$co->peerassessment=$peerassessment->id;
		$co->timecreated= $submittime;
		$co->timemodified= $submittime;
		$co->studentcomment=$comments;
		$co_result = insert_record('peerassessment_comments',$co); 
	}
  }
  else {
    //we are already completed but can't edit
    ///we really shoulnd't do anything 
  
  }
  
  //if (!$success) {
      //die('Unable to save whole peer assessment!');
  //}

//print_r($data);
//  peerassessment_update_grades($peerassessment),$USER->id);    //update this user's grade in gradebook
  peerassessment_update_grades(stripslashes_recursive($peerassessment));
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

  if(!$assignment_cm = get_coursemodule_from_id('assignment',$peerassessment->assignment)){//,$peerassessment->course)) {
    die('Couldn\'t get cm for assignment');
  }
  $groupmode = groups_get_activity_groupmode($assignment_cm,$course);

  $groupid = groups_get_activity_group($assignment_cm);
  
  $group_context = get_context_instance(CONTEXT_MODULE, $assignment_cm->id);
}
else {
  $groupmode = groups_get_activity_groupmode($cm);
  $groupid = groups_get_activity_group($cm,true);
  $group_context = get_context_instance(CONTEXT_MODULE, $cm->id);

}

$canRecordRating = has_capability('mod/peerassessment:recordrating',$context,$USER->id);
$canViewReport = has_capability('mod/peerassessment:viewreport',$context,$USER->id);


if (!$group = groups_get_group($groupid)  ) {
  if (!$canViewReport) {
    notice(get_string('nogroup','peerassessment'));
    exit();
  }
}
else {
  //we couldn't get a group
  //This could be due to the fact that we've got "DOANYTHING" rights 
  //or we're just not in a group

}
if (!$canRecordRating & !$canViewReport) {
    $a = new stdClass;
    $a->id = $cm->id;
    notice(get_string('mustbestudent','peerassessment',$cm->id));
    exit();
}

if ($members = groups_get_members($groupid)) {
  if (is_array($members) && !in_array($USER->id,array_keys($members))) {//$USER->id)) {
    notice(get_string('usernotactuallyingroup','peerassessment'));
    add_to_log($course->id, 'peerassessment', 'rate other', "", "Attempted to record attempt for group user wasn't a member of.",$cm->id);
    exit();
  }
}
if ($group) {
  $a = new stdClass;
  $a->peerassessmentname = $peerassessment->name;
  if (substr(strtolower($group->name),0,6) == 'group ') {
    $a->groupname =substr($group->name,6);
  }
  else {
    $a->groupname =$group->name;
  }
  print_heading(get_string('peerassessmentactivityheadingforgroup','peerassessment',$a));
}
else {
  print_heading(get_string('modulename','peerassessment'));
}

$groups = groups_get_activity_allowed_groups($cm);
echo '<table id="layout-table"><tr>';
$lt = (empty($THEME->layouttable)) ? array('left', 'middle', 'right') : $THEME->layouttable;
foreach ($lt as $column) {
    switch ($column) {
        case 'left':
          
          break;
        case 'middle':
          {
          	echo "<td>";
            if ($canViewReport) {
				//echo '<div class="reportlink">';
				if (!$group) {
					print_box_start();
					print_report_select_form($id,$groups,$groupid);
					print_box_end();
				}
				else {
					echo '<div class="reportlink">';
					print_report_select_form($id,$groups,$groupid);
					echo '</div>';
				}
			/*
				$displaygroups= array();
				//display a list of groups to display
				
				foreach($groups as $g) {
					$displaygroups[$g->id] =$g->name;
				}
				//print_r($displaygroups);
				ksort($displaygroups,SORT_STRING);   
				
				echo "<form action='report.php' method='get'><p>". get_string('viewreportgroup','peerassessment');
				choose_from_menu($displaygroups,'selectedgroup',$groupid);
				echo "<input type='hidden' name='id' value='{$id}'/><input type='submit' value='Select'/></p>";
				echo '</form>';
              echo "<a href=\"report.php?id=$cm->id&gid={$groupid}\">".get_string('viewreport', 'peerassessment').'</a>';
              echo '</div>';
			  */
            }
            
            //echo "Completed: ". (int)$alreadyCompleted;
            //if ($alreadyCompleted) {
           // echo ("Aready completed: ".(int)$alreadyCompleted);
            
             $editResponses = $alreadyCompleted && $peerassessment->canedit;
           // echo ("Responses Can be Edited: ".(int)$editResponses);
             switch($alreadyCompleted) {
                case PA_COMPLETED:
                  if ($peerassessment->canedit) {
                    print_box(get_string('alreadycompletedcanedit','peerassessment'));
                  }
                  else {
                    notice(get_string('alreadycompleted','peerassessment'));
                  }
                  break;
                case PA_COMPLETED_THIS_WEEK:
                  if ($peerassessment->canedit) {
                    print_box(get_string('notenoughtimepassedcanedit','peerassessment'));
                  }                  
                  else {
                    notice(get_string('notenoughtimepassed','peerassessment'));
                  }
                  break;
              }
              

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
                //require_capability('mod/peerassessment:recordrating',$context,true);
                if (!empty($peerassessment->additionaltext)){
	                print_box_start('center generalbox','','');
	                echo $peerassessment->additionaltext;
	                print_box_end();
                }
                if ($canRecordRating && $group) {
                  print_box_start('center generalbox','','');
                  //get a list of the all the members of the group that this user is in for the underlying a
                  // assignment
                  //if ($chatusers = chat_get_users($chat->id, $currentgroup, $cm->groupingid)) {
                echo '<form  method="post">';
                echo "<input type='hidden' name='cmid' value='{$cm->id}'/>";
                echo '<table id="members">';
                $strLo = get_string('low','peerassessment');
                $strHo = get_string('high','peerassessment');
                $strName = get_string('name','peerassessment');
                $strcommenthelp = get_string('commenthelp','peerassessment');
                echo "<tr><th></th><th>$strLo</th><th></th><th></th><th></th><th>$strHo</th></tr>";
                echo "<tr><th>$strName</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr>";
                if ($members){
					foreach ($members as $user) {
						if (!has_capability('mod/peerassessment:recordrating',$context,$user->id)) {
						  continue;
						}
						echo "<tr class='peerassessment_row'>";
						echo '<td>';
						echo "<a href='{$CFG->wwwroot}/user/view.php?id={$user->id}' target='_blank'>";                      
						if ($user->id == $USER->id) {
							echo "<strong>{$user->lastname}, {$user->firstname}</strong>";
							//echo ' (You)';
						}
						else {
						  echo "{$user->lastname}, {$user->firstname}";  
						}
						echo "</a>";
						echo '</td>';
						if ($editResponses) {
							$select = "peerassessment={$peerassessment->id} AND ratedby ={$USER->id} AND userid={$user->id}";
							$lastRatingTime = get_field_select('peerassessment_ratings','max(timemodified) as Timestamp',$select);
							$previousResponses = false;
							if ($lastRatingTime =='') {
								// we don't have a last record
							}
							else {
								$previousResponses = get_records_select('peerassessment_ratings',$select . " AND timemodified =$lastRatingTime");
							}
							if ($previousResponses !== false) {
								foreach($previousResponses as $prev) {
									//print_r($prev);
									echo "<td class='peerassessment_center'><input type='radio' name='rating_{$prev->userid}' ";
									if ($prev->rating == 1) { echo 'checked ' ;}
									echo "value='1'></td>";
									echo "<td class='peerassessment_center'><input type='radio' name='rating_{$prev->userid}' ";
									if ($prev->rating == 2) { echo 'checked ' ;}
									echo "value='2'></td>";
									echo "<td class='peerassessment_center'><input type='radio' name='rating_{$prev->userid}' ";
									if ($prev->rating == 3) { echo 'checked ' ;}
									echo "value='3'></td>";
									echo "<td class='peerassessment_center'><input type='radio' name='rating_{$prev->userid}' ";
									if ($prev->rating == 4) { echo 'checked ' ;}
									echo "value='4'></td>";
									echo "<td class='peerassessment_center'><input type='radio' name='rating_{$prev->userid}' ";
									if ($prev->rating == 5) { echo 'checked ' ;}
									echo "value='5'></td>";
								}
							}
							else {
								echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='1'></td>";
								echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='2'></td>";
								echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='3'></td>";
								echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='4'></td>";
								echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='5'></td>";							
								echo "<td class='peerassessment_center'>*</td>";
							}
						}
						else {
							echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='1'></td>";
							echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='2'></td>";
							echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='3'></td>";
							echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='4'></td>";
							echo "<td class='peerassessment_center'><input type='radio' name='rating_{$user->id}' value='5'></td>";
						}
						echo '</tr>'  ;
					}
                }
                else {
                    echo "<tr><td>".get_string('nomembersfound','peerassessment').'</td></tr>';
                }
                echo "<tr><th colspan='6'>Comments</th></tr>";
                echo "<tr><td colspan='6'>$strcommenthelp</td></tr>";
                
                echo "<tr><td colspan='6'><textarea name='comments' rows='5' columns='40' class='peerassessment_fullwidth'>";
                //really should display existing comment
                echo "</textarea></td></tr>";
                echo "<tr><th colspan='6'><input type='submit' value='Save'/><input type='submit' name='cancel' value='Cancel'/></td></tr>";
                echo '</table>';
                echo '</form>';
                print_box_end();
              }
              else  {
				if ($canViewReport & !$group) {
					//print_box('You are staff but not in a group');
					//print_report_select_form($id,$groups,$selectedGroupId);
				}
				else if (!$group) {
					print_box(get_string('nogroup','peerassessment'));
				}
              }
            }
          }
       
          
           
        }
        	echo "</td>";
          break;
        case 'right':
          break;
    }
}
echo '</table>';
print_footer($course);