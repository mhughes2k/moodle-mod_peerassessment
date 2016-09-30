<?php
namespace mod_peerassessment\rating;

/**
 * Represents the data for passing to the template that represents
 * a single rating option
 */
class ratingelement {
	
	/**
	 * ID of the user making the rating
	 * @var int 
	 */
	public $rater;
	
	/**
	 * ID of the user they are rating.
	 * @var unknown
	 */
	public $ratee;
	
	/**
	 * The Rating value (number or scale value) being recorded
	 * @var unknown
	 */
	public $rating;
	
	/**
	 * Human displayed term
	 * @var unknown
	 */
	public $name;
	
}