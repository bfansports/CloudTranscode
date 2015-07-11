<?php

/**
 * Interface for any transcoder
 * You must extend this class to create a new transcoder
 */

require_once __DIR__."/../../../vendor/autoload.php";

require_once __DIR__.'/../../utils/S3Utils.php';

use SA\CpeSdk;

class BasicTranscoder 
{
    public $activityLogKey; // Valling activity loggin key
    public $activityObj; // Calling activity object
    public $task; // Activity TASK

    public $cpeLogger; // Logger
    public $cpeSqsWriter; // SQS write for sending msgs to client
    public $cpeJsonValidator; // SQS write for sending msgs to client
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
        $this->activityObj      = $activityObj;
        $this->activityLogKey   = $activityObj->activityLogKey;
        $this->task             = $task;

        $this->cpeLogger        = $activityObj->cpeLogger;
        $this->cpeSqsWriter     = $activityObj->cpeSqsWriter;
        $this->cpeJsonValidator = $activityObj->cpeJsonValidator;
        $this->executer         = new CommandExecuter($activityObj->cpeLogger);
        $this->s3Utils          = new S3Utils($activityObj->cpeLogger);
    }

    public function is_dir_empty($dir)
    {
        if (!is_readable($dir)) return null; 
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry !== '.' && $entry !== '..') { 
                return false;
            }
        }
        closedir($handle); 
        return true;
    }
}