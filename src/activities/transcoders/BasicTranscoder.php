<?php

/**
 * Interface for any transcoder
 * You must extend this class to create a new transcoder
 */

class BasicTranscoder 
{
    public $activityLogKey; // Valling activity loggin key
    public $activityObj;    // Calling activity object
    public $task;           // Activity TASK

    public $s3Utils;  // Used to manipulate S3
    public $executer; // Executer obj
    
    
    function __construct($activityObj, $task) 
    { 
        $this->activityObj    = $activityObj;
        $this->activityLogKey = $activityObj->activityLogKey;
        $this->task           = $task;
        
        $this->executer = new CommandExecuter();
        $this->s3Utils  = new S3Utils();
    }
}