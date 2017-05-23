<?php
function xmldb_peerassessment_upgrade($oldversion=0) {
    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager();
    $result = true;
    /*
    if ($result && $oldversion < 2010091407) {

    /// Define field lowerbound to be added to peerassessment
        $table = new xmldb_table('peerassessment');
        $field = new xmldb_field('lowerbound');
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
    */
    if ($result && $oldversion < 2010120304) {

        // Define table classcatalogue to be created
        $table = new xmldb_table('peerassessment');

        $field = new xmldb_field('course', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, null, null, null, 'cachelastupdated');

        // Conditionally launch add field course
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'course');

        // Conditionally launch add field intro
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'intro');

        // Conditionally launch add field introformat
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // classcatalogue savepoint reached
        upgrade_mod_savepoint(true, 2010120304, 'peerassessment');
    }
    /*
     * All of the subsequent updates can be rolled in to a single update later.
     */
    if ($oldversion < 2016092305) {
    
        // Define field groupid to be added to peerassessment_ratings.
        $table = new xmldb_table('peerassessment_ratings');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'ratedby');
    
        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('peerassessment');
        $field = new xmldb_field('ratingscale', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'introformat');
        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('completionrating', XMLDB_TYPE_INTEGER, '1', null, null, null, 0, 'ratingscale');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
   
        // Define field groupid to be added to peerassessment_ratings.
        $table = new xmldb_table('peerassessment_comments');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, 0, 'studentcomment');
        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Migrate all "old" peer assessments to use simple 5 point ratings
        require_once("{$CFG->dirroot}/mod/peerassessment/db/upgradelib.php");
        mod_peerassessment_upgrade_to_2016092305($dbman);
        upgrade_mod_savepoint(true, 2016092305, 'peerassessment');
    }
    return $result;

}

