<?php
require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_peerassessment_mod_form extends moodleform_mod {

    function definition() {
        global $CFG,$COURSE;
        $mform =& $this->_form;
        //$mform->setHelpButton('upper_bound',array('bounds',get_string('upper_bound','peerassessment'),'peerassessment'));
//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
//        $mform->addElement('static', 'statictype', get_string('assignmenttype', 'assignment'), get_string('type'.$type,'assignment'));
        
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        
        
        // get_records("assignment",'course',$COURSE->id);
        $assignments = array();
        $assignments[0] = get_string('noassignment','peerassessment');
        //print_r($raw_assignments);
        if ($raw_assignments = get_coursemodules_in_course('assignment',$COURSE->id)) {
          foreach($raw_assignments as $a) {
            $assignments[$a->id] = $a->name;
          }
        }

        $mform->addElement('select','assignment',get_string('assignment','peerassessment'),$assignments,array('optional'=>true));
        $mform->addHelpButton('assignment','assignment','peerassessment');
        //array('mods',get_string('assignment','peerassessment'),'peerassessment'));

        $this->add_intro_editor(true, get_string('introduction', 'peerassessment'));
        

        $mform->addElement('selectyesno','canedit',get_string('canedit','peerassessment'));
        $mform->addElement('header','additionalinfo',get_string('additionalinfoheader','peerassessment'));
        $mform->addElement('html',get_string('additionalinfo','peerassessment')); 


        
        //$mform->addElement('header','scheduling',get_string('scheduling','peerassessment'));
        $mform->addElement('header','advancedsettings','Advanced');
        $options=array();
        $options[0]  = get_string('oncefrequency', 'peerassessment');
        
        $options[1]  = get_string('weeklyfrequency', 'peerassessment');
       // $options[2]  = get_string('unlimitedfrequency', 'peerassessment');
        
        $mform->addElement('select', 'frequency', get_string('frequency', 'peerassessment'), $options);
        //$mform->setAdvanced('scheduling');
        //$mform->disabledIf('frequency','assignment','neq','0');
        
        $mform->addElement('date_time_selector', 'timeavailable', get_string('availablefrom', 'peerassessment'),array('optional'=>true));
        //$mform->setAdvanced('timeavailable');
        $mform->addElement('date_time_selector', 'timedue', get_string('submissiondate', 'peerassessment'),array('optional'=>true));
        //$mform->setAdvanced('timedue');

        
        $mform->setAdvanced('advancedsettings');
        $mform->addElement('text','lowerbound',get_string('lowerbound','peerassessment'),array('value'=>'2.5'));
        $mform->addHelpButton('lowerbound','lowerbound','peerassessment');//,array('bounds',get_string('lowerbound','peerassessment'),'peerassessment'));
        $mform->addElement('text','upperbound',get_string('upperbound','peerassessment'),array('value'=>'3.5'));
        $mform->addHelpButton('upperbound','upperbound','peerassessment');//array('bounds',get_string('upperbound','peerassessment'),'peerassessment'));

        $mform->addRule('lowerbound','Must be numeric','numeric',null,'client');
        //$mform->addRule('lower_bound','Must be between less than or equal to 5','compare',array(,'client');
        $mform->addRule('upperbound','Must be numeric','numeric',null,'client');


        $this->standard_coursemodule_elements();//array('groups'=>true, 'groupmembersonly'=>true, 'gradecat'=>true));
		
//		$mform->disabledIf();

        $this->add_action_buttons();        
  }
  
}
