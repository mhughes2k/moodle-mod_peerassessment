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
        $record = (object)(array)$record;
        $defaultsettings = [
            'ratingscale' => 5,
            'grade' => 100,
            'lowerbound' => 2.5,
                'upperbound' => 4.5,
                'groupmode' => 1
        ];
        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }
        return parent::create_instance($record, (array)$options);
    }
}
