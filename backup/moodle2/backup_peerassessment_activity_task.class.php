<?php
/**
 * 
 */
class backup_peerassessment_activity_task extends backup_activity_task {
	
	protected function define_my_settings() {
		
	}
	protected function define_my_steps() {
		$this->add_step(new backup_peerassessment_activity_structure_step('peerassessment_structure', 'peerassessment.xml'));
		
	}
	static public function encode_content_links($content) {
		return $content;
	}
}