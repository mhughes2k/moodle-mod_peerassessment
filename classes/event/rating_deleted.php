<?php

namespace mod_peerassessment\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents the deletion of a peer assessment rating.
 *
 * @package mod_peerassessment
 * @since Moodle 3.2
 * @copyright University of Strathclyde
 * @author Michael Hughes
 * @property-read array $other {
 *  - int groupid: Moodle ID of the group affected.
 * }
 *
 */
class rating_deleted extends \core\event\assessable_submitted {
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        //$this->data['objecttable'] = 'peerassessment_ratings';
    }
    
    public function get_description() {
        return "The user with id '$this->userid' deleted user '$this->relateduserid's rating for group id '{$this->other['groupid']}' in peer assessment module id '$this->contextinstanceid'.";
    }
    public static function get_name() {
        return get_string('eventratingdeleted', 'peerassessment');
    }
}