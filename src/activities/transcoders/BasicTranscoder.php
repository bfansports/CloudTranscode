<?php

/**
 * Interface for any transcoder
 * You must extend this class to create a new transcoder
 */

require_once __DIR__.'/../../utils/S3Utils.php';

use SA\CpeSdk;

class BasicTranscoder 
{
    public $activityLogKey; // Valling activity loggin key
    public $activityObj; // Calling activity object
    public $task; // Activity TASK

    public $cpeLogger; // Logger
    public $s3Utils; // Used to manipulate S3
    public $executer; // Executer obj

    // Types
    const VIDEO = "VIDEO";
    const THUMB = "THUMB";
    const AUDIO = "AUDIO";
    const DOC   = "DOC";
    const IMAGE = "IMAGE";
    
    public function __construct($activityObj, $task) 
    { 
        $this->activityObj    = $activityObj;
        $this->activityLogKey = $activityObj->activityLogKey;
        $this->task           = $task;

        $this->cpeLogger = $activityObj->cpeLogger;
        $this->executer  = new CommandExecuter();
        $this->s3Utils   = new S3Utils();
    }
}