<?php
define('PA_TABLE','peerassessment');
define('PA_FREQ_ONCE',0);
define('PA_FREQ_WEEKLY',1);
define('PA_FREQ_UNLIMITED',2);
define('PA_COMPLETED',1);
define('PA_COMPLETED_THIS_WEEK',2);
define('PA_UPPER_THRESHOLD',3.5);
define('PA_LOWER_THRESHOLD',2.5);
/**
 *  One week in seconds!
 **/ 
define('PA_ONE_WEEK',604800);//000);
//define('PA_ONE_WEEK',60*60*24); // 5 seconds (for testing only!)
/**
 * This function enables our "test role" to work
 * 
 * Returns a list of "types" which are displayed in the add fields.  
 */ 
function peerassessment_get_types() {
  global $USER,$COURSE;
  $context =  get_context_instance(CONTEXT_COURSE,$COURSE->id);
  if (has_capability('mod/peerassessment:usepeerassessment',$context)) {
    $type = new stdclass;
    $type->modclass=MOD_CLASS_ACTIVITY;
    $type->type='peerassessment';
    $type->typestr=get_string('modulename','peerassessment');
    return array($type);     
  }
  return array(); 
}

function peerassessment_add_instance($pa) {
  global $USER;
  
  //print_object($pa);
  
  if (!$returnid = insert_record('peerassessment',$pa)) {
    return false;
  }
  $pa->id=$returnid;        
  peerassessment_grade_item_update($pa);//stripslashes_recursive($pa));
  return $returnid;                              
}

function peerassessment_update_instance($pa) {
  //print_object($pa);
  $pa->id = $pa->instance;
  if (!$returnid = update_record('peerassessment',$pa)) {
    return false;  
  }
  //peerassessment_grade_item_update(stripslashes_recursive($pa));
  peerassessment_grade_item_update($pa);  
  //peerassessment_update_grades(stripslashes_recursive($pa),0,false);
  return $returnid;
}

function peerassessment_delete_instance($id) {
  if (! $pa = get_record('peerassessment','id',$id)) {
    return false;
  }
  $result = true;
  if (! delete_records('peerassessment','id',$pa->id)) {
    $result = false;
  }  
  if (! delete_records('peerassessment_ratings','peerassessment',$pa->id)) {
    $result = false;
  }
    
  if ($events = get_records_select('event',"modulename = 'peerassessment' and instance = '{$pa->id}'")) {
    foreach($events as $event) {
      delete_event($event->id);
    }
  }  
  peerassessment_grade_item_delete($pa);  
  //die("deleteing {$pa}");
  return $result;
}
function peerassessment_get_user_grades($pa,$userid=0) {
  global $CFG;
  $user = $userid ? "AND userid=$userid" :"";
  
  $sql = "SELECT userid as userid, AVG(rating) AS rawgrade FROM {$CFG->prefix}peerassessment_ratings WHERE peerassessment={$pa->id} $user GROUP BY userid";

  //TODO we still need to sling the comment into the grade object.
  
  $grades = get_records_sql($sql);
  $i=0;
  foreach($grades as $grade) {
    //$i++;
    
    //$grade->feedback = 'Feedback '.$i;
  }
//print_r($grades);
  return $grades;
  //return $grades;
/*  
  //print_r($grades);
  //print_r($pa);
  
  
  $results = array();
  $r = new stdClass;
  $r->userid = $userid;
  $r->rawgrade = 0;
  $r->feedback ='ffedback';
  $r->usermodified = 0;
  $r->dategraded = 0;
  $r->datesubmitted = 0;
  $results[$r->userid]=$r;        
  print_object($results);             
  return $results;// get_records_sql($sql); 
  */ 
}
$pugCounter = 0;
/** 
 * Updates the grades in the Gradebook
 */ 
function peerassessment_update_grades($pa = null,$userid=0, $nullifnone=true) {
  global $CFG, $pugCounter;
  //echo debug_backtrace();
  $pugCounter++;   
  if (!function_exists('grade_update')) {
    require_once($CFG->libdir.'/gradelib.php');
  }
  //die('updating grade 1');
  if ($pa != null) {
    
    if ($grades = peerassessment_get_user_grades($pa,$userid)) {
      peerassessment_grade_item_update($pa,$grades);      
    }
    else if ($userid and $nullifnone) {
      $grade = new Stdclass;
      $grade->userid=$userid;
      $grade->rawgrade = NULL;
      //die('p_u_g 1');
      peerassessment_grade_item_update($pa,$grade);
    }
    else {
      //die('p_u_g 2');
      peerassessment_grade_item_update($pa);    
    }
    //die('updating grade 2');  
  }
  //die('updating grade END');  
  //return true;
}

function peerassessment_grade_item_delete($data) {
  global $CFG;
  if (!function_exists('grade_update')) {
    require_once($CFG->libdir.'/gradelib.php');
  }
  return grade_update('mod/peerassessment',$data->course,'mod','peerassessment',$data->id,0,NULL, array('deleted'=>1));
}

/**
 * Creates a grade item for a particular peerassessment activity
 */  
function peerassessment_grade_item_update($pa,$grades=null) {
  global $CFG;

  if (!function_exists('grade_update')) {
    require_once($CFG->libdir.'/gradelib.php');
  }

  $params =array('itemname'=>$pa->name);
  $params['gradetype'] = GRADE_TYPE_VALUE;
  $params['grademax'] = 5;
  $params['grademin'] = 1;
  if ($grades ==='reset') {
    $params['reset'] = true;
    $grades= NULL;
  }
  else if (!empty($grades)){
  
    if (is_object($grades)) {
      $grades = array($grades->userid =>$grades);
    }
    else if(array_key_exists('userid',$grades)) {
      $grades = array($grades['userid'] => $grades);
    }
    foreach($grades as $key=>$grade) {
      if (!is_array($grade)) {
        $grades[$key]= $grade = (array)$grade;
      }
      $grades[$key]['rawgrade'] = ($grade['rawgrade']);
    }
    
  }
  $r = grade_update('mod/peerassessment',$pa->course,'mod','peerassessment',$pa->id,0,$grades,$params);
//print_r($grades);
  return $r;
}

function peerassessment_reset_gradebook() {

}


/*
 * From here on is all custom code, above is "Moodle" required code
 */ 

function get_week_start_from_time($start_date){
  return mktime(0, 0, 0, date('m', $start_date), date('d', $start_date), date('Y', $start_date)) - ((date("w", $start_date) ==0) ? 0 : (86400 * date("w", $start_date)));
}


/**
 * Returns a table displaying the results for SINGLE frequency Peerassessment
 */ 
function peerassessment_get_table_single_frequency($peerassessment,$group) {
  global $CFG;
  //print_r($group);

  $cm = get_coursemodule_from_instance('peerassessment',$peerassessment->id);  
  $context = get_context_instance(CONTEXT_MODULE,$cm->id);

  $members = groups_get_members($group->id);
  $table=new stdClass;
  $table->head = array();
  //$table->head[] ="";
  $table->head[] ="Student\Recipient &gt;";
  foreach($members as $m2) {
    if (!has_capability('mod/peerassessment:recordrating',$context,$m2->id)) {
      continue;
    }
    $table->head[] = "{$m2->lastname}, {$m2->firstname}";// ({$m2->id})";
  }
  $table->head[] = "Average Rating Given";
  $recieved_totals = array();
  $recieved_counts = array();

  $timemodified = -1;

   
  foreach($members as $m) {
    if (!has_capability('mod/peerassessment:recordrating',$context,$m->id)) {
      continue;
    }
    $a = array();
    $select ="userid = {$m->id} AND peerassessment={$peerassessment->id}";
    $comments = get_records_select('peerassessment_comments',$select);
//    print_r($comment);
    $name = "{$m->lastname}, {$m->firstname}";// ({$m->id})";
    if($comments) {
      //$c = addslashes($comment->studentcomment);
      $name .="<sup>";
      $c='';
      foreach($comments as $comment) {
        $c = "$comment->studentcomment\n". $c;
        
      }
      $name.="<span class='popup' title=\"{$c}\"><a href='{$CFG->wwwroot}/mod/peerassessment/comments.php?p={$peerassessment->id}&userid={$m->id}'>[Comment]</a></span></sup>";
      $name .="<sup>";
      
    }
    $a[] = $name;
    $t1 = 0;
    $c=0;
    //$recieved_counts[$m2->id] = 0;
    //$recieved_totals[$m2->id] = 0;             
    $hasEntries = false;   
    foreach($members as $m2) {
      if (!has_capability('mod/peerassessment:recordrating',$context,$m2->id)) {
        continue;
      }
      $sql ="SELECT * FROM {$CFG->prefix}peerassessment_ratings WHERE peerassessment={$peerassessment->id} AND ratedby={$m->id} AND userid={$m2->id}";
      //echo $sql;
      $rating = get_record_sql($sql);
      //print_object($rating);
      
      if ($rating) {
        $hasEntries = true;
        $timemodified = $rating->timemodified;
        //we have a ratiing fo r this user
        //$rating = $ratings[0];
        $a[] = $rating->rating ;//.print_delete_attempt_form($peerassessment,$m->id,$rating,null);
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
      $a['avggiven'] = get_average_rating_by_student($peerassessment,$m->id);
    if (has_capability('mod/peerassessment:deleteratings',$context)) {     
      if ($hasEntries) {
        $a[''] = print_delete_attempt_form($peerassessment,$group,$m->id,null,$timemodified);
      }
    }
    else {
      //$a[] ='';
    }
    
    /*
    if ($c>0) {
      $a[] = $t1/$c;
    }
    else {$a[] ='';}
    */
    $table->data[] = $a;
  }
  //output the average grade received by top of column
  $a = array();
  $a['avgrecieved'] = 'Average Rating Recieved';
  //$table->rowclass['avgrecieved']='header';
  foreach($members as $m) {
    if (!has_capability('mod/peerassessment:recordrating',$context,$m->id)) {
      continue;
    }
    $a[] = get_average_rating_for_student($peerassessment,$m->id);
    /*
    $recieved_ave=''; 
    if (isset($recieved_counts[$m->id]) && $recieved_counts[$m->id] > 0 ) {
      $recieved_ave = $recieved_totals[$m->id] / $recieved_counts[$m->id];
    }
    else {
      $recieved_ave ='&nbsp;';
    }
    $a[] = $recieved_ave;        //NOTE THIS IS ALSO THE RESULT THAT SHOULD GO TO GRADEBOOK!
    */
  }
  //$a[] = '&nbsp;';
  $table->data['avgrow'] = $a;
  
  return $table;
}

function peerassessment_get_table_weekly_frequency($peerassessment,$group,$showdetails=true) {
  global $CFG;
  $cm = get_coursemodule_from_instance('peerassessment',$peerassessment->id);  
  $context = get_context_instance(CONTEXT_MODULE,$cm->id);
  $user_can_delete_ratings = has_capability('mod/peerassessment:deleteratings',$context);
  
  $members = $members = groups_get_members($group->id);
  $table=new stdClass;
  $table->head = array();
  //$table->head[] ="";
  
  foreach($members as $m2) {
     // $table->head[] = "{$m2->lastname}, {$m2->firstname} ({$m2->id})";
	 
  }
  $recieved_totals = array();
  $recieved_counts = array();

  $heading = array();
  $heading[]='';
  $heading[]='';
  //get earliest recorded entry
  $earliest_sql = "SELECT min(timemodified) AS timemodifed FROM {$CFG->prefix}peerassessment_ratings WHERE  peerassessment ={$peerassessment->id} ";
  $earliest_rs = get_record_sql($earliest_sql);
  //print_r($earliest_rs);
  if (!isset($earliest_rs->timemodifed)) {
    $table->data[] = array('No Entries');
    return $table;
  } 
  $earliest_date=strtotime("last monday", $earliest_rs->timemodifed);
  //echo "Start of week: ".date('r',$earliest_date). ':'.$earliest_date;
  $last_sql ="SELECT max(timemodified) AS timemodifed FROM {$CFG->prefix}peerassessment_ratings WHERE  peerassessment ={$peerassessment->id}"; 
  $last_rs = get_record_sql($last_sql);
  $last_date =strtotime("next sunday", $last_rs->timemodifed); 
  //echo "Last day of entries: ".date('r',$last_date).":".$last_date;
  
  $duration_secs = $last_date -$earliest_date; //gives number of seconds entries have been made over
  $duration_weeks = ceil($duration_secs/PA_ONE_WEEK);//((($duration_secs/60)/60)/24)/7);
  echo "Duration of entries: ".$duration_weeks.' periods ';
  //get_week_start_from_time($start_date
  //if ($duration_weeks > 10) {
    //$duration_weeks = 1
  //}  
  $startDate = $earliest_date;
  $entries_for_week = array();
  for($i = 0 ;$i < $duration_weeks; $i++) {
    //get all of the entries for the given week and each member
    $offset="+ ".PA_ONE_WEEK .' seconds';
    $endDate = strtotime($offset,$startDate); //this is the sunday/monday midnight
   // echo (date('r',$startDate) .'-'.date('r',$endDate)."<br />");
    $entries_for_week_sql ="SELECT * FROM {$CFG->prefix}peerassessment_ratings WHERE peerassessment={$peerassessment->id} AND timemodified >= $startDate and timemodified<=$endDate";
    $entries = get_records_sql($entries_for_week_sql);
//print_object($entries);    
    $entries_for_week[$startDate] = array();//
    if ($entries) {
      foreach($entries as $e) {
        if (!isset($entries_for_week[$startDate][$e->ratedby])) {
          $entries_for_week[$startDate][$e->ratedby]= array();      
        }
        $entries_for_week[$startDate][$e->ratedby][$e->userid]= $e;
      }
    }
    $startDate = $endDate; 
  }
  /*we should now have an array of "mondays", containing an array of 
  userids (representing the user who MADE the rating), with each value 
  containing an array of the ratings they actually made.
  */
  $doneheadings = false;
  $userheadings = array();
  $done_user_headings =false;
  foreach($members as $m1) 
  {
  /*
    if (!has_capability('mod/peerassessment:recordrating',$context,$m2->id)) {
		continue;
    }
	*/
    $t1 = 0;
    $c = 0;
    //echo $m1->id .":".$m1->lastname .', '.$m1->firstname;
    $row=array();
    $row[] = $m1->lastname .', '.$m1->firstname;   
    $userheadings[] = 'Student';
    $userheadings[] = 'Average Rating Given';  //we merged the average to the first column 
    foreach($entries_for_week as $week => $value) {
      //echo 'Week Starting ' .date('d-M-Y',$week);
      if (!$doneheadings && $showdetails) {
        $heading[]='Week Starting ' .date('D d-M-Y',$week);
        for($j= 0 ;$j<count($members)-1;$j++) {
          $heading[] ='';
        }
      //$row[] ='';
      }
      else {
        $heading[] ='';
      }
      $user_entries = $value;//$entries_for_week[$m1->id];
      foreach($members as $m2) 
      {
        //echo 'Looking at '.$m2->id;
        if (!$doneheadings) {
          //$heading[]='';//Week Starting ' .date('d-M-Y',$week);
      //$row[] ='';
        }
        if ($done_user_headings) {
          //$row[]='';
        } 
        else {
          if ($showdetails) {
            $userheadings[] = $m2->lastname .', '.$m2->firstname;
          }
        }
        //$row[] ='';
        if (isset($user_entries[$m1->id][$m2->id])) {
          $entry = $user_entries[$m1->id][$m2->id];
          if (true){//$showdetails) {
			$content =$entry->rating; 
			if ($user_can_delete_ratings) {     
			//function print_delete_attempt_form($peerassessment,$group,$userid,$rating=null,$timemodified=null, $return = true) {
				$content .= print_delete_attempt_form($peerassessment,$group,$m1->id,$entry,$entry->timemodified,true);
			}
            $row[]  = $content;
          }
          $t1 = $t1+$entry->rating;
          $c++;
          if (!isset( $recieved_totals[$m2->id]) ) {
            $recieved_totals[$m2->id] =0;      
          }
          $recieved_totals[$m2->id] = $recieved_totals[$m2->id]+$entry->rating;
          if (!isset( $recieved_counts[$m2->id]) ) {
           $recieved_counts[$m2->id] = 0;
          }
          $recieved_counts[$m2->id] = $recieved_counts[$m2->id]+1;
        }
        else {
          if ($showdetails) {
            $row[]='-';
          }
        }
        //}
         
      }
      
          
      //$table->data[] = array('Week Starting ' .date('r',$week));//date('r',$week);
    }
    
    if (!$doneheadings) {
        $heading[] = ''; 
        if ($showdetails) {
          $table->data[] = $heading;
        }
        else {
        
        }
        $doneheadings = true;
    }     
    if (!$done_user_headings) {
        //$userheadings[] = 'Average Rating Given';
        if ($showdetails) {
        $table->data[] = $userheadings;
        }
        else {
          $table->head = $userheadings;        
        }
        $done_user_headings = true;
      }
    $name = array_slice($row,0,1);
    $values = array_slice($row,1);
    $ave = array(get_average_rating_by_student($peerassessment,$m1->id));
    $row = array_merge($name,$ave,$values);
    //if ($c>0) {
      //$row[] = $t1/$c;
    //}
    //else {$row[] ='';}
    $table->data[$m1->id] = $row; 
    
  }  
  //$table->head=$heading; 
  
  $a = array();
  //$a[] = '';
  $a[]='';
  $a[] ='Average rating recieved';
  foreach($members as $m1) {
    $a[] = get_average_rating_for_student($peerassessment,$m1->id);
  }  
  $table->data[] = $a;//rray('Average rating recieved'); 
  
  return $table;
}

function peerassessment_get_table_unlimited_frequency($peerassessment,$group) {
  $table=new stdClass;
  $table->head = array();
  $members = $members = groups_get_members($group->id);
  //$table->head[] ="";
  $table->head[] ="Student\Recipient &gt;";
  foreach($members as $m2) {
      $table->head[] = "{$m2->lastname}, {$m2->firstname}";//({$m2->id})";
  }
  $recieved_totals = array();
  $recieved_counts = array();
  
  return $table;
}

function get_average_rating_for_student($peerassessment,$userid) {
  global $CFG;
  $sql = "SELECT AVG(rating) AS average FROM {$CFG->prefix}peerassessment_ratings WHERE peerassessment={$peerassessment->id} AND userid={$userid}";
  $rs = get_record_sql($sql);
  if ($rs->average >PA_UPPER_THRESHOLD) {
    return "<span style='color:green'><sup>+</sup>".$rs->average."</span>";//"<img src='abovethreshold.png' alt='Above Threshold'/>";
  }  
  if ($rs->average < PA_LOWER_THRESHOLD) {
    return "<span style='color:red'><sup style='color:red'>-</sup>".$rs->average."</span>";//"<img src='belowthreshold.png' alt='Below Threshold'/>";
  }
  return $rs->average;
}
function get_average_rating_by_student($peerassessment,$userid) {
   global $CFG;
  $sql = "SELECT AVG(rating) AS average FROM {$CFG->prefix}peerassessment_ratings WHERE peerassessment={$peerassessment->id} AND ratedby={$userid}";
  $rs = get_record_sql($sql);
  //print_r($rs);  
  if ($rs->average >PA_UPPER_THRESHOLD) {
    return "<span style='color:green'><sup style='color:green'>+</sup>".$rs->average."</span>";//"<img src='abovethreshold.png' alt='Above Threshold'/>";
  }  
  if ($rs->average <PA_LOWER_THRESHOLD) {
    return "<span style='color:red'><sup style='color:red'>-</sup>".$rs->average ."</span>";//<img src='belowthreshold.png' alt='Below Threshold'/>";
  }
  return $rs->average;
}

/**
 * Displays a form that allows an appropriate user to delete a rating or ratings.
 **/ 
function print_delete_attempt_form($peerassessment,$group,$userid,$rating=null,$timemodified=null, $return = true) {
  global $CFG, $USER;
  $cm = get_coursemodule_from_instance('peerassessment',$peerassessment->id);  
  $context = get_context_instance(CONTEXT_MODULE,$cm->id);
  if (!has_capability('mod/peerassessment:deleteratings',$context)) {
	return '';  //don't return a form
  }
  $out = "<form action='report.php' method='post'>";
  
  $ratingid='';
  if(!is_null($rating)) {
    $ratingid=$rating->id;  
  }
  $out.="<input type='hidden' name='ratingid' value='{$ratingid}'/>";
  $out.="<input type='hidden' name='ratingtime' value='{$timemodified}'/>";
  $out.="<input type='hidden' name='peerassessment' value='{$peerassessment->id}'/>";
  $out.="<input type='hidden' name='userid' value='{$userid}'/>";
  $out.="<input type='hidden' name='selectedgroup' value='{$group->id}'/>";  
  $out.="<input type='submit' name='delete' value='Delete'/>";
  $out.="</form>";                                                               

  if ($return) {
    return $out;
  }
  echo $out;
}

function print_report_select_form($id,$groups,$selectedGroupId) {
	$displaygroups= array();
	//display a list of groups to display
	//$groups = groups_get_activity_allowed_groups($cm);
    if (!$groups) {
        notify (get_string('nogroups','peerassessment'));
        return;
    }
	foreach($groups as $g) {
		$displaygroups[$g->id] =$g->name;
	}
	//print_r($displaygroups);
	ksort($displaygroups,SORT_STRING);   
	
	echo "<form action='report.php' method='get'><p>". get_string('viewreportgroup','peerassessment');
	choose_from_menu($displaygroups,'selectedgroup',$selectedGroupId);
	echo "<input type='hidden' name='id' value='{$id}'/><input type='submit' value='Select'/></p>";
	echo '</form>';
  /*echo "<a href=\"report.php?id=$cm->id&gid={$groupid}\">".get_string('viewreport', 'peerassessment').'</a>';*/
   //echo '</div>';

}