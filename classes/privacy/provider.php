<?php

namespace mod_peerassessment\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider
{

    public static function get_metadata(collection $collection): collection
    {
        $collection->add_database_table(
            'peerassessment_ratings',
            [
                'userid' => 'privacy:metadata:peerassessment_ratings:userid',
                'peerassessment' => 'privacy:metadata:peerassessment_ratings:peerassessment',
                'rating' => 'privacy:metadata:peerassessment_ratings:rating',
                'ratedby' => 'privacy:metadata:peerassessment_ratings:ratedby',
                'groupid' => 'privacy:metadata:groupid',
                'timemodified' => 'privacy:metadata:timemodified'
            ],
            'privacy:metadata:peerassessment_ratings'
        );
        $collection->add_database_table(
          'peerassessment_comments',
          [
              'userid' => 'privacy:metadata:peerassessment_comments:userid',
              'peerassessment' => 'privacy:metadata:peerassessment',
              'timecreated' => 'privacy:metadata:timecreated',
              'timemodified' => 'privacy:metadata:timemodified',
              'studentcomment' => 'privacy:metadata:peerassessment_comments:studentcomment',
              'groupid' => 'privacy:metadata:groupid',
          ]
        );
        
        return $collection;
    }

    /**
     *
     * Having spoken with DPOffice, ratings belong to the student *being* rated, but not the student *doing* the rating
     * Comments are more problematic as *if* they mention another student then they should be returned as part of the
     * other student's return. The should be included in the *rating* student's data.
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist
    {
        // TODO: Implement get_contexts_for_userid() method.
        /*
         * Peer assessment context should only be in the activity
         */
        $contextlist = new \core_privacy\local\request\contextlist();

        // Fetch all the contexts where the subject has been rated by another user.
        // Fetch all the contexts where the subject has made a comment
        $sql = "SELECT distinct(c.id)
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {peerassessment} pa ON pa.id = cm.instance
             LEFT JOIN {peerassessment_ratings} par ON par.peerassessment = pa.id
             LEFT JOIN {peerassessment_comments} pac ON pac.peerassessment = pa.id
                 WHERE (
                  par.userid = :userid1
                 )
                 OR ( 
                  pac.userid = :userid2
                  AND 
                  pac.studentcomment != ''
                )
        ";
        /* Note: pac.studentcomment shouldn't ever be empty as we expect the student to provide some sort of commentary
         * on their group...But the comments *may* never have any information relating to another user.
         * This is more of an issue later on when we have to extract information.
         */
        $params = [
            'modname' => "peerassessment",
            'contextlevel' => CONTEXT_MODULE,
            'userid1'   => $userid,
            'userid2'   => $userid
        ];

        $contextlist->add_from_sql($sql, $params);

        /* Working below has been combined in to the above single DB query.
         * Some one to validate this !
         */
        /*
        // Fetch all the contexts where the subject has been rated by another user.
        $ratingssql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {peerassessment} pa ON pa.id = cm.instance
             LEFT JOIN {peerassessment_ratings} par ON par.peerassessment = pa.id
                 WHERE (
                  par.userid        = :userid1
                )
        ";

        $ratingsparams = [
            'modname' => "peerassessment",
            'contextlevel' => CONTEXT_MODULE,
            'userid1'   => $userid,
            'userid2'   => $userid,
            'userid3'   => $userid,
        ];

        $contextlist->add_from_sql($ratingssql, $ratingsparams);

        // Fetch all the contexts where the subject has made a comment
        $commentssql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {peerassessment} pa ON pa.id = cm.instance
             LEFT JOIN {peerassessment_comments} pac ON pac.peerassessment = pa.id
                 WHERE (
                  pac.userid        = :userid1
                )
        ";
        $commentsparams = [
            'modname' => "peerassessment",
            'contextlevel' => CONTEXT_MODULE,
            'userid1'   => $userid,
        ];

        $contextlist->add_from_sql($commentssql, $commentsparams);
        */

        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist)
    {
        // TODO: Implement export_user_data() method.
    }

    public static function delete_for_context(deletion_criteria $criteria)
    {
        // TODO: Implement delete_for_context() method.
    }

    public static function delete_user_data(approved_contextlist $contextlist)
    {
        // TODO: Implement delete_user_data() method.
    }
}