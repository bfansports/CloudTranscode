<?php

/**
 * The Decider listen to the workflow and make decisions based on previous events.
 * "decision tasks" != "activity tasks".
 * Decision tasks are "command tasks", resulting from an event in the workfow
 * workflow start, workflow exec complete, workflow failed, etc ...
 * Using the workflow history, it makes decisions
 */

require __DIR__ . '/utils/Utils.php';
require __DIR__ . '/WorkflowManager.php';
require __DIR__ . '/DeciderBrain.php';

class Decider
{
    private $debug;
    private $domain;
    private $decisionTaskList;
    private $activityList;

    private $workflowManager;

    // Decider brain, where all decisions are made
    private $deciderBrain;
  
    function __construct($config)
    {
        global $debug;
        
        $this->debug            = $debug;
        $this->domain           = $config['cloudTranscode']['workflow']['domain'];
        $this->decisionTaskList = array("name" => 
            $config['cloudTranscode']['workflow']['decisionTaskList']);
        $this->activityList     = $config['cloudTranscode']['activities'];
    
        // Init domain. see: Utils.php
        if (!init_domain($this->domain))
            throw new Exception("Unable to init the domain !\n");
        
        // Init workflow. see: Utils.php
        if (!init_workflow($config['cloudTranscode']['workflow']))
            throw new Exception("Unable to init the workflow !\n");

        // Instantiate manager
        // Used to perform actions on the workflow. Toolbox.
        $this->workflowManager = new WorkflowManager($config);
        
        // Instantiate DeciderBrain
        // This is where the decisions are made and new activity initiated
        $this->deciderBrain = new DeciderBrain(
            $config, 
            $this->workflowManager, 
            $this->debug
        );
    }

    // Poll for decision tasks
    public function poll_for_decisions()
    {
        global $swf;
        global $activities; 

        if ($this->debug)
            log_out(
                "INFO", 
                basename(__FILE__), 
                "Polling decision taskList ..."
            );

        try {
            // Poll decision task
            $decisionTask = $swf->pollForDecisionTask(
                array(
                    "domain"   => $this->domain,
                    "taskList" => $this->decisionTaskList,
                ));

            // Polling timeout, we return for another round ...
            if (!($workflowExecution = $decisionTask->get("workflowExecution")))
                return true;

        } catch (Exception $e) {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                "Unable to pull jobs for decision ! " . $e->getMessage());
            return true;
        }
    
        // We give the new decision task to the event handler for processing
        $this->decision_task_event_handler($decisionTask, $workflowExecution);

        return true;
    }

    // We received a new decision task. Now what do we do ?
    private function decision_task_event_handler($decisionTask, $workflowExecution)
    {
        global $swf;
        
        // Get list of all events in WF history
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
            $this->deciderBrain->handle_event(
                $event, 
                $decisionTask["taskToken"], 
                $workflowExecution
            );
        }
    }
}


/**
 * DECIDER START
 */

$debug = false;

function usage($defaultConfigFile)
{
    echo("Usage: php ". basename(__FILE__) . " [-h] [-c <path to JSON config file>]\n");
    echo("-h: Print this help\n");
    echo("-d: Debug mode\n");
    echo("-c <file path>: Optional parameter to override the default configuration file: '$defaultConfigFile'.\n");
    exit(0);
}

function check_input_parameters(&$defaultConfigFile)
{
    global $debug;
    
    // Handle input parameters
    $options = getopt("c:hd");
    if (isset($options['h']))
        usage($defaultConfigFile);
    
    if (isset($options['d']))
        $debug = true;
  
    if (isset($options['c']))
    {
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Custom config file provided: '" . $options['c'] . "'"
        );
        $defaultConfigFile = $options['c'];
    }
}

// Get config file
$defaultConfigFile = realpath(dirname(__FILE__)) . "/../config/cloudTranscodeConfig.json";
check_input_parameters($defaultConfigFile);
if (!($config = json_decode(file_get_contents($defaultConfigFile), true)))
{
    log_out(
        "FATAL", 
        basename(__FILE__), 
        "Configuration file '$defaultConfigFile' invalid!"
    );
    exit(1);
}
log_out(
    "INFO", 
	basename(__FILE__), 
    "Domain: '" . $config['cloudTranscode']['workflow']['domain'] . "'"
);
log_out(
    "INFO", 
	basename(__FILE__), 
    "TaskList: '" . $config['cloudTranscode']['workflow']['decisionTaskList'] . "'"
);
log_out("INFO", basename(__FILE__), $config['clients']);

// Create decider object
try {
    $decider = new Decider($config);
} 
catch (Exception $e) {
    log_out(
        "FATAL", 
        basename(__FILE__), 
        $e->getMessage()
    );
    exit(1);
}

// Start polling loop to get decision tasks
if ($debug)
    log_out(
        "DEBUG", 
        basename(__FILE__), 
        "Starting decision tasks polling"
    );
while (42)
{
    if (!$decider->poll_for_decisions())
    {
        if ($debug)
            log_out(
                "DEBUG", 
                basename(__FILE__), 
                "Polling for decisions over! Exiting ..."
            );
        exit(1);
    }
} 


