<?php

require __DIR__ . '/WorkflowTracker.php';

class DeciderBrain
{
    private $debug;
    private $config;
    private $eventsMap;
    private $workflowTracker;
    private $workflowManager;
    private $decisionTaskList;
    private $activityList;
    private $CTCom;
  
    // Errors
    const ACTIVITY_TIMEOUT = "ACTIVITY_TIMEOUT";
    const ACTIVITY_FAILED  = "ACTIVITY_FAILED";
    const DECIDER_ERROR    = "DECIDER_ERROR";
    const WF_NO_INPUT      = "WF_NO_INPUT";
    const WF_TRACKER_ISSUE = "WF_TRACKER_ISSUE";
    const ACTIVITY_SCHEDULE_FAILED = "ACTIVITY_SCHEDULE_FAILED";
    const TRACKER_RECORD_SCHEDULE_FAILED = "TRACKER_RECORD_SCHEDULE_FAILED";

    // Activities
    const VALIDATE_INPUT  = "ValidateInputAndAsset";
    const TRANSCODE_ASSET = "TranscodeAsset";

    function __construct($config, $workflowManager, $debug)
    {
        $this->debug = $debug;
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
            'DecisionTaskStarted'        => 'decision_task_started'
        ];
    
        // Instantiate tracker. 
        // Used to track workflow execution and track workflow status
        $this->workflowTracker = new WorkflowTracker($this->config, $this->workflowManager);
        
        $this->workflowManager  = $workflowManager;
        $this->decisionTaskList = array(
            "name" => $config['cloudTranscode']['workflow']['decisionTaskList']
        );
        $this->activityList     = $this->config['cloudTranscode']['activities'];

        // Instanciate CloudTranscode SDK
        $this->CTCom = new SA\CTComSDK(false, false, false, $this->debug);
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
        if (!isset($this->eventsMap[$event["eventType"]])) {
            if ($this->debug)
                log_out(
                    "DEBUG", 
                    basename(__FILE__), 
                    "UnHandled event: *" . $event["eventType"] . "*", 
                    $workflowExecution['workflowId']
                );
            return;
        }
    
        log_out(
            "INFO", 
            basename(__FILE__), "*" . $event["eventType"] . "*", 
            $workflowExecution['workflowId']
        );

        try {
            // We call the callback function that handles this event 
            $this->{$this->eventsMap[$event["eventType"]]}(
                $event, 
                $taskToken, 
                $workflowExecution);
        }
        catch (CTException $e) {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                "[" . $e->ref . "] " . $e->getMessage(), 
                $workflowExecution['workflowId']
            );
            $this->workflowManager->terminate_workflow(
                $workflowExecution, 
                $e->ref, 
                $e->getMessage()
            );
        }
        catch (Exception $e) {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                $e->getMessage(), 
                $workflowExecution['workflowId']
            );
            $this->workflowManager->terminate_workflow(
                $workflowExecution, 
                self::DECIDER_ERROR, 
                $e->getMessage()
            );
        }
    }

  
    /**
     * Callback functions
     */

    // Workflow started !
    private function workflow_execution_started($event, $taskToken, $workflowExecution)
    {
        if (!isset($event["workflowExecutionStartedEventAttributes"]["input"]))
            throw new CTException(
                "Workflow doesn't contain any input data!",
                self::WF_NO_INPUT
            );
        
        // Get the input passed to the workflow at startup
        $workflowInput = $event["workflowExecutionStartedEventAttributes"]["input"];
        
        // Register workflow in tracker if not already register
        if (!$this->workflowTracker->register_workflow_in_tracker(
                $workflowExecution, 
                $this->activityList,
                $workflowInput))
        {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                "Unable to register the workflow in tracker! Can't process decision task!"
            );
            return false; 
        }
        
        // Next Task to process
        if (!($fistActivity = 
                $this->workflowTracker->get_first_activity($workflowExecution)))
            throw new CTException(
                "Unable to get first registered activity to process!",
                self::WF_TRACKER_ISSUE
            );

        // Start new activity
        if (!$this->schedule_new_activity(
                $workflowExecution, 
                $taskToken, 
                $fistActivity, 
                [json_decode($workflowInput)]))
            throw new CTException(
                "Unable to schedule new activity '" . $activity["name"]  ."'!",
                self::ACTIVITY_SCHEDULE_FAILED
            );
        
        // ComSDK - Notify Workflow started
        $this->CTCom->job_started($workflowExecution, $workflowInput);
        
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

        //print_r($event);
        
        // XXX
        // SQS Workflow completed
        // XXX
        $this->CTCom->job_completed();

        return true;
    }
  
    // Activity scheduled
    private function activity_task_scheduled($event, $taskToken, $workflowExecution)
    {
        // Register new scheduled activities in tracker
        if (!($activity = 
                $this->workflowTracker->record_activity_scheduled($workflowExecution, $event)))
            throw new CTException(
                "Unable to record activity schedule '" 
                . $event["activityTaskScheduledEventAttributes"]["activityType"]['name'] ."'!",
                self::TRACKER_RECORD_SCHEDULE_FAILED
            );

        // XXX
        // Send message through SQS to tell activity task scheduled
        // XXX
        $this->CTCom->activity_scheduled(
            $workflowExecution,
            $this->workflowTracker->get_workflow_input($workflowExecution),
            $activity
        );
    }

    // Activity started !
    private function activity_task_started($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity task started
        // XXX
        $this->CTCom->activity_started();
        
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
        $this->CTCom->activity_completed();
        
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
                    "job_id"           => $activityResult->{"job_id"},
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
        $this->CTCom->activity_timeout();

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
        $this->CTCom->job_terminated();

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
        $this->CTCom->job_terminated();

        return true;
    }

    private function decision_task_started($event, $taskToken, $workflowExecution)
    {
        /* if ($this->workflowTracker->is_workflow_completed($workflowExecution)) */
        /* { */
        /*     log_out( */
        /*         "INFO",  */
        /*         basename(__FILE__),  */
        /*         "No more activity to perform. Completing Workflow ...!",  */
        /*         $workflowExecution['workflowId'] */
        /*     ); */

        /*     // The workflow is over ! */
        /*     if (!$this->workflowManager->respond_decisions($taskToken, */
        /*             [ ["decisionType" => "CompleteWorkflowExecution"] ])) */
        /*         return false; */
        /* } */
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
      
            return true;
        }

        log_out(
            "INFO", 
            basename(__FILE__), 
            "All transcode activities are over. Workflow completed.", 
            $workflowExecution['workflowId']
        );
    
        // Mark WF as completed in tracker
        $this->workflowTracker->record_workflow_completed($workflowExecution);
        
        // The workflow is over !
        /* if (!$this->workflowManager->respond_decisions($taskToken,  */
        /*         [ ["decisionType" => "CompleteWorkflowExecution"] ])) */
        /*     return false; */
    
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
