<?php
class restore_peerassessment_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');
        $paths[] = new restore_path_element('peerassessment', '/activity/peerassessment');
        if ($userinfo) {
            $paths[] = new restore_path_element('peerassessment_rating', '/activity/peerassessment/ratings/rating');
            $paths[] = new restore_path_element('peerassessment_comment', '/activity/peerassessment/comments/comment');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_peerassessment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // TODO Shift the cut off dates
        // timeavailable
        if ($data->timeavailable > 0) {
            $data->timeavailable = $this->apply_date_offset($data->timeavailable);
        }
        // timedue fields.
        if ($data->timedue > 0) {
            $data->timedue = $this->apply_date_offset($data->timedue);
        }
        if ($data->ratingscale < 0) {
            $data->ratingscale = - ($this->get_mappingid('scale', abs($data->ratingscale)));
        }

        $newitemid = $DB->insert_record('peerassessment', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_peerassessment_rating($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->peerassessment = $this->get_new_parentid('peerassessment');
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }
        if ($data->ratedby > 0) {
            $data->ratedby = $this->get_mappingid('user', $data->ratedby);
        }
        if ($data->groupid > 0) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
        }

        //$data->timemodified = $this->apply_date_offset($data->modified)    // TODO concerns about changing this!
        $newitemid = $DB->insert_record('peerassessment_ratings', $data);
        $this->set_mapping('peerassessment_rating', $oldid, $newitemid);
    }

    protected function process_peerassessment_comment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->peerassessment = $this->get_new_parentid('peerassessment');
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }
        //$data->timemodified = $this->apply_date_offset($data->modified)    // TODO concerns about changing this!
        $newitemid = $DB->insert_record('peerassessment_comments', $data);
        $this->set_mapping('peerassessment_rating', $oldid, $newitemid);
    }
    protected function after_execute() {

    }
}
