<?php
  $mod_peerassessment_capabilities = array(
    'mod/peerassessment:viewreport' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'read',
      'contextlevel'=>  CONTEXT_MODULE,
      'legacy'      =>  array(
          'admin'   => CAP_ALLOW,
          'teacher' => CAP_ALLOW
      )
    ),
  );
?>
