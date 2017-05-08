<?php
class restore_peerassessment_activity_structure_step extends restore_activity_structure_step {
	protected function define_structure() {
		$paths = array();
		$userinfo = $this->get_setting_value('userinfo');
		$paths[] = new restore_path_element('peerassessment', '/activity/peerassessment');
		$paths[] = new restore_path_element('peerassessment_rating', '/activity/peerassessment/ratings/rating');
		$paths[] = new restore_path_element('peerassessment_comment', '/activity/peerassessment/comments/comment');
		
		return $this->prepare_activity_structure($paths);
	}
	
	protected function process_peerassessment($data) {
		global $DB;
		
		$data = (object)$data;
		$oldid = $data->id;
		$data->course = $this->get_courseid();
		
		$newitemid = $DB->insert_record('peerassessment', $data);
		$this->apply_activity_instance($newitemid);
	}
	
	protected function process_peerassessment_rating($data) {
		global $DB;
		
		$data = (object)$data;
		$oldid = $data->id;
		$data->peerassessment = $this->get_new_parentid('peerassessment');
		//$data->timemodified = $this->apply_date_offset($data->modified)	// TODO concerns about changing this!
		$newitemid = $DB->insert_record('peerassessment_ratings', $data);
		$this->set_mapping('peerassessment_rating', $oldid, $newitemid);
	}
	
	protected function process_peerassessment_comment($data) {
		global $DB;
		
		$data = (object)$data;
		$oldid = $data->id;
		$data->peerassessment = $this->get_new_parentid('peerassessment');
		//$data->timemodified = $this->apply_date_offset($data->modified)	// TODO concerns about changing this!
		$newitemid = $DB->insert_record('peerassessment_comments', $data);
		$this->set_mapping('peerassessment_rating', $oldid, $newitemid);
	}
	protected function after_execute() {

	}
}