<?php

Class DeciderBrain
{
    // Event mapper
    private $eventsMap;
    
	private $workflowTracker;
	private $workflowManager;
    private $taskList;
    
    // Errors
	const ACTIVITY_TIMEOUT     = "ACTIVITY_TIMEOUT";
	const ACTIVITY_FAILED      = "ACTIVITY_FAILED";

    function __construct($workflowTracker, $workflowManager, $taskList)
	{
        // Init eventMap. Maps events with callback functions.
        $this->eventsMap = [
            'WorkflowExecutionStarted'   => 'workflow_execution_started',
            'WorkflowExecutionCompleted' => 'workflow_execution_completed',
            'ActivityTaskScheduled'      => 'activity_task_scheduled',
            'ActivityTaskStarted'        => 'activity_task_started',
            'ActivityTaskCompleted'      => 'activity_task_completed',
            'ActivityTaskFailed'         => 'activity_task_failed',
            'ActivityTaskTimedOut'       => 'activity_task_timed_out',
            'ActivityTaskCanceled'       => 'activity_task_canceled',
        ];
        
        $this->workflowTracker = $workflowTracker;
        $this->workflowManager = $workflowManager;
        $this->taskList = $taskList;
    }
    
    public function handle_event($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        log_out("INFO", basename(__FILE__), "*" . $event["eventType"] . "*", 
            $workflowExecution['workflowId']);
        
        // Do we know this event ?
        if (!isset($this->eventsMap[$event["eventType"]]))
            return false;
        
        
        // We call the callback function that handles this event 
        $this->{$this->eventsMap[$event["eventType"]]}($events, $newEvents, $event, $taskToken, $workflowExecution);
    }

    /**
     * Callback functions
     */

    // Workflow started !
    private function workflow_execution_started($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        if (!isset($event["workflowExecutionStartedEventAttributes"]["input"]))
        {
            log_out("ERROR", basename(__FILE__), "Workflow doesn't contain any input data !", 
                $workflowExecution['workflowId']);
            return false;
        }
        // Get the input passed to the workflow at startup
        $workflowInput = $event["workflowExecutionStartedEventAttributes"]["input"];

        // Next Task to process
		if (!($fistActivity = $this->workflowTracker->get_first_activity($workflowExecution)))
			return false;

		log_out("INFO", basename(__FILE__), "Starting activity: '" . $fistActivity["name"] . "'", 
            $workflowExecution['workflowId']);
        // Start new activity
        if (!$this->start_new_activity($taskToken, 
                $fistActivity, 
                [json_decode($workflowInput)]))
			return false;
        
        return true;
    }
    
    // Workflow completed !
    private function workflow_execution_completed($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        log_out("INFO", basename(__FILE__), 
            "Workflow '" . $workflowExecution["workflowId"] . "' has completed !", 
            $workflowExecution['workflowId']);
        return true;
    }
    
    // Activity scheduled
    private function activity_task_scheduled($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        //print_r($event);
        
        // Register new scheduled activities in tracker
        if (!($activity = $this->workflowTracker->record_activity_scheduled($workflowExecution, $event)))
            return false;
    }

    // Activity started !
    private function activity_task_started($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        //print_r($event);

        // Register new started activities in tracker
        if (!($activity = $this->workflowTracker->record_activity_started($workflowExecution, $event)))
            return false;
    }
    
    // Activity Task completed
    private function activity_task_completed($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        //print_r($event);
        
        // We get the output of the completed activity
        $activityResult = json_decode($event['activityTaskCompletedEventAttributes']['result']);

        // Register new completed activities in tracker
        if (!($activity = $this->workflowTracker->record_activity_completed($workflowExecution, $event)))
            return false;
        
        // We completed 'ValidateInputAndAsset' activity
        if ($activity['activityType']['name'] == 'ValidateInputAndAsset')
        {
            // We get the next activity information
            $nextActivity = $this->workflowTracker->move_to_next_activity($workflowExecution);
            
            // Prepare the data for transcoding activity
            // One input for each transcoding activity
            $nextActivitiesInput = array();
            foreach ($activityResult->{"outputs"} as $output)
            {
                array_push($nextActivitiesInput, [
                        "input_json"               => $activityResult->{"input_json"},
                        "input_file"               => $activityResult->{"input_file"},
                        "output"                   => $output
                    ]);
            }
            
            log_out("INFO", basename(__FILE__), 
                "Starting activity: '" . $nextActivity["name"] . "'", 
                $workflowExecution['workflowId']);
            // Start new activity(ies).
            // Several activity to be scheduled if several outputs needed !
            if (!$this->start_new_activity($taskToken, 
                    $nextActivity, 
                    $nextActivitiesInput))
                return false;
        }
        else if ($activity['activityType']['name'] == 'TranscodeAsset')
        {
            // Check if we are done with all outputs that needs to be transcoded ?
            if (!$this->workflowTracker->are_similar_activities_completed($workflowExecution, $activity))
            {
                log_out("INFO", basename(__FILE__), 
                    "There are still 'TranscodeAsset' activities running ...", 
                    $workflowExecution['workflowId']);
                
                // Send 
                $this->workflowManager->respond_decisions($taskToken);
                
                return false;
            }

            // We get the next activity information
            $nextActivity = $this->workflowTracker->move_to_next_activity($workflowExecution);

            log_out("INFO", basename(__FILE__), 
                "Starting activity: '" . $nextActivity["name"] . "'", 
                $workflowExecution['workflowId']);
            // Start new activity to validate transcoded outputs
            if (!$this->start_new_activity($taskToken, $nextActivity, [ 
                        [ 
                            "input_file" => $activityResult->{"input_file"},
                            "input_json" => $activityResult->{"input_json"}
                        ]
                    ]))
                return false;
        }
        else if ($activity['activityType']['name'] == 'ValidateTrancodedAsset')
        {
            log_out("INFO", basename(__FILE__), 
                "Post processing validation performed! Workflow is over ...", 
                $workflowExecution['workflowId']);
           
            // The workflow is over !
            if (!$this->workflowManager->respond_decisions($taskToken, [
                        ["decisionType" => "CompleteWorkflowExecution"]
                    ]))
                return false;
        }
        else
        {
            log_out("ERROR", basename(__FILE__), 
                "Unknown activity has completed ! Something is messed up !", 
                $workflowExecution['workflowId']);
            return false;
        }
        
        return true;
    }

    private function activity_task_timed_out($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        if (!($activity = $this->workflowTracker->get_current_activity($workflowExecution)))
        {
            log_out("ERROR", basename(__FILE__), 
                "Activity timed out but we can't get the current activity ! Something is messed up ...", 
                $workflowExecution['workflowId']);
            $this->workflowManager->terminate_workflow($workflowExecution);
            return false;
        }

        $msg = "Activity '" . $activity['name'] . "' timed out ! Killing workflow ...";
        log_out("ERROR", basename(__FILE__), $msg, $workflowExecution['workflowId']);
        $this->workflowManager->terminate_workflow($workflowExecution, self::ACTIVITY_TIMEOUT, $msg);

        return true;
    }
    
    private function activity_task_failed($events, $newEvents, $event, $taskToken, $workflowExecution)
    {
        if (!($activity = $this->workflowTracker->get_current_activity($workflowExecution)))
        {
            log_out("ERROR", basename(__FILE__), 
                "Activity failed but we can't get the current activity ! Something is messed up ...", 
                $workflowExecution['workflowId']);	
            $this->workflowManager->terminate_workflow($workflowExecution);
            return false;
        }

        $msg = "Activity '" . $activity['name'] . "' failed :[ ! Killing workflow ...";
        log_out("ERROR", basename(__FILE__), $msg, $workflowExecution['workflowId']);
        $this->workflowManager->terminate_workflow($workflowExecution, self::ACTIVITY_FAILED, $msg);

        return true;
    }


    
    /** 
     * TOOLS
     */
    
    // Start a new activity
	private function start_new_activity($taskToken, $activity, $inputs)
	{
        if (!$inputs || !count($inputs))
        {
            log_out("ERROR", basename(__FILE__), "No inputs provided for the next activity !");
            return false;
        }

        // Create new decisions based on input
        $decisions = array();
        foreach ($inputs as $input)
        {
            // See doc:
            // http://docs.aws.amazon.com/amazonswf/latest/apireference/API_RespondDecisionTaskCompleted.html
            // XXX Timeout should be configurable base on client/job profile
            // If slow profile then longer timeout
            array_push($decisions, [
                    "decisionType" => "ScheduleActivityTask",
                    "scheduleActivityTaskDecisionAttributes" => [
                        "activityType" => [
                            "name"    => $activity["name"],
                            "version" => $activity["version"]
                        ],
                        "activityId"   => uniqid(),
                        "input"		   => json_encode($input),
                        "taskList"     => $this->taskList,
                        "scheduleToStartTimeout" => "7200", // 2 hours
                        "startToCloseTimeout"    => "18000", // 5 hours
                        "scheduleToCloseTimeout" => "25200", // 7 hours
                        "heartbeatTimeout"       => "60"
                    ]
                ]);
        }
        
        // Send response to SWF to schedule new activities
        if (!$this->workflowManager->respond_decisions($taskToken, $decisions))
            return false;
        
		return true;
	}
    
    
}