<?php

/**
 * Converts pre 2016092305 instances
 *
 * Pre 2016092305 instances had\;
 * * no option for the number of rating values that could be specified, it
 * was always 5.
 * * no "completion" rating. This is allows the peer assessment to be used as a completion requirement.
 *
 * This step will primarily:
 * * convert these to have a *points* rating of 1-5 (not a scale).
 * @param unknown $dbmanager
 */
function mod_peerassessment_upgrade_to_2016092305() {
    global $DB;
    $defaultOldInstancesTo5Points = $DB->set_field_select('peerassessment', 'ratingscale', 5, 'ratingscale IS NULL or ratingscale = 0');

    return $defaultOldInstancesTo5Points;
}
