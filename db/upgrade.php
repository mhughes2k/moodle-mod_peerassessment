<?php
  function xmldb_peerassessment_upgrade($oldversion=0) {
    global $CFG, $THEME, $db;

    $result = true;
    /*
    if ($result && $oldversion < 2007012100) {

    /// Changing precision of field lang on table chat_users to (30)
        $table = new XMLDBTable('chat_users');
        $field = new XMLDBField('lang');
        $field->setAttributes(XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, null, null, 'course');

    /// Launch change of precision for field lang
        $result = $result && change_field_precision($table, $field);
    }

    */

    return $result;
  
  }
?>
