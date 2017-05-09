<?php 
$string['modulename'] = 'Peer Assessment';
$string['modulenameplural'] = 'Peer Assessments';

$string['modulename_help'] = 'The Peer Assessment activity allows members of groups to rate their peers against a scale. 

It may be used to solicit feedback on:

* Group dynamic
* Contribution to group work

Or any scenario where a value judgement is being made on peers.'; 

$string['pluginname'] = 'Peer Assessment';
$string['pluginadministration'] = 'Peer Assessment';

$string['alreadyrated'] = 'You have already rated your peers.';
$string['canedit'] 	= 'Allow student to change their responses.';
$string['cantrate'] = 'You are not able to rate your peers. Please check with your lecturer that you are in a group.';
$string['chooseexportfields'] = 'Choose fields to export';
$string['chooseexportformat'] = 'Choose export format';
$string['chooseexportgroups'] = 'Choose groups to export';
$string['comment'] = 'Comment';
$string['completionratinggroup'] = 'Require peer rating';
$string['completionrating'] = 'Students must have rated their peers.';
$string['confirmdelete'] = 'Are you sure you want to delete this user\'s ratings';
$string['csvwithseleteddelimiter'] = 'CSV with Selected Delimiter';
$string['eventreportviewed'] = 'Report viewed';
$string['eventratingcreated'] = 'Rating Created';
$string['eventratingdeleted'] = 'Rating Deleted';

$string['frequency'] = 'Frequency';
$string['frequency_help'] = 'How often can users complete this activity';
$string['frequencyonce'] = 'Once';
$string['frequencyweekly'] = 'Weekly';
$string['introduction'] = 'Introduction';
$string['issues_staff'] = 'The following problems were identified:';
$string['issues_student'] = 'This activity has not been configured correctly, please ask your lecturer to access this page and a list of identified issues for them to address will be displayed.';
$string['heading_viewcomment'] = '{$a->activity} - {$a->groupname} - {$a->username}'; 
$string['lowerbound'] = 'Lower bound';
$string['lowerbound_help'] = 'Highlight average ratings equal or less than this value.

Only available if rating scale is "point".';
$string['upperbound'] = 'Upper bound';
$string['upperbound_help'] = 'Highlight average ratings equal or greater than this value.

Only available if rating scale is "point".';
$string['scaledisplayformat'] = '{$a->text} ({$a->value})';
$string['norating'] = '-';
$string['grade'] = 'Grade';
$string['nocomment'] = 'No comment entered';
$string['nogradeinfo'] = 'Grading Peer Assessments';
$string['nogradeinfo_text'] = '**Grading is disabled by default.** (See the help icon for further information).

There is **no** grading interface within the Peer Assessment activity, you must enter grades via the grade book.';
$string['nogradeinfo_help'] ='As it is not possible to determine if you would be wanting to give the grade
to a student on the basis of the ratings they\'ve given or on the basis of the ratings they have recieved grading is disabled by default.

If you wish to be able to give a grade to a user (for what ever reason you choose) you may enable grading. This will create a grade item in the 
gradebook for this activity.
        
It is then up to you to decide what the grade represents to the student.
';
$string['grade_help'] ='Select the type of grading used for this activity. If "scale" is chosen, you can then choose the scale from the "scale" dropdown. If using "point" grading, you can then enter the maximum grade available for this activity.';
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