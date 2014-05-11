<?php

/**
 * The Decider brain is where the workflow logic happens
 * Based on the workflow historic (using WorkflowTracker) we make decisions
 * We fire new activities, catch timeout, failure, etc.
 * We make the workflows move on util they complete
 */

require __DIR__ . '/WorkflowTracker.php';
require __DIR__ . '/WorkflowManager.php';

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
    const WF_TRACKER_ISSUE = "WF_TRACKER_ISSUE";
    const NO_INPUT         = "NO_INPUT";
    const INPUT_INVALID    = "INPUT_INVALID";
    const RESULT_INVALID   = "RESULT_INVALID";
    const UNKNOWN_ACTIVITY = "UNKNOWN_ACTIVITY";
    const FATAL_ISSUE      = "FATAL_ISSUE";
    
    // Known Activities
    const VALIDATE_INPUT   = "ValidateInputAndAsset";
    const TRANSCODE_ASSET  = "TranscodeAsset";

    function __construct($config, $debug)
    {
        $this->debug = $debug;
        $this->config = $config;

        // Init eventMap. Maps workflow events with callback functions.
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
    
        // Instantiate manager
        // Used to perform actions on the workflow. Toolbox.
        $this->workflowManager = new WorkflowManager($config);
        
        // Instantiate tracker. 
        // Used to track workflow execution and track workflow status
        $this->workflowTracker = new WorkflowTracker($this->config, $this->workflowManager);
        
        $this->decisionTaskList = array(
            "name" => $config['cloudTranscode']['workflow']['decisionTaskList']
        );
        $this->activityList     = $this->config['cloudTranscode']['activities'];

        // Instanciate CloudTranscode COM SDK
        // Used to communicate to job owner: progress, issues, completions, etc
        $this->CTCom = new SA\CTComSDK(false, false, false, $this->debug);
    }
  
    // Handles new workflow events
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

        $errRef = 0;
        $errMsg = 0;
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
            $errRef = $e->ref;
            $errMsg = $e->getMessage();
        }
        catch (Exception $e) {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                $e->getMessage(), 
                $workflowExecution['workflowId']
            );
            $errRef = self::DECIDER_ERROR;
            $errMsg = $e->getMessage();
        }
        finally {
            if ($errRef && $errMsg)
            {
                try {
                    $this->workflowManager->terminate_workflow(
                        $workflowExecution, 
                        $errRef, 
                        $errMsg
                    );
                }
                catch (Exception $e) {
                    log_out(
                        "ERROR", 
                        basename(__FILE__), 
                        "Unable to terminate workflow! Details: " . $e->getMessage(), 
                        $workflowExecution['workflowId']
                    );
                }
            }
        }
    }

  
    /**
     * Callback functions
     */

    // A new Workflow started !
    private function workflow_execution_started($event, $taskToken, $workflowExecution)
    {
        // Get the input passed to the workflow at startup
        if (!isset($event["workflowExecutionStartedEventAttributes"]["input"]))
            throw new CTException(
                "Workflow doesn't contain any input data!",
                self::NO_INPUT
            );
        if (!($workflowInput = 
                json_decode($event["workflowExecutionStartedEventAttributes"]["input"])))
            throw new CTException(
                "Workflow input JSON is invalid!",
                self::INPUT_INVALID
            );
        
        // Register workflow in tracker if not already register
        $newWorkflow = $this->workflowTracker->register_workflow_in_tracker(
            $workflowExecution, 
            $this->activityList,
            $workflowInput
        );
        
        // Next Task to process
        $fistActivity = 
            $this->workflowTracker->get_first_activity($workflowExecution);

        // Start new activity
        $this->schedule_new_activity(
            $workflowExecution, 
            $taskToken, 
            $fistActivity, 
            [$workflowInput]);
        
        // ComSDK - Notify Workflow started
        $this->CTCom->job_started($workflowExecution, $workflowInput);
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
        if (!isset($event["activityTaskScheduledEventAttributes"]["input"]))
            throw new CTException(
                "Activity doesn't contain any input data!",
                self::NO_INPUT
            );
        if (!($input = 
                json_decode($event["activityTaskScheduledEventAttributes"]["input"])))
            throw new CTException(
                "Activity input JSON is invalid!",
                self::INPUT_INVALID
            );

        // Register new scheduled activities in tracker
        $activity = 
            $this->workflowTracker->record_activity_scheduled(
                $workflowExecution, 
                $event, 
                $input
            );
        
        // ComSDK - Notify Activity scheduled
        $this->CTCom->activity_scheduled(
            $workflowExecution,
            $this->workflowTracker->get_workflow_input($workflowExecution),
            $activity
        );
    }

    // Activity started !
    private function activity_task_started($event, $taskToken, $workflowExecution)
    {
        // Register new started activities in tracker
        $activity = 
            $this->workflowTracker->record_activity_started($workflowExecution, $event);
            

        // ComSDK - Notify Activity scheduled
        $this->CTCom->activity_started(
            $workflowExecution,
            $this->workflowTracker->get_workflow_input($workflowExecution),
            $activity
        );
    }
  
    // Activity Task completed
    private function activity_task_completed($event, $taskToken, $workflowExecution)
    {
        // Register completed activity in tracker
        $activity = 
            $this->workflowTracker->record_activity_completed(
                $workflowExecution,
                $event
            );

        // ComSDK - Notify Activity scheduled
        $this->CTCom->activity_completed(
            $workflowExecution,
            $this->workflowTracker->get_workflow_input($workflowExecution),
            $activity
        );

        // We completed 'ValidateInputAndAsset' activity
        if ($activity['activityType']['name'] == self::VALIDATE_INPUT)
        {
            // We get the output of the completed activity
            if (!($activityResult = 
                    json_decode($event['activityTaskCompletedEventAttributes']['result'])))
                throw new CTException(
                    "Activity result JSON is invalid!",
                    self::RESULT_INVALID
                );

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
            $this->schedule_new_activity(
                $workflowExecution, 
                $taskToken, 
                $nextActivity, 
                $nextActivitiesInput
            );

            return;
        }
        else if ($activity['activityType']['name'] == self::TRANSCODE_ASSET)
        {
            $this->transcode_asset_completed(
                $event, 
                $taskToken, 
                $workflowExecution, 
                $activity
            );
            return;
        }

        throw new CTException(
            "Unknown activity has completed! Something is messed up!",
            self::UNKNOWN_ACTIVITY
        );
    }

    private function activity_task_timed_out($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity transcode timed out
        // XXX
        $this->CTCom->activity_timeout();

        // Record activity timeout in tracker
        $activity = 
            $this->workflowTracker->record_activity_timed_out(
                $workflowExecution,
                $event
            );
        
        log_out(
            "ERROR", 
            basename(__FILE__), 
            "Activity '" . $activity['activityType']['name'] . "' timed out !", 
            $workflowExecution['workflowId']
        );

        // If TRANSCODE_ASSET, we want to continue as more than one transcode 
        // may be in progress
        if ($activity['activityType']['name'] == self::TRANSCODE_ASSET)
        {
            $this->transcode_asset_completed(
                $event, 
                $taskToken, 
                $workflowExecution, 
                $activity
            );
            return;
        }

        throw new CTException(
            "Can't continue workflow execution. Fatal issue!",
            self::FATAL_ISSUE
        );
    }
  
    private function activity_task_failed($event, $taskToken, $workflowExecution)
    {
        // XXX
        // Send message through SQS to tell activity transcode failed
        // XXX

        // Record activity timeout in tracker
        $activity = 
            $this->workflowTracker->record_activity_failed(
                $workflowExecution,
                $event
            );
        
        log_out(
            "ERROR", 
            basename(__FILE__), 
            "Activity '" . $activity['activityType']['name'] . "' failed !", 
            $workflowExecution['workflowId']
        );
        
        if ($activity['activityType']['name'] == self::TRANSCODE_ASSET)
        {
            // If TRANSCODE_ASSET, we want to continue as more than one transcode 
            // may be in progress
            $this->transcode_asset_completed(
                $event, 
                $taskToken, 
                $workflowExecution, 
                $activity
            );
            return ;
        }

        throw new CTException(
            "Can't continue workflow execution. Fatal issue!",
            self::FATAL_ISSUE
        );
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
        if (!$this->workflowTracker->are_similar_activities_completed(
                $workflowExecution, 
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
      
            return;
        }

        log_out(
            "INFO", 
            basename(__FILE__), 
            "All transcode activities are over! Requesting workflow completion ...", 
            $workflowExecution['workflowId']
        );
    
        // Mark WF as completed in tracker
        $this->workflowTracker->record_workflow_completed($workflowExecution);
        
        // The workflow is over !
        /* if (!$this->workflowManager->respond_decisions($taskToken,  */
        /*         [ ["decisionType" => "CompleteWorkflowExecution"] ])) */
        /*     return false; */
    }

    // Start a new activity
    private function schedule_new_activity(
        $workflowExecution, 
        $taskToken, 
        $activity, 
        $inputs)
    {
        if (!$inputs || !count($inputs))
            throw new CTException(
                "No input provided for the next activity !",
                self::NO_INPUT
            );

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
        $this->workflowManager->respond_decisions($taskToken, $decisions);
    }
}
