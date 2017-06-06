<?php
defined('MOODLE_INTERNAL') || die();

use mod_peerassessment\peerassessment;
/**
 * 
 * @param unknown $feature
 * @return boolean|NULL
 */
function peerassessment_supports($feature) {
	switch($feature) {
		case FEATURE_GROUPS:
			return true;
		case FEATURE_GROUPINGS:
			return true;
		case FEATURE_MOD_INTRO:
			return true;
		case FEATURE_COMPLETION_TRACKS_VIEWS:
			return false;
		case FEATURE_GRADE_HAS_GRADE:
			return true;	// TODO to be decided.
		case FEATURE_COMPLETION_HAS_RULES: 
			return true;
		case FEATURE_GRADE_OUTCOMES:
			return true;
		case FEATURE_BACKUP_MOODLE2:
			return true;
		case FEATURE_SHOW_DESCRIPTION:
			return true;
		case FEATURE_RATE:
			return false;
		case FEATURE_ADVANCED_GRADING:
		    return false;
	    	
		default:
			return null;
	}
}

/**
 * Adds a new Peer Assessment activity
 */
function peerassessment_add_instance($data, $mform) {
	global $DB, $USER;
	if (!$returnid = $DB->insert_record('peerassessment', $data)) {
		return false;
	}
	$data->id=$returnid;
    peerassessment_grade_item_update($data);
	return $returnid;
}

/**
 * Update Peer Assessment Activity Instance
 */
function peerassessment_update_instance($data, $mform) {
	global $DB;
	$data->id = $data->instance;
	$cmid = $data->coursemodule;
	unset($data->introformat);
	if (!$returnid = $DB->update_record('peerassessment', $data)) {
		return false;
	}
	peerassessment_grade_item_update($data);
	return $returnid;
}

/**
 * Remove a Peer Assessment activity instance
 */
function peerassessment_delete_instance($id) {
	global $DB;
	if (! $data = $DB->get_record('peerassessment', array('id'=>$id))) {
		return false;
	}
	$result = true;
	if (! $DB->delete_records('peerassessment', array('id'=>$data->id))) {
		$result = false;
	}
	if (! $DB->delete_records('peerassessment_ratings', array('peerassessment'=>$data->id))) {
		$result = false;
	}
	if ($events = $DB->get_records_select('event', "modulename = 'peerassessment' and instance = '{$data->id}'")) {
		foreach ($events as $event) {
			delete_event($event->id);
		}
	}
	//peerassessment_grade_item_delete($data);
	return $result;
}
/*
function peerassessment_grading_areas_list() {
    return array('rating'=>get_string('peerassignment', 'peerassignment'));
}*/
/*
function peerassessment_get_user_grades($peerassessment, $userid = 0) {
    
}*/
/**
 * Update activity grades
 * This is not implemented as we don't record grades in the activity, it has to be done in the
 * grade book at the moment.
 * @category grade
 * 
 * @param stdClass $peerassessment Null means all peerassessments (with extra cmidnumber property)
 * @param number $userid specific user only, 0 means all
 * @param string $nullifnone If true and the user has no grade then a grade item with rawgrade == null will be inserted
 */
function peerassessment_update_grades($peerassessment = null, $userid=0, $nullifnone = true) {
    /*
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');
    if ($peerassessment->grade == 0) {
        peerassessment_grade_item_update($peerassessment);
    } else if () {
        
    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        peerassessment_grade_item_update($peerassessment, $grade);
    } else {
        peerassessment_grade_item_update($peerassessment);
    }*/
    return true;
}

/**
 * Create grade item for given peer assessment
 * @param unknown $data
 * @param unknown $grades
 */
function peerassessment_grade_item_update($data, $grades = null) {
	global $CFG;
	if (!function_exists('grade_update')) {
		require_once("{$CFG->libdir}/gradelib.php");
	}	
    // based on the data module implementation..
    $params = array('itemname'=>$data->name, 'idnumber'=>$data->cmidnumber);
    if ($data->grade == 0) {
        // No grading
        $params['gradetype'] = GRADE_TYPE_NONE;
        
    } else if ($data->grade > 0) {
        // Grade Type value
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $data->grade;
        $params['grademin']  = 0;
        
    } else if ($data->grade < 0) {
        // Grade type scale
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$data->grade;
    }
    if ($grades === 'reset') {
        $params['reset'] =  true;
        $grades = null;
    }
    
    return grade_update('mod/peerassessment', $data->course, 'mod', 'peerassessment', $data->id, 0, $grades, $params);
}



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

/**
 * Trace function
 * @param string $message Message to output
 * @param int $level Unused at the moment
 */
function peerassessment_trace($message, $level = DEBUG_DEVELOPER) {
	global $CFG, $USER;
	return; 
	if (debugging('', DEBUG_DEVELOPER) && !empty($message)) {		
		
		$forcedebug = false;
		if (!empty($CFG->debugusers) && $USER) {
			$debugusers = explode(',', $CFG->debugusers);
			$forcedebug = in_array($USER->id, $debugusers);
		}
		
		if (!$forcedebug and (empty($CFG->debug) || ($CFG->debug != -1 and $CFG->debug < $level))) {
			return false;
		}
		
		if (!isset($CFG->debugdisplay)) {
			$CFG->debugdisplay = ini_get_bool('display_errors');
		}
		$backtrace = debug_backtrace();
		$from = format_backtrace($backtrace, CLI_SCRIPT || NO_DEBUG_DISPLAY);
		if (PHPUNIT_TEST) {
			// NOP do nothing with this.
		} else if (NO_DEBUG_DISPLAY) {
            // Script does not want any errors or debugging in output,
            // we send the info to error log instead.
            error_log('Debugging: ' . $message . ' in '. PHP_EOL . $from);

        } else if ($forcedebug or $CFG->debugdisplay) {
            if (!defined('DEBUGGING_PRINTED')) {
                define('DEBUGGING_PRINTED', 1); // Indicates we have printed something.
            }
            if (CLI_SCRIPT) {
                echo "++ $message ++\n$from";
            } else {
                echo '<div class="notifytiny debuggingmessage" data-rel="debugging">' , $message , $from , '</div>';
            }

        } else {
            trigger_error($message . $from, E_USER_NOTICE);
        }
		
	}
	
}

function peerassessment_get_completion_state($course, $cm, $userid, $type) {
	global $DB, $CFG;
	if (!($pa = $DB->get_record('peerassessment', array('id' => $cm->instance)))) {
		throw new \moodle_exception("Can't find peer assessment {$cm->instance}");
	}
	if($pa->completionrating) {
		$usergroups = groups_get_activity_allowed_groups($cm, $userid);
		$usergroupids = array_keys($usergroups);
		list($ugsql, $ugparams) = $DB->get_in_or_equal($usergroupids);
		$completionssql = "SELECT count(distinct groupid) 
				FROM {peerassessment_ratings} 
				WHERE userid = ?";
		//$completions = $DB->get_records_sql($completionssql, array($userid));
		$completions = $DB->count_records_sql($completionssql, array($userid));
		
		if ($pa->completionrating == peerassessment::RATE_ALL_GROUPS) {
			$expected = count($usergroupids);
			if ($completions == $expected) {
				return true;
			} else {
				return false;
			}
		} else if ($pa->completionrating == peerassessment::RATE_ANY_GROUP) {
			if ($completions > 0) {
				return true;
			} else {
				return false;
			}
		}
	}
	return $type;
}