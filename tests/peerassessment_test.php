<?php
use mod_peerassessment\exception\multiplecomments_exception;
/**
 * 
 * @author igs03102
 * @group mod_peerassessment
 */
class mod_peerassessment_peerassessment_testcase extends advanced_testcase {
	
	protected $testCourse;
	protected $testUsers;
	protected $testGroups;
	protected $testPa;
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
	public function setUp() {
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
		
		$this->testPa = $this->getDataGenerator()->create_module('peerassessment', array('course' => $this->testCourse->id, 'groupmode' => SEPARATEGROUPS));
	}
    /**
     * Test the pushing of grades to the grade book.
     * 
     * In this test 1 group is reated, with all members being rated 1.
     * 
     * Each student should receive a grade of 1 / 5.
     * 
     * @group peerassessment_gradebook
     */
    public function test_gradebook() {
        $this->resetAfterTest(true);
        $expectedGrade = "1.00000"; // Moodle grades to 5 decimal places.
        $this->gradebooktest($expectedGrade, 1);
    }
    /**
     * Test the pushing of grades to the grade book.
     *
     * In this test 1 group is reated, with all members being rated 5.
     *
     * Each student should receive a grade of 5 / 5.
     *
     * @group peerassessment_gradebook
     */
    public function test_gradebook2() {
        $this->resetAfterTest(true);
        $expectedGrade = "5.00000"; // Moodle grades to 5 decimal places.
        $this->gradebooktest($expectedGrade, 5);
    }
    
    /**
     * Configure activity with a specifed rating, and test that each
     * user receives the expected value in grade book.
     * 
     * @param unknown $expected
     * @param unknown $rating
     */
    private function gradebooktest($expectedGrade, $rating) {
        // Generate some ratings
        global $DB;
        
        $instance = $DB->get_record('peerassessment', array('id' => $this->testPa->id));
        $group = $this->testGroups[0];	// use the 1st group
        $pai = new \mod_peerassessment\peerassessment($instance, $group->id);
        // For first group everyone rates everyone "1"
        foreach($pai->get_members() as $mid => $member1) {
            foreach($pai->get_members() as $mid2 => $member2) {
                $pai->rate($mid2, $rating, $mid);
            }
        }
        $pai->save_ratings();

        // Check ratings have appeared in grade book.
        //$grades = grade_get_grades($courseid, $itemtype, $itemmodule, $iteminstance)
        foreach($pai->get_members() as $mid => $member) {
            //$grade_grades = grade_grade::fetch_users_grades($grade_item, $userids, true);
            $grades = grade_get_grades($this->testCourse->id,
                    'mod', 
                    'peerassessment', 
                    $instance->id,
                    $mid
            );
            //var_dump($grades);
            $gradeitem = array_pop($grades->items);
            $this->assertEquals('peerassessment', $gradeitem->itemmodule);
            $this->assertEquals($instance->id, $gradeitem->iteminstance);

            $grade = array_pop($gradeitem->grades);

            $this->assertEquals($expectedGrade, $grade->grade, "Gradebook value does not match for user {$mid}");
        }
        //
        
    }


	/**
	 * Test the rate() and average function.
	 * 
	 * This uses the first two groups. In the first instance all members of the first group are rated 1.
	 * As a result the averate rating given and recieved should equals 1
	 * 
	 * In the 2nd group all members will be rated 5, so the average for both should equal 5.
	 */
	public function test_rate() {
		global $DB;
		$this->resetAfterTest(true);
		$instance = $DB->get_record('peerassessment', array('id' => $this->testPa->id));
		$group = $this->testGroups[0];	// use the 1st group
		$pai = new \mod_peerassessment\peerassessment($instance, $group->id);
		// For first group everyone rates everyone "1"
		foreach($pai->get_members() as $mid => $member1) {
			foreach($pai->get_members() as $mid2 => $member2) {
				$pai->rate($mid2, 1, $mid);
			}
		}
		$pai->save_ratings();
		
		foreach($pai->get_members() as $mid => $member1) {
			$this->assertEquals(1, $pai->get_student_average_rating_received($mid),"Calculated Average Rating Received incorrect");
			$this->assertEquals(1, $pai->get_student_average_rating_given($mid),"Calculated Average Rating Given incorrect");
		}
		
		$group = $this->testGroups[1];	// use the 2nd group
		$pai = new \mod_peerassessment\peerassessment($instance, $group->id);
		// For first group everyone rates everyone "5"
		foreach($pai->get_members() as $mid => $member1) {
			foreach($pai->get_members() as $mid2 => $member2) {
				$pai->rate($mid2, 5, $mid);
			}
		}
		$pai->save_ratings();
		foreach($pai->get_members() as $mid => $member1) {
			$this->assertEquals(5, $pai->get_student_average_rating_received($mid),"Calculated Average Rating Received incorrect");
			$this->assertEquals(5, $pai->get_student_average_rating_given($mid),"Calculated Average Rating Given incorrect");
		}
		
		// TODO Should really have a test case that does something in the middle of the 2 extremes.
		
		
					// Because we know that we're going to make debugging calls!
	}
	
	/**
	 * 
	 */
	public function test_rate_by_nongroup_user() {
		global $DB;
		$this->resetAfterTest(true);
		$instance = $DB->get_record('peerassessment', array('id' => $this->testPa->id));
		
		$group = $this->testGroups[1];	// use the 1st group
		$pai = new \mod_peerassessment\peerassessment($instance, $group->id);
		$randomuser = $this->getDataGenerator()->create_user();
		
		// For first group everyone rates everyone "5"
		$exceptioncount = 0;
		
		foreach($pai->get_members() as $mid => $member1) {
			try {
				$pai->rate($mid, 5, $randomuser->id);
			 } catch (mod_peerassessment\exception\invalid_rating_exception $e) {
				if ($e->errorcode == 'notamemberofgroup_warning') {
					$exceptioncount++;
				}
			} 
		}
		$this->assertEquals(5, $exceptioncount, "Expected 5 not a member exceptions");
			
	}
	/**
	 * Check has_rated() method.
	 */
	public function test_has_rated() {
		global $DB;
		$this->resetAfterTest(true);
		$rater = $this->testUsers[0];
		$ratee = $this->testUsers[1];
		
		$this->setUser($rater);
		$group = $this->testGroups[0];

		$instance = $DB->get_record('peerassessment', array('id' => $this->testPa->id));
		$pa = new \mod_peerassessment\peerassessment($instance, $group->id);
		
		$hasRated = $pa->has_rated($rater->id);
		$this->assertFalse($hasRated, "Rater should not have a rating in activity: {$hasRated}");
		
		$pa->rate($ratee->id, 1, $rater->id);
		
		$this->assertFalse($pa->has_rated($rater->id), "Rater should not have a rating in activity before save_ratings()");
		
		$pa->save_ratings();
		
		$this->assertTrue($pa->has_rated($rater->id), "Rater should have a rating in activity");
		
		
	}
	
    /**
     * Test that multiple comments are detected and reported.
     */
    public function test_multiplecomments() {
        global $DB;
        $this->resetAfterTest(true);
        $instance = $DB->get_record('peerassessment', array('id' => $this->testPa->id));
        $group = $this->testGroups[0];	// use the 1st group
        $pai = new \mod_peerassessment\peerassessment($instance, $group->id);
        $rater = array_values($pai->get_members())[0];
        foreach($pai->get_members() as $mid2 => $member2) {
            $pai->rate($mid2, 1, $rater->id);
        }
        $commenttext1 = 'test text';
        $pai->comment($rater->id, $commenttext1);
        $pai->save_ratings();
        $commentcount = $DB->count_records('peerassessment_comments', [
                'userid' => $rater->id,
                'peerassessment' => $pai->get_instance()->id,
                'groupid' => $group->id
        ]);
        $this->assertEquals(1, $commentcount);
        
        $group = $this->testGroups[0];	// use the 1st group
        $pai = new \mod_peerassessment\peerassessment($instance, $group->id);
        $rater = array_values($pai->get_members())[0];
        foreach($pai->get_members() as $mid2 => $member2) {
            $pai->rate($mid2, 1, $rater->id);
        }
        $commenttext2 = 'test text2';
        $pai->comment($rater->id, $commenttext2);
        $pai->save_ratings();
        $commentcount = $DB->count_records('peerassessment_comments', [
                'userid' => $rater->id,
                'peerassessment' => $pai->get_instance()->id,
                'groupid' => $group->id
        ]);
        $this->assertEquals(1, $commentcount);
        
        // Inject a second commetn for the user direclty and check that exceptions are thrown
        $comments = $DB->get_records('peerassessment_comments', [
            'userid' => $rater->id,
            'peerassessment' => $pai->get_instance()->id,
            'groupid' => $group->id
        ]);
        $this->assertEquals(1, count($comments));
        $newcomment = clone(array_values($comments)[0]);
        unset($newcomment->id);
        $DB->insert_record('peerassessment_comments', $newcomment);
        
        // Should now be 2 comments for the same pa-user-group
        $comments = $DB->get_records('peerassessment_comments', [
                'userid' => $rater->id,
                'peerassessment' => $pai->get_instance()->id,
                'groupid' => $group->id
        ]);
        $this->assertEquals(2, count($comments));
        // Try to update it
        $this->expectException('\mod_peerassessment\exception\multiplecomments_exception');
        $group = $this->testGroups[0];	// use the 1st group
        $pai = new \mod_peerassessment\peerassessment($instance, $group->id);
        $rater = array_values($pai->get_members())[0];
        foreach($pai->get_members() as $mid2 => $member2) {
            $pai->rate($mid2, 1, $rater->id);
        }
        $commenttext3 = 'test text3';
        $pai->comment($rater->id, $commenttext3);// Should throw an exception
        //$pai->save_ratings(); 
        

        
    }
} 