<?php

require('../../config.php');
//require_once("{$CFG->dirroot}/mod/peerassessment/locallib.php");
//set_debugging(DEBUG_NORMAL);
$id      = required_param('id', PARAM_INT);             // Course Module ID

$cm = get_coursemodule_from_id('peerassessment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$pa = $DB->get_record('peerassessment', array('id' => $cm->instance), '*', MUST_EXIST);
require_login($course, false, $cm);

const MOD_PEERASSESSMENT_MODE_VIEW = 0 ;
const MOD_PEERASSESSMENT_MODE_REPORT = 1;
$mode = optional_param('mode', MOD_PEERASSESSMENT_MODE_VIEW, PARAM_INT);
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

$PAGE->set_title($pa->name);
$PAGE->set_heading($course->fullname);

$context = context_module::instance($cm->id);
$canmanage = has_capability('mod/peerassessment:viewreport', $context);
$canrate = has_capability('mod/peerassessment:recordrating', $context);
if (!$canmanage) {
	$mode = MOD_PEERASSESSMENT_MODE_VIEW;
} 


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
	
	$completion = new completion_info($course);
	if ($completion->is_enabled($cm) && $pa->completionrating) {
		debugging('Updating completion state');
		$completion->update_state($cm, COMPLETION_COMPLETE);
	}
	
	redirect(new \moodle_url('/mod/peerassessment/view.php', array('id' => $id, 'groupid' => $groupid)));	
	exit();
} 
/**
 * Peer Assessment Instance
 * @var peerassessment $pa_instance
 */
$pa_instance = null;
$groups = groups_get_activity_allowed_groups($cm);
$groupid = optional_param('groupid', false, PARAM_INT);
if ($groupid == false && count($groups) >0 ) {
	$groupid = groups_get_activity_group($cm);
	// Choose the "active" one
	$group = array_values($groups)[0];
	$groupid = $group->id;	
}
$group = false;
if ($groupid) {
	$group = groups_get_group($groupid);
	if (!$canmanage && !groups_is_member($group->id, $USER->id)) {
		print_error("notamemberofgroup", "peerassessment");
	} else {

	}
}

// Prepare shared Data
// Most of this is all about marshalling the data from the PeerAssessment object
// into a format that is easily used in the mustache templates.
$tdata['cmid'] = $id;	// Page data first
$tdata['sesskey'] = sesskey();

// Permission data
$tdata['canmanage'] = $canmanage;
$tdata['pagemode'] = $mode;
// Activity Data
$tdata['name'] = $pa->name; 
$tdata['intro'] = format_module_intro('peerassessment', $pa, $cm->id);

if ($group) {
	/* Initialise variables */
	$scaleitems = null;
	$scalename = null;

	$pa_instance = new mod_peerassessment\peerassessment($pa, $group->id);
	
	// Prepare rating scales for rating UI as it saves multiple loops later!
	$scaleid = (int)$pa->ratingscale;	// NOTE Scales are negative, points are +
	if ($scaleid < 0) {
		peerassessment_trace("Rating is via Scale {$scaleid}");
		if ($scale = $DB->get_record('scale', array('id'=> (-$scaleid)))) {
			$scaleitems = \mod_peerassessment\peerassessment::make_ratings_for_template_from_list($scale->scale);
			$scalename = $scale->name;
		} else {
			// We have an invalid scale
			//print_error('scalenotfound', 'peerassessment');
		}
	} else {
		//we have a points system.
		peerassessment_trace("Rating is via Points");
		$scalename = "Points";
	}
	$hasrated = $pa_instance->has_rated($USER->id);
	
	$tdata['canrate'] = $canrate & !$hasrated;
	$tdata['hasrated'] = $hasrated;
	$tdata['ratings'] = array();
	$tdata['members'] = array();//array_values($pa_instance->members);
	$tdata['ratings'] = $pa_instance->ratings;
	
	$tdata['isscale'] = ($scaleid < 0);
	$tdata['scaleitems'] = array_values($scaleitems);
	$tdata['scalecount'] = count($scaleitems);
	$tdata['scalename'] = $scalename;

	$tdata['group'] = $group;
	$tdata['groupid'] = $groupid;
	$tdata['groupname'] = $group->name;
	$tdata['membercount'] = count($pa_instance->members);
	/* Group Jump box */
	$tdata['groupselect'] = '';
	if(count($groups) > 1) {
		$groupoptions = array_map(function($o) {
			return $o->name;
		}, $groups);
		$tdata['groupselect'] = $OUTPUT->single_select(
				new \moodle_url('/mod/peerassessment/view.php', array(
						'id' => $id,
						'mode' => $mode
				)),
				'groupid', 
				$groupoptions,
				$formid = null, 
				$attributes = array('label' => get_string('switchgroups', 'peerassessment'))
				);
	}
	 
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
			if ($scaleid < 0 && isset($pa_instance->ratings[$key])) {
				$scalekey = ($pa_instance->ratings[$key]->rating) - 1;
				peerassessment_trace("Rating:{$pa_instance->ratings[$key]->rating}, Scalekey: $scalekey, Name:{$scaleitems[$scalekey]->name}");
				$mdata['ratings'][] = array('rating' => $scaleitems[$scalekey]->name . " ({$pa_instance->ratings[$key]->rating})");
			} else {
				$mdata['ratings'][] = $pa_instance->ratings[$key];
			}
			$mdata['averagerating_received'] = $pa_instance->get_student_average_rating_received($mid, true);
			
			if ($scaleid < 0) {
				$avgrec = $mdata['averagerating_received'];
				$averagescalekey = abs($avgrec) - 1 ;
				if(abs($avgrec)) {
					$mdata['averagerating_received'] = get_string('scaledisplayformat', 'peerassessment', 
						array(	
							'text'=>$scaleitems[$averagescalekey]->name,
							'value'=>$avgrec
						)); 
				}
			}
			$ratingitems = array();		
		}
		$mdata['averagerating_given'] = $pa_instance->get_student_average_rating_given($mid, true);
		if ($scaleid < 0) {
			$avggiven = $mdata['averagerating_given'];
			$averagescalekey = abs($avggiven) - 1;
			if (abs($avggiven)) {	// Only display if we've gotten to a sensible value.
				$mdata['averagerating_given'] = get_string('scaledisplayformat', 'peerassessment', 
					array(	
						'text'=>$scaleitems[$averagescalekey]->name,
						'value'=>$avggiven
					));
			}
		}
		foreach($scaleitems as $sc) {
			$r = clone $sc;
			$r->rater = $USER->id;
			$r->ratee = $mid;
			$mdata['scaleitems'][] = $r;
		}
		$tdata['members'][] = $mdata;
	}
	
	$myratings = $pa_instance->get_myratings($USER->id);
	$tdata['averagerating_given'] = array();
	
	// Ouput data about the current user's ratings that they've made
	$tdata['myratings'] = array();
	foreach($pa_instance->members as $mid => $member) {
		
		$r = array(
			'userid' => $member->id,
			'lastname' => $member->lastname,
			'firstname' => $member->firstname,
			'userpicture' => $OUTPUT->user_picture($member),
			'rating' => isset($myratings[$member->id]) ? $myratings[$member->id] : null
		);
		
		if ($scaleid < 0 && isset($r['rating'])) {
			peerassessment_trace("Displaying rating scale name");
			$scalekey = ($r['rating']->rating) - 1;
			$r['rating'] = get_string('scaledisplayformat', 'peerassessment', 
					array(	
						'text' => $scaleitems[$scalekey]->name,
						'value' => $myratings[$member->id]->rating
					)); 
		}
		$tdata['myratings'][] = $r;
	}
	$tdata['averagerating_given'] = $pa_instance->get_student_average_rating_given($USER->id, true);
	if ($scaleid < 0) {
		$avgiven = $tdata['averagerating_given'];
		$averagescalekey = abs($avgiven) - 1;
		if (abs($avgiven)) {
			$tdata['averagerating_given'] = get_string('scaledisplayformat', 'peerassessment', 
				array(	
					'text' => $scaleitems[$averagescalekey]->name,
					'value' => $avgiven
				));  
		}
		//$scaleitems[$averagescalekey]->name. " ({$avgiven})";
	}
	if (!$force_readonly && $canrate) {
		$readonly = false;
	}
	
	if ($readonly) {
		// Any stuff that is read only
	} else {
		// Prepare the rateui
		
	}
}


$url = new moodle_url('/mod/peerassessment/view.php', array(
	'id' => $id, 
	'groupid' => $groupid,
	'mode' => $mode
));
$PAGE->set_url($url);

$showscalevalues = true;
if ($mode == MOD_PEERASSESSMENT_MODE_VIEW) {
	if ($canmanage) {
		$reporturl = new moodle_url($PAGE->url, array('mode' => MOD_PEERASSESSMENT_MODE_REPORT));
		$PAGE->set_button($OUTPUT->action_link($reporturl, get_string('viewreport', 'peerassessment'), null,
			array('class' => 'btn btn-primary')
		));
	} else {
		$PAGE->set_button($tdata['groupselect']);
	}
} else if ($mode == MOD_PEERASSESSMENT_MODE_REPORT) {
	$PAGE->set_button($tdata['groupselect']);
}
/* Render the actual page */
if(isset($tdata['group'])) {
	$PAGE->set_title($pa->name .": ". $group->name);
} else {
	$PAGE->set_title($pa->name);
}

echo $OUTPUT->header();
if ($mode == MOD_PEERASSESSMENT_MODE_REPORT) {
	echo $OUTPUT->render_from_template("mod_peerassessment/report", $tdata);
} else {
	echo $OUTPUT->render_from_template("mod_peerassessment/view", $tdata);
}
/*
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

*/
echo $OUTPUT->footer();