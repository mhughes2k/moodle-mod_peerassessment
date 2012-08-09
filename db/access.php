c<?php
$capabilities = array(
    'mod/peerassessment:addinstance' => array(
        'riskbitmask' => 0,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
                'manager' => CAP_ALLOW,
                'teacher' => CAP_ALLOW,
                )
    ),
    'mod/peerassessment:usepeerassessment' => array(
      'riskbitmask' =>  0,
      'captype'     =>  'read',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
      )
    ),
    'mod/peerassessment:recordrating' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'write',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
        'student'   =>  CAP_ALLOW,
      )
    ),
    'mod/peerassessment:viewreport' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'read',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
          'manager' =>CAP_ALLOW,
          'teacher' => CAP_ALLOW
      )
    ),
    'mod/peerassessment:deleteratings' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'write',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
          'manager' =>CAP_ALLOW,
          'teacher' => CAP_ALLOW
      )
    ),
);
