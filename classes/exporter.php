<?php
namespace mod_peerassessment;

class exporter {
    var $type;
    var $pa;
    var $delimiter;
    var $fields;
    var $selectedfields;
    var $selectedgroups;
    
    /**
     * 
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
        $this->fields= $fields;
        $this->selectedfields = $selectedfields;
        $this->selectedgroups = $selectedgroups;
    }
    function get_exportdata() {
        //var_dump($this);
        //exit();
        $include_comments = in_array('comment', $this->selectedfields);
        $include_ratings = false;
        $exportdata = [];
        // headers
        $line = ['id', 'firstname', 'lastname', 'regno', 'groupid'];
        if ($include_comments) {
            $line[] = 'comment';
        }
        if ($include_ratings) {
            $line[] = 'ratings';
        }
        $exportdata[] = $line;
        // content
        foreach($this->selectedgroups as $group) {
            $pa = new peerassessment($this->pa, $group);
            try {
                $g = exporter::get_group($group);
                $members = $pa->get_members();
                foreach($members as $member) {
                    $line = [];
                    /* User Information */
                    $line[] = $member->id;
                    $line[] = $member->firstname;
                    $line[] = $member->lastname;
                    $line[] = $member->msn; // Strathclyde specific as we have re-purposed this field as RegNo
                    //var_dump($member);
                    $line[] = $g->id;
                    /* Comment details */
                    if ($include_comments && null !== $comment = $pa->get_comment($member->id)) {
                        //var_dump($comment);
                        $line[] = $comment->studentcomment;
                    } else {
                        //$exportdata[] = null;
                        $line[] = null;
                    }
                    $exportdata[] = $line;
                }
            }
            catch (\coding_exception $e) {
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
    static $groups;
    static function get_group($groupid) {
        if(isset(exporter::$groups[$groupid])) {
            return exporter::$groups[$groupid];
        } else {
            if($g = groups_get_group($groupid)) {
                exporter::$groups[$g->id] = $g;
                return $g;
            }
        }
        throw new \coding_exception("Group does not exist");
    }
    
    /**
     * Export the data
     * 
     * if $exportdata is not provided then get_exportdata() will be implicitly
     * called to fetch the required data using the class's set parameters.
     * 
     * @param array $exportdata Array of data to be exported
     * @return unknown
     */
    function export($exportdata = false) {
        if ($exportdata === false) {
            $exportdata = $this->get_exportdata();
        }
        /* echo'<pre>';
        var_dump($exportdata);
        echo'</pre>';
        exit(); */
        $exportfunc = "export_{$this->type}";
        if (\method_exists($this, $exportfunc)) {
            return $this->$exportfunc($exportdata);
        } else {
            // handle no export function for the export type specified.
        }
        
    }
    /**
     * Exports the Peer assessment as CSV
     */
    protected function export_csv($exportdata) {
        $filename = 'download.csv';
        \csv_export_writer::download_array($filename, $exportdata, $this->delimiter);
    }
    
    protected function export_json($exportdata) {
        //array_shift($exportdata);
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
        $filename = "download.json";
        $mimetype = "application/json";
        if (defined('BEHAT_SITE_RUNNING')) {
            // For text based formats - we cannot test the output with behat if we force a file download.
            return;
        }
        if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: max-age=10');
            header('Pragma: ');
        } else { //normal http - prevent caching at all cost
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
            header('Pragma: no-cache');
        }
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header("Content-Type: $mimetype\n");
        header("Content-Disposition: attachment; filename=\"$filename\"");
    }
}