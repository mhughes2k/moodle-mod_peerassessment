<?php
require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_peerassessment_mod_form extends moodleform_mod {

    function definition() {
        global $CFG,$COURSE;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
//        $mform->addElement('static', 'statictype', get_string('assignmenttype', 'assignment'), get_string('type'.$type,'assignment'));
        
        // get_records("assignment",'course',$COURSE->id);
        $assignments = array();
        $assignments[0] = get_string('noassignment','peerassessment');
        //print_r($raw_assignments);
        if ($raw_assignments = get_coursemodules_in_course('assignment',$COURSE->id)) {
          foreach($raw_assignments as $a) {
            $assignments[$a->id] = $a->name;
          }
        }
        /*
        array();
        $assignments[0]  = "Some Assignment";
        $assignments[1]  = "Some Assignment2";
        */
        $mform->addElement('select','assignment',get_string('assignment','peerassessment'),$assignments,array('optional'=>true));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('selectyesno','canedit',get_string('canedit','peerassessment'));
        
        $mform->addElement('header','scheduling',get_string('scheduling','peerassessment'));
        $options=array();
        $options[0]  = get_string('oncefrequency', 'peerassessment');
        
        $options[1]  = get_string('weeklyfrequency', 'peerassessment');
        $options[2]  = get_string('unlimitedfrequency', 'peerassessment');
        
        $mform->addElement('select', 'frequency', get_string('frequency', 'peerassessment'), $options);
        
        $mform->addElement('date_time_selector', 'timeavailable', get_string('availablefrom', 'peerassessment'),array('optional'=>true));
        $mform->addElement('date_time_selector', 'timedue', get_string('submissiondate', 'peerassessment'),array('optional'=>true));
  
        $mform->addElement('header','additionalinfo','Additional Information');
               
        $mform->addElement('html',get_string('additionalinfo','peerassessment')); 
        $this->standard_coursemodule_elements(array('groups'=>true, 'groupmembersonly'=>true, 'gradecat'=>true));

        $this->add_action_buttons();        
  }
  
}