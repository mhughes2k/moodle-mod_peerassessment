<?php

namespace mod_peerassessment\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a user recording a Peer Assessment Rating.
 *
 * @package mod_peerassessment
 * @since Moodle 3.2
 * @copyright University of Strathclyde
 * @author Michael Hughes
 *
 */
class course_module_viewed extends \core\event\course_module_viewed {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'peerassessment_ratings';
    }
    
    public function get_description() {
        return "The user with id '$this->userid' viewed the peer assessment for group id '{$this->other['groupid']}' in peer assessment module id '$this->contextinstanceid'.";
    }
    
    /*public static function get_name() {
        return get_string('eventreportviewed', 'peerassessment');
    }*/
}