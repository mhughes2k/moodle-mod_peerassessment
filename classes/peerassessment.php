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
			debugging("Loading $name for the first time", DEBUG_DEVELOPER);
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
		debugging("Loading Ratings for PA: {$this->instance->id}, group {$this->group->id}", DEBUG_DEVELOPER);
		$memberids = array_keys($this->get_members());
			
		debugging("Member ids: ". implode(',', $memberids), DEBUG_DEVELOPER);
		
		list($uselect, $uparams) = $DB->get_in_or_equal($memberids);
		$select = "userid {$uselect} AND groupid = ? AND peerassessment = ?";
		$params = array_merge($uparams, array($this->group->id, $this->instance->id));
		
		$dbratings = $DB->get_records_select('peerassessment_ratings', $select, $params);

		var_dump($dbratings);
		$tmpratings = array();
		foreach($dbratings as $r){
			$key = "{$r->userid}:{$r->ratedby}";
			if (!isset($tmpratings[$key])) {
				$tmpratings[$key] = $r;
			}
		}
		var_dump($tmpratings);
		$this->ratings = $tmpratings;
		return $this->ratings;
	}	
	
	/**
	 * Fetch the members in the Peer Assessment Rating
	 * @return \mod_peerassessment\unknown
	 */
	protected function get_members() {
		if (!isset($this->members)) {
			debugging("Loading members", DEBUG_DEVELOPER);
			$this->members = groups_get_members($this->group->id);
		}
		return $this->members;
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
		
		if (!isset($this->ratings)) {
			debugging("Ratings not yet loaded", DEBUG_DEVELOPER);
			$this->get_ratings();
		}
		
		$key = "{$userid}:{$ratedbyuserid}";
		debugging("Attempting to rate {$key}", DEBUG_DEVELOPER);
		if (isset($this->ratings[$key])) {
			debugging("Rating found for {$key}", DEBUG_DEVELOPER);
			$r = $this->ratings[$key];
		} else {
			debugging("Rating not found for {$key}", DEBUG_DEVELOPER);
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
				debugging('Inserting new rating', DEBUG_DEVELOPER);
				$r->id = $DB->insert_record('peerassessment_ratings', $r);
			} else {
				debugging('Updating existing rating', DEBUG_DEVELOPER);
				$DB->update_record('peerassessment_ratings', $r);
			}
			$this->ratings[$key] = $r;
		}
		catch (\dml_exception $ex) {
		//	debugging($ex->getMessage() . $ex->errorcode);
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
		if ($given == 0) {
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
		
		$sql = "SELECT AVG(rating) AS average 
				FROM {peerassessment_ratings}
				WHERE peerassessment = ? 
				AND userid = ?
				AND groupid = ? ";
		$params = array($this->instance->id, $userid, $this->group->id);
		if (!$includeself) {
			debugging('Excluding self', DEBUG_DEVELOPER);
			$sql .= "AND ratedby <> ?";
			$params += $userid;
		}
		$rs = $DB->get_record_sql($sql, $params);
		debugging("User {$userid} average rating awarded: {$rs->average}", DEBUG_DEVELOPER);
		return $rs->average;
	}
	
	/**
	 * Get the average rating *given* by a student.
	 * @param int $userid ID of the user giving the rating.
	 * @param bool $includeself Include ratings use has made of themself.
	 */
	public function get_student_average_rating_given($userid, $includeself = false) {
		global $DB;
		$sql = "SELECT AVG(rating) AS average
				FROM {peerassessment_ratings}
				WHERE peerassessment = ?
				AND ratedby = ?
				AND groupid = ?";
		$params =array($this->instance->id, $userid, $this->group->id);
		if (!$includeself) {
			debugging('Excluding self', DEBUG_DEVELOPER);
			$sql .= "AND userid <> ?";
			$params += $userid;
		}
		$rs = $DB->get_record_sql($sql, $params);
		debugging("User {$userid} average rating awarded: {$rs->average}", DEBUG_DEVELOPER);
		return $rs->average;
	}
	
	
}