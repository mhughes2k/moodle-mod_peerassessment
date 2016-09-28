<?php
require_once("{$CFG->dirroot}/mod/peerassessment/backup/moodle2/restore_peerassessment_stepslib.php");

class restore_peerassessment_activity_task extends restore_activity_task {
	
	protected function define_my_settings() {
		
	}
	
	protected function define_my_steps() {
		$this->add_step(new restore_peerassessment_activity_structure_step('peerassessment_structure', 'peerassessment.xml'));
		
	}
	
	static public function define_decode_contents() {
		$contents = array();
		// TODO Decode intro
		return $contents;
	}
	
	static public function define_decode_rules() {
		$rules = array();
		return $rules;
		
	}
	
	static public function define_restore_log_rules() {
		$rules = array();
		return $rules;
	}
	
	static public function define_restore_log_rules_for_course() {
		$rules = array();
		return $rules;
	}
}