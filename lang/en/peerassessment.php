<?php 
$string['modulename'] = 'Peer Assessment';
$string['modulenameplural'] = 'Peer Assessments';
$string['pluginname'] = 'Peer Assessment';
$string['pluginadministration'] = 'Peer Assessment';

$string['alreadyrated'] = 'You have already rated your peers.';
$string['canedit'] 	= 'Allow student to change their responses.';
$string['cantrate'] = 'You are not able to rate your peers. Please check with your lecturer that you are in a group.';
$string['comment'] = 'Comment';
$string['completionratinggroup'] = 'Require peer rating';
$string['completionrating'] = 'Students must have rated their peers.';
$string['confirmdelete'] = 'Are you sure you want to delete this user\'s ratings';
$string['introduction'] = 'Introduction';
$string['issues_staff'] = 'The following problems were identified:';
$string['issues_student'] = 'This activity has not been configured correctly, please ask your lecturer to access this page and a list of identified issues for them to address will be displayed.';
$string['scaledisplayformat'] = '{$a->text} ({$a->value})';
$string['norating'] = '-';
$string['notamemberofgroup'] = 'You do not belong to a group. Please contact your lecturer / tutor.';
$string['notamemberofgroup_warning'] = '{$a->affecteduserid} is not a member of the group {$a->groupid}';
$string['ratedallgroups'] = 'Rated all groups';
$string['ratedanygroup'] = 'Rated any group';
$string['ratingscale'] = 'Scale to rate peers against';
$string['ratingscale_help'] = 'You can select either a scale or a number of points to rate each member against.

If you select **Scale**, each scale item will be given a value from 1 to the number of items in the scale.

If you select **Point**, then there will be a single option for each integer value from 1 to the maximum grade.';
$string['resetpeerassessmentall'] = 'Delete All Peer Ratings';
$string['switchgroups'] = 'Switch Group';
$string['viewreport'] = 'View Report';

/* Strings from pagrade form element */
$string['pagradeerrorbadpoint'] = 'Invalid Grade Value. This must be an integer between 1 and {$a}';
$string['pagradeerrorbadscale'] = 'Invalid scale selected. Please make sure you select a scale from the selections below.';
$string['pagrade'] = 'Grade';
$string['pagrade_help'] = 'Select the type of grading used for this activity. If "scale" is chosen, you can then choose the scale from the "scale" dropdown. If using "point" grading, you can then enter the maximum grade available for this activity.';
$string['pagrademaxgrade'] = 'Maximum points';
$string['pagradetype'] = 'Type';
$string['pagradetypenone'] = 'None';
$string['pagradetypepoint'] = 'Point';
$string['pagradetypescale'] = 'Scale';

/* Errors */
$string['unabletoloadgroups'] = 'Unable to load groups';
$string['raternotingroup'] = 'Rater {$a->rater} is not in the group {$a->groupname}.';
$string['rateenotingroup'] = 'Ratee {$a->ratee} is not in the group {$a->groupname}.';
$string['targetsnotingroup'] = 'Neither rater {$a->rater} or ratee {$a->ratee} are in the group {$a->groupname}';