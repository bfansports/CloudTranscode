<?php

/**
 * This class serves as a skeleton for classes implementing actual activity
 */

require __DIR__ . '../../utils/S3Utils.php';
require __DIR__ . '../../utils/SQSUtils.php';

class BasicActivity
{
    public   $input_str; // Complete activity input string
    public   $input; // Complete activity input JSON object
    public   $time; // Time of the activity. Comes from $input
    public   $data; // Data input for the activity. The job we got to do. Comes from $input
    public   $client; // The client that request this activity. Comes from $input
    public   $jobId; // The Activity ID. Comes from $input
    
    public   $tmpPathInput; // PAth to directory containing TMP file
    public   $pathToInputFile; // PAth to input file locally
    
    public   $activityId; // ID of the activity
    public   $activityType; // Type of activity
    public   $activityResult; // Contain activity result output
    public   $activityLogKey; // Create a key workflowId:activityId to put in logs
    
    public   $SQSUtils; // Used to communicate
  
    // Constants
    const NO_INPUT             = "NO_INPUT";
    const NO_WF_EXECUTION      = "NO_WF_EXECUTION";
    const ACTIVITY_TASK_EMPTY  = "ACTIVITY_TASK_EMPTY";
    const HEARTBEAT_FAILED     = "HEARTBEAT_FAILED";
    const TMP_FOLDER_FAIL      = "TMP_FOLDER_FAIL";
    const NO_ACTIVITY_NAME     = "NO_ACTIVITY_NAME";
    const NO_ACTIVITY_VERSION  = "NO_ACTIVITY_VERSION";
    const ACTIVITY_INIT_FAILED = "ACTIVITY_INIT_FAILED";

    // JSON checks
    const INPUT_INVALID        = "INPUT_INVALID";
    const FORMAT_INVALID       = "FORMAT_INVALID";

    // XXX Use EFS for storage
    // Nico: Expensive though.
    // This is where we store temporary files for transcoding
    const TMP_FOLDER           = "/tmp/CloudTranscode/";
    
    function __construct($params, $debug)
    {
        if (!isset($params["name"]) || !$params["name"])
            throw new CTException("Can't instantiate asicActivity: 'name' is not provided or empty !\n", 
			    Self::NO_ACTIVITY_NAME);
    
        if (!isset($params["version"]) || !$params["version"])
            throw new CTException("Can't instantiate BasicActivity: 'version' is not provided or empty !\n", 
			    Self::NO_ACTIVITY_VERSION);
    
        if (!$this->init_activity($params))
            throw new CTException("Unable to init the activity !\n", 
			    Self::ACTIVITY_INIT_FAILED);
        
        $this->debug = $debug;

        // Instanciate CloudTranscode COM SDK
        $this->SQSUtils = new SQSUtils($this->debug);
    }

    // Init activity in SWF. REgister it if not existing.
    private function init_activity($params)
    {
        global $swf;

        // Save activity info
        $this->activityType = array(
            "name"    => $params["name"],
            "version" => $params["version"]);

        // Check if activity already exists 
        try {
            $swf->describeActivityType(array(
                    "domain"       => $params["domain"],
                    "activityType" => $this->activityType
                ));
            return true;
        } catch (\Aws\Swf\Exception\UnknownResourceException $e) {
            log_out("ERROR", basename(__FILE__), 
                "Activity '" . $params["name"] . "' doesn't exists. Creating it ...\n");
        } catch (Exception $e) {
            log_out("ERROR", basename(__FILE__), 
                "Unable describe activity ! " . $e->getMessage() . "\n");
            return false;
        }

        // Register activites if doesn't exists in SWF
        try {
            $swf->registerActivityType($params);
        } catch (Exception $e) {
            log_out("ERROR", basename(__FILE__), 
                "Unable to register new activity ! " . $e->getMessage() . "\n");
            return false;
        }

        return true;
    }

    // Init some Activity data
    protected function do_init($task)
    {
        $this->activityId     = $task->get("activityId");
        $this->activityType   = $task->get("activityType");
        // Create a key workflowId:activityId to put in logs
        $this->activityLogKey = $task->get("workflowExecution")['workflowId'] 
            . ":$this->activityId";
    }
    
    // Perform the activity
    protected function do_activity($task)
    {
        // Send started through SQSUtils to notify client
        $this->SQSUtils->activity_started($task);
        
        // Create TMP storage to store input file to transcode 
        $inputFileInfo = pathinfo($this->data->{'input_file'});
        // Use workflowID to generate a unique TMP folder localy.
        $this->tmpPathInput = self::TMP_FOLDER 
            . $task["workflowExecution"]["workflowId"] . "/" 
            . $inputFileInfo['dirname'];
        if (!file_exists($this->tmpPathInput))
            if (!mkdir($this->tmpPathInput, 0750, true))
                throw new CTException(
                    "Unable to create temporary folder '$this->tmpPathInput' !",
                    self::TMP_FOLDER_FAIL
                );

        // Download input file and store it in TMP folder
        $saveFileTo = $this->tmpPathInput . "/" . $inputFileInfo['basename'];
        $this->pathToInputFile = 
            $this->get_file_to_process(
                $task, 
                $this->data->{'input_bucket'},
                $this->data->{'input_file'},
                $saveFileTo
            );
    }

    // Perform JSON input validation
    protected function do_input_validation(
        $task, 
        $taskType)
    {
        // Check JSON input
        if (!($this->input = json_decode($this->input_str)))
            throw new CTException("JSON input is invalid !", 
			    self::INPUT_INVALID);

        /*
         * Nico: Reactivate JSON Schema
         *       Remove dependency from Utils.php for when we split the Engine from the Activities
         *       We need an Activity SDK.
         */
        // From Utils.php
        //if (($err = validate_json($decoded, "activities/$taskType.json")))
        /*   throw new CTException("JSON input format is not valid! Details:\n".$err,  */
        /*       self::FORMAT_INVALID); */
        
        $this->time   = $this->input->{'time'};  
        $this->jobId  = $this->input->{'job_id'};         
        $this->data   = $this->input->{'data'};  
        $this->client = $this->input->{'client'};
    }
    
    // Check basic Task data
    protected function do_task_check($task)
    {
        if (!$task)
            throw new CTException("Activity Task empty !", 
			    self::ACTIVITY_TASK_EMPTY); 
        
        if (!isset($task["input"]) || !$task["input"] ||
            $task["input"] == "")
            throw new CTException("No input provided to 'ValidateInputAndAsset'", 
			    self::NO_INPUT);

        $this->input_str = $task["input"];
    }

    // Send activity failed to SWF
    public function activity_failed($task, $reason = "", $details = "")
    {
        global $swf;

        try {
            // Notify client of failure
            $this->SQSUtils->activity_failed($task, $reason, $details);
            
            log_out("ERROR", basename(__FILE__),
                "[$reason] $details",
                $this->activityLogKey);
            $swf->respondActivityTaskFailed(array(
                    "taskToken" => $task["taskToken"],
                    "reason"    => $reason,
                    "details"   => $details,
                ));
        } catch (Exception $e) {
            log_out("ERROR", basename(__FILE__), 
                "Unable to send 'Task Failed' response ! " . $e->getMessage(),
                $this->activityLogKey);
            return false;
        }
    }

    // Send activity completed to SWF
    public function activity_completed($task, $result = null)
    {
        global $swf;
        
        try {
            // Notify client of failure
            $this->SQSUtils->activity_completed($task, $result);
        
            log_out("INFO", basename(__FILE__),
                "Notify SWF activity is completed !",
                $this->activityLogKey);
            $swf->respondActivityTaskCompleted(array(
                    "taskToken" => $task["taskToken"],
                    "result"    => json_encode($result),
                ));
        } catch (Exception $e) {
            log_out("ERROR", basename(__FILE__), 
                "Unable to send 'Task Completed' response ! " . $e->getMessage(),
                $this->activityLogKey);
            return false;
        }
    }

    /**
     * Send heartbeat to SWF to keep the task alive.
     * Timeout is configurable at the Activity level
     */
    public function send_heartbeat($task, $details = null)
    {
        global $swf;

        try {
            $taskToken = $task->get("taskToken");
            log_out("INFO", basename(__FILE__), 
                "Sending heartbeat to SWF ...",
                $this->activityLogKey);
      
            $info = $swf->recordActivityTaskHeartbeat(array(
                    "details"   => $details,
                    "taskToken" => $taskToken));

            // Workflow returns if this task should be canceled
            if ($info->get("cancelRequested") == true)
            {
                log_out("WARNING", basename(__FILE__), 
                    "Cancel has been requested for this task '" . $task->get("activityId") . "' ! Killing task ...",
                    $this->activityLogKey);
                throw new CTException("Heartbeat failed !",
                    self::HEARTBEAT_FAILED);
            }
        } catch (Exception $e) {
            throw new CTException("Heartbeat failed !",
                self::HEARTBEAT_FAILED);
        }
    }
    
    // Create TMP folder and download file to process
    public function get_file_to_process($task, $inputBuket, $inputFile, $saveFileTo)
    {        
        // Get file from S3 or local copy if any
        $s3Utils = new S3Utils();
        log_out("INFO", 
            basename(__FILE__), 
            "Downloading '$inputBuket/$inputFile' to '$saveFileTo' ...",
            $this->activityLogKey);
        $s3Output = $s3Utils->get_file_from_s3(
            $inputBuket, 
            $inputFile, 
            $saveFileTo,
            array($this, "s3_get_processing_callback"), 
            $task
        );
        
        log_out("INFO", basename(__FILE__), 
            $s3Output['msg'],
            $this->activityLogKey);
        
        log_out("INFO", basename(__FILE__), 
            "Input file successfully downloaded into local TMP folder '$saveFileTo' !",
            $this->activityLogKey);
        
        return $saveFileTo;
    }
    
    // Called from S3Utils while GET from S3 is in progress
    public function s3_get_processing_callback($task)
    {
        // Tells SWF we're alive !
        $this->send_heartbeat($task);

        // Send progress through SQSUtils to notify client of download
        $this->SQSUtils->activity_preparing($task);
    }

    // Called from S3Utils while PUT to S3 is in progress
    public function s3_put_processing_callback($task)
    {
        // Tells SWF we're alive !
        $this->send_heartbeat($task);

        // Send progress through SQSUtils to notify client of upload
        $this->SQSUtils->activity_finishing($task);
    }
}




