<?php
namespace mod_peerassessment\tests;
defined('MOODLE_INTERNAL') || die();

global $CFG;

abstract class mod_peerassessment_testcase extends \advanced_testcase{

    const TESTTITLE = 'A Peer Assessment Title';
    const TESTRATING = 3;
    const TESTCOMMENT = 'This is a test comment by a user.';

    protected $course = null;
    protected $numstudents = 40;
    protected $numgroups = 10;
    protected $numstudentspergroup = 4;
    protected $students = [];
    protected $groups = [];

    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setupTestActivity();
    }

    protected function setupTestActivity() {
        $this->course = $this->getDataGenerator()->create_course();

        $this->students = [];
        for($i = 0 ; $i < $this->numstudents; $i++) {
            $this->students[] = $this->getDataGenerator()->create_user();
        }

        $this->groups = [];
        $stupointer = 0;
        for($i = 0; $i < $this->numgroups; $i++) {
            $g = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
            for($j = 0; $j < $this->numstudentspergroup; $j++) {
                $this->getDataGenerator()->create_group_member([
                    'userid' => $this->students[$stupointer],
                    'groupid' => $g->id
                ]);
                $stupointer++;
            }
            $this->groups[] = $g;
        }

        $this->instance = $this->getDataGenerator()->create_module('peerassessment', [
            'name' => self::TESTTITLE,
            'course' => $this->course->id,
            'groupmode' => 1,
            'ratingscale' => 5
        ]);
        $this->generateTestRatings();
    }

    /**
     * Generate a user rating.
     *
     * All members will be rated 3.
     * @throws \mod_peerassessment\exception\invalid_rating_exception
     * @throws moodle_exception
     */
    protected function generateTestRatings() {

        // Use 1st student.
        $student = $this->students[0];
        $this->setUser($student);

        // They will be a member of the first group.
        $studentgroup = $this->groups[0];

        $pa = new \mod_peerassessment\peerassessment($this->instance, $studentgroup->id);

        $members = groups_get_groups_members($studentgroup->id);
        foreach($members as $member) {
            $pa->rate($member->id, self::TESTRATING);
        }
        $pa->comment($student->id, self::TESTCOMMENT);
        $pa->save_ratings();
    }

    /**
     * Function to test that the test cases have been done correctly.
     */
    function test_setup() {
        $this->resetAfterTest();
        $student = $this->students[0];
        $studentgroup = $this->groups[0];
        $pa = new \mod_peerassessment\peerassessment($this->instance, $studentgroup->id);

        $this->assertTrue($pa->has_rated($student->id));

    }
}