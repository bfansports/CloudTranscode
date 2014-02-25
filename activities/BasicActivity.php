<?php

require_once 'ActivityUtils.php';

/**
 * This class serves as a skeletton for classes impleting actual activity
 */
class BasicActivity
{
	private   $activityType; // Type of activity
	private   $activityResult; // Contain activity result output
    protected $activityLogKey; // Create a key workflowId:activityId to put in logs

    // Constants
	const NO_INPUT             = "NO_INPUT";
	const INPUT_INVALID        = "INPUT_INVALID";
	const NO_WF_EXECUTION      = "NO_WF_EXECUTION";
    const ACTIVITY_TASK_EMPTY  = "ACTIVITY_TASK_EMPTY";

	function __construct($params)
	{
		if (!isset($params["name"]) || !$params["name"])
			throw new Exception("Can't instantiate asicActivity: 'name' is not provided or empty !\n");

		if (!isset($params["version"]) || !$params["version"])
			throw new Exception("Can't instantiate BasicActivity: 'version' is not provided or empty !\n");

		if (!$this->init_activity($params))
			throw new Exception("Unable to init the activity !\n");
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
    protected function input_validator()
	{
        // To be implemented in class that extends this class
	}

    protected function check_task_basics($task)
    {
        if (!$task)
             return [
                "status"  => "ERROR",
                "error"   => self::ACTIVITY_TASK_EMPTY,
                "details" => "Activity Task empty !"
            ];

        if (!isset($task["input"]) || !$task["input"] || $task["input"] == "")
            return [
                "status"  => "ERROR",
                "error"   => self::NO_INPUT,
                "details" => "No input provided to 'ValidateInputAndAsset'"
            ];
        
		// Validate JSON data and Decode as an Object
		if (!($input = json_decode($task["input"])))
            return [
                "status"  => "ERROR",
                "error"   => self::INPUT_INVALID,
                "details" => "JSON input is invalid !"
            ];

        return [
            "status" => "VALID",
            "input"  => $input
        ];
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
                return false;
            }
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), 
                "Unable to send heartbeat ! " . $e->getMessage(),
                $this->activityLogKey);
			return false;
		}
        
        return true;
	}
}


