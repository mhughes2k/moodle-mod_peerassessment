<?php

require('../../config.php');
//require_once("{$CFG->dirroot}/mod/peerassessment/locallib.php");

$id      = required_param('id', PARAM_INT);             // Course Module ID

$cm = get_coursemodule_from_id('peerassessment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$pa = $DB->get_record('peerassessment', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

/** 
 * Can the user modify their ratings or make them in the 1st place.
 * @var bool $readonly
 */
$readonly = true;
if ($pa->canedit) {
	$readonly = false;
}

/**
 * Template Data
 * @var array $tdata
 */
$tdata = array();

// Mark as viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$url = new moodle_url('/mod/peerassessment/view.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_title($pa->name);
$PAGE->set_heading($course->fullname);

$context = context_module::instance($cm->id);
$canmanage = has_capability('mod/peerassessment:viewreport', $context);
$canrate = has_capability('mod/peerassessment:recordrating', $context);

// Handle data entry
$data = data_submitted();
if ($data) {
	exit();
} 
/**
 * Peer Assessment Instance
 * @var peerassessment $pa_instance
 */
$pa_instance = null;
$groupid = groups_get_activity_group($cm);
$group = groups_get_group($groupid);
//var_dump($group);
if ($canrate) {
	if ($group) {
		
		$PAGE->set_title($pa->name .": ". $group->name);
		$pa_instance = new mod_peerassessment\peerassessment($pa, $group->id);
		
		if ($readonly) {
			$tdata['ratings'] = array();
			$tdata['members'] = array();//array_values($pa_instance->members);
			$tdata['ratings'] = $pa_instance->ratings;
			$tdata['membercount'] = count($pa_instance->members);
			foreach($pa_instance->members as $mid => $member) {
				$mdata = array(
					'userid' => $member->id,
					'lastname' => $member->lastname,
					'firstname' => $member->firstname,
					'userpicture' => $OUTPUT->user_picture($member),
					'ratings' => array(),
					'averagerating_given' => $pa_instance->get_student_average_rating_given($mid, true)
				);
				debugging("Ratings awarded to {$member->id}");
				foreach($pa_instance->members as $mid2 => $member2) {
					$key = "{$mid}:{$mid2}";
					$mdata['ratings'][] = $pa_instance->ratings[$key];
					//$mdata['ratings'][] = $pa_instance->members[$mid2];
					/*
					if (is_null($pa_instance->ratings[$mid2])) {
						debugging("No rating awarded to {$mid} by {$mid2}", DEBUG_DEVELOPER);
						$mdata['ratings'][] = null;//get_string('norating', 'peerassessment'); 
					} else {
						debugging("Rating awarded to {$pa_instance->ratings[$mid2]->userid} by {$pa_instance->ratings[$mid2]->ratedby} ", DEBUG_DEVELOPER);
						$mdata['ratings'][] = $pa_instance->ratings[$mid2];
					}
					*/
					$mdata['averagerating_received'] = $pa_instance->get_student_average_rating_received($mid, true);
				}
				$tdata['members'][] = $mdata;
			}
		}
	} else {
		// No group!
		echo $OUTPUT->header();
		print_error('no groups');
		echo $OUTPUT->footer();
		exit();
	}
}

/* Render the actual page */
echo $OUTPUT->header();
if (!empty($pa->intro)) {
	echo $OUTPUT->box(format_module_intro('peerassessment', $pa, $cm->id));
}

if ($readonly) {
	echo $OUTPUT->render_from_template('mod_peerassessment/ratings', $tdata);
} else {
	if (!$canrate) {
		if ($canmanage) {
			// Staff typically can't rate but can manage
			echo $OUTPUT->render_from_template('mod_peerassessment/manageui', $tdata);
		} else {
			// Capture student's that aren't in a groupt
			echo $OUTPUT->box('cantrate', 'peerassessment');
		}
	} else {
		// Can rate and not read only so display the rating UI
		if ($pa_instance->has_rated($USER->id)) {
			echo $OUTPUT->box('alreadyrated', 'peerassessment');
		} else{
			
			echo $OUTPUT->render_from_template('mod_peerassessment/rateui', $tdata);
		}
	}
}

echo $OUTPUT->footer();