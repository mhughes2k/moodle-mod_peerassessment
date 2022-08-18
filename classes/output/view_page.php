<?php
namespace mod_peerassessment\output;

use renderable;
use templatable;

class view_page implements renderable, templatable {

    protected $peerassessment;

    public function view_page($peerassessment) {
        $this->peerassessment = $peerassessment;
    }

    public function export_for_template(\renderer_base $output) {

    }
}
