<?php
  $mod_peerassessment_capabilities = array(
    'mod/peerassessment:usepeerassessment' => array(
      'riskbitmask' =>  0,
      'captype'     =>  'read',
      'contextlevel'=>  CONTEXT_MODULE,
      'legacy'      =>  array(
        'admin'   =>  CAP_ALLOW,
      )
    ),
    'mod/peerassessment:recordrating' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'write',
      'contextlevel'=>  CONTEXT_MODULE,
      'legacy'      =>  array(
        'student'   =>  CAP_ALLOW,
      )
    ),
    'mod/peerassessment:viewreport' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'read',
      'contextlevel'=>  CONTEXT_MODULE,
      'legacy'      =>  array(
          'admin'   => CAP_ALLOW,
          'teacher' => CAP_ALLOW
      )
    ),
    'mod/peerassessment:deleteratings' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'write',
      'contextlevel'=>  CONTEXT_MODULE,
      'legacy'      =>  array(
          'admin'   => CAP_ALLOW,
          'teacher' => CAP_ALLOW
      )
    ),
  );
?>
