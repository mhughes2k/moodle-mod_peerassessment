<?php

namespace mod_peerassessment\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

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

    /**
     * Export the supplied personal data for a single peer assessment activity,
     * along with any generatic data or area files.
     * @see \mod_choice\privacy\provider::export_choice_data_for_user()  Basis of this code.
     * @param array $ratingdata
     * @param \context_module $context
     * @param \stdClass $user
     */
    protected static function export_pa_data_for_user(array $padata, \context_module $context, \stdClass $user, $subcontext=[]) {
        // Fetch generic module data.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with peer assessment rating data.
        $contextdata = (object) array_merge((array)$contextdata, $padata);
        writer::with_context($context)->export_data($subcontext, $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }
    /**
     * Export User data
     *
     * We need to export :
     * 1. the ratings made *against* this user (where they are the ratee)
     * 2. the comments made *by* this user (where they are the rater)
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist)
    {
        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        self::export_ratings($contextlist, $user);
        self::export_comments($contextlist, $user);
    }

    /**
     * Export ratings.
     * @param approved_contextlist $contextlist
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected static function export_ratings(approved_contextlist $contextlist, $user) {
        global $DB;

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                      par.rating AS rating,
                      par.timemodified AS timemodified
                 FROM {context} c
           INNER JOIN {course_modules} cm ON cm.id = c.instanceid 
           INNER JOIN {peerassessment_ratings} par ON par.peerassessment = cm.instance
                WHERE c.id {$contextsql}
                      AND par.userid = :userid
             ORDER BY cm.id";

        $params = ['userid' => $user->id]+$contextparams;

        $lastcmid = null;

        $ratings = $DB->get_recordset_sql($sql, $params);
        // Loop through each of the ratings given to this user
        foreach($ratings as $rating) {
            if ($lastcmid != $rating->cmid) {
                if (!empty($ratingdata)) {
                    $context = \context_module::instance($lastcmid);
                    self::export_pa_data_for_user($ratingdata, $context, $user, ['ratings']);
                }
                $ratingdata = [
                    'rating' => [],
                    'timemodified' => \core_privacy\local\request\transform::datetime($rating->timemodified)
                ];
            }
            $ratingdata['rating'] = $rating->rating;
            $lastcmid= $rating->cmid;
        }
        $ratings->close();

        if (!empty($ratingdata)) {
            $context = \context_module::instance($lastcmid);
            self::export_pa_data_for_user($ratingdata, $context, $user, ['ratings']);
        }
    }

    /**
     * Export peer assessment comments made *by* the user
     * @param approved_contextlist $contextlist
     * @param stdClass $user
     */
    protected static function export_comments(approved_contextlist $contextlist, $user) {
        global $DB;
        if (empty($contextlist->get_contextids())) {
            // This will also prevent an empty array being passed to get_in_or_equal().
            return;
        }
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                      pac.studentcomment AS comment,
                      pac.timecreated AS timecreated,
                      pac.timemodified AS timemodified
                 FROM {context} c
           INNER JOIN {course_modules} cm ON cm.id = c.instanceid 
           INNER JOIN {peerassessment_comments} pac ON pac.peerassessment = cm.instance
                WHERE c.id {$contextsql}
                      AND pac.userid = :userid
             ORDER BY cm.id";

        $params = ['userid' => $user->id]+$contextparams;

        $lastcmid = null;

        $comments = $DB->get_recordset_sql($sql, $params);
        foreach($comments as $comment) {
            if ($lastcmid != $comment->cmid) {
                if (!empty($commentdata)) {
                    $context = \context_module::instance($lastcmid);
                    self::export_pa_data_for_user($commentdata, $context, $user, ['comments']);
                }
                $commentdata = [
                    'comment' => [],
                    'timecreated' => \core_privacy\local\request\transform::datetime($comment->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($comment->timemodified)

                ];
            }
            $commentdata['comment'] = $comment->comment;
            $lastcmid= $comment->cmid;
        }
        $comments->close();

        if (!empty($commentdata)) {
            $context = \context_module::instance($lastcmid);
            self::export_pa_data_for_user($commentdata, $context, $user, ['comments']);
        }
    }
    /**
     * @param \context|context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context)
    {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        if (!$cm = get_coursemodule_from_id('peerassessment', $context->instanceid)) {
            return;
        }

        $instanceid = $cm->instance;

        $tx = $DB->start_delegated_transaction();
        $DB->delete_records('peerassessment_ratings', [
            'peerassessment' => $instanceid
        ]);

        $DB->delete_records('peerassessment_comments', [
            'peerassessment' => $instanceid
        ]);
        $tx->allow_commit();

    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist)
    {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $tx = $DB->start_delegated_transaction();
        $userid = $contextlist->get_user()->id;
        foreach($contextlist->get_contexts() as $context) {
            // Sanity check that the context is a peerassessment context.
            if (!$cm = get_coursemodule_from_id('peerassessment', $context->instanceid)) {
                continue;
            }
            $instanceid = $cm->instance;

            $DB->delete_records('peerassessment_ratings', [
                'peerassessment' => $instanceid,
                'ratedby' => $userid
            ]);
            $DB->delete_records('peerassessment_comments', [
                'peerassessment' => $instanceid,
                'userid' => $userid
            ]);
        }
        $tx->allow_commit();
    }
}