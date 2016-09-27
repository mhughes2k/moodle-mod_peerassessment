<?php
/**
 * 
 */

class backup_peerassessment_activity_structure_step extends backup_activity_structure_step {
	
	protected function define_structure() {
		
		$userinfo = $this->get_setting_value('userinfo');
		// Define each element separated
		$pa = new backup_nested_element('peerassessment', array('id'), array(
				'name','intro', 'timeavailable','timedue','canedit','lowerbound','upperbound',
				'timemodified','frequency'
		));
		
		$ratings = new backup_nested_element('ratings');
		
		$rating = new backup_nested_element('rating', array('id'), array(
				'userid','rating','timemodified','ratedby', 'groupid'
		));
		
		$comments = new backup_nested_element('comments');
		$comment = new backup_nested_element('comment', array('id'), array(
			'userid','timemodified','timecreated', 'studentcomment'	
		));
		// Build the tree
		$pa->add_child($ratings);
		$ratings->add_child($rating);
		$pa->add_child($comments);	// TODO Comments should really belong to Ratings, but we haven't changed that in the code yet.
		$comments->add_child($comment);
		
		// Define sources
		$pa->set_source_table('peerassessment', array('id' => backup::VAR_ACTIVITYID));
		if ($userinfo) { 
			$rating->set_source_table('peerassessment_ratings', array('peerassessment' => backup::VAR_PARENTID));
			$comments->set_source_table('peerassessment_comments', array('peerassessment' => backup::VAR_PARENTID));
		}
		// Define id annotations
		$rating->annotate_ids('user', 'userid');
		$rating->annotate_ids('user', 'ratedby');
		
		$comment->annotate_ids('user', 'userid');
		// Define file annotations
		
		// Return the root element (choice), wrapped into standard activity structure
		return $this->prepare_activity_structure($pa);
	}
}