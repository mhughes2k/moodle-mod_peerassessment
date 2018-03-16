<?php

namespace mod_peerassessment\output;

use plugin_renderer_base;
use mod_peerassessment\output\view_page;

class renderer extends plugin_renderer_base {
    public function render_view_page(view_page $page) {
        global $PAGE;
        $data = $page->export_for_template($this);
        $PAGE->set_button($data['viewreportbutton']);
        return parent::render_from_template('mod_peerassessment/view', $data);
    }

}