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
$userid = required_param('userid', PARAM_INT);
$groupid = required_param('groupid', PARAM_INT);
$mode = optional_param('mode', 0, PARAM_INT);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
$canmanage = has_capability('mod/peerassessment:viewreport', $context, $USER);

$tdata = [];
$pa_instance = new \mod_peerassessment\peerassessment($pa, $groupid);
$user = core_user::get_user($userid);
$myratings= $pa_instance->get_myratings($user->id);
$group = groups_get_group($groupid);

$url = new \moodle_url(
    '/mod/peerassessment/viewcomment.php',
    [
        'id' => $id,
        'groupid' => $groupid,
        'userid' => $userid,
        'mode' => $mode
    ]
);

$backurl = new \moodle_url('/mod/peerassessment/view.php',[
    'id' => $id,
    'groupid' => $groupid,
    'mode' => $mode
]);
$PAGE->set_url($url);
$PAGE->set_button($OUTPUT->render(new \single_button($backurl, 'Back to Group', 'GET')));



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
    
    $scaleitems = array();
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

foreach($pa_instance->get_members() as $mid => $member) {
    
    $r = array(
            'userid' => $member->id,
            'lastname' => $member->lastname,
            'firstname' => $member->firstname,
            'userpicture' => $OUTPUT->user_picture($member),
            'rating' => isset($myratings[$member->id]) ? $myratings[$member->id]->rating : null
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
$tdata['comment'] = $pa_instance->get_comment($user->id);

$PAGE->set_title($pa->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading_viewcomment', 'peerassessment', [
        'activity' => $pa->name,
        'groupname' => $group->name,
        'username' => fullname($user)
])); 
        //[$pa->name .'-'. fullname($user));
echo $OUTPUT->render_from_template("mod_peerassessment/userratings", $tdata);
echo $OUTPUT->footer();