<?php

namespace mod_peerassessment\event;

class peerassessment_viewed extends \core\event\base {

    protected function init() {
       $this->data['crud'] = 'r';
       $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

}