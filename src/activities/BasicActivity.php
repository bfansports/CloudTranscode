<?php

/**
 * This class serves as a skeleton for classes implementing actual activity
 */

require __DIR__ . '../../utils/S3Utils.php';
require __DIR__ . '/InputValidator.php';

class BasicActivity
{
    private   $activityType; // Type of activity
    private   $activityResult; // Contain activity result output
    private   $root; // This file location
    public    $activityLogKey; // Create a key workflowId:activityId to put in logs
  
    // Constants
    const NO_INPUT             = "NO_INPUT";
    const NO_WF_EXECUTION      = "NO_WF_EXECUTION";
    const ACTIVITY_TASK_EMPTY  = "ACTIVITY_TASK_EMPTY";
    const HEARTBEAT_FAILED     = "HEARTBEAT_FAILED";
    const TMP_FOLDER_FAIL      = "TMP_FOLDER_FAIL";
    const NO_ACTIVITY_NAME     = "NO_ACTIVITY_NAME";
    const NO_ACTIVITY_VERSION  = "NO_ACTIVITY_VERSION";
    const ACTIVITY_INIT_FAILED = "ACTIVITY_INIT_FAILED";
    
    // File types
    const VIDEO = "VIDEO";
    const AUDIO = "AUDIO";
    const IMAGE = "IMAGE";
    const DOC   = "DOC";
  
    function __construct($params)
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
    
        $this->root = realpath(dirname(__FILE__));
    }

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
  
    // Perform the activity
    protected function do_activity($task)
    {
        // To be implemented in class that extends this class
    }

    // Perform JSON input validation
    public function do_input_validation($task, $taskType)
    {
        // Check Task integrity
        $input = $this->check_task_basics($task);

        // Check JSON input
        $validator = new InputValidator();
        $decoded = $validator->decode_json_format($input);
        $validator->validate_input($decoded, $taskType);
    
        return ($decoded);
    }

    protected function check_task_basics($task)
    {
        if (!$task)
            throw new CTException("Activity Task empty !", 
			    self::ACTIVITY_TASK_EMPTY); 

        if (!isset($task["input"]) || !$task["input"] || $task["input"] == "")
            throw new CTException("No input provided to 'ValidateInputAndAsset'", 
			    self::NO_INPUT);
    
        return $task["input"];
    }

    // Send activity failed to SWF
    public function activity_failed($task, $reason = "", $details = "")
    {
        global $swf;

        try {
            log_out("ERROR", basename(__FILE__), "[$reason] $details",
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
    public function activity_completed($task, $result)
    {
        global $swf;
    
        try {
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
    public function get_file_to_process($task, $input)
    {
        // Create TMP storage to put the file to validate. 
        $inputFileInfo = pathinfo($input->{'input_file'});
        $localPath = 
            $this->create_tmp_local_storage($task["workflowExecution"]["workflowId"],
                $inputFileInfo['dirname']);
        $saveFileTo = $localPath . $inputFileInfo['basename'];
        
        // Get file from S3 or local copy if any
        $s3Utils = new S3Utils();
        log_out("INFO", 
            basename(__FILE__), 
            "Downloading '" . $input->{'input_bucket'} . "/" . $input->{'input_file'}  . "' to '$saveFileTo' ...",
            $this->activityLogKey);
        $s3Output = $s3Utils->get_file_from_s3($input->{'input_bucket'}, 
            $input->{'input_file'}, $saveFileTo,
            array($this, "s3_get_processing_callback"), $task);
        
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
        // Tell SWF we alive !
        $this->send_heartbeat($task);

        // XXX
        // Send SQS notification of GET progress
        // XXX
    }

    // Called from S3Utils while PUT to S3 is in progress
    public function s3_put_processing_callback($task)
    {
        // Tell SWF we alive !
        $this->send_heartbeat($task);

        // XXX
        // Send SQS notification of PUT progress
        // XXX
    }
        
    // Create a local TMP folder using the workflowID
    public function create_tmp_local_storage($workflowId, $extra = null)
    {
        $localPath = "/tmp/CloudTranscode/$workflowId/";
        if ($extra)
            $localPath .= "$extra/";
        if (!file_exists("$localPath/transcode/"))
        {
            if (!mkdir("$localPath/transcode/", 0750, true))
                throw new CTException("Unable to create temporary folder '$localPath/transcode/' !",
                    self::TMP_FOLDER_FAIL);
        }
    
        return $localPath;
    }
}




