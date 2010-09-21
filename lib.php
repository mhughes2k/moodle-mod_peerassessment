<?php
define('PA_TABLE','peerassessment');
define('PA_FREQ_ONCE',0);
define('PA_FREQ_WEEKLY',1);
define('PA_FREQ_UNLIMITED',2);
define('PA_COMPLETED',1);
define('PA_COMPLETED_THIS_WEEK',2);

function peerassessment_add_instance($pa) {
  global $USER;
  
  //print_object($pa);
  
  if ($returnid = insert_record('peerassessment',$pa)) {
  
  }
  return $returnid;                              
}

function peerassessment_update_instance($pa) {
  //print_object($pa);
  $pa->id = $pa->instance;
  if ($returnid = update_record('peerassessment',$pa)) {
  
  }
  
  return $returnid;
}