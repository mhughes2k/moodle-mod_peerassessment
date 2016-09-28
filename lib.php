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