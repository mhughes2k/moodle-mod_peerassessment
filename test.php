<?php

require('../../config.php');
require_once("lib.php");
$id = required_param('id', PARAM_INT);
$userid = 0;
echo '<pre>';
$groupid = 1;
$group = groups_get_group($groupid);
$members = groups_get_members($groupid);


$cm = get_coursemodule_from_id('peerassessment', $id);
$instance = $DB->get_record('peerassessment', array('id' => $cm->instance));

var_dump($instance);
$pa = new \mod_peerassessment\peerassessment($instance, $groupid);
//var_dump($pa);
peerassessment_update_grades($instance, $userid);
/*
foreach($members as $m) {
	foreach($members as $m2) {
		$pa->rate($m->id, 5, $m2->id);
	}
}

var_dump($pa->ratings);
foreach($members as $m) {
	var_dump($pa->get_student_average_rating($m->id));
}
*/
echo '</pre>';