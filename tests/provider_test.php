<?php
//namespace mod_peerassessment\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/peerassessment/lib.php');
use \mod_peerassessment\privacy\provider as provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
/**
 * Class mod_peerassessment_provider_testcase
 */
class mod_peerassessment_provider_testcase extends provider_testcase {

    protected $testCourse;
    protected $testUsers;
    protected $testGroups;
    protected $emptyGroup;
    protected $testPa;
    protected $testPa2;

    /**
     * Number of Database tables expected
     */
    const DBTABLECOUNT = 2;

    /**
     * SetUp test baseline
     *
     * We have:
     *  * 1 course
     *  * 10 users in the course
     *  * 2 groups, each containing 5 members
     *
     * {@inheritDoc}
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp(): void {
        $this->testCourse =  $this->getDataGenerator()->create_course();
        $this->testGroups = array();
        for($i = 0; $i < 2; $i++ ) {
            $this->testGroups[] = $this->getDataGenerator()->create_group(array('courseid' => $this->testCourse->id));
        }
        $this->testUsers = array();
        $memberlimit = 5;
        $mcount = 0;
        $gindex = 0;
        $group = $this->testGroups[$gindex];
        for($i = 0; $i < 10; $i++) {
            $u = $this->getDataGenerator()->create_user();
            $this->testUsers[] = $u;
            $this->getDataGenerator()->enrol_user($u->id, $this->testCourse->id);
            if ($mcount < $memberlimit) {

                $result = $this->getDataGenerator()->create_group_member(array(
                    'userid' => $u->id,
                    'groupid' => $group->id
                ));
                $this->assertTrue($result,'Failed to create group member');
                $mcount++;

            } else {
                $mcount = 1;
                $gindex++;
                $group = $this->testGroups[$gindex];
                $result = $this->getDataGenerator()->create_group_member(array(
                    'userid' => $u->id,
                    'groupid' => $group->id
                ));
                $this->assertTrue($result,'Failed to create group member');
            }
        }
        $this->assertEquals(10, count($this->testUsers), 'There should be 10 test users');
        $this->assertEquals(2, count($this->testGroups), 'There should be 2 test groups');
        foreach($this->testGroups as $g) {
            $gms = groups_get_members($g->id);
            $this->assertEquals(5, count($gms), 'There should be 5 members in each group:' .$g->id. $g->name);
        }

        $this->emptyGroup = $this->getDataGenerator()->create_group(array('courseid' => $this->testCourse->id));
        $this->testGroups[] = $this->emptyGroup;
        $this->assertEquals(3, count($this->testGroups), 'There should be 3 test groups (1 with no members).');

        $this->testPa = $this->getDataGenerator()->create_module('peerassessment', array('course' => $this->testCourse->id, 'groupmode' => SEPARATEGROUPS));
        $this->testPa2 = $this->getDataGenerator()->create_module('peerassessment', array('course' => $this->testCourse->id, 'groupmode' => SEPARATEGROUPS));
        $this->generate_ratings($this->testPa->id, 5, 0);
        $this->generate_ratings($this->testPa2->id, 5, 0, true);    // We comment on the second
    }

    /**
     * @group contexts
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();
        /*
         * There should be one peerassessment context for any user
         * we select
         */
        $activitycontext = context_module::instance($this->testPa->cmid);
        $testUser = $this->testUsers[0];
        $contextdata = provider::get_contexts_for_userid($testUser->id);
        $this->assertEquals(2, $contextdata->count());
        $foundMatching = false;
        foreach($contextdata as $ctx) {
            if ($ctx->id == $activitycontext->id) {
                $foundMatching = true;
                continue;
            }
        }
        $this->assertTrue($foundMatching);
    }

    /**
     * We test the user export with the 1st member of group[0]
     * @throws dml_exception
     * @group export_user_data
     */
    public function test_export_user_data() {
        $this->resetAfterTest();

        $pai = new \mod_peerassessment\peerassessment($this->testPa->id, $this->testGroups[0]->id);
        $testUser = array_values($pai->get_members())[0];

        $activitycontext = context_module::instance($this->testPa->cmid);
        $ac2 = context_module::instance($this->testPa2->cmid);
        $contexts = [
            $activitycontext->id => $activitycontext,
            $ac2->id => $ac2
        ];
        $contextlist = new approved_contextlist($testUser, 'mod_peerassessment',
            array_keys($contexts)
        );
        //provider::export_user_data($contextlist);

        //This calls the provider::export_user_data() function.
        $this->export_all_data_for_user($testUser->id, 'mod_peerassessment');

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data_in_any_context());

        /* 1st should have ratings but no comment */
        $writer = writer::with_context($activitycontext);
        $this->assertTrue($writer->has_any_data());
        $hasrating = !empty($writer->get_data(['ratings']));
        $hascomment = is_object($writer->get_data(['comments'])) || !empty($writer->get_data(['comments']));
        $this->assertTrue($hasrating);
        $this->assertFalse($hascomment);

        /* 2nd should have rating and comment */
        $writer = writer::with_context($ac2);
        $this->assertTrue($writer->has_any_data());
        $hasrating = !empty($writer->get_data(['ratings']));
        $hascomment = is_object($writer->get_data(['comments'])) || !empty($writer->get_data(['comments']));
        $comment = $writer->get_data(['comments'])->comment;

        $this->assertTrue($hasrating);
        $this->assertTrue($hascomment);
        $this->assertEquals("Comment ". $testUser->id, $comment); // Check comment matches user id.
    }

    /**
     * @group deleteall
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->resetAfterTest();
        $module_context1 = context_module::instance($this->testPa->cmid);
        $module_context2 = context_module::instance($this->testPa2->cmid);
        $numPas = $DB->count_records('peerassessment', [
            'course' => $this->testCourse->id
        ]);
        $this->assertEquals(2, $numPas);
        list($inSql, $params) = $DB->get_in_or_equal([
            $this->testPa->id,
            $this->testPa2->id
        ]);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );
        // 5 users x 5 ratings x 2 activities
        $this->assertEquals(50, $numRatings);
        provider::delete_data_for_all_users_in_context($module_context1);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );
        $this->assertEquals(25, $numRatings);
        provider::delete_data_for_all_users_in_context($module_context2);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );
        $this->assertEquals(0, $numRatings);
    }

    /**
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @group delete_data_for_user
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->resetAfterTest();
        $module_context1 = context_module::instance($this->testPa->cmid);
        $module_context2 = context_module::instance($this->testPa2->cmid);
        $numPas = $DB->count_records('peerassessment', [
            'course' => $this->testCourse->id
        ]);
        $this->assertEquals(2, $numPas);
        list($inSql, $params) = $DB->get_in_or_equal([
            $this->testPa->id,
            $this->testPa2->id
        ]);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );
        $this->assertEquals(50, $numRatings);
        /* we'll take 1 user from testPa and expire 1 activity instance
         * 50 ratings - 1users ratings (5 ratings) == 45!
         */
        $pai = new \mod_peerassessment\peerassessment($this->testPa->id, $this->testGroups[0]->id);
        $testUser = array_values($pai->get_members())[0];

        $contextlist = new approved_contextlist($testUser, 'mod_peerassessment',[
            $module_context1->id
        ]);

        provider::delete_data_for_user($contextlist);

        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );
        $this->assertEquals(45, $numRatings);
    }

    /**
     * This is like the test_delete_for_data_user() function
     * but passes in all the contexts for peer assessments.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_delete_data_for_user2() {
        global $DB;
        $this->resetAfterTest();
        $module_context1 = context_module::instance($this->testPa->cmid);
        $module_context2 = context_module::instance($this->testPa2->cmid);
        $numPas = $DB->count_records('peerassessment', [
            'course' => $this->testCourse->id
        ]);
        $this->assertEquals(2, $numPas);
        list($inSql, $params) = $DB->get_in_or_equal([
            $this->testPa->id,
            $this->testPa2->id
        ]);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );
        $this->assertEquals(50, $numRatings);
        /* we'll take 1 user from testPa and expire all the PA modules.
         * This is closer the effect when the user has been expired.
         * 2 ratings by 1 user = 10 ratings
         * -> 50 - 10 = 40 ratings expected.
         */
        $pai = new \mod_peerassessment\peerassessment($this->testPa->id, $this->testGroups[0]->id);
        $testUser = array_values($pai->get_members())[0];

        $contextlist = new approved_contextlist($testUser, 'mod_peerassessment',[
            $module_context1->id,
            $module_context2->id
        ]);

        provider::delete_data_for_user($contextlist);

        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );
        $this->assertEquals(40, $numRatings);
    }

    /**
     * This checks provider behaviour if a non PA context is passed in
     * @group invalid_contexts
     */
    public function test_non_pa_context1() {
        global $DB;
        $this->resetAfterTest();
        $numPas = $DB->count_records('peerassessment', []);
        list($inSql, $params) = $DB->get_in_or_equal([
            $this->testPa->id,
            $this->testPa2->id
        ]);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );

        $c = \context_system::instance();

        $this->assertEquals(2, $numPas);
        $this->assertEquals(50, $numRatings);

        provider::delete_data_for_all_users_in_context($c);

        $this->assertEquals(2, $numPas);
        $this->assertEquals(50, $numRatings);
    }

    /**
     * This checks provider behaviour if a non PA context is passed in
     * @group invalid_contexts
     */
    public function test_non_pa_context2() {
        global $DB;
        $this->resetAfterTest();
        $numPas = $DB->count_records('peerassessment', []);
        list($inSql, $params) = $DB->get_in_or_equal([
            $this->testPa->id,
            $this->testPa2->id
        ]);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );

        $c = \context_course::instance($this->testCourse->id);
        $this->assertEquals(2, $numPas);
        $this->assertEquals(50, $numRatings);

        provider::delete_data_for_all_users_in_context($c);

        $this->assertEquals(2, $numPas);
        $this->assertEquals(50, $numRatings);
    }
    /**
     * This checks provider behaviour if a non PA context is passed in
     * @group invalid_contexts
     */
    public function test_non_pa_context3() {
        global $DB;
        $this->resetAfterTest();
        $numPas = $DB->count_records('peerassessment', []);
        list($inSql, $params) = $DB->get_in_or_equal([
            $this->testPa->id,
            $this->testPa2->id
        ]);
        $numRatings = $DB->count_records_select('peerassessment_ratings',
            "peerassessment {$inSql}",
            $params
        );

        $c = \context_course::instance($this->testCourse->id);
        $this->assertEquals(2, $numPas);
        $this->assertEquals(50, $numRatings);

        $pai = new \mod_peerassessment\peerassessment($this->testPa->id, $this->testGroups[0]->id);
        $testUser = array_values($pai->get_members())[0];

        $contextlist = new approved_contextlist($testUser, 'mod_peerassessment',[
            \context_system::instance()->id,
            $c->id
        ]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(2, $numPas);
        $this->assertEquals(50, $numRatings);
    }
    /**
     * Configure activity with a specifed rating
     *
     * @param int $rating Value that each student will rate each other student.
     */
    private function generate_ratings($paid, $rating, $testGroup = 0, $comment = false)
    {
        // Generate some ratings
        global $DB;
        $instance = $DB->get_record('peerassessment', array('id' => $paid));
        $group = $this->testGroups[$testGroup];
        $pai = new \mod_peerassessment\peerassessment($instance, $group->id);
        // For first group everyone rates everyone "1"
        foreach ($pai->get_members() as $mid => $member1) {
            foreach ($pai->get_members() as $mid2 => $member2) {
                $pai->rate($mid2, $rating, $mid);
            }
        }

        $pai->save_ratings();
        if ($comment) {
            $pai = new \mod_peerassessment\peerassessment($instance, $group->id);
            $cuser = array_values($pai->get_members())[0];
            $pai->comment($cuser->id, "Comment {$cuser->id}");
            $pai->save_ratings();
        }

        foreach($pai->get_members() as $mid => $member1) {
            $this->assertEquals($rating, $pai->get_student_average_rating_received($mid),"Calculated Average Rating Received incorrect");
            $this->assertEquals($rating, $pai->get_student_average_rating_given($mid),"Calculated Average Rating Given incorrect");
        }
    }
}