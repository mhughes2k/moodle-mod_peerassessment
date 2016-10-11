<?php

//use mod_peerassessment;
use mod_peerassessment\exception;

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
 * Any error messages to display
 * @var array $errors
 */
$errors = array();
/**
 * Exceptions thrown that we've caught (ie we can handle)
 * @var array $exceptions
 */
$exceptions = array();
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
$canmanage = has_capability('mod/peerassessment:viewreport', $context, $USER);
$isadmin = false; //in_array($USER->id, array_keys(get_admins()));
$canrate = has_capability('mod/peerassessment:recordrating', $context, $USER, false);
if (!$canmanage) {
    $mode = MOD_PEERASSESSMENT_MODE_VIEW;
} 


// Handle data entry
$data = data_submitted();
if ($data) {    
    require_sesskey();
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
    
    if ($data->rate) {
        $oktorate = false;
        // Now we're good to go!
        // extract rating_{rater|ratee} items
        $ratingstostore = array();
        $memberids = array_keys($pa_instance->get_members());
        foreach($data as $key=>$value) {
            if (!is_null($r = \mod_peerassessment\peerassessment::extract_rating_values($key))) {
                // it is a rating!
                $r->rating = $value;
                $ratingstostore[] = $r;
            }
        }
        
        try {
            foreach($ratingstostore as $r2s) {
                $pa_instance->rate($r2s->ratee, $r2s->rating, $USER->id);
            }
            $pa_instance->comment($USER->id, $data->comment);
        }
        catch (mod_peerassessment\exception\security_exception $ex) {
            // This is a more serious attempt to bypass things
            throw $ex;
        }
        catch (mod_peerassessment\exception\invalid_rating_exception $ex) {
            $exceptions[] = $ex;
        }
        catch (mod_peerassessment\exception\peerassessment_exception $ex) {
            $exceptions[] = $ex;
        }
        if (empty($exceptions)) {
            $pa_instance->save_ratings();
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && $pa->completionrating) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
            redirect(new \moodle_url('/mod/peerassessment/view.php', array('id' => $id, 'groupid' => $groupid, 'mode'=> $mode)));
            exit();
        } else {
            var_dump($exceptions);
        }
        // We fall through to displaying the form again
    }
} 

$delete = optional_param('delete', false, PARAM_TEXT);
if($delete === 'delete') {
    require_sesskey();
    //     Validate this 
    $groupid = required_param('groupid', PARAM_INT);

    // Check user is member of the group
    $group = groups_get_group($groupid);
    if (!$group) {
        // Fail as group trying to rate for doesn't exist
        print_error('groupdoesnotexist', 'peerassessment');
        exit();
    }

    if (!$canmanage) {
        print_error('onlystaffcandeletearating', 'peerassessment');
        exit();
    }
    $ratedbyid = required_param('ratedby', PARAM_INT);
    $pa_instance = new \mod_peerassessment\peerassessment($pa, $groupid);
    $pa_instance->delete_ratings($ratedbyid);
    redirect(new \moodle_url('/mod/peerassessment/view.php', array('id' => $id, 'groupid' => $groupid, 'mode'=> $mode)));
    exit();
}
// Deal with broken setups really early, not using templates
$problems = array();

if ($cm->groupmode == 0) {
    $problems[] = 'Activity requires Group mode to be set to either Separate or Visible groups';
}
if ($pa->ratingscale == 0) {
    $problems[] = "Rating Scale Type is to None. This means there will be no options for the students to rate against.";
}
if(count($problems) >0) {
    $PAGE->set_url('/mod/peerassessment/view.php', array('id' => $id));
    $editlink = new moodle_url('/course/modedit.php', array('update'=> $id, 'return'=>1));
    $btn = $OUTPUT->single_button($editlink, get_string('editsettings'));
    $PAGE->set_button($btn);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($pa->name);
    if ($canmanage) {
        echo $OUTPUT->box_start('generalbox bg-danger');
        echo html_writer::tag('p',get_string('issues_staff', 'peerassessment'));
        echo html_writer::start_tag('ol');
        foreach($problems as $p) {
            echo html_writer::tag('li', $p);
        }
        echo html_writer::end_tag('ol');
        echo $btn;
        echo $OUTPUT->box_end();
    } else {
        echo $OUTPUT->box(get_string('issues_student', 'peerassessment'), 'generalbox bg-warning');
    }
    echo $OUTPUT->footer();
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
    
    if (!$canmanage & !groups_is_member($group->id, $USER->id)) {
        $PAGE->set_url(new moodle_url('/mod/peerassessment/view.php', array('id' =>$id, 'groupid'=>$groupid)));
        echo $OUTPUT->header();
        echo $OUTPUT->heading($pa->name);
        echo $OUTPUT->box_start();
        echo $OUTPUT->error_text(get_string("notamemberofgroup", "peerassessment"));
        echo $OUTPUT->box_end();
        //print_error
        echo $OUTPUT->footer();
        exit();
    } else {

    }
}

// Prepare shared Data
// Most of this is all about marshalling the data from the PeerAssessment object
// into a format that is easily used in the mustache templates.
$tdata['cmid'] = $id;    // Page data first
$tdata['sesskey'] = sesskey();

// Permission data
$tdata['canmanage'] = $canmanage;
$tdata['pagemode'] = $mode;
// Activity Data
$tdata['name'] = $pa->name; 
$tdata['intro'] = format_module_intro('peerassessment', $pa, $cm->id);
foreach($exceptions as $ex) {
    $errors[] = $ex->getMessage();
}
$tdata['errors'] = $errors;

if ($group) {
    /* Initialise variables */
    $scaleitems = null;
    $scalename = null;

    $pa_instance = new mod_peerassessment\peerassessment($pa, $group->id);
    
    // Prepare rating scales for rating UI as it saves multiple loops later!
    $scaleid = (int)$pa->ratingscale;    // NOTE Scales are negative, points are +
    if ($scaleid < 0) {
        peerassessment_trace("Rating is via Scale {$scaleid}");
        if ($scale = $DB->get_record('scale', array('id'=> (-$scaleid)))) {
            $scaleitems = \mod_peerassessment\peerassessment::make_ratings_for_template_from_list($scale->scale);
            $scalename = $scale->name;
            $tdata['scaleitems'] = array_values($scaleitems);
            $tdata['scalecount'] = count($scaleitems);
            $tdata['scalename'] = $scalename;
        }
    } else {
        //we have a points system.
        peerassessment_trace("Rating is via Points");
        $scalename = "Points";
        
        debugging("point scale");
        // We have an invalid scale
        //print_error('scalenotfound', 'peerassessment');
        $scaleitems = array();
        //for ($i=$scaleid; $i>=0; $i--) {
        for ($i=1; $i<=$scaleid; $i++) {
            $r = new \mod_peerassessment\rating\ratingelement();
            $r->rating = $i;
            $r->name = ' ' .$i .'/'. $scaleid. ' ';
            $scaleitems[] = $r;
        }
        $tdata['scaleitems'] = $scaleitems;

    }
    $tdata['isscale'] = ($scaleid < 0);
    $tdata['scalecount'] = count($scaleitems);
    $tdata['scalename'] = $scalename;
    
    $hasrated = $pa_instance->has_rated($USER->id);
    $deleteratingurl = new moodle_url('/mod/peerassessment/view.php', array(
        'id' => $id, 'groupid'=>$groupid, 'delete' => 'delete', 'sesskey' => sesskey(), 'ratedby'=> false, 'mode' => $mode)
    );
    
    $tdata['canrate'] = $canrate & groups_is_member($group->id, $USER->id) & !$hasrated ;
    $tdata['hasrated'] = $hasrated;
    $tdata['isadmin'] = $isadmin;
    $tdata['ratings'] = array();
    $tdata['members'] = array();//array_values($pa_instance->members);
    $tdata['ratings'] = $pa_instance->get_ratings();

    $tdata['group'] = $group;
    $tdata['groupid'] = $groupid;
    $tdata['groupname'] = $group->name;
    $tdata['membercount'] = count($pa_instance->get_members());
    $tdata['comment'] = $pa_instance->get_comment();
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
    
    foreach($pa_instance->get_members() as $mid => $member) {
        
        $mdata = array(
            'userid' => $member->id,
            'lastname' => $member->lastname,
            'firstname' => $member->firstname,
            'userpicture' => $OUTPUT->user_picture($member),
            'ratings' => array(),
            'scaleitems' => array(),
            'comment'=> $pa_instance->get_comment($member->id),
            'deletelink' => ''
        );
        if ($pa_instance->has_rated($member->id)) {
            $mdata['deletelink'] = $OUTPUT->action_link($deleteratingurl->out(false, array('ratedby' => $member->id)),
                $OUTPUT->pix_icon('t/delete',get_string('delete')),
                new confirm_action(get_string('confirmdelete', 'peerassessment'))
            );
        }
        peerassessment_trace("Ratings awarded to {$member->id}", DEBUG_DEVELOPER);
        foreach($pa_instance->get_members() as $mid2 => $member2) {
            $key = "{$mid}:{$mid2}";
            $rating = $pa_instance->get_ratings()[$key];
            if ($scaleid < 0 && isset($rating)) {
                $scalekey = ($rating->rating) - 1;
                peerassessment_trace("Rating:{$rating->rating}, Scalekey: $scalekey, Name:{$scaleitems[$scalekey]->name}");
                $mdata['ratings'][] = array('rating' => get_string('scaledisplayformat', 'peerassessment',
                    array(
                        'text' => $scaleitems[$scalekey]->name,
                        'value'=> $rating->rating
                    ))
                );
            } else {
                $mdata['ratings'][] = $pa_instance->get_ratings()[$key];
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
            if (abs($avggiven)) {    // Only display if we've gotten to a sensible value.
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
    foreach($pa_instance->get_members() as $mid => $member) {
        
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
}

if ($mode == MOD_PEERASSESSMENT_MODE_VIEW && !$canrate & $canmanage) {
    $mode = MOD_PEERASSESSMENT_MODE_REPORT;
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
        $viewreportbutton = $OUTPUT->action_link($reporturl, get_string('viewreport', 'peerassessment'), null,
            array('class' => 'btn btn-primary')
        );
        $PAGE->set_button($viewreportbutton);
        $tdata['viewreportbutton'] = $viewreportbutton;
    } else {
        $PAGE->set_button($tdata['groupselect']);
    }
} else if ($mode == MOD_PEERASSESSMENT_MODE_REPORT) {
    $PAGE->set_button($tdata['groupselect']);
}
/* Render the actual page */
if(isset($tdata['group'])) {
    $PAGE->set_title($pa->name .": ". $group->name);
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