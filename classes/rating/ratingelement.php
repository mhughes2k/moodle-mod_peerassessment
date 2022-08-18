<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
