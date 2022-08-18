<?php
namespace mod_peerassessment\exception;

class multiplecomments_exception extends \moodle_exception {
    
    function __construct($count, $userid, $instanceid) {
        parent::__construct('multiplecommentsfounderror', 
                'mod_peerassessment', '', 
                ['count' => $count, 'userid' => $userid, 'instanceid' => $instanceid]
            );
    }
}
