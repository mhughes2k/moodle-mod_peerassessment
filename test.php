<?php

require('../../config.php');
require_once("lib.php");
echo '<pre>';
$groupid = 42;
$group = groups_get_group($groupid);
$members = groups_get_members($groupid);


$cm = get_coursemodule_from_id('peerassessment', 65);
$instance = $DB->get_record('peerassessment',array('id' => $cm->instance));


$pa = new \mod_peerassessment\peerassessment($instance, $groupid);
var_dump($pa->ratings);
foreach($members as $m) {
	$pa->rate($m->id, 5);
}

var_dump($pa->ratings);
foreach($members as $m) {
	var_dump($pa->get_student_average_rating($m->id));
}
echo '</pre>';