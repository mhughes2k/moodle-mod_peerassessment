<?php
define('PA_TABLE', 'peerassessment');
define('PA_FREQ_ONCE', 0);
define('PA_FREQ_WEEKLY', 1);
define('PA_FREQ_UNLIMITED', 2);
define('PA_COMPLETED', 1);
define('PA_COMPLETED_THIS_WEEK', 2);
define('PA_UPPER_THRESHOLD', 3.5);
define('PA_LOWER_THRESHOLD', 2.5);
/**
 *  One week in seconds!
 **/
define('PA_ONE_WEEK', 604800);
//define('PA_ONE_WEEK',60*60*24); // 5 seconds (for testing only!)
/**
 * This function enables our "test role" to work
 *
 * Returns a list of "types" which are displayed in the add fields.
 */
/*
function peerassessment_get_types() {
    global $DB, $USER, $COURSE;
    $context =  get_context_instance(CONTEXT_COURSE, $COURSE->id);
    if (has_capability('mod/peerassessment:usepeerassessment', $context)) {
        $type = new stdclass;
        $type->type='peerassessment';
        $type->typestr=get_string('modulename', 'peerassessment');
        $type->modclass = MOD_CLASS_ACTIVITY;
        return array($type);
    }
    return array();
}
*/
function peerassessment_add_instance($pa) {
    global $DB, $USER;
    if (empty($pa->assignment)) {
    	$pa->assignment = 0;	//we may consider dropping this in a future version.
    }
    if (!$returnid = $DB->insert_record('peerassessment', $pa)) {
        return false;
    }
    $pa->id=$returnid;
    peerassessment_grade_item_update($pa);
    return $returnid;
}

function peerassessment_update_instance($pa) {
    global $DB;
    $pa->id = $pa->instance;
    unset($pa->introformat);
    if (!$returnid = $DB->update_record('peerassessment', $pa)) {
        return false;
    }
    peerassessment_grade_item_update($pa);
    return $returnid;
}

function peerassessment_delete_instance($id) {
    global $DB;
    if (! $pa = $DB->get_record('peerassessment', array('id'=>$id))) {
        return false;
    }
    $result = true;
    if (! $DB->delete_records('peerassessment', array('id'=>$pa->id))) {
        $result = false;
    }
    if (! $DB->delete_records('peerassessment_ratings', array('peerassessment'=>$pa->id))) {
        $result = false;
    }
    if ($events = $DB->get_records_select('event', "modulename = 'peerassessment' and instance = '{$pa->id}'")) {
        foreach ($events as $event) {
            delete_event($event->id);
        }
    }
    peerassessment_grade_item_delete($pa);
    return $result;
}
function peerassessment_get_user_grades($pa, $userid=0) {

    global $CFG, $DB;
    $user = $userid ? "AND userid=$userid" :"";

    $sql = "SELECT userid as userid, AVG(rating) AS rawgrade FROM ";
    $sql .="{$CFG->prefix}peerassessment_ratings WHERE peerassessment={$pa->id} ";
    $sql .="$user GROUP BY userid";

    //TODO we still need to sling the comment into the grade object.

    $grades = $DB->get_records_sql($sql);
    $i=0;
    return $grades;
}

$pugcounter = 0;    //What was this for?
/**
 * Updates the grades in the Gradebook
 */
function peerassessment_update_grades($pa = null, $userid=0, $nullifnone=true) {
    global $CFG, $pugcounter;

    $pugcounter++;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }
    if ($pa != null) {
        if ($grades = peerassessment_get_user_grades($pa, $userid)) {
            peerassessment_grade_item_update($pa, $grades);
        } else if ($userid and $nullifnone) {
            $grade = new Stdclass;
            $grade->userid=$userid;
            $grade->rawgrade = null;
              peerassessment_grade_item_update($pa, $grade);
        } else {
            peerassessment_grade_item_update($pa);
        }
    }
}

function peerassessment_grade_item_delete($data) {
    global $CFG;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }
    return grade_update('mod/peerassessment',
        $data->course,
        'mod',
        'peerassessment',
        $data->id,
        0,
        null,
        array('deleted'=>1)
    );
}

/**
 * Creates a grade item for a particular peerassessment activity
 */
function peerassessment_grade_item_update($pa, $grades=null) {
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
        $grades= null;
    } else if (!empty($grades)) {
        if (is_object($grades)) {
            $grades = array($grades->userid =>$grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid'] => $grades);
        }
        foreach ($grades as $key => $grade) {
            if (!is_array($grade)) {
                $grades[$key]= $grade = (array)$grade;
            }
            $grades[$key]['rawgrade'] = ($grade['rawgrade']);
        }
    }
    $r = grade_update('mod/peerassessment', $pa->course, 'mod', 'peerassessment', $pa->id, 0, $grades, $params);
    return $r;
}

/**
 * We only allow a full reset of everything!
 * @param unknown $mform
 */
function peerassessment_reset_course_form_definition(&$mform) {
	$mform->addElement('header', 'peerassessmentheader', get_string('modulenameplural', 'peerassessment'));

	$mform->addElement('checkbox', 'reset_peerassessment_all', get_string('resetpeerassessmentall', 'peerassessment'));
}

/**
 * Course reset form defaults.
 * @return array
 */
function peerassessment_reset_course_form_defaults($course) {
	return array('reset_peerassessment_all'=>1);
}

/**
 * Resets the peer assessment activites
 *
 * This deletes the comments and ratings content.
 *
 * If any deletion fails whilst processing the course then whole thing fails.
 * @param unknown $data
 */
function peerassessment_reset_userdata($data) {
	global $DB;
	$comstr = get_string('modulename', 'peerassessment');
	$result_ratings = false;
	$result_comments = false;
	$result_gradebook = false;
	$overall = true;
	$error_str  = '';
	$status = array();

	if (!empty($data->reset_peerassessment_all)) {
		$pasql = "SELECT pa.id
					   FROM {peerassessment} pa
					  WHERE pa.course = ?";

		$params = array($data->courseid);//implode(',',$pas);
		//delete the ratings
		$result_ratings = $DB->delete_records_select('peerassessment_ratings',
				"peerassessment IN ($pasql)",
				$params
				);
		if (!$result_ratings) {
			$status[] =  array('component' => $comstr, 'item' => 'Remove ratings', 'error' => 'Unable to delete ratings');
			$overall = $overall & false;
		} else {
			$status[] =  array('component' => $comstr, 'item' => 'Remove ratings', 'error' => false);
			$overall = $overall & true;
		}

		//delete the comments
		$result_comments = $DB->delete_records_select('peerassessment_comments',
				"peerassessment IN ($pasql)",
				$params
				);
		if (!$result_comments) {
			$status[] =  array('component' => $comstr, 'item' => 'Remove comments', 'error' => 'Unable to delete comments');
			$overall = $overall & false;
		} else {
			$status[] =  array('component' => $comstr, 'item' => 'Remove comments', 'error' => false);
			$overall = $overall & true;
		}

		//reset grades
		peerassessment_reset_gradebook($data->courseid);
		if ($overall) {
			$status[]  =  array('component' => $comstr, 'item' => 'Reset Peer Assessments', 'error' => false);
		} else {
			//$status[]  =  array('component' => $comstr, 'item' => 'Reset Peer Assessments', 'error' => $error_str);
		}
	}
	return $status;
}
/**
 * Resets the grade boook data.
 * @param unknown $courseid
 * @param string $type
 */
function peerassessment_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    /*
    if ($type) {
        $wheresql = "AND pa.type=?";
        $params[] = $type;
    }*/

    $sql = "SELECT pa.*, cm.idnumber as cmidnumber, pa.course as courseid
              FROM {peerassessment} pa, {course_modules} cm, {modules} m
             WHERE m.name='peerassessment' AND m.id=cm.module AND cm.instance=pa.id AND pa.course=? $wheresql";

    if ($forums = $DB->get_records_sql($sql, $params)) {
        foreach ($forums as $forum) {
            // peerassessment_grade_item_update($forum, 'reset');
        }
    }
}


/*
 * From here on is all custom code, above is "Moodle" required code
 */
function peerassessment_get_week_start_from_time($start_date) {
    return mktime(0, 0, 0, date('m', $start_date), date('d', $start_date), date('Y', $start_date))
    -
    ((date("w", $start_date) ==0) ? 0 : (86400 * date("w", $start_date)));
}

/**
 * Returns a table displaying the results for SINGLE frequency Peerassessment
 */
function peerassessment_get_table_single_frequency($peerassessment, $group) {
    global $CFG, $DB;

    $cm = get_coursemodule_from_instance('peerassessment', $peerassessment->id);
    $context = context_course::instance($cm->course);//@TODO check this works.

    $members = groups_get_members($group->id);
    $table= new html_table();
    $table->head = array();
    $table->head[] ="Student\Recipient &gt;";
    foreach ($members as $m2) {
        if (!has_capability('mod/peerassessment:recordrating', $context, $m2->id)) {
            continue;
        }
        $table->head[] = "{$m2->lastname}, {$m2->firstname}";// ({$m2->id})";
    }
    $table->head[] = "Average Rating Given";
    $recieved_totals = array();
    $recieved_counts = array();

    $timemodified = -1;

    foreach ($members as $m) {
        if (!has_capability('mod/peerassessment:recordrating', $context, $m->id)) {
            continue;
        }
        $a = array();
        $select ="userid = {$m->id} AND peerassessment={$peerassessment->id}";
        $comments = $DB->get_records_select('peerassessment_comments', $select);
        $name = "{$m->lastname}, {$m->firstname}";// ({$m->id})";
        if ($comments) {
            $name .="<sup>";
            $c='';
            foreach ($comments as $comment) {
                $c = "$comment->studentcomment\n". $c;
            }
            $name.="<span class='popup' title=\"{$c}\">";
            $name.="<a href='{$CFG->wwwroot}/mod/peerassessment/comments.php?p={$peerassessment->id}&userid={$m->id}'>";
            $name.="<img src='{$CFG->wwwroot}/mod/peerassessment/comment.gif' alt='Comment'></img></a></span>";
        }
        $a[] = $name;
        $t1 = 0;
        $c=0;
        $hasentries = false;
        foreach ($members as $m2) {
            if (!has_capability('mod/peerassessment:recordrating', $context, $m2->id)) {
                continue;
            }
            $sql ="SELECT * FROM {$CFG->prefix}peerassessment_ratings
                    WHERE peerassessment={$peerassessment->id} AND ratedby={$m->id} AND userid={$m2->id}";
            $rating = $DB->get_record_sql($sql);
            if ($rating) {
                $hasentries = true;
                $timemodified = $rating->timemodified;

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
            } else {
                $a[] ='-';
            }
        }
        $a['avggiven'] = peerassessment_get_average_rating_by_student($peerassessment, $m->id);
        if (has_capability('mod/peerassessment:deleteratings', $context)) {
            if ($hasentries) {
                $a[''] = print_delete_attempt_form($peerassessment, $group, $m->id, null, $timemodified);
            }
        }
        $table->data[] = $a;
    }
    //output the average grade received by top of column
    $a = array();
    $a['avgrecieved'] = 'Average Rating Received';
    foreach ($members as $m) {
        if (!has_capability('mod/peerassessment:recordrating', $context, $m->id)) {
            continue;
        }
        $a[] = peerassessment_get_average_rating_for_student($peerassessment, $m->id);
    }
    $table->data['avgrow'] = $a;
    return $table;
}

function peerassessment_get_table_weekly_frequency($peerassessment, $group, $showdetails=true) {
    global $CFG, $DB;
    $cm = get_coursemodule_from_instance('peerassessment', $peerassessment->id);
//print_r($cm);
    $context = context_course::instance($cm->course);
    $user_can_delete_ratings = has_capability('mod/peerassessment:deleteratings', $context);

    $members = $members = groups_get_members($group->id);
//    $table=new stdClass;
    $table = new html_table();
    $table->head = array();
    $recieved_totals = array();
    $recieved_counts = array();

    $heading = array();
    $heading[]='';
    $heading[]='';

    $earliest_sql = "SELECT min(timemodified) AS timemodifed
                       FROM {$CFG->prefix}peerassessment_ratings
                      WHERE  peerassessment ={$peerassessment->id} ";
    $earliest_rs = $DB->get_record_sql($earliest_sql);

    if (!isset($earliest_rs->timemodifed)) {
        $table->data[] = array('No Entries');
        return $table;
    }
    $earliest_date=strtotime("last monday", $earliest_rs->timemodifed);

    $last_sql ="SELECT max(timemodified) AS timemodifed
                  FROM {$CFG->prefix}peerassessment_ratings
                 WHERE  peerassessment ={$peerassessment->id}";

    $last_rs = $DB->get_record_sql($last_sql);
    $last_date =strtotime("next sunday", $last_rs->timemodifed);

    $duration_secs = $last_date -$earliest_date; //gives number of seconds entries have been made over
    $duration_weeks = ceil($duration_secs/PA_ONE_WEEK);
    echo "Duration of entries: ".$duration_weeks.' periods ';

    $startdate = $earliest_date;
    $entries_for_week = array();
    for ($i = 0; $i < $duration_weeks; $i++) {
        //get all of the entries for the given week and each member
        $offset="+ ".PA_ONE_WEEK .' seconds';
        $enddate = strtotime($offset, $startdate); //this is the sunday/monday midnight
        $entries_for_week_sql ="SELECT * FROM {$CFG->prefix}peerassessment_ratings
        WHERE peerassessment={$peerassessment->id} AND timemodified >= $startdate and timemodified<=$enddate";
        $entries = $DB->get_records_sql($entries_for_week_sql);
        $entries_for_week[$startdate] = array();//
        if ($entries) {
            foreach ($entries as $e) {
                if (!isset($entries_for_week[$startdate][$e->ratedby])) {
                    $entries_for_week[$startdate][$e->ratedby]= array();
                }
                $entries_for_week[$startdate][$e->ratedby][$e->userid]= $e;
            }
        }
        $startdate = $enddate;
    }
    /*we should now have an array of "mondays", containing an array of
    userids (representing the user who MADE the rating), with each value
    containing an array of the ratings they actually made.
    */
    $doneheadings = false;
    $userheadings = array();
    $done_user_headings =false;
    foreach ($members as $m1) {
        $t1 = 0;
        $c = 0;
        $row=array();
        $row[] = $m1->lastname .', '.$m1->firstname;
        $userheadings[] = 'Student';
        $userheadings[] = 'Average Rating Given';  //we merged the average to the first column
        foreach ($entries_for_week as $week => $value) {
            if (!$doneheadings && $showdetails) {
                $heading[]='Week Starting ' .date('D d-M-Y', $week);
                for ($j= 0; $j<count($members)-1; $j++) {
                    $heading[] ='';
                }
            } else {
                $heading[] ='';
            }
            $user_entries = $value;
            foreach ($members as $m2) {
                if (!$done_user_headings) {
                    if ($showdetails) {
                        $userheadings[] = $m2->lastname .', '.$m2->firstname;
                    }
                }
                if (isset($user_entries[$m1->id][$m2->id])) {
                    $entry = $user_entries[$m1->id][$m2->id];
                    if (true){//$showdetails) {
                        $content =$entry->rating;
                        if ($user_can_delete_ratings) {
                            $content .= print_delete_attempt_form($peerassessment,
                                    $group,
                                    $m1->id,
                                    $entry,
                                    $entry->timemodified,
                                    true
                            );
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
                } else {
                    if ($showdetails) {
                        $row[]='-';
                    }
                }
            }
        }

        if (!$doneheadings) {
            $heading[] = '';
            if ($showdetails) {
                $table->data[] = $heading;
            }
            $doneheadings = true;
        }
        if (!$done_user_headings) {
            if ($showdetails) {
                $table->data[] = $userheadings;
            } else {
                $table->head = $userheadings;
            }
            $done_user_headings = true;
        }
        $name = array_slice($row, 0, 1);
        $values = array_slice($row, 1);
        $ave = array(peerassessment_get_average_rating_by_student($peerassessment, $m1->id));
        $row = array_merge($name, $ave, $values);
        $table->data[$m1->id] = $row;
    }
    $a = array();
    $a[]='';
    $a[] ='Average rating recieved';
    foreach ($members as $m1) {
        $a[] = peerassessment_get_average_rating_for_student($peerassessment, $m1->id);
    }
    $table->data[] = $a;
    return $table;
}

function peerassessment_get_table_unlimited_frequency($peerassessment, $group) {
    $table=new stdClass;
    $table->head = array();
    $members = $members = groups_get_members($group->id);
    $table->head[] ="Student\Recipient &gt;";
    foreach ($members as $m2) {
        $table->head[] = "{$m2->lastname}, {$m2->firstname}";//({$m2->id})";
    }
    $recieved_totals = array();
    $recieved_counts = array();

    return $table;
}

function peerassessment_get_average_rating_for_student($peerassessment, $userid) {
    global $CFG, $DB;
    $sql = "SELECT AVG(rating) AS average
              FROM {$CFG->prefix}peerassessment_ratings
             WHERE peerassessment={$peerassessment->id} AND userid={$userid}";
    $rs = $DB->get_record_sql($sql);
    if ($rs->average >PA_UPPER_THRESHOLD) {
        return "<span style='color:green'><sup>+</sup>".$rs->average."</span>";
    }
    if ($rs->average < PA_LOWER_THRESHOLD) {
        return "<span style='color:red'><sup style='color:red'>-</sup>".$rs->average."</span>";
    }
    return $rs->average;
}
function peerassessment_get_average_rating_by_student($peerassessment, $userid) {
    global $CFG, $DB;
    $sql = "SELECT AVG(rating) AS average
              FROM {$CFG->prefix}peerassessment_ratings
             WHERE peerassessment={$peerassessment->id} AND ratedby={$userid}";
    $rs = $DB->get_record_sql($sql);
    if ($rs->average >PA_UPPER_THRESHOLD) {
        return "<span style='color:green'><sup style='color:green'>+</sup>".$rs->average."</span>";
    }
    if ($rs->average <PA_LOWER_THRESHOLD) {
        return "<span style='color:red'><sup style='color:red'>-</sup>".$rs->average ."</span>";
    }
    return $rs->average;
}

/**
 * Displays a form that allows an appropriate user to delete a rating or ratings.
 **/
function print_delete_attempt_form($peerassessment, $group, $userid, $rating=null, $timemodified=null, $return = true) {
    global $CFG, $USER;
    $cm = get_coursemodule_from_instance('peerassessment', $peerassessment->id);
    $context = context_module::instance($cm->id);
    if (!has_capability('mod/peerassessment:deleteratings', $context)) {
        return '';  //don't return a form
    }
    $out = "<form action='report.php' method='post'>";

    $ratingid='';
    if (!is_null($rating)) {
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

function print_report_select_form($id, $groups, $selectedgroupid) {
    $displaygroups= array();
    //display a list of groups to display
    if (!$groups) {
        notify (get_string('nogroups', 'peerassessment'));
        return;
    }
    foreach ($groups as $g) {
        $displaygroups[$g->id] =$g->name;
    }
    ksort($displaygroups, SORT_STRING);

    echo "<form action='report.php' method='get'><p>". get_string('viewreportgroup', 'peerassessment');
    echo html_writer::select($displaygroups, 'selectedgroup', $selectedgroupid);
    echo "<input type='hidden' name='id' value='{$id}'/><input type='submit' value='Select'/></p>";
    echo '</form>';
}
/**
 *
 * Peer assessment currently supports all features except Adv. Grading and Backup moodle2.
 * @param string $feature
 */
function peerassessment_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_BACKUP_MOODLE2:          return false;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_ADVANCED_GRADING:        return false;
        case FEATURE_RATE:                    return true;

        default: return null;
    }
}
