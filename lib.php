<?php
defined('MOODLE_INTERNAL') || die();

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
			return true;
		case FEATURE_GRADE_HAS_GRADE:
			return true;
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
	//                 peerassessment_grade_item_update($data);
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
	//peerassessment_grade_item_update($data);
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

/**
 * Create grade item for given peer assessment
 * @param unknown $peerassessment
 * @param unknown $grades
 */
function peerassessment_grade_item_update($peerassessment, $grades = null) {
	global $CFG;
	if (!function_exists('grade_update')) {
		require_once("{$CFG->libdir}/gradelib.php");
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
		
		if ($pa->completionrating == 1) {
			$expected = count($usergroupids);
			if ($completions == $expected) {
				return true;
			} else {
				return false;
			}
		} else if ($pa->completionrating == 2) {
			if ($completions > 0) {
				return true;
			} else {
				return false;
			}
		}
	}
	return $type;
}