<?php
class mod_peerassessment_external extends external_api {
    public static function get_peerassessment_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }
    public static function get_peerassessment_by_courses($courseids = array()) {
            $returnedpas = [];
            $warnings = [];

            $params = self::validate_parameters(
                self::get_peerassessment_by_courses_parameters(),
                ['courseids' => $courseids]
            );
            $courses = [];

            if (empty($params['courseids'])) {
                $courses = enrol_get_my_courses();
                $params['courseids'] = array_keys($courses);
            }

            if (!empty($params['courseids'])) {
                list($courses, $warnings) = external_util::validate_courses($params['courses']);

                $pas = get_all_instances_in_courses('peerassessment', $courses);
                foreach($pas as $pa) {
                    $context = context_module::instance($pa->coursemodule);
                    $d = [];
                    $d['id'] ='';
                }
             }
    }

    public static function get_peerassessment_by_courses_returns() {
        return new external_single_structure(
            array(
                'peerassessments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Book id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_INT, 'Course id'),
                            'name' => new external_value(PARAM_RAW, 'Book name'),
                            'intro' => new external_value(PARAM_RAW, 'The Book intro'),
                            'introformat' => new external_format_value('intro'),
                            'introfiles' => new external_files('Files in the introduction text', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'Time of creation', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'Course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'Visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Group id', VALUE_OPTIONAL),
                        ), 'Peer Assessments'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}