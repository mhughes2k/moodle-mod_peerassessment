<?php
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_peerassessment_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $COURSE;
        $mform =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('introduction', 'peerassessment'));

        $mform->addElement('selectyesno', 'canedit', get_string('canedit', 'peerassessment'));
        $mform->addElement('header', 'additionalinfo', get_string('additionalinfoheader', 'peerassessment'));
        $mform->addElement('html', get_string('additionalinfo', 'peerassessment'));

        $mform->addElement('header', 'advancedsettings', 'Advanced');
        $options=array();
        $options[0]  = get_string('oncefrequency', 'peerassessment');

        $options[1]  = get_string('weeklyfrequency', 'peerassessment');

        $mform->addElement('select', 'frequency', get_string('frequency', 'peerassessment'), $options);

        $mform->addElement('date_time_selector', 'timeavailable',
                get_string('availablefrom', 'peerassessment'),
                array('optional' => true)
        );
        $mform->addElement('date_time_selector', 'timedue',
                get_string('submissiondate', 'peerassessment'),
                array('optional' => true)
        );

        $mform->setAdvanced('advancedsettings');
        
        $ratingscaleoptions = array();
        $mform->addElement('modgrade', 'ratingscale', get_string('scale'), $ratingscaleoptions);
        //$mform->disabledIf('ratingscale', 'assessed', 'eq', 0);
        $mform->addHelpButton('ratingscale', 'ratingscale', 'peerassessment');
        //$mform->setDefault('ratingscale', $CFG->gradepointdefault);
        
        $mform->addElement('text', 'lowerbound', get_string('lowerbound', 'peerassessment'), array('value' => '2.5'));
	$mform->setType('lowerbound', PARAM_RAW);
        $mform->addHelpButton('lowerbound', 'lowerbound', 'peerassessment');
        $mform->addElement('text', 'upperbound', get_string('upperbound', 'peerassessment'), array('value' => '3.5'));
	$mform->setType('upperbound', PARAM_RAW);

        $mform->addHelpButton('upperbound', 'upperbound', 'peerassessment');

        $mform->addRule('lowerbound', 'Must be numeric', 'numeric', null, 'client');
        $mform->addRule('upperbound', 'Must be numeric', 'numeric', null, 'client');

        $this->standard_grading_coursemodule_elements();
        
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}
