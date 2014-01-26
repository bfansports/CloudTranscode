<?php

/**
 * The Decider listen to the workflow and make decisions automaticaly.
 * "decision tasks" != "activity tasks".
 * Decision tasks are "command tasks", resulting from an event in the workfow
 * workflow start, workflow exec complete, workflow failed, etc ...
 * Using the workflow history, it makes decisions
 */

require 'Utils.php';
require 'WorkflowTracker.php';

Class WorkflowDecider
{
	private $domain;
	private $taskList;

	// Custom object used to inspect workflow history
	private $workflowTracker;

	function __construct($config)
	{
		$this->domain   = $config['cloudTranscode']['SWF']['domain'];
		$this->taskList = array("name" => $config['cloudTranscode']['SWF']['taskList']);

		// Init domain
		if (!init_domain($this->domain))
			throw new Exception("[ERROR] Unable to init the domain !\n");

		if (!init_workflow($config['cloudTranscode']['SWF']))
			throw new Exception("[ERROR] Unable to init the workflow !\n");

		// Instantiate tracker. USed to track workflow execution and return next activity to execute
		$this->workflowTracker = new WorkflowTracker($this->domain);
	}	

	// Poll for decision tasks
	public function poll_for_decisions()
	{
		global $swf;
		global $activities;

		try {
			// Poll decision task
			log_out("INFO", basename(__FILE__), "Polling ...");
			$decisionTask = $swf->pollForDecisionTask(array(
				"domain"   => $this->domain,
				"taskList" => $this->taskList,
				));

			// Polling timeout, we return for another round ...
			if (!($workflowExecution = $decisionTask->get("workflowExecution")))
				return true;

		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to pull jobs for decision ! " . $e->getMessage());
			return true;
		}

		// Register workflow in tracker. If already registered nothing happens
		if (!$this->workflowTracker->register_workflow_in_tracker($workflowExecution, $activities))
		{
			log_out("ERROR", basename(__FILE__), "Unable to register the workflow in tracker ! Can't process decision task !");
			return false; 
		}

		// We give the new decision task to the event handler for processing
		$this->decision_task_event_handler($decisionTask, $workflowExecution);

		return true;
	}

	// We received a new decision task. Now what do we do ?
	private function decision_task_event_handler($decisionTask, $workflowExecution)
	{
		global $swf;

		// Creating $events shortcut.
		$events = $decisionTask->get("events");

		// We modify the event array to keep only the latest events 
		//
		// Set index of the last event in array
		$indexStart = $decisionTask["previousStartedEventId"] - 1;
		if (!$decisionTask["previousStartedEventId"])
			$indexStart = 0;
		// Splice to get latest events since last execution
		$newEvents = array_splice($events, $indexStart);

		// Check new incoming event
		foreach ($newEvents as $event) 
		{
			if ($event["eventType"] == 'WorkflowExecutionStarted')
			{
				// Get the input passed to the workflow at startup
				$workflowInput = $this->workflowTracker->get_workflow_input($workflowExecution, $events);

				// Initate first activity as the workflow just started
				if (!$this->workflow_started($decisionTask, $event, $workflowInput, $workflowExecution))
				{
					log_out("ERROR", basename(__FILE__), "Cannot initiate the first TASK after the workflow started ! Killing workflow ...");
					$this->kill_workflow($workflowExecution);
					return false;
				}

				return true;
			}
			elseif ($event["eventType"] == 'ActivityTaskCompleted')
			{
				// Get the previous activity output result
				$eventAttributes = $event["activityTaskCompletedEventAttributes"]; 
				$prevActivityResult = $eventAttributes["result"];
				//log_out("DEBUG", basename(__FILE__), "Previous task results: " . $prevActivityResult);

				if ($this->workflowTracker->is_workflow_finished($workflowExecution)) 
				{
					log_out("INFO", basename(__FILE__), "The workflow is finished. Terminating ...");
					$this->complete_workflow($decisionTask, $event);
					return false;
				}

				// Move to the next activity
				if (!$this->workflowTracker->move_to_next_activity($workflowExecution))
				{
					log_out("ERROR", basename(__FILE__), "Cannot move to the next activity ! Killing workflow ...");
					$this->kill_workflow($workflowExecution);
					return false;
				}

				// Initate next activity
				if (!$this->activity_completed($decisionTask, $event, $prevActivityResult, $workflowExecution))
				{
					log_out("ERROR", basename(__FILE__), "Cannot start next activity ! Killing workflow ...");
					$this->kill_workflow($workflowExecution);
					return false;
				}

				return true;
			}
			elseif ($event["eventType"] == 'ActivityTaskTimedOut')
			{
				log_out("INFO", basename(__FILE__), "Event -> ActivityTaskTimedOut");
				if (!($activity = $this->workflowTracker->get_current_activity($workflowExecution)))
				{
					log_out("ERROR", basename(__FILE__), "Activity timed out but we can't get the current activity ! Something is messed up ...");
					$this->kill_workflow($workflowExecution);
					return false;
				}

				log_out("ERROR", basename(__FILE__), "Activity '" . $activity['name'] . "' timed out ! Killing workflow ...");
				$this->kill_workflow($workflowExecution);

				return true;
			}
			elseif ($event["eventType"] == 'ActivityTaskFailed')
			{
				log_out("INFO", basename(__FILE__), "Event -> ActivityTaskFailed");
				if (!($activity = $this->workflowTracker->get_current_activity($workflowExecution)))
				{
					log_out("ERROR", basename(__FILE__), "Activity failed but we can't get the current activity ! Something is messed up ...");	
					$this->kill_workflow($workflowExecution);
					return false;
				}

				log_out("ERROR", basename(__FILE__), "Activity '" . $activity['name'] . "' failed :[ ! Killing workflow ...");
				$this->kill_workflow($workflowExecution);

				return true;
			}
			elseif ($event["eventType"] == 'WorkflowExecutionCompleted')
			{
				log_out("INFO", basename(__FILE__), "Event -> WorkflowExecutionCompleted");
				log_out("ERROR", basename(__FILE__), "Workflow '" . $workflowExecution["workflowId"] . "' has completed !");

				return true;
			}
			else 
			{
				// XXX Unknown !
			}
		}
	}

	// Called when an activity has completed
	private function activity_completed($decisionTask, $event, $input, $workflowExecution)
	{
		global $swf;

		log_out("INFO", basename(__FILE__), "Event -> ActivityTaskCompleted");

		// Get previous activity
		if (!($previous = $this->workflowTracker->get_previous_activity($workflowExecution)))
		{
			log_out("ERROR", basename(__FILE__), "Cannot the current activity ! Killing workflow ...");
			$this->kill_workflow($workflowExecution);
			return false;
		}

		// Next Task to process
		if (!($next = $this->workflowTracker->get_current_activity($workflowExecution)))
			return false;



		/**
		 * TODO:
		 * We MUST evaluate the previous task result information.
		 * Here we must schedule the necessary following task.
		 * e.g: If the previous task was 'ValidateInputAndAsset' then the output contains the inptu video information.
		 * Also the input contains the different out videos needed.
		 * If several output videos, then we must create several 'TranscodeAsset' activities
		 *
		 * Basic implementation for now
		 */
		
		// If we just finished validating input
		// Now we schedule N transcoding tasks based on config
		if ($previous["name"] == 'ValidateInputAndAsset')
		{
			if (!isset($input) || !($decodedInput = json_decode($input)))
			{
				log_out("ERROR", basename(__FILE__), "No input data from validation activity ! Killing workflow ...");
				$this->kill_workflow($workflowExecution);
				return false;
			}

			if (!isset($decodedInput->{"outputs"}))
			{
				log_out("ERROR", basename(__FILE__), "No outputs configuration from the input config ! Killing workflow ...");
				$this->kill_workflow($workflowExecution);
				return false;
			}

			// Prepare the different inputs based on the number of desired output videos
			$nextActivityInputs = array();
			foreach ($decodedInput->{"outputs"} as $output)
			{
				array_push($nextActivityInputs, array(
					"input_file"               => $decodedInput->{"input_file"},
					"input_config"             => $decodedInput->{"input_config"},
					"ffmpeg_validation_output" => $decodedInput->{"ffmpeg_validation_output"},
					"input_file_duration"      => $decodedInput->{"input_file_duration"},
					"output"                   => $output
					));

				log_out("INFO", basename(__FILE__), "Registering transcoding activity: '" . $next["name"] . "' for output: '" . $output->{"label"} . "'");
			}

			// We provide the custom list of decisions directly
			if (!$this->start_new_activity($decisionTask, $next, $event, $nextActivityInputs))
				return false;
		}

		return true;
	}

	// Called when we receive first event "Workflow Started"
	private function workflow_started($decisionTask, $event, $input, $workflowExecution)
	{
		// Next Task to process
		if (!($activity = $this->workflowTracker->get_current_activity($workflowExecution)))
			return false;

		log_out("INFO", basename(__FILE__), "Event -> WorkflowExecutionStarted");
		log_out("INFO", basename(__FILE__), "Starting activity: '" . $activity["name"] . "'");

		// Start new activity
		if (!$this->start_new_activity($decisionTask, $activity, $event, array(json_decode($input))))
			return false;

		return true;
	}

	// Start a new activity
	private function start_new_activity($decisionTask, $activity, $event, $inputs)
	{
		global $swf;

		// Start an activity 
		try {
			if (!$inputs || !count($inputs))
			{
				log_out("ERROR", basename(__FILE__), "No inputs provided for the next activity !");
				return false;
			}

			// Push all decisions based on input
			$decisions = array();
			foreach ($inputs as $input)
			{
				array_push($decisions, array(
					"decisionType" => "ScheduleActivityTask",
					"scheduleActivityTaskDecisionAttributes" => array(
						"activityType" => array(
							"name"    => $activity["name"],
							"version" => $activity["version"]
							),
						"activityId"   => uniqid(),
						"input"		   => json_encode($input),
						"taskList"     => $this->taskList,
						"scheduleToStartTimeout" => "70",
						"startToCloseTimeout"    => "21600",
						"scheduleToCloseTimeout" => "21600",
						"heartbeatTimeout"       => "60"
						)
					));
			}


			// http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.Swf.SwfClient.html#_respondDecisionTaskCompleted
			// Send decisions back to SWF so activity can be scheduled
			$swf->respondDecisionTaskCompleted(array(
				"taskToken" => $decisionTask["taskToken"],
				"decisions" => $decisions
				));
		} catch (\Aws\Swf\Exception\UnknownResourceException $e) {
			log_out("ERROR", basename(__FILE__), "Resource Unknown ! " . $e->getMessage());
			return false;
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to respond to the decision task '" . $event['eventType'] . "' ! " . $e->getMessage());
			return false;
		}

		return true;
	}

	// Mark workflow as completed
	private function complete_workflow($decisionTask, $event)
	{
		global $swf;

		// Complete workflow 
		try {
			// http://docs.aws.amazon.com/aws-sdk-php/latest/class-Aws.Swf.SwfClient.html#_respondDecisionTaskCompleted
			$swf->respondDecisionTaskCompleted(array(
				"taskToken" => $decisionTask["taskToken"],
				"decisions" => [ array(
					"decisionType" => "CompleteWorkflowExecution",
					"completeWorkflowExecutionDecisionAttributes" => array(
						"result" => "SUCCESS")
					)]));
		} catch (\Aws\Swf\Exception\UnknownResourceException $e) {
			log_out("ERROR", basename(__FILE__), "Resource Unknown ! " . $e->getMessage());
			return false;
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to respond to the decision task '" . $event['eventType'] . "' ! " . $e->getMessage());
			return false;
		}

		return true;
	}

	// Force workflow termination
	private function kill_workflow($workflowExecution)
	{
		global $swf;

		try {
			$swf->terminateWorkflowExecution(array(
				"domain"     => $this->domain,
				"workflowId" => $workflowExecution["workflowId"]
				));
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Cannot kill the workflow ! Something is messed up ...");
			return false;
		}

		return true;
	}
}



/**
 * DECIDER START
 */

// Get config file
$config = json_decode(file_get_contents(dirname(__FILE__) . "/config/cloudTranscodeConfig.json"), true);
log_out("INFO", basename(__FILE__), "Domain: '" . $config['cloudTranscode']['SWF']['domain'] . "'");
log_out("INFO", basename(__FILE__), "TaskList: '" . $config['cloudTranscode']['SWF']['taskList'] . "'");
log_out("INFO", basename(__FILE__), "Clients: ");
print_r($config['clients']);

// Start decider
try {
	$wfDecider = new WorkflowDecider($config);
} catch (Exception $e) {
	log_out("ERROR", basename(__FILE__), "Unable to create WorkflowDecider ! " . $e->getMessage());
	exit (1);
}

// Start polling loop
log_out("INFO", basename(__FILE__), "Starting decision tasks polling");
while (1)
{
	if (!$wfDecider->poll_for_decisions())
	{
		log_out("INFO", basename(__FILE__), "Polling for decisions finished !");
		exit (1);
	}
} 


