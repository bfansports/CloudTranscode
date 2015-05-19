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
    
    // Errors
    const TRACKER_RECORD_STARTED_FAILED    = "TRACKER_RECORD_STARTED_FAILED";
    const TRACKER_RECORD_COMPLETED_FAILED  = "TRACKER_RECORD_STARTED_FAILED";
    const WF_NOT_TRACKED  = "WF_NOT_TRACKED";

    // Activity statuses
    const SCHEDULED = "SCHEDULED";
    const STARTED   = "STARTED";
    const TIMED_OUT = "TIMED_OUT";
    const FAILED    = "FAILED";
    const COMPLETED = "COMPLETED";

    function __construct($config, $workflowManager)
    {
        $this->workflowManager = $workflowManager;

        // We keep track of all ongoing workflows in there
        $this->excutionTracker = [];
    }   
  
    /**
     * WORKFLOWS
     */

    // Register a workflow in the tracker for further use
    // We register the workflow execution and its activity list
    // See Utils.php for activity list
    public function register_workflow_in_tracker(
        $workflowExecution, 
        $activityList, 
        $workflowInput) 
    {
        if ($this->is_workflow_tracked($workflowExecution))
            return true;
    
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Registering workflow '" 
            . $workflowExecution["workflowId"] . "' in the workflow tracker !", 
            $workflowExecution['workflowId']
        );
        $newWorkflow = [
            "status"            => self::STARTED,
            "step"              => 0,
            "info"              => $workflowExecution,
            "activityList"      => $activityList,
            "ongoingActivities" => [],
            "input"             => $workflowInput
        ];
        $this->executionTracker[$workflowExecution["workflowId"]] = $newWorkflow;
        
        return $newWorkflow;
    }

    // Mark WF as completed
    public function record_workflow_completed($workflowExecution)
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            return false;
        
        $this->executionTracker[$workflowExecution["workflowId"]]["status"] = 
            self::COMPLETED;
    }

    // Return true if WF is completed
    public function is_workflow_completed($workflowExecution)
    {
        if ($this->executionTracker[$workflowExecution["workflowId"]]["status"] ==
            self::COMPLETED)
            return true;
        return false;
    }

    // Return WF input data
    public function get_workflow_input($workflowExecution)
    {
        if ($this->is_workflow_tracked($workflowExecution))
            return ($this->executionTracker[$workflowExecution["workflowId"]]["input"]);
        return false;
    }
    
    // Is the workflow tracked by the tracker ?
    private function is_workflow_tracked($workflowExecution)
    {
        if (!isset($this->executionTracker[$workflowExecution["workflowId"]]))
            return false;

        return true;
    }
    
    
    /**
     * ACTIVITES
     */

    // Register newly scheduled activity in the tracker
    public function record_activity_scheduled($workflowExecution, $event, $activityInput) 
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        // Get the tracker for this workflow
        // &$this-> to get the object so we can modify its content
        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
        
        // Create an activity snapshot for tracking
        $newActivity = [
            "status"       => self::SCHEDULED,
            "activityId"   => $event["activityTaskScheduledEventAttributes"]["activityId"],
            "activityType" => $event["activityTaskScheduledEventAttributes"]["activityType"],
            "scheduledId"  => $event["eventId"],
            "startedId"    => 0,
            "completedId"  => 0,
            "input"        => $activityInput
        ];
        
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Recording scheduled activityId '" . $newActivity['activityId'] 
            . "', activityType '" . $newActivity['activityType']['name'] . "'", 
            $workflowExecution['workflowId']
        );
        // We store that activity in the workflow tracker
        array_push($tracker["ongoingActivities"], $newActivity);
    
        return $newActivity;
    }
  
    // Register 
    public function record_activity_started($workflowExecution, $event) 
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );
        
        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
    
        $scheduledEventId  = $event["activityTaskStartedEventAttributes"]["scheduledEventId"];
        $ongoingActivities = &$tracker["ongoingActivities"];
        foreach ($ongoingActivities as &$activity)
        {
            // Did I find the ongoing activity I'm looking for ?
            if ($activity["scheduledId"] == $scheduledEventId)
            {
                log_out(
                    "INFO", 
                    basename(__FILE__), 
                    "Recording started activityId '" . $activity['activityId'] . "'.", 
                    $workflowExecution['workflowId']
                );
                $activity["startedId"] = $event["eventId"];
                $activity["status"]    = self::STARTED;
                return $activity;
            }
        }
        
        throw new CTException(
            "Can't find the scheduled activity that just started! Something is messed up!",
            self::TRACKER_RECORD_STARTED_FAILED
        );
    }

    // Register newly created activities in the tracker
    public function record_activity_completed($workflowExecution, $event) 
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

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
                log_out(
                    "INFO", 
                    basename(__FILE__), 
                    "Recording completed activityId '" . $activity['activityId'] . "'.", 
                    $workflowExecution['workflowId']
                );
                $activity["completedId"]   = $event["eventId"];
                $activity["completedTime"] = $event["eventTimestamp"];
                $activity["status"]        = self::COMPLETED;
                if ($event["activityTaskCompletedEventAttributes"]["result"] != "")
                    $activity["result"] = 
                        json_decode($event["activityTaskCompletedEventAttributes"]["result"]);
                return $activity;
            }
        }

        throw new CTException(
            "Can't find the scheduled/started ID related to this activity that just completed! Something is messed up !",
            self::TRACKER_RECORD_COMPLETED_FAILED
        );
    }

    // Register newly created activities in the tracker
    public function record_activity_timed_out($workflowExecution, $event) 
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
    
        $scheduledEventId  = $event["activityTaskTimedOutEventAttributes"]["scheduledEventId"];
        $startedEventId    = $event["activityTaskTimedOutEventAttributes"]["startedEventId"];
        $timeoutType       = $event["activityTaskTimedOutEventAttributes"]["timeoutType"];
        $ongoingActivities = &$tracker["ongoingActivities"];
        foreach ($ongoingActivities as &$activity)
        {
            // Did I find the ongoing activity I'm looking for ?
            if ($activity["scheduledId"] == $scheduledEventId &&
                $activity["startedId"]   == $startedEventId)
            {
                log_out(
                    "INFO", 
                    basename(__FILE__), 
                    "Recording timed out activityId '" . $activity['activityId'] . "'.", 
                    $workflowExecution['workflowId']
                );
                $activity["timeoutType"] = $timeoutType;
                $activity["status"] = self::TIMED_OUT;
                return $activity;
            }
        }
    
        throw new CTException(
            "Can't find the scheduled/started ID related to this activity that just timed out ! Something is messed up !",
            self::TRACKER_RECORD_TIMEOUT_FAILED
        );
    }

    // Register newly created activities in the tracker
    public function record_activity_failed($workflowExecution, $event) 
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
    
        $scheduledEventId  = $event["activityTaskFailedEventAttributes"]["scheduledEventId"];
        $startedEventId    = $event["activityTaskFailedEventAttributes"]["startedEventId"];
        $details           = $event["activityTaskFailedEventAttributes"]["details"];
        $reason            = $event["activityTaskFailedEventAttributes"]["reason"];
        $ongoingActivities = &$tracker["ongoingActivities"];
        foreach ($ongoingActivities as &$activity)
        {
            // Did I find the ongoing activity I'm looking for ?
            if ($activity["scheduledId"] == $scheduledEventId &&
                $activity["startedId"]   == $startedEventId)
            {
                log_out(
                    "INFO", 
                    basename(__FILE__), 
                    "Recording failed activityId '" . $activity['activityId'] . "'.", 
                    $workflowExecution['workflowId']
                );
                $activity["details"] = $details;
                $activity["reason"]  = $reason;
                $activity["status"] = self::FAILED;
                return $activity;
            }
        }
    
        throw new CTException(
            "Can't find the scheduled/started ID related to this activity that just failed! Something is messed up!",
            self::TRACKER_RECORD_FAIL_FAILED
        );
    }

    // Check if all similar activites are completed
    public function are_similar_activities_completed($workflowExecution, $completedActivity)
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
    
        $activityId   = $completedActivity["activityId"];
        $activityType = $completedActivity["activityType"];
        $ongoingActivities = $tracker["ongoingActivities"];
        
        foreach ($ongoingActivities as $activity)
        {
            // If another activity of the same type is still running
            if ($activity["activityType"]["name"]    == $activityType["name"] &&
                $activity["activityType"]["version"] == $activityType["version"] &&
                ($activity["status"] == "SCHEDULED" || $activity["status"] == "STARTED"))
                return false;
        }
        return true;
    }

      
    /**
     * TOOLS to get previous, ongoing or next activity from the activityList in JSON config file
     * Used to keep track of what activity comes next
     */

    // Return the next activity in the activity list
    public function get_first_activity($workflowExecution)
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        return ($tracker["activityList"][0]);
    }

    // Return the next activity to process
    public function get_current_activity($workflowExecution)
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        return ($tracker["activityList"][$tracker["step"]]);
    }

    // Return the previous activity 
    public function get_previous_activity($workflowExecution)
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        $tracker = $this->executionTracker[$workflowExecution["workflowId"]];
        if (!$tracker["step"])
            return ($tracker["activityList"][$tracker["step"]]);
    
        return ($tracker["activityList"][$tracker["step"]-1]);
    }
  
    // Increment step to the next activity
    public function move_to_next_activity($workflowExecution)
    {
        if (!$this->is_workflow_tracked($workflowExecution))
            throw new CTException(
                "Workflow not tracked!",
                self::WF_NOT_TRACKED
            );

        $tracker = &$this->executionTracker[$workflowExecution["workflowId"]];
        if ($tracker["step"] >= count($tracker["activityList"]))
            return false;
    
        // Increment step
        $tracker["step"] += 1;
    
        // Return next activity after increment step
        return $tracker["activityList"][$tracker["step"]];
    }
}

