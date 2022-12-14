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

namespace mod_peerassessment;

class exporter {
    private $type;
    private $pa;
    private $delimiter;
    private $fields;
    private $selectedfields;
    private $selectedgroups;

    /**
     * @param unknown $pa Peer Assessment DB Record
     * @param unknown $type
     * @param unknown $delimiter
     * @param unknown $fields
     * @param unknown $selectedfields
     * @param number $selectedgroups
     */
    public function __construct($pa, $type, $delimiter, $fields, $selectedfields, $selectedgroups = 0) {
        $this->pa = $pa;
        $this->type = $type;
        $this->delimiter = $delimiter;
        $this->fields = $fields;
        $this->selectedfields = $selectedfields;
        $this->selectedgroups = $selectedgroups;
    }
    protected function get_exportdata() {
        $includecomments = in_array('comment', $this->selectedfields);
        $includeratings = true;
        $exportdata = [];
        // Ouput CSV headers.
        $line = ['userid', 'firstname', 'lastname', 'regno', 'groupid', 'groupname'];
        if ($includecomments) {
            $line[] = 'comment';
        }
        if ($includeratings) {
            $line[] = "ratedduserid";
            $line[] = "rateduserfirstname";
            $line[] = "rateduserlastname";
            $line[] = "rateduserregno";
            $line[] = 'rating';
        }
        $exportdata[] = $line;
        // Output the content.
        foreach ($this->selectedgroups as $group) {
            $pa = new peerassessment($this->pa, $group);
            try {
                $g = self::get_group($group);

                $members = $pa->get_members();

                foreach ($members as $member) {
                    $comment = $pa->get_comment($member->id);
                    $line = [];
                    /* User Information */
                    $line["userid"] = $member->id;
                    $line["firstname"] = $member->firstname;
                    $line["lastname"] = $member->lastname;
                    $line["regno"] = $member->phone2; // Now phone2 .
                    $line["groupid"] = $g->id;
                    $line ['groupname'] = $g->name;

                    if ($includeratings) {
                        $ratings = $pa->get_ratings();
                        foreach ($members as $rated) {
                            $rline = $line;
                            if ($includecomments) {
                                if (null !== $comment) {
                                    $rline["comment"] = $comment->studentcomment;
                                } else {
                                    $rline["comment"] = "";
                                }
                            }
                            $rline["rateduserid"] = $rated->id;
                            $rline["rateduserfirstname"] = $rated->firstname;
                            $rline["rateduserlastname"] = $rated->lastname;
                            $rline["rateduserregno"] = $rated->phone2;
                            $key = "$rated->id:$member->id";
                            if (isset($ratings[$key])) {
                                $rating = $ratings[$key];
                                $rline["rating"] = $rating->rating;
                            } else {
                                $rline["rating"] = "";
                            }
                            $exportdata[] = $rline;
                        }
                    } else {
                        /* Comment details */
                        if ($includecomments) {
                            if (null !== $comment = $pa->get_comment($member->id)) {
                                $line[] = $comment->studentcomment;
                            } else {
                                $line[] = null;
                            }
                        }
                    }
                    if (!$includeratings) {
                        $exportdata[] = $line;
                    }
                }
            } catch (\coding_exception $e) {
                debugging("{$e->getMessage()}", DEBUG_DEVELOPER);
                continue;
            }
        }
        return $exportdata;
    }
    /**
     * Static acceleration for groups we've seen
     * @var unknown
     */
    private static $groups;
    public static function get_group($groupid) {
        if (isset(self::$groups[$groupid])) {
            return self::$groups[$groupid];
        } else {
            if ($g = groups_get_group($groupid)) {
                self::$groups[$g->id] = $g;
                return $g;
            }
        }
        throw new \coding_exception("Group does not exist");
    }

    /**
     * Export the data
     * if $exportdata is not provided then get_exportdata() will be implicitly
     * called to fetch the required data using the class's set parameters.
     *
     * @param array $exportdata Array of data to be exported
     * @return unknown
     */
    public function export($exportdata = false) {
        if ($exportdata === false) {
            $exportdata = $this->get_exportdata();
        }
        $exportfunc = "export_{$this->type}";
        if (\method_exists($this, $exportfunc)) {
            return $this->$exportfunc($exportdata);
        } else {
            // Handle no export function for the export type specified.
            throw new \moodle_exception('unknownformat', 'peerassessment');
        }
    }
    /**
     * Exports the Peer assessment as CSV
     */
    protected function export_csv($exportdata) {
        $filename = get_string('defaultdownloadname', 'peerassessment');
        \csv_export_writer::download_array($filename, $exportdata, $this->delimiter);
    }

    /**
     * Generates a filename for json data that matches standard CSV format filename.
     * @param $dataname
     * @return string
     * @see csv_export_writer::set_filename()
     */
    protected function getjsonfilename($dataname) {
        $filename = clean_filename($dataname);
        $filename .= clean_filename('-' . gmdate("Ymd_Hi"));
        $filename .= clean_filename("-{$this->delimiter}_separated");
        $filename .= ".json";
        return $filename;
    }
    protected function export_json($exportdata) {
        array_shift($exportdata);
        $this->send_json_header();
        echo json_encode($exportdata);
    }
    /**
     * Output file headers to initialise the download of the file.
     *
     * Based on csvlib.class.php send_header() function.
     */
    protected function send_json_header() {
        global $CFG;
        $filename = $this->getjsonfilename(get_string('defaultdownloadname', 'peerassessment'));
        $mimetype = "application/json";
        if (defined('BEHAT_SITE_RUNNING')) {
            // For text based formats - we cannot test the output with behat if we force a file download.
            return;
        }
        if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: max-age=10');
            header('Pragma: ');
        } else { // Normal http - prevent caching at all cost.
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
            header('Pragma: no-cache');
        }
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header("Content-Type: $mimetype\n");
        header("Content-Disposition: attachment; filename=\"$filename\"");
    }
}
