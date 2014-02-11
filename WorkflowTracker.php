<?php

/**
 * workflow tracker helps you track Worflows execution.
 * WE store all workflows and ongoing activities in here
 * !! Depends on Utils.php. Should be included in parent caller.
 */
class WorkflowTracker
{
    private $domainName;
    private $workflowManager;

    // Execution tracker
    public $excutionTracker;

    function __construct($domainName, $workflowManager)
    {
        if (!$domainName)
            throw new Exception("Domain is null !\n");

        $this->domainName      = $domainName;
        $this->workflowManager = $workflowManager;

        // We keep track of all ongoing workflows in there
        $this->excutionTracker = array();
    }   

    // Return workflow input data from history
    public function get_workflow_input($workflowExecution, $events = null)
    {
        if (!$events) {
            if (!($events = $this->workflowManager->get_workflow_excution_history($workflowExecution))) {
                log_out("ERROR", basename(__FILE__), "Unable to get workflow Input data !");
                return false;
            }
        }

        // Find the Execution started event which contain the 'input' value
        foreach ($events as $event) 
        {
            if (isset($event["workflowExecutionStartedEventAttributes"]["input"]))
                return ($event["workflowExecutionStartedEventAttributes"]["input"]);
        }

        log_out("ERROR", basename(__FILE__), "Input value cannot be retrieved from workflow events history !");
        return false;
    }
    
    // Register a workflow in the tracker for further use
    // We register the workflow execution and its activity list
    // See Utils.php for activity list
    public function register_workflow_in_tracker($workflowExecution, $activities) 
    {
        log_out("INFO", basename(__FILE__), "Registering workflow '" . $workflowExecution["workflowId"] . "' in the workflow tracker !");
        $this->executionTracker[$workflowExecution["workflowId"]] = 
            array(
                "step"              => 0,
                "activities"        => $activities,
                "ongoingActivities" => array()
            );

        return true;
    }

    // Register newly scheduled activity in the tracker
    public function record_activity_scheduled($workflowExecution, $event) 
    {
        // Create an activity snapshot for tracking
        $newActivity = [
            "activityId"   => $event["activityTaskScheduledEventAttributes"]["activityId"],
		    "activityType" => $event["activityTaskScheduledEventAttributes"]["activityType"],
		    "scheduledId"  => $event["eventId"],
            "startedId"    => 0,
            "completedId"  => 0,
        ];
        
        log_out("INFO", basename(__FILE__), "Registering scheduled activityId '" . $newActivity['activityId'] . "' for workflow: '" . $workflowExecution["workflowId"] . "'.");
        // We store that activity in the workflow tracker
        array_push($this->executionTracker[$workflowExecution["workflowId"]]["ongoingActivities"], $newActivity);
        return $newActivity;
    }
    
    // Register 
    public function record_activity_started($workflowExecution, $event) 
    {
        $scheduledEventId = $event["activityTaskStartedEventAttributes"]["scheduledEventId"];
        $ongoingActivities = &$this->executionTracker[$workflowExecution["workflowId"]]["ongoingActivities"];
        foreach ($ongoingActivities as &$activity)
        {
            // Did I find the ongoing activity I'm looking for ?
            if ($activity["scheduledId"] == $scheduledEventId)
            {
                log_out("INFO", basename(__FILE__), "Registering started activityId '" . $activity['activityId'] . "' for workflow: '" . $workflowExecution["workflowId"] . "'.");
                $activity["startedId"] = $event["eventId"];
                return $activity;
            }
        }
        
        log_out("ERROR", basename(__FILE__), "Can't find the scheduled activity that just started ! Something is messed up !");
        return false;
    }

    // Register newly created activities in the tracker
    public function record_activity_completed($workflowExecution, $event) 
    {
        $scheduledEventId  = $event["activityTaskCompletedEventAttributes"]["scheduledEventId"];
        $startedEventId    = $event["activityTaskCompletedEventAttributes"]["startedEventId"];
        $ongoingActivities = &$this->executionTracker[$workflowExecution["workflowId"]]["ongoingActivities"];
        foreach ($ongoingActivities as &$activity)
        {
            // Did I find the ongoing activity I'm looking for ?
            if ($activity["scheduledId"] == $scheduledEventId &&
                $activity["startedId"]   == $startedEventId)
            {
                log_out("INFO", basename(__FILE__), "Registering completed activityId '" . $activity['activityId'] . "' for workflow: '" . $workflowExecution["workflowId"] . "'.");
                $activity["completedId"]   = $event["eventId"];
                $activity["completedTime"] = $event["eventTimestamp"];
                return $activity;
            }
        }

        log_out("ERROR", basename(__FILE__), "Can't find the scheduled/started activity that just completed ! Something is messed up !");
        return false;
    }

    // Check if all similar activites are completed
    public function are_similar_activities_completed($workflowExecution, $completedActivity)
    {
        $activityId = $completedActivity["activityId"];
        $activityType = $completedActivity["activityType"];
        $ongoingActivities = &$this->executionTracker[$workflowExecution["workflowId"]]["ongoingActivities"];
        foreach ($ongoingActivities as $activity)
        {
            // If another activity of the same type is still running
            if ($activity["activityType"]["name"] == $activityType["name"] &&
                $activity["activityType"]["version"] == $activityType["version"] &&
                $activity["completedId"] == 0)
                return false;
        }
        return true;
    }
    
    // Return the next activity to process based on the current step
    public function get_first_activity($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        
        return ($tracker["activities"][0]);
    }

    // Return the next activity to process based on the current step
    public function get_current_activity($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        $step    = $this->executionTracker[$workflowExecution["workflowId"]]["step"];
        
        return ($tracker["activities"][$step]);
    }

    // Return the previous activity based on the current step
    public function get_previous_activity($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        $step    = $this->executionTracker[$workflowExecution["workflowId"]]["step"];
        if (!$step)
            return ($tracker["activities"][$step]);
        
        return ($tracker["activities"][$step-1]);
    }
    
    // Increment step to the next activity
    public function move_to_next_activity($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        // Increment step
        $this->executionTracker[$workflowExecution["workflowId"]]["step"] += 1;
        
        // Return next activity after increment step
        return $tracker["activities"][$this->executionTracker[$workflowExecution["workflowId"]]["step"]];
    }
    
    // Is the workflow over ?
    public function is_workflow_finished($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        $step    = $this->executionTracker[$workflowExecution["workflowId"]]["step"];

        // Are we at the last step ?
        if (count($tracker["activities"]) == $step+1)
            return true;

        return false;
    }

    // Is the workflow tracked by the tracker ?
    public function is_workflow_tracked($workflowExecution)
    {
        if (!isset($this->executionTracker[$workflowExecution["workflowId"]]))
            return false;

        return true;
    }

}

