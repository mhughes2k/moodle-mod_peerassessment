<?php

function peerassessment_backup_mods($bf,$preferences) {
    global $CFG;
    
    $status = true;
    
    $peerAssessments = get_records('peerassessment','course',$preferences->backup_corse,'id');
    if ($peerAssessments) {
        foreach($peerAssessments as $pa) {
            if (backup_mod_selected($preferences,'peerassessment',$pa->id) ){
                $status = peerassessment_backup_one_mod($bf,$preferences,$forum);
            }
        }
    }
    return $status;
}

function peerassessment_backup_one_mod($bf, $preferences, $pa) {
    global $CFG;
    
    if (is_numeric($pa)) {
        $pa = get_record('peerassessment','id',$pa);
    }
    
    $instanceid = $pa->id;
    
    $status = true;
    
    
    
    
    return $status;
}