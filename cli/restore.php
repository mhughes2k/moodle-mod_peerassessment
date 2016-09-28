<?php
 
define('CLI_SCRIPT', true);
 
require_once('../../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
 
// Transaction.
$transaction = $DB->start_delegated_transaction();
 
// Create new course.
$folder             = "cad59d08db00e34d9ebb51a215f817c5"; // as found in: $CFG->dataroot . '/temp/backup/' 
$categoryid         = 1; // e.g. 1 == Miscellaneous
$userdoingrestore   = 2; // e.g. 2 == admin
$courseid           = restore_dbops::create_new_course('AA', 'AA', $categoryid);
 
// Restore backup into course.
$controller = new restore_controller($folder, $courseid, 
        backup::INTERACTIVE_NO, backup::MODE_SAMESITE, $userdoingrestore,
        backup::TARGET_NEW_COURSE);
$controller->execute_precheck();
$controller->execute_plan();
 
// Commit.
$transaction->allow_commit();