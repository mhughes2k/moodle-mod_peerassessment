<?php // $Id: pagelib.php,v 1.10 2007/04/16 20:59:17 mattc-catalyst Exp $

require_once($CFG->libdir.'/pagelib.php');

define('PAGE_PEERASSESSMENT_VIEW',   'mod-peerassessment-view');

page_map_class(PAGE_PEERASSESSMENT_VIEW, 'page_peerassessment');

$DEFINEDPAGES = array(PAGE_PEERASSESSMENT_VIEW);

/**
 * Class that models the behavior of a chat
 *
 * @author Jon Papaioannou
 * @package pages
 */

class page_peerassessment extends page_generic_activity {

    function init_quick($data) {
        if(empty($data->pageid)) {
            error('Cannot quickly initialize page: empty course id');
        }
        $this->activityname = 'peerassessment';
        parent::init_quick($data);
    }

    function get_type() {
        return PAGE_PEERASSESSMENT_VIEW;
    }
}

?>
