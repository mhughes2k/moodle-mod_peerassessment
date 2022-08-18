<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_peerassessment;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden!');

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir . '/csvlib.class.php');

class export_form extends \moodleform {
    public function __construct($url, $groups, $cm, $data) {
        $this->_groups = $groups;
        $this->_cm = $cm;
        $this->_data = $data;
        parent::__construct($url);
    }

    public function definition() {

        $mform = $this->_form;
        $mform->addElement('header', 'export', get_string('chooseexportformat', 'mod_peerassessment'));
        $choices = \csv_import_reader::get_delimiter_list();
        $key = array_search(';', $choices);
        if (! $key === false) {
            unset($choices[$key]);
        }

        $typesarray = [];
        $str = get_string('csvwithseleteddelimiter', 'mod_peerassessment');
        $typesarray[] = $mform->createElement("radio", 'exporttype', null, $str . '&nbsp;', 'csv');
        $typesarray[] = $mform->createElement('select', 'delimiter_name', null, $choices);
        $typesarray[] = $mform->createElement('radio', 'exporttype', null, "JSON&nbsp;", 'json');

        $mform->addGroup($typesarray, 'exportar', '', array(''), false);
        $mform->addRule('exportar', null, 'required');
        $mform->setDefault('exporttype', 'csv');
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }
        $fields = ['ratings', 'comment'];
        $mform->addElement("header", 'export', get_string('chooseexportfields', 'mod_peerassessment'));
        foreach ($fields as $field) {
            $html = "";
            $name = ucfirst($field);
            $mform->addElement("advcheckbox", "field_{$field}", $html, $name, array('group' => 1));
            $mform->setDefault("field_{$field}", 1);
        }

        $mform->addElement("header", 'hdrexportgroups', get_string('chooseexportgroups', 'mod_peerassessment'));
        $mform->setExpanded('hdrexportgroups', false);
        foreach ($this->_groups as $group) {
            $html = "";
            $name = $group->name;
            $mform->addElement("advcheckbox", "group_{$group->id}", $html, $name, array('group' => 2));
            $mform->setDefault("group_{$group->id}", 1);
        }
        $this->add_checkbox_controller(2, null, null, 1);

        $this->add_action_buttons(true, get_string('exportentries', 'data'));
    }
}
