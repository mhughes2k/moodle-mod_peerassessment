<?php

namespace mod_peerassessment\output;

defined('MOODLE_INTERNAL') || die();

use context_module;
//use mod_peerassessment_exter

/**
 * Mobile output class for peer assessment
 * @copyright 2018 Michael Hughes, University of Strathclyde
 * @package mod_peerassessment
 * @license http://www.gnu.org/copyhleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Display view of assessment activity
     * @param $args
     */
    public static function view($args) {

    }
    /**
     * Display rating form.
     * @param $args
     */
    public static function rate($args) {
        global $OUTPUT, $USER, $DB;

    }

    /**
     * Display readonly - view
     * @param $args
     */
    public static function readonly_view($args) {

    }
}
