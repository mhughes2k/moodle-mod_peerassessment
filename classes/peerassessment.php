<?php
namespace mod_peerassessment;

/**
 * Manages all of the responses and calculation of peer assessment ratings
 * for a particular group.
 * @author igs03102
 *
 */
class peerassessment {
	
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
	 * 
	 * @var unknown
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
	
	public function __get($name) {
		if (!isset($this->$name)) {
			peerassessment_trace("Loading $name for the first time", DEBUG_DEVELOPER);
			$loadfunc = "get_{$name}";
			$this->$name = $this->$loadfunc();
		}
		return $this->$name;
	}
	
	/**
	 * Fetch the ratings for this group's instance (from the DB if necessary);
	 * Array is indexed by the *target* of the rating, not who rated them.
	 * @return \mod_peerassessment\unknown
	 */
	protected function get_ratings() {
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
	protected function get_members() {
		if (!isset($this->members)) {
			peerassessment_trace("Loading members", DEBUG_DEVELOPER);
			$this->members = groups_get_members($this->group->id);
			peerassessment_trace(count($this->members) .' members loaded');
		}
		return $this->members;
	}
	
	/**
	 * DB Transaction for rating action
	 * @var \moodle_transaction
	 */
	private $rating_transaction;
	public function start_rating() {
		global $DB;
		if (is_null($this->rating_transaction)) {
			$this->rating_transaction = $DB->start_delegated_transaction();
		} else {
			peerassessment_trace("Rating transaction already in progress", DEBUG_DEVELOPER);
		}
	}
	public function end_rating() {
		global $DB;
		if (!is_null($this->rating_transaction)) {
			$this->rating_transaction->allow_commit();
		} else {
			peerassessment_trace('No Rating Transaction in progress', DEBUG_DEVELOPER);
		}
	}
	/**
	 * Record a peer assessment for a user (in a current group)
	 * @param int $userid
	 * @param unknown $value
	 * @param string $ratedbyuserid
	 * @throws dml_exception
	 */
	public function rate($userid, $value, $ratedbyuserid = false) {
		global $USER, $DB;
		if ($ratedbyuserid === false) {
			$ratedbyuserid = $USER->id; 
		};
		
		if (!in_array($ratedbyuserid, array_keys($this->get_members()))) {
			throw new \moodle_exception('notmemberofgroup', 'peerassessment', '',
				(object)array(
					'rater' => $ratedbyuserid,
					'memberids' => array_keys($this->get_members())
				)
			);
		}
		
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

		try {
			if (empty($r->id)) {
				peerassessment_trace('Inserting new rating', DEBUG_DEVELOPER);
				$r->id = $DB->insert_record('peerassessment_ratings', $r);
			} else {
				peerassessment_trace('Updating existing rating', DEBUG_DEVELOPER);
				$DB->update_record('peerassessment_ratings', $r);
			}
			$this->ratings[$key] = $r;
		}
		catch (\dml_exception $ex) {
		//	peerassessment_trace($ex->getMessage() . $ex->errorcode);
		throw $ex;
		}
	}
	
	/**
	 * Check if the PAInstance is available to a given user.
	 * 
	 * It is available if the $touserid *hasn't* given a rating to any 
	 * @param unknown $touserid
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
		
		
		/*
		
		global $DB;
		
		$sql = "SELECT AVG(rating) AS average 
				FROM {peerassessment_ratings}
				WHERE peerassessment = ? 
				AND userid = ?
				AND groupid = ? ";
		$params = array($this->instance->id, $userid, $this->group->id);
		if (!$includeself) {
			peerassessment_trace('Excluding self', DEBUG_DEVELOPER);
			$sql .= "AND ratedby <> ?";
			$params += $userid;
		}
		$rs = $DB->get_record_sql($sql, $params);
		peerassessment_trace("User {$userid} average rating awarded: {$rs->average}", DEBUG_DEVELOPER);
		return $rs->average;*/
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