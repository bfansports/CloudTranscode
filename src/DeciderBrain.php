<?php

class DeciderBrain
{
    private $config;
    private $eventsMap;
    private $workflowTracker;
    private $workflowManager;
    private $decisionTaskList;
    private $activityList;
  
    // Errors
    const ACTIVITY_TIMEOUT = "ACTIVITY_TIMEOUT";
    const ACTIVITY_FAILED  = "ACTIVITY_FAILED";

    // Activities
    const VALIDATE_INPUT  = "ValidateInputAndAsset";
    const TRANSCODE_ASSET = "TranscodeAsset";

    function __construct($config, $workflowTracker, $workflowManager)
    {
        $this->config = $config;

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
    
        $this->workflowTracker  = $workflowTracker;
        $this->workflowManager  = $workflowManager;
        $this->decisionTaskList = array(
            "name" => $config['cloudTranscode']['workflow']['decisionTaskList']
        );
        $this->activityList     = $this->config['cloudTranscode']['activities'];
    }
  
    /**
     * Handles new incoming events
     * @param [String] $event [New event]
     * @param [String] $taskToken [Decision task token]
     * @param [String] $workflowExecution [workflow info]
     */
    public function handle_event($event, $taskToken, $workflowExecution)
    {
        // Do we know this event ?
        if (!isset($this->eventsMap[$event["eventType"]]))
            return;
    
        log_out(
            "INFO", 
            basename(__FILE__), "*" . $event["eventType"] . "*", 
            $workflowExecution['workflowId']
        );

        // We call the callback function that handles this event 
        $this->{$this->eventsMap[$event["eventType"]]}(
            $event, 
            $taskToken, 
            $workflowExecution
        );
    }

  
    /**
     * Callback functions
     */

    // Workflow started !
    private function workflow_execution_started($event, $taskToken, $workflowExecution)
    {
        // XXX
        // SQS Workflow started
        // XXX

        if (!isset($event["workflowExecutionStartedEventAttributes"]["input"]))
        {
            log_out(
                "ERROR", 
                basename(__FILE__), "Workflow doesn't contain any input data !", 
                $workflowExecution['workflowId']
            );
            return false;
        }
        // Get the input passed to the workflow at startup
        $workflowInput = $event["workflowExecutionStartedEventAttributes"]["input"];

        // Next Task to process
        if (!($fistActivity = 
                $this->workflowTracker->get_first_activity($workflowExecution)))
            return false;

        // Start new activity
        if (!$this->schedule_new_activity(
                $workflowExecution, 
                $taskToken, 
                $fistActivity, 
                [json_decode($workflowInput)]))
            return false;
        
        return true;
    }
  
    // Workflow completed !
    private function workflow_execution_completed($event, $taskToken, $workflowExecution)
    {
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Workflow '" . $workflowExecution["workflowId"] . "' has completed !", 
            $workflowExecution['workflowId']
        );

        // XXX
        // SQS Workflow completed
        // XXX

        return true;
    }
  
    // Activity scheduled
    private function activity_task_scheduled($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity task scheduled
        // XXX

        // Register new scheduled activities in tracker
        if (!($activity = 
                $this->workflowTracker->record_activity_scheduled($workflowExecution, $event)))
            return false;
    }

    // Activity started !
    private function activity_task_started($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity task started
        // XXX
        
        // Register new started activities in tracker
        if (!($activity = $this->workflowTracker->record_activity_started($workflowExecution, $event)))
            return false;
    }
  
    // Activity Task completed
    private function activity_task_completed($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity validate completed
        // XXX
        
        // We get the output of the completed activity
        $activityResult = 
            json_decode($event['activityTaskCompletedEventAttributes']['result']);

        // Register completed activity in tracker
        if (!($activity = 
                $this->workflowTracker->record_activity_completed(
                    $workflowExecution,
                    $event
                )))
            return false;

        // We completed 'ValidateInputAndAsset' activity
        if ($activity['activityType']['name'] == self::VALIDATE_INPUT)
        {
            
            // We get the next activity information
            $nextActivity = 
                $this->workflowTracker->move_to_next_activity($workflowExecution);
            
            // Prepare the data for transcoding activity
            // One input for each transcoding activity
            $nextActivitiesInput = [];
            foreach ($activityResult->{"outputs"} as $output)
            {
                $newInput = [
                    "input_json"       => $activityResult->{"input_json"},
                    "input_asset_type" => $activityResult->{"input_asset_type"},
                    "input_asset_info" => $activityResult->{"input_asset_info"},
                    "output"           => $output
                ];
                if (isset($activityResult->{"client"}))
                    $newInput["client"] = $activityResult->{"client"};

                array_push($nextActivitiesInput, $newInput);
            }
      
            // Start new activity(ies).
            // Several activity to be scheduled if several outputs needed !
            if (!$this->schedule_new_activity(
                    $workflowExecution, 
                    $taskToken, 
					$nextActivity, 
					$nextActivitiesInput
                ))
                return false;
        }
        else if ($activity['activityType']['name'] == self::TRANSCODE_ASSET)
        {
            return $this->transcode_asset_completed(
                $event, 
                $taskToken, 
                $workflowExecution, 
                $activity, 
                $activityResult
            );
        }
        else
        {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                "Unknown activity has completed ! Something is messed up !", 
                $workflowExecution['workflowId']
            );
            return false;
        }
    
        return true;
    }

    private function activity_task_timed_out($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity transcode timed out
        // XXX

        // Record activity timeout in tracker
        if (!($activity = 
                $this->workflowTracker->record_activity_timed_out(
                    $workflowExecution,
                    $event
                )))
            return false;

        $msg = "Activity '" . $activity['activityType']['name'] . "' timed out !";
        log_out(
            "ERROR", 
            basename(__FILE__), 
            $msg, 
            $workflowExecution['workflowId']
        );

        // If TRANSCODE_ASSET, we want to continue as more than one transcode 
        // may be in progress
        if ($activity['activityType']['name'] == self::TRANSCODE_ASSET)
        {
            return $this->transcode_asset_completed(
                $event, 
                $taskToken, 
                $workflowExecution, 
                $activity
            );
        }

        // Kill workflow
        log_out(
            "ERROR", 
            basename(__FILE__), 
            "Killing workflow ...", 
            $workflowExecution['workflowId']
        );
        $this->workflowManager->terminate_workflow(
            $workflowExecution, 
            self::ACTIVITY_TIMEOUT, 
            $msg
        );

        // XXX
        // SQS: Workflow terminated
        // XXX

        return true;
    }
  
    private function activity_task_failed($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity transcode failed
        // XXX

        // Record activity timeout in tracker
        if (!($activity = 
                $this->workflowTracker->record_activity_failed(
                    $workflowExecution,
                    $event
                )))
            return false;
    
        $msg = "Activity '" . $activity['activityType']['name'] . "' failed !";
        log_out(
            "ERROR", 
            basename(__FILE__), 
            $msg, 
            $workflowExecution['workflowId']
        );
        
        if ($activity['activityType']['name'] == self::TRANSCODE_ASSET)
        {
            // If TRANSCODE_ASSET, we want to continue as more than one transcode 
            // may be in progress
            return $this->transcode_asset_completed(
                $event, 
                $taskToken, 
                $workflowExecution, 
                $activity
            );
        }
    
        // Kill workflow
        log_out(
            "ERROR", 
            basename(__FILE__), 
            "Killing workflow ...", 
            $workflowExecution['workflowId']
        );
        $this->workflowManager->terminate_workflow(
            $workflowExecution, 
            self::ACTIVITY_FAILED, 
            $msg
        );

        // XXX
        // SQS: Workflow terminated
        // XXX

        return true;
    }


  
    /** 
     * TOOLS
     */
  
    // When we receive completed, failed or timeout event from 'TranscodeAsset' activity
    private function transcode_asset_completed(
        $event, 
        $taskToken, 
        $workflowExecution, 
        $activity)
    {
        // Check if we are done with all outputs that needs to be transcoded ?
        if (!$this->workflowTracker->are_similar_activities_completed($workflowExecution, 
                $activity))
        {
            log_out(
                "INFO", 
                basename(__FILE__), 
                "There are still 'TranscodeAsset' activities running ...", 
                $workflowExecution['workflowId']
            );
      
            // Send decision
            $this->workflowManager->respond_decisions($taskToken);
      
            return false;
        }

        log_out(
            "INFO", 
            basename(__FILE__), 
            "All transcode activities are over. Workflow completed.", 
            $workflowExecution['workflowId']
        );
    
        // The workflow is over !
        if (!$this->workflowManager->respond_decisions($taskToken, 
                [ ["decisionType" => "CompleteWorkflowExecution"] ]))
            return false;
    
        return true;
    }

    // Start a new activity
    private function schedule_new_activity(
        $workflowExecution, 
        $taskToken, 
        $activity, 
        $inputs)
    {
        if (!$inputs || !count($inputs))
        {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                "No inputs provided for the next activity !"
            );
            return false;
        }

        // Create new decisions based on input
        $decisions = [];
        foreach ($inputs as $input)
        {
            array_push($decisions, [
                    "decisionType" => "ScheduleActivityTask",
                    "scheduleActivityTaskDecisionAttributes" => [
                        "activityType" => 
                        [
                            "name"     => $activity["name"],
                            "version"  => $activity["version"]
                        ],
                        "activityId"   => uniqid(),
                        "input"	       => json_encode($input),
                        "taskList"     => [ "name" => $activity["activityTaskList"] ],
                        "scheduleToStartTimeout"   => $activity["scheduleToStartTimeout"],
                        "startToCloseTimeout"      => $activity["startToCloseTimeout"],
                        "scheduleToCloseTimeout"   => $activity["scheduleToCloseTimeout"],
                        "heartbeatTimeout"         => $activity["heartbeatTimeout"]
                    ]
                ]);
        }

        log_out(
            "INFO", 
            basename(__FILE__), 
            "Scheduling new activity: name='" . $activity["name"] . "',version='" 
            . $activity["version"] . "',taskList='" . $activity["activityTaskList"]  . "'", 
            $workflowExecution['workflowId']
        );
    
        // Send response to SWF to schedule new activities
        if (!$this->workflowManager->respond_decisions($taskToken, $decisions))
            return false;
    
        return true;
    }
}
