<?php
//namespace \mod_peerassessment\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/peerassessment/lib.php');
//require_once($CFG->dirroot . '/mod/peerassessment/classes/lib.php');
//use mod_peerassessment_testcase;
use \mod_peerassessment\privacy\provider as provider;


/**
 * Class mod_peerassessment_provider_testcase
 */
class mod_peerassessment_provider_testcase extends mod_peerassessment_testcase {

    /**
     * Number of Database tables expected
     */
    const DBTABLECOUNT = 2;

    public function test_getmetadata() {
        $collection = provider::get_metadata();
        $tablecount = 0;
        $exlocation = 0;
        $plugintypelink = 0;
        $subsystemlink = 0;
        $userpref = 0;
        foreach($collection as $type) {
            if (is_a($type, "database_table")) {
                $tablecount++;
            }
        }
        $this->assertEquals(self::DBTABLECOUNT, $tablecount);

        // TODO: Add other items that are in the collection/
    }

    public function test_get_contexts_for_userid() {

    }

    public function test_export_user_data() {

    }

    public function test_delete_data_for_all_users_in_context() {

    }

    public function test_delete_data_for_user() {

    }
}