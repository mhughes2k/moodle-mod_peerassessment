<?php

/**
 * Unit tests for the Mod Peer Assessment Generator
 * @author Michael Hughes
 * @category phpunit
 * @copyright 2016 Michael Hughes
 * @group mod_peerassessment
 */
class mod_peerassessment_generator_testcase extends advanced_testcase {
	/**
	 * Test the Generator.
	 */
	public function test_generator() {
		global $DB;
		$this->resetAfterTest(true);
		
		$this->assertEquals(0, $DB->count_records('peerassessment'));
		
		$generator = $this->getDataGenerator()->get_plugin_generator('mod_peerassessment');
		$this->assertInstanceOf('mod_peerassessment_generator', $generator);
		$this->assertEquals('peerassessment', $generator->get_modulename());
	}
	
	/**
	 * Test the Generator create instance method.
	 */
	public function test_create_instance() {
		global $DB;
		$this->resetAfterTest(true);
		
		$this->assertEquals(0, $DB->count_records('peerassessment'));
		$this->setAdminUser();
		
		$course = $this->getDataGenerator()->create_course();
		
		$this->assertFalse($DB->record_exists('lesson',array('course' => $course->id)));
		
		$this->getDataGenerator()->create_module('peerassessment', array('course' => $course->id));
		$this->getDataGenerator()->create_module('peerassessment', array('course' => $course->id));
		$this->getDataGenerator()->create_module('peerassessment', array('course' => $course->id));
		$pa = $this->getDataGenerator()->create_module('peerassessment', array('course' => $course->id));
		$this->assertEquals(4, $DB->count_records('peerassessment'));
		
		$cm = get_coursemodule_from_instance('peerassessment', $pa->id);
		$this->assertEquals($pa->id, $cm->instance);
		$this->assertEquals('peerassessment', $cm->modname);
		$this->assertEquals($course->id, $cm->course);
		
		$context = context_module::instance($cm->id);
		$this->assertEquals($pa->cmid, $context->instanceid);
	}
}