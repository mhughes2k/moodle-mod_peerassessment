<?php
namespace mod_peerassessment;
use mod_peerassessment\exception;
use mod_peerassessment\exception\security_exception;
use mod_peerassessment\exception\invalid_rating_exception;
/**
 * Manages all of the responses and calculation of peer assessment ratings
 * for a particular group.
 * @author igs03102
 *
 */
class peerassessment {

    const RATE_ALL_GROUPS = 1;
    const RATE_ANY_GROUP = 2;
	/**
	 * Peer Assessment Instance that this Peer Assessment is associated with.
	 * @var stdClass
	 */
	private $instance;
	/**
	 * ID of the group that the Peer assessment is recorded fo
	 * @var group
	 */
	private $group;
	
	/**
	 * Array of group members indexed by user id.
	 * @var array[int] 
	 */
	private $members;
	/**
	 * The Ratings recieved by each user (userid) by their peers (ratedby).
	 * This is index by the recieving user.
	 * @var unknown
	 */
	private $ratings;
	/**
	 * Instantiate a Peer Assessment.
	 * @param stdClass $instance A PeerAssessment DB Activity instance.
	 */
	public function __construct($instance, $groupid) {
		global $DB;
		$this->instance = $instance;
		if (!$group = groups_get_group($groupid)) {
			throw new \moodle_exception('unabletoloadgroups','peerassessment');
		}
		$this->group = $group;
	}
	
	/*
	public function __get($name) {
		if (!isset($this->$name)) {
			peerassessment_trace("Loading $name for the first time", DEBUG_DEVELOPER);
			$loadfunc = "get_{$name}";
			$this->$name = $this->$loadfunc();
		}
		return $this->$name;
	}*/
	
	/**
	 * Fetch the ratings for this group's instance (from the DB if necessary);
	 * Array is indexed by the *target* of the rating, not who rated them.
	 * @return \mod_peerassessment\unknown
	 */
	public function get_ratings() {
		global $DB;
		peerassessment_trace("Loading Ratings for PA: {$this->instance->id}, group {$this->group->id}", DEBUG_DEVELOPER);
		$memberids = array_keys($this->get_members());
			
		if (empty($memberids)) {
			peerassessment_trace("No members found for group {$this->group->id}", DEBUG_DEVELOPER);
			$this->ratings = array();
			return $this->ratings;
		}
		peerassessment_trace("Member ids: ". implode(',', $memberids), DEBUG_DEVELOPER);
		list($uselect, $uparams) = $DB->get_in_or_equal($memberids);
		$select = "userid {$uselect} AND groupid = ? AND peerassessment = ?";
		$params = array_merge($uparams, array($this->group->id, $this->instance->id));
		
		$dbratings = $DB->get_records_select('peerassessment_ratings', $select, $params);

		$tmpratings = array();
		foreach($dbratings as $r){
			$key = "{$r->userid}:{$r->ratedby}";
			if (!isset($tmpratings[$key])) {
				$tmpratings[$key] = $r;
			}
		}
		// Check that every member has a rating record or null
		foreach($memberids as $mid) {
			foreach($memberids as $mid2) {
				$key = "{$mid}:{$mid2}";	
				if (!isset($tmpratings[$key])) {
					$tmpratings[$key] = null;
				}
			}
		}
		$this->ratings = $tmpratings;
		return $this->ratings;
	}	
	
	/**
	 * Returns array of ratings awarded by $userid, indexed by the ratee userid.
	 * 
	 * @params int $userid
	 */
	public function get_myratings($userid) {
		global $DB;
		$myratings = $DB->get_records('peerassessment_ratings', array(
			'peerassessment' => $this->instance->id,
			'ratedby' => $userid,
			'groupid' => $this->group->id
		));
		
		$o = array();
		foreach($myratings as $r) {
			$o[$r->userid] = $r;
		}
		return $o;
	}
	/**
	 * Fetch the members in the Peer Assessment Rating
	 * @return \mod_peerassessment\unknown
	 */
	public function get_members() {
		if (!isset($this->members)) {
			peerassessment_trace("Loading members", DEBUG_DEVELOPER);
			$this->members = groups_get_members($this->group->id);
			peerassessment_trace(count($this->members) .' members loaded');
		}
		return $this->members;
	}
	
	/**
	 * Used to hold ratings prior to insertion to DB.
	 * This is indexed by rater|ratee so that it should never hold more than 1 rating
	 * @var unknown
	 */
	private $dbratings;
	
	/**
	 * 
	 * @throws dml_exception
	 */
	public function save_ratings() {
		global $DB;
		$rating_transaction = $DB->start_delegated_transaction();
        $isUpdate = false;
        $dbRatingRecords = [];
		if (!empty($this->dbratings)) {
			foreach($this->dbratings as $r) {
				$this->validate_rating($r);	
				if (empty($r->id)) {
					peerassessment_trace('Inserting new rating', DEBUG_DEVELOPER);
					$r->id = $DB->insert_record('peerassessment_ratings', $r);
                    $isUpdate = $isUpdate & false;       
				} else {
					peerassessment_trace('Updating existing rating', DEBUG_DEVELOPER);
                    $isUpdate = $isUpdate & true;
					$DB->update_record('peerassessment_ratings', $r);
				}
				$key = "{$r->ratedby}:{$r->userid}";
				$this->ratings[$key] = $r;
			}
		}
		if (isset($this->dbcomment)) {
		    var_dump($this->dbcomment);
		    if (empty($dbcomment->id)) {
				$this->dbcomment->id = $DB->insert_record('peerassessment_comments', $this->dbcomment);
				$isUpdate = $isUpdate & false;
			} else {
				$DB->update_record('peerassessment_comments', $this->dbcomment);
				$isUpdate = $isUpdate & true;
			}
		}
		$rating_transaction->allow_commit();
        $cm = get_coursemodule_from_instance('peerassessment', $this->instance->id);
        $context = \context_module::instance($cm->id);
        // Record that ratings were made!
        $eventdata = [
                'objectid' => $this->instance->id,
                'context' =>$context,
                'courseid' => $this->instance->course
        ];
        
        if ($isUpdate) {
            $event = \mod_peerassessment\event\rating_updated::create($eventdata);
        } else {
            $event = \mod_peerassessment\event\rating_created::create($eventdata);
            
        }
        foreach($this->dbratings as $dbrating) {
            $event->add_record_snapshot('peerassessment_ratings', $dbrating);
        }
        $event->add_record_snapshot('peerassessment_comments', $this->dbcomment);
        $event->trigger();
        $this->clear_rating_queue();
	}
	public function clear_rating_queue() {
		$this->dbratings = array(); // reset the items waiting for update / insert
		$this->dbcomment = null;
	}
	/**
	 * Record a peer assessment for a user (in a current group).
	 * 
	 * This can be called as many timesas you like, the data is only persisted to the DB
	 * when save_ratings() is called.
	 * @param int $userid
	 * @param unknown $value
	 * @param string $ratedbyuserid
	 * @throws \peer_assessment\exception\invalid_rating_exception Thrown if the rating isn't valid (e.g. users aren't in the group);
	 */
	public function rate($userid, $value, $ratedbyuserid = false) {
		global $USER, $DB;
		if ($ratedbyuserid === false) {
			$ratedbyuserid = $USER->id; 
		};
		
		
		if (!isset($this->ratings)) {
			peerassessment_trace("Ratings not yet loaded", DEBUG_DEVELOPER);
			$this->get_ratings();
		}
		
		$key = "{$userid}:{$ratedbyuserid}";
		peerassessment_trace("Attempting to rate {$key} : {$value}", DEBUG_DEVELOPER);
		if (isset($this->ratings[$key])) {
			peerassessment_trace("Rating found for {$key}", DEBUG_DEVELOPER);
			$r = $this->ratings[$key];
		} else {
			peerassessment_trace("Rating not found for {$key}", DEBUG_DEVELOPER);
			$r = new \stdClass();
			$r->peerassessment = $this->instance->id;
			$r->userid = $userid;
			$r->ratedby = $ratedbyuserid;
			$r->groupid = $this->group->id;
				
		}
		$r->rating = $value;
		$r->timemodified = time();
		$key = "{$ratedbyuserid}|{$userid}";
		$this->validate_rating($r);
		$this->dbratings[$key] = $r;
	}
	
	/**
	 * Deletes ratings by specified userid.
	 * @param int $byuserid ID of user whose ratings to remove.
	 */
	public function delete_ratings($byuserid) {
		global $DB, $USER;
		$ratings = $DB->get_records('peerassessment_ratings', array(
		        'peerassessment' => $this->instance->id,
		        'ratedby' => $byuserid,
		        'groupid' => $this->group->id
		));
		$DB->delete_records('peerassessment_ratings', array(
			'peerassessment' => $this->instance->id,
			'ratedby' => $byuserid,
			'groupid' => $this->group->id
		));
		unset($this->ratings);
		// we should remove the comment too!
        
		// Log this occurence
        $cm = get_coursemodule_from_instance('peerassessment', $this->instance->id);
        $context = \context_module::instance($cm->id);
        $eventdata = [
                'contextid' => $context->id,
                'userid' => $USER->id,
                'relateduserid' => $byuserid,
                'other'=> [
                        'groupid' => $this->group->id
                ]
        ];
        $event = \mod_peerassessment\event\rating_deleted::create($eventdata);
        foreach($ratings as $deleted) {
            $event->add_record_snapshot('peerassessment_ratings', $deleted);
        }
        $event->trigger();
	}
	
	/**
	 * Validates a rating record against the DB.
	 * @param unknown $rating
	 */
	public function validate_rating($rating) {
		// Check that the person doing the rating is a member of the group
		if (!in_array($rating->ratedby, array_keys($this->get_members()))) {
			throw new invalid_rating_exception('notamemberofgroup_warning', 'peerassessment', '',
				(object)array(
						'affecteduserid' => $rating->ratedby,
						'memberids' => array_keys($this->get_members()),
						'groupid' => $this->group->id
				)
			);
		}
		
		// Check that the person being rated is a member of the group
		if (!in_array($rating->userid, array_keys($this->get_members()))) {
			throw new invalid_rating_exception('notamemberofgroup_warning', 'peerassessment', '',
				(object)array(
						'affecteduserid' => $rating->userid,
						'memberids' => array_keys($this->get_members()),
						'groupid' => $this->group->id
				)
			);
				
		}
		
		// TODO check that rating is a valid value on the scale / points
		

	}
	private $dbcomment;
	/**
	 * Saves a comment by userid against the PA activity
	 * @param int $userid Moodle ID of user making the comment
	 * @param string $commenttext Comment text entered by the user.
	 */
	public function comment($userid, $commenttext) {
		global $DB;
		// save the comment
		if ($comment = $DB->get_record('peerassessment_comments', array(
				'userid' => $userid,
				'groupid' => $this->group->id,
                'peerassessment' => $this->instance->id
		))){
			// Should we save a copy?
		} else {
			$comment = new \stdClass();
			$comment->userid = $userid;
			$comment->peerassessment = $this->instance->id;
			$t = time();
			$comment->timemodified = $t;
			$comment->timecreated = $t;
			$comment->groupid = $this->group->id;
		}
		$comment->studentcomment = $commenttext;
		$this->dbcomment = $comment;
	}
	private $comments;
	/**
	 * Returns the comment made by user in the activity 
	 */
	public function get_comment($userid = false) {
		global $DB, $USER;
		if ($userid === false) {
			$userid = $USER->id;
		}
		if (!isset($this->comments)) {
			$comments = $DB->get_records('peerassessment_comments', array(
                'peerassessment' => $this->instance->id,
                'groupid' => $this->group->id
			));
			$this->comments = array();
			foreach($comments as $cid=>$c) {
				$this->comments[$c->userid] = $c;
			}
		}
		if (isset($this->comments[$userid])) {
			return $this->comments[$userid];
		}
		return null;
	}
	
	/**
	 * Check if the PAInstance is available to a given user.
	 * 
	 * It is available if the $touserid *hasn't* given a rating to any 
	 * @param int $touserid User id of user to check if they have given a rating.
	 */
	public function has_rated($touserid) {
		global $DB;
		$given = $DB->count_records('peerassessment_ratings', array(
			'peerassessment' => $this->instance->id,
			'ratedby' => $touserid,
			'groupid' => $this->group->id
		));
		if ($given != 0) {
			return true;
		} 
		return false;
	}
	/**
	 * Get the average rating for a given student.
	 * @param bool $includeself Include ratings use has made of themself.
	 */
	public function get_student_average_rating_received($userid, $includeself = false) {
		global $DB;
		$memberids = array_keys($this->get_members());
		list($uselect, $uparams) = $DB->get_in_or_equal($memberids);
		$sql = "SELECT AVG(rating) AS average
			FROM {peerassessment_ratings} pa
			JOIN {groups_members} gm ON gm.groupid = pa.groupid
			WHERE peerassessment = ?
			AND pa.userid = ? 
			AND pa.groupid = ?
			AND pa.ratedby {$uselect}";

		$params =array($this->instance->id, $userid, $this->group->id);
		$params = array_merge($params, $uparams);
		if (!$includeself) {
			peerassessment_trace('Excluding self', DEBUG_DEVELOPER);
			$sql .= "AND pa.ratedby <> ?";
			$params[] = $userid;
		}
		$rs = $DB->get_record_sql($sql, $params);
		peerassessment_trace("User {$userid} average rating awarded: {$rs->average}", DEBUG_DEVELOPER);
		return $rs->average;
	}
	
	/**
	 * Get the average rating *given* by a student.
	 * @param int $userid ID of the user giving the rating.
	 * @param bool $includeself Include ratings use has made of themself.
	 */
	public function get_student_average_rating_given($userid, $includeself = false) {
		global $DB;
		$memberids = array_keys($this->get_members());
		list($uselect, $uparams) = $DB->get_in_or_equal($memberids);
		
		// Average of ratings given by $userid to users currently in the group 
		$sql = "SELECT AVG(rating) AS average
				FROM {peerassessment_ratings} pa
				WHERE peerassessment = ?
				AND pa.ratedby = ?
				AND pa.groupid = ?
				AND pa.userid {$uselect}
				";
		$params =array($this->instance->id, $userid, $this->group->id);
		$params = array_merge($params, $uparams);
		if (!$includeself) {
			peerassessment_trace('Excluding self', DEBUG_DEVELOPER);
			$sql .= "AND pa.userid <> ?";
			$params[] = $userid;
		}

		$rs = $DB->get_record_sql($sql, $params);
		peerassessment_trace("User {$userid} gave an average rating of : {$rs->average}", DEBUG_DEVELOPER);
		return $rs->average;
	}
	
	const RATING_POINTS = 0;
	const RATING_SCALE = 1;
	private $scale;
	public function get_rating_method() {
		global $DB;
		$scaleid = -($this->instance->ratingscale);
		if ($scale = $DB->get_record('scale', array('id' => $scaleid))) {
			$this->scale = $scale;			
		}
	}
	private $RatingMethodType;
	/**
	 * Returns what sort of rating method is being used
	 */
	public function get_rating_method_type() {
		$scaleid = (int)$this->instance->ratingscale;
		if ($scaleid < 0) {
			return peerassessment::RATING_SCALE;
		} else {
			return peerassessment::RATING_SCALE;
		}
	}
	
	private $scaleitems;
	public function get_scaleitems() {
		
	}
	
	/**
	 * Instead of returning an associative array, return an array with the items
	 * as objects;
	 * @param unknown $list
	 * @param string $separator
	 * @return \stdClass[]
	 */
	static function make_ratings_for_template_from_list($list, $separator=',') {
		//$array = array_reverse(explode($separator, $list), true);
		$array = explode($separator, $list);
		$outarray = array();
		foreach ($array as $key => $item) {
			$r = new \mod_peerassessment\rating\ratingelement();
			$r->rating = $key + 1;
			$r->name = $item;
			$outarray[$key] = $r;
		}
		return $outarray;
	}
	/**
	 * Extracts the rater and ratee from a string that fits the form "rating_X|Y"
	 * @param unknown $string
	 * @return \mod_peerassessment\rating\ratingelement|NULL
	 */
	static function extract_rating_values($key) {
		if (substr(strtolower($key),0,7) =='rating_') {
			$target = substr(strtolower($key),7);
			list($rater, $ratee) = explode("|",$target);
			$r = new \mod_peerassessment\rating\ratingelement();
			$r->rater = $rater;
			$r->ratee = $ratee;
			return $r;
		}
		return null;
	}
}