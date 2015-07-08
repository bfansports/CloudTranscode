<?php

/**
 * This class serves as a skeleton for classes implementing actual activity
 */

require __DIR__.'/../utils/S3Utils.php';

use SA\CpeSdk;

class BasicActivity extends CpeSdk\CpeActivity
{
    public $tmpPathInput; // PAth to directory containing TMP file
    public $pathToInputFile; // PAth to input file locally
    public $s3Utils; // Used to manipulate S3. Download/Upload
  
    // Constants
    const NO_INPUT             = "NO_INPUT";
    const NO_WF_EXECUTION      = "NO_WF_EXECUTION";
    const ACTIVITY_TASK_EMPTY  = "ACTIVITY_TASK_EMPTY";
    const HEARTBEAT_FAILED     = "HEARTBEAT_FAILED";
    const TMP_FOLDER_FAIL      = "TMP_FOLDER_FAIL";
    const NO_ACTIVITY_NAME     = "NO_ACTIVITY_NAME";
    const NO_ACTIVITY_VERSION  = "NO_ACTIVITY_VERSION";
    const ACTIVITY_INIT_FAILED = "ACTIVITY_INIT_FAILED";
    const UNKOWN_INPUT_TYPE    = "UNKOWN_OUTPUT_TYPE";

    // JSON checks
    const INPUT_INVALID        = "INPUT_INVALID";
    const FORMAT_INVALID       = "FORMAT_INVALID";

    // Types
    const VIDEO                = "VIDEO";
    const THUMB                = "THUMB";
    const AUDIO                = "AUDIO";
    const DOC                  = "DOC";
    const IMAGE                = "IMAGE";

    // XXX Use EFS for storage
    // Nico: Expensive though.
    // This is where we store temporary files for transcoding
    const TMP_FOLDER = "/tmp/CloudTranscode/";
    
    function __construct($params, $debug)
    {
        parent::__construct($params, $debug);
        
        // S3 utils
        $this->s3Utils = new S3Utils();  
    }

    /**
     * CpeActivity Implementation
     */
    
    // Perform JSON input validation
    public function do_input_validation($task)
    {
        parent::do_input_validation($task);
        
        /*
         * Nico: Reactivate JSON Schema
         *       Remove dependency from Utils.php for when we split the Engine from the Activities
         *       We need an Activity SDK.
         */
        // From Utils.php
        //if (($err = validate_json($decoded, "activities/".$this->activityType.".json")))
        /*   throw new CpeSdk\CpeException("JSON input format is not valid! Details:\n".$err,  */
        /*       self::FORMAT_INVALID); */
    }
    
    // Perform the activity
    public function do_activity($task)
    {
        parent::do_activity($task);
        
        // Create TMP storage to store input file to transcode 
        $inputFileInfo = pathinfo($this->data->{'input_file'});
        
        // Use workflowID to generate a unique TMP folder localy.
        $this->tmpPathInput = self::TMP_FOLDER 
            . $task["workflowExecution"]["workflowId"]."/" 
            . $inputFileInfo['dirname'];
        
        if (!file_exists($this->tmpPathInput)) {
                    if (!mkdir($this->tmpPathInput, 0750, true))
                throw new CpeSdk\CpeException(
                    "Unable to create temporary folder '$this->tmpPathInput' !",
                    self::TMP_FOLDER_FAIL
                );
        }
        
        // Download input file and store it in TMP folder
        $saveFileTo = $this->tmpPathInput."/".$inputFileInfo['basename'];
        $this->pathToInputFile = 
            $this->get_file_to_process(
                $task, 
                $this->data->{'input_bucket'},
                $this->data->{'input_file'},
                $saveFileTo
            );
    }
    
    /**
     * Custom code for Cloud Transcode
     */
    
    // Create TMP folder and download file to process
    public function get_file_to_process($task, $inputBuket, $inputFile, $saveFileTo)
    {        
        // Get file from S3 or local copy if any
        $this->cpeLogger->log_out("INFO", 
            basename(__FILE__), 
            "Downloading '$inputBuket/$inputFile' to '$saveFileTo' ...",
            $this->activityLogKey);

        // Use the S3 utils to initiate the download
        $s3Output = $this->s3Utils->get_file_from_s3(
            $inputBuket, 
            $inputFile, 
            $saveFileTo,
            array($this, "s3_get_processing_callback"), 
            $task
        );
        
        $this->cpeLogger->log_out("INFO", basename(__FILE__), 
            $s3Output['msg'],
            $this->activityLogKey);
        
        $this->cpeLogger->log_out("INFO", basename(__FILE__), 
            "Input file successfully downloaded into local TMP folder '$saveFileTo' !",
            $this->activityLogKey);
        
        return $saveFileTo;
    }
    
    // Callback from S3Utils while GET from S3 is in progress
    public function s3_get_processing_callback($task)
    {
        // Tells SWF we're alive while downloading!
        $this->send_heartbeat($task);

        // Send progress through SQS to notify client of download
        $this->cpeSqsWriter->activity_preparing($task);
    }

    // Callback from S3Utils while PUT to S3 is in progress
    public function s3_put_processing_callback($task)
    {
        // Tells SWF we're alive while uploading!
        $this->send_heartbeat($task);

        // Send progress through SQS to notify client of upload
        $this->cpeSqsWriter->activity_finishing($task);
    }
}




