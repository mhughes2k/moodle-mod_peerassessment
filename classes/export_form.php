<?php
namespace mod_peerassessment;
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden!');
}
//require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once ($CFG->libdir.'/formslib.php');
require_once($CFG->libdir . '/csvlib.class.php');
//MoodleQuickForm::registerElementType('pagrade', $CFG->dirroot.'/mod/peerassessment/classes/output/pagrade.php', '\mod_peerassessment\output\pagrade');

class export_form extends \moodleform {
    public function __construct($url, $groups, $cm, $data) {
        $this->_groups = $groups;
        $this->_cm = $cm;
        $this->_data = $data;
        parent::__construct($url);
    }
    function definition() {
        
        $mform = $this->_form;
        //$mform->addElement('hidden', 'id');
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
        $typesarray[]= $mform->createElement('radio', 'exporttype', null, "JSON&nbsp;", 'json');
        //$typesarray[] = $optjson;
        
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
        $fields= ['comment','ratings'];
        $mform->addElement("header", 'export', get_string('chooseexportfields', 'mod_peerassessment'));
        foreach($fields as $field) {
            $html= "";//<div class='d-inline-block'>{$field}</div>";
            $name = ucfirst($field);
            $mform->addElement("advcheckbox", "field_{$field}", $html, $name, array('group' => 1));
            $mform->setDefault("field_{$field}", 1);
        }
        $mform->disabledIf('field_ratings', 'exporttype', 'eq', 'csv');
        $this->add_checkbox_controller(1, null, null, 1);
        
        $mform->addElement("header", 'hdrexportgroups', get_string('chooseexportgroups', 'mod_peerassessment'));
        $mform->setExpanded('hdrexportgroups', false);
        foreach($this->_groups as $group) {
            $html = "";//<div class='d-inline-block'>{$group->name}</div>";
            $name = $group->name;
            $mform->addElement("advcheckbox", "group_{$group->id}", $html, $name, array('group' => 2));
            $mform->setDefault("group_{$group->id}", 1);
        }
        $this->add_checkbox_controller(2, null, null, 1);
        
        
        $this->add_action_buttons(true, get_string('exportentries', 'data'));
    }
}