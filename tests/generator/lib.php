<?php
/**
 * mod_peerassessment peer assessment data generator
 */
defined('MOODLE_INTERNAL') || die();

class mod_peerassessment_generator extends testing_module_generator {
	
	public function reset() {
		parent::reset();
	}
	
	public function create_instance($record = null, array $options = null) {
		$pa_config = get_config("mod_peerassessment");
		
		$record = (array)$record + array(
				
		);
		return parent::create_instance($record, (array)$options);
	}
}