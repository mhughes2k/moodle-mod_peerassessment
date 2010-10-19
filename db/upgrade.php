<?php
  function xmldb_peerassessment_upgrade($oldversion=0) {
    global $CFG, $THEME, $db;

    $result = true;
    if ($result && $oldversion < 2010091407) {

    /// Define field lowerbound to be added to peerassessment
        $table = new XMLDBTable('peerassessment');
        $field = new XMLDBField('lowerbound');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '3, 2', XMLDB_UNSIGNED, null, null, null, null, '2.5', 'canedit');

    /// Launch add field lowerbound
        $result = $result && add_field($table, $field);
		

    /// Define field upperbound to be added to peerassessment
        $table = new XMLDBTable('peerassessment');
        $field = new XMLDBField('upperbound');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '3, 2', XMLDB_UNSIGNED, null, null, null, null, '3.5', 'lowerbound');

    /// Launch add field upperbound
        $result = $result && add_field($table, $field);
    }


    return $result;
  
  }
?>
