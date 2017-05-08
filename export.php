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
 * Template Data
 * @var array $tdata
 */
$tdata = array();


$PAGE->set_title($pa->name);
$PAGE->set_heading($course->fullname);

$context = context_module::instance($cm->id);
$canmanage = has_capability('mod/peerassessment:viewreport', $context, $USER);
$isadmin = false; //in_array($USER->id, array_keys(get_admins()));
$canrate = has_capability('mod/peerassessment:recordrating', $context, $USER, false);
if (!$canmanage) {
    

}

$groups = groups_get_activity_allowed_groups($cm);

$form = new \mod_peerassessment\export_form(
        new \moodle_url('/mod/peerassessment/export.php', ['id' =>$id]),
        $groups,
        $cm,
        []
);
// Handle data entry
if ($form->is_cancelled()) {
    redirect('view.php?id='.$id);
} elseif(!$data = $form->get_data()) {
    
    $groupid = optional_param('groupid', false, PARAM_INT);
    if ($groupid == false && count($groups) >0 ) {
        $groupid = groups_get_activity_group($cm);
        // Choose the "active" one
        $group = array_values($groups)[0];
        $groupid = $group->id;
    }
    $PAGE->set_url('/mod/peerassessment/export.php',['id'=> $id, 'groupid' => $groupid]);
    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
    exit();
}
$groups = [];
foreach($data as $prop=>$value) {
    $p = explode('_', $prop);
    if ($p[0] === 'group' && $value == '1') {
        $groups[] = $p[1];
    }
}

$exporter = new mod_peerassessment\exporter(
    $pa, 
    $data->exporttype, 
    $data->delimiter_name, 
    ['comment'], 
    ['comment'],
    $groups
);
//$exporter->get_exportdata();
$exporter->export();
exit();






