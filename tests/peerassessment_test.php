<?php
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
}