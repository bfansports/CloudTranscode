<?php

/**
 * workflow tracker helps you track Worflows execution.
 * WE store all workflows and ongoing activities in here
 * !! Depends on Utils.php. Should be included in parent caller.
 */
class WorkflowTracker
{
    private $workflowManager;
    private $excutionTracker;

    function __construct($config, $workflowManager)
    {
        $this->workflowManager = $workflowManager;

        // We keep track of all ongoing workflows in there
        $this->excutionTracker = [];
    }   
    
    // Register a workflow in the tracker for further use
    // We register the workflow execution and its activity list
    // See Utils.php for activity list
    public function register_workflow_in_tracker($workflowExecution, $activityList) 
    {
        if ($this->is_workflow_tracked($workflowExecution))
            return true;
        
        log_out("INFO", basename(__FILE__), 
            "Registering workflow '" . $workflowExecution["workflowId"] . "' in the workflow tracker !", 
            $workflowExecution['workflowId']);
        $this->executionTracker[$workflowExecution["workflowId"]] = [
            "step"              => 0,
            "activityList"      => $activityList,
            "ongoingActivities" => []
        ];

        return true;
    }

    // Register newly scheduled activity in the tracker
    public function record_activity_scheduled($workflowExecution, $event) 
    {
        // Get the tracker for this workflow
        // &$this-> to get the reference to the object so we can modify its content
        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
        
        // Create an activity snapshot for tracking
        $newActivity = [
            "activityId"   => $event["activityTaskScheduledEventAttributes"]["activityId"],
		    "activityType" => $event["activityTaskScheduledEventAttributes"]["activityType"],
		    "scheduledId"  => $event["eventId"],
            "startedId"    => 0,
            "completedId"  => 0,
        ];
        
        log_out("INFO", basename(__FILE__), 
            "Recording scheduled activityId '" . $newActivity['activityId'] . "'.", 
            $workflowExecution['workflowId']);
        // We store that activity in the workflow tracker
        array_push($tracker["ongoingActivities"], $newActivity);
        
        return $newActivity;
    }
    
    // Register 
    public function record_activity_started($workflowExecution, $event) 
    {
        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
        
        $scheduledEventId  = $event["activityTaskStartedEventAttributes"]["scheduledEventId"];
        $ongoingActivities = &$tracker["ongoingActivities"];
        foreach ($ongoingActivities as &$activity)
        {
            // Did I find the ongoing activity I'm looking for ?
            if ($activity["scheduledId"] == $scheduledEventId)
            {
                log_out("INFO", basename(__FILE__), 
                    "Recording started activityId '" . $activity['activityId'] . "'.", 
                    $workflowExecution['workflowId']);
                $activity["startedId"] = $event["eventId"];
                return $activity;
            }
        }
        
        log_out("ERROR", basename(__FILE__), 
            "Can't find the scheduled activity that just started ! Something is messed up !", 
            $workflowExecution['workflowId']);
        return false;
    }

    // Register newly created activities in the tracker
    public function record_activity_completed($workflowExecution, $event) 
    {
        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
        
        $scheduledEventId  = $event["activityTaskCompletedEventAttributes"]["scheduledEventId"];
        $startedEventId    = $event["activityTaskCompletedEventAttributes"]["startedEventId"];
        $ongoingActivities = &$tracker["ongoingActivities"];
        foreach ($ongoingActivities as &$activity)
        {
            // Did I find the ongoing activity I'm looking for ?
            if ($activity["scheduledId"] == $scheduledEventId &&
                $activity["startedId"]   == $startedEventId)
            {
                log_out("INFO", basename(__FILE__), 
                    "Recording completed activityId '" . $activity['activityId'] . "'.", 
                    $workflowExecution['workflowId']);
                $activity["completedId"]   = $event["eventId"];
                $activity["completedTime"] = $event["eventTimestamp"];
                return $activity;
            }
        }

        print "$scheduledEventId - $startedEventId \n";
        print_r($ongoingActivities);

        log_out("ERROR", basename(__FILE__), 
            "Can't find the scheduled/started ID related to this activity that just completed ! Something is messed up !", 
            $workflowExecution['workflowId']);
        return false;
    }

    // Check if all similar activites are completed
    public function are_similar_activities_completed($workflowExecution, $completedActivity)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        
        $activityId   = $completedActivity["activityId"];
        $activityType = $completedActivity["activityType"];
        $ongoingActivities = $tracker["ongoingActivities"];
        foreach ($ongoingActivities as $activity)
        {
            // If another activity of the same type is still running
            if ($activity["activityType"]["name"]    == $activityType["name"] &&
                $activity["activityType"]["version"] == $activityType["version"] &&
                $activity["completedId"] == 0)
                return false;
        }
        return true;
    }
    
    // Is the workflow tracked by the tracker ?
    private function is_workflow_tracked($workflowExecution)
    {
        if (!isset($this->executionTracker[$workflowExecution["workflowId"]]))
            return false;

        return true;
    }

    
    /**
     * TOOLS to get previous, ongoing or next activity from the activityList in JSON config file
     * Used to keep track of what activity comes next
     */

    // Return the next activity in the activity list
    public function get_first_activity($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        return ($tracker["activityList"][0]);
    }

    // Return the next activity to process
    public function get_current_activity($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        return ($tracker["activityList"][$tracker["step"]]);
    }

    // Return the previous activity 
    public function get_previous_activity($workflowExecution)
    {
        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        if (!$tracker["step"])
            return ($tracker["activityList"][$tracker["step"]]);
        
        return ($tracker["activityList"][$tracker["step"]-1]);
    }
    
    // Increment step to the next activity
    public function move_to_next_activity($workflowExecution)
    {
        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
        if ($tracker["step"] >= count($tracker["activityList"]))
            return false;
        
        // Increment step
        $tracker["step"] += 1;
        
        // Return next activity after increment step
        return $tracker["activityList"][$tracker["step"]];
    }
}

