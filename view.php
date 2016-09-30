<?php

require('../../config.php');
//require_once("{$CFG->dirroot}/mod/peerassessment/locallib.php");
set_debugging(DEBUG_NORMAL);
$id      = required_param('id', PARAM_INT);             // Course Module ID

$cm = get_coursemodule_from_id('peerassessment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$pa = $DB->get_record('peerassessment', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

/** 
 * Can the user modify their ratings or make them in the 1st place.
 * @var bool $force_readonly
 */
$force_readonly = false;

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
	if (!$canrate) {
		// user doesn't hold the capability to rate in this assignment
		print_error('cantrate', 'peerassessment');
	}
	
	// Validate this 
	$groupid = required_param('groupid', PARAM_INT);

	// Check user is member of the group
	$group = groups_get_group($groupid);
	if (!$group) {
		// Fail as group trying to rate for doesn't exist
		print_error('groupdoesnotexist', 'peerassessment');
	}
	if (!groups_is_member($groupid, $USER->id)) {
		print_error('notamemberofgroupspecified', 'peerassessment');
	}
	
	// Construct a PA instance
	$pa_instance = new \mod_peerassessment\peerassessment($pa, $groupid);
	if ($pa_instance->has_rated($USER->id)) {
		print_error('alreadyrated', 'peerassessment');
	}
	
	// Now we're good to go!
	// extract rating_{rater|ratee} items
	$ratingstostore = array();
	foreach($data as $key=>$value) {
		if (!is_null($r = \mod_peerassessment\peerassessment::extract_rating_values($key))) {
			// it is a rating!
			$r->rating = $value;
			$ratingstostore[] = $r;
		}
	}
	//var_dump($ratingstostore);

	$pa_instance->start_rating();
	foreach($ratingstostore as $r2s) {
		$pa_instance->rate($r2s->ratee, $r2s->rating, $USER->id);
	}
	$pa_instance->end_rating();
	
	redirect(new \moodle_url('/mod/peerassessment/view.php', array('id' => $id)));	
	exit();
} 
/**
 * Peer Assessment Instance
 * @var peerassessment $pa_instance
 */
$pa_instance = null;
$groups = groups_get_activity_allowed_groups($cm);
if ($canmanage) {
	$groupid = optional_param('groupid', false, PARAM_INT);
} else {
	$groupid = groups_get_activity_group($cm);
	
}
$group = false;
if ($groupid) {
	$group = groups_get_group($groupid);
}

if ($canrate) {
	if ($group) {
		/* Initialise variables */
		$scaleitems = null;
		$scalename = null;
		
		
		$PAGE->set_title($pa->name .": ". $group->name);
		$pa_instance = new mod_peerassessment\peerassessment($pa, $group->id);
		
		// Prepare rating scales for rating UI as it saves multiple loops later!
		$scaleid = (int)$pa->ratingscale;	// NOTE Scales are negative, points are +
		if ($scaleid < 0) {
			if ($scale = $DB->get_record('scale', array('id'=> (-$scaleid)))) {
				$scaleitems = \mod_peerassessment\peerassessment::make_ratings_for_template_from_list($scale->scale);
				$scalename = $scale->name;
			} else {
				// We have an invalid scale
				//print_error('scalenotfound', 'peerassessment');
			}
		} else {
			//we have a points system.
			$scalename = "Points";
		}
		
		// Prepare shared Data
		$tdata['id'] = $id;	// Page data first
		$tdata['sesskey'] = sesskey();
		$tdata['groupid'] = $groupid;
		$tdata['ratings'] = array();
		$tdata['members'] = array();//array_values($pa_instance->members);
		$tdata['ratings'] = $pa_instance->ratings;
		$tdata['membercount'] = count($pa_instance->members);
		$tdata['isscale'] = ($scaleid < 0);
		$tdata['scaleitems'] = array_values($scaleitems);
		$tdata['scalecount'] = count($scaleitems);
		$tdata['scalename'] = $scalename;
		 
		foreach($pa_instance->members as $mid => $member) {
			$mdata = array(
				'userid' => $member->id,
				'lastname' => $member->lastname,
				'firstname' => $member->firstname,
				'userpicture' => $OUTPUT->user_picture($member),
				'ratings' => array(),
				
				'scaleitems' => array()
			);
			peerassessment_trace("Ratings awarded to {$member->id}", DEBUG_DEVELOPER);
			foreach($pa_instance->members as $mid2 => $member2) {
				$key = "{$mid}:{$mid2}";
				$mdata['ratings'][] = $pa_instance->ratings[$key];
				$mdata['averagerating_received'] = $pa_instance->get_student_average_rating_received($mid, true);
				$ratingitems = array();		
			}
			$mdata['averagerating_given'] = $pa_instance->get_student_average_rating_given($mid, true);
			foreach($scaleitems as $sc) {
				$r = clone $sc;
				$r->rater = $USER->id;
				$r->ratee = $mid;
				$mdata['scaleitems'][] = $r;
			}
			$tdata['members'][] = $mdata;
		}
		
		$myratings = $pa_instance->get_myratings($USER->id);
		$tdata['myratings'] = array();
		$tdata['averagerating_given'] = array();
		foreach($pa_instance->members as $mid => $member) {
			$r = array(
				'userid' => $member->id,
				'lastname' => $member->lastname,
				'firstname' => $member->firstname,
				'userpicture' => $OUTPUT->user_picture($member),
				'rating' => isset($myratings[$member->id]) ? $myratings[$member->id]->rating : null
			);
			
			
			if ($scaleid<=0 && isset($myratings[$member->id])) {
				$scalekey = $myratings[$member->id]->rating - 1;
				$r['rating'] = $scaleitems[$scalekey]->name. " ({$myratings[$member->id]->rating})";
				
				
				//unset($tdata['averagerating_given']);
			}
			$tdata['myratings'][] = $r;
		}
		
		$tdata['averagerating_given'] = $pa_instance->get_student_average_rating_given($USER->id, true);
		if ($scaleid < 0) {
			$avgiven = $tdata['averagerating_given'];
			$averagescalekey = abs($avgiven);
			$tdata['averagerating_given'] = $scaleitems[$averagescalekey]->name. " ({$avgiven})";
		}
		if (!$force_readonly && $canrate) {
			$readonly = false;
		}
		
		if ($readonly) {
			// Any stuff that is read only
		} else {
			// Prepare the rateui
			
		}
	} else {
		// No group!
		echo $OUTPUT->header();
		if ($canmanage) {
			$groupoptions = array_map(function($o) {
				return $o->name;
			}, $groups);
			echo get_string('selectgroup', 'peerassessment');
			echo $OUTPUT->single_select(
				new \moodle_url('/mod/peerassessment/view.php', array(
					'id' => $id
				)),
				'groupid', $groupoptions
			);
		} else {
			print_error('no groups');
		}
		echo $OUTPUT->footer();
		exit();
	}
}

$showscalevalues = true;

/* Render the actual page */
echo $OUTPUT->header();
if ($canmanage) {
	$groupoptions = array_map(function($o) {
		return $o->name;
	}, $groups);
	echo get_string('selectgroup', 'peerassessment');
	echo $OUTPUT->single_select(
		new \moodle_url('/mod/peerassessment/view.php', array(
				'id' => $id
		)),
		'groupid', $groupoptions
		);

}
if (!empty($pa->intro)) {
	echo $OUTPUT->box(format_module_intro('peerassessment', $pa, $cm->id));
}

if ($force_readonly) {
	echo $OUTPUT->render_from_template('mod_peerassessment/ratings', $tdata);
} else {
	
	if ($canmanage) {
		// Staff typically can't rate but can manage
		echo $OUTPUT->render_from_template('mod_peerassessment/ratings', $tdata);
		
		echo $OUTPUT->render_from_template('mod_peerassessment/scalevalues', array('scale'=>$scale, 'scaleitems' => $scaleitems));
			
	} 
	if (groups_is_member($groupid) && $canrate) {
		// Can rate and not read only so display the rating UI
		if ($pa_instance->has_rated($USER->id)) {
			echo $OUTPUT->box(get_string('alreadyrated', 'peerassessment'));
			echo $OUTPUT->render_from_template('mod_peerassessment/userratings', $tdata);
		} else{
			
			echo $OUTPUT->render_from_template('mod_peerassessment/rateui', $tdata);
		}
	}
}


echo $OUTPUT->footer();