<?php

/**
 * Converts pre 2016092305 instances
 * 
 * Pre 2016092305 instances had no option for the number of rating values that could be specified, it
 * was always 5.
 * 
 * This step will primarily convert these to have a *points* rating of 1-5 (not a scale).
 * @param unknown $dbmanager
 */
function mod_peerassessment_upgrade_to_2016092305(&$dbman) {
    $result = true;
    $upgradeSql = "UPDATE {$CFG->prefix}peerassessment SET ratingscale = 5 WHERE ratingscale = 0";
    $result = $result && $dbman->executeSql($updateSql);
    return $result;
}