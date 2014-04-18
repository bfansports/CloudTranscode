<?php

/**
 * Interface for any transcoder
 * You must extend this class to create a new transcoder
 */

class BasicTranscoder 
{
    public $activityLogKey; // Valling activity loggin key
    public $activityObj; // Calling activity object
    public $executer; // Executer obj
    public $task; // Activity TASK
    
    function __construct($activityObj, $task) 
    { 
        $this->activityObj = $activityObj;
        $this->activityLogKey = $activityObj->activityLogKey;
        $this->task = $task;
        $this->executer = new CommandExecuter();
    }

    // Function used by ValidationActivity. To implement
    protected function get_asset_info($pathToFile)
    {
        
    }

    // Function used by TranscodeActivity. To implement
    protected function transcode_asset($inputAssetInfo, $outputDetails)
    {
        
    }
}