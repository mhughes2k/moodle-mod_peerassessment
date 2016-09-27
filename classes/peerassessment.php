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
	 * The Ratings given to each user by their peers
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
		$group = groups_get_group($groupid,'*', MUST_EXIST);
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
	 * @return \mod_peerassessment\unknown
	 */
	public function get_ratings() {
		global $DB;
		debugging("Loading Ratings for PA: {$this->instance->id}, group {$this->group->id}", DEBUG_DEVELOPER);
		$this->members = groups_get_members($this->group->id);
		$memberids = array_keys($this->members);
		
		debugging("Member ids: ". implode(',', $memberids), DEBUG_DEVELOPER);
		
		list($uselect, $uparams) = $DB->get_in_or_equal($memberids);
		$select = "userid {$uselect} AND groupid = ? AND peerassessment = ?";
		$params = array_merge($uparams, array($this->group->id, $this->instance->id));
		
		$ratings = $DB->get_records_select('peerassessment_ratings', $select, $params);
		foreach($ratings as $r) {
			if (!isset($this->ratings[$r->userid])) {
				$this->ratings[$r->userid] = $r;
			} else {
				$existing = $this->ratings[$r->userid];
				debugging("Rating already found for {$r->userid}: {$existing->id}");
			}
		}

		return $this->ratings;
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
		if (isset($this->ratings[$userid])) {
			debugging("Rating found for {$userid}", DEBUG_DEVELOPER);
			$r = $this->ratings[$userid];
		} else {
			debugging("Rating not found for {$userid}", DEBUG_DEVELOPER);
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
			$this->ratings[$userid] = $r;
		}
		catch (\dml_exception $ex) {
		//	debugging($ex->getMessage() . $ex->errorcode);
		throw $ex;
		}
	}
	
	/**
	 * Get the average rating for a given student.
	 */
	public function get_student_average_rating($userid) {
		global $DB;
		
		$sql = "SELECT AVG(rating) AS average 
				FROM {peerassessment_ratings}
				WHERE peerassessment = ? 
				AND userid = ?
				AND groupid = ?";
		$rs = $DB->get_record_sql($sql, array($this->instance->id, $userid, $this->group->id));
		debugging("User {$userid} average rating awarded: {$rs->average}", DEBUG_DEVELOPER);
		return $rs->average;
	}
	
	
}