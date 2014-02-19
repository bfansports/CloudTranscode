<?php

/**
 * The Decider listen to the workflow and make decisions based on previous events.
 * "decision tasks" != "activity tasks".
 * Decision tasks are "command tasks", resulting from an event in the workfow
 * workflow start, workflow exec complete, workflow failed, etc ...
 * Using the workflow history, it makes decisions
 */

require 'Utils.php';
require 'WorkflowTracker.php';
require 'WorkflowManager.php';
require 'DeciderBrain.php';

Class Decider
{
	private $domain;
	private $taskList;

	private $workflowManager;
	private $workflowTracker;

    // Decider brain, where all decisions are made
    private $deciderBrain;


	function __construct($config)
	{
		$this->domain   = $config['cloudTranscode']['SWF']['domain'];
		$this->taskList = array("name" => $config['cloudTranscode']['SWF']['taskList']);
        
		// Init domain
		if (!init_domain($this->domain))
			throw new Exception("[ERROR] Unable to init the domain !\n");

		if (!init_workflow($config['cloudTranscode']['SWF']))
			throw new Exception("[ERROR] Unable to init the workflow !\n");

        // Instantiate manager
        // Used to perform actions on the workflow. Toolbox.
		$this->workflowManager = new WorkflowManager($config);
        
		// Instantiate tracker. 
        // Used to track workflow execution and track workflow status
		$this->workflowTracker = new WorkflowTracker($this->domain, $this->workflowManager);
        
        // Instantiate DeciderBrain
        // This is where the decisions are made and new activity initiated
		$this->deciderBrain = new DeciderBrain($this->workflowTracker, 
            $this->workflowManager, $this->taskList);
	}	

	// Poll for decision tasks
	public function poll_for_decisions()
	{
		global $swf;
		global $activities; // From Utils.php

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

        // Is workflow already trackked by tracker ?
        if (!$this->workflowTracker->is_workflow_tracked($workflowExecution))
            {
                // Register workflow in tracker.
                if (!$this->workflowTracker->register_workflow_in_tracker($workflowExecution, $activities))
                    {
                        log_out("ERROR", basename(__FILE__), 
                            "Unable to register the workflow in tracker ! Can't process decision task !");
                        return false; 
                    }
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
		// Set index of the last event in array
		$indexStart = $decisionTask["previousStartedEventId"] - 1;
		if (!$decisionTask["previousStartedEventId"])
			$indexStart = 0;
		// Splice to get latest events since last execution
		$newEvents = array_splice($events, $indexStart);
        
		// Check new incoming event
		foreach ($newEvents as $event) 
            {
                // We ask the brain to make a decision
                // We pass all events, new events, and this event
                $this->deciderBrain->handle_event($events, $newEvents, $event, 
                    $decisionTask["taskToken"], $workflowExecution);
            }
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
	$wfDecider = new Decider($config);
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


