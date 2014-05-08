<?php

/**
 * The activity poller listen for "activity tasks" 
 * Stuff to do, compute, process, whatever.
 * We do transcoding
 */

require __DIR__ . "/utils/Utils.php";
require __DIR__ . "/activities/BasicActivity.php";

class ActivityPoller
{
    private $debug;
    private $domain;
    private $knownActivities;
    private $activitiesToHandle;
    private $activityTaskLists;
  
    const EMPTY_RESULT    = "EMPTY_RESULT";
    const ACTIVITY_FAILED = "ACTIVITY_FAILED";
  
    function __construct($config, $activitiesToHandle)
    {
        global $activities;
        global $debug;

        $this->debug  = $debug;
        $this->domain = $config['cloudTranscode']['workflow']['domain'];
        $this->knownActivities = $config['cloudTranscode']['activities'];
        $this->activitiesToHandle = $activitiesToHandle["activities"];
        $this->activityTaskLists = [];
    
        // Init domain. see: Utils.php
        if (!init_domain($this->domain)) 
            throw new Exception("Unable to init the domain !\n");

        // Check and load activities to handle
        if (!$this->register_activities())
            throw new Exception("No activity class registered! Exiting ...");
    }	

    // We poll for new activities
    // Return true to keep polling even on failure
    // Return false will stop process !
    public function poll_for_activities()
    {
        global $swf;

        // Initiate polling
       
        // Poll from all the taskList registered for each activities 
        foreach ($this->activityTaskLists as $taskList => $x)
        {
            if ($this->debug)
                log_out(
                    "INFO", 
                    basename(__FILE__), 
                    "Polling activity taskList '" . $taskList  . "' ... "
                );
            
            try {
                // Call SWF and poll for incoming tasks
                $activityTask = $swf->pollForActivityTask([
                        "domain"   => $this->domain,
                        "taskList" => array("name" => $taskList)
                    ]);
            } catch (Exception $e) {
                log_out(
                    "ERROR", 
                    basename(__FILE__), 
                    "Unable to poll activity tasks! " . $e->getMessage()
                );
            }

            // Handle and process the new activity task
            $this->process_activity_task($activityTask);
        }
        
        return true;
    }

    // Process the new task using one of the activity handler classes registered
    private function process_activity_task($activityTask)
    {
        // Get activityType and WorkflowExecution info
        if (!($activityType      = $activityTask->get("activityType")) ||
            !($workflowExecution = $activityTask->get("workflowExecution")))
            return false;
        
        // Can activity be handled by this poller ?
        if (!($activity = $this->get_activity($activityType))) 
        {
            log_out("ERROR", basename(__FILE__), 
                "This activity type is unknown ! Skipping ...",
                $workflowExecution['workflowId']);
            return false;
        }
    
        log_out("INFO", basename(__FILE__), 
            "Starting activity: name=" . $activity["name"] . ",version=" . $activity["version"],
            $workflowExecution['workflowId']);

        // Has activity handler object been instantiated ?
        if (!isset($activity["object"])) 
        {
            log_out("ERROR", basename(__FILE__),
                "The activity handler class for this activity type is not instantiated !",
                $workflowExecution['workflowId']);
            return false;
        }

        // Run activity task
        try {
            $result = $activity["object"]->do_activity($activityTask);
        } catch (CTException $e) {
            $activity["object"]->activity_failed($activityTask, 
                $e->ref, 
                $e->getMessage());
            return false;
        } catch (Exception $e) {
            $activity["object"]->activity_failed($activityTask, 
                self::ACTIVITY_FAILED, 
                $e->getMessage());
            return false;
        }
    
        // Send completion msg
        $activity["object"]->activity_completed($activityTask, $result);
        return true;
    }
  
    // Register and instantiate activities handlers classes
    private function register_activities()
    {
        $registered = 0;

        // Dynamically load classes responsible for handling each activity.
        foreach ($this->activitiesToHandle as &$activityToHandle)
        {
            foreach ($this->knownActivities as $knownActivity)
            {
                if ($activityToHandle["name"] == $knownActivity["name"] &&
                    $activityToHandle["version"] == $knownActivity["version"])
                {
                    $activityToHandle = $knownActivity;

                    // Load the file representing the activity
                    $file = dirname(__FILE__) . $activityToHandle["file"];
                    require_once $file;

                    try {
                        // Instantiate the class
                        $activityToHandle["object"] = 
                            new $activityToHandle["class"]([
                                    "domain"  => $this->domain,
                                    "name"    => $activityToHandle["name"],
                                    "version" => $activityToHandle["version"]
                                ]);
                    } catch (CTException $e) {
                        throw new Exception("Unable to load and register activity class '" 
                            . $activityToHandle["class"] . "'. Abording ...");
                    }

                    log_out("INFO", 
                        basename(__FILE__), 
                        "Activity handler registered: name=" 
                        . $activityToHandle["name"] . ",version=" 
                        . $activityToHandle["version"]
                    );

                    // REgister this activity taskList is the activityTaskLists Tracker
                    if (!isset($this->activityTaskLists[$activityToHandle["activityTaskList"]]))
                        $this->activityTaskLists[$activityToHandle["activityTaskList"]] = true;

                    $registered++;
                    break;
                }
            }
        }

        return $registered;
    }

    // Get the activity from name and version
    private function get_activity($activityType)
    {
        foreach ($this->activitiesToHandle as $activityToHandle)
        {
            if ($activityToHandle["name"]    == $activityType["name"] &&
                $activityToHandle["version"] == $activityType["version"])
                return $activityToHandle;
        }

        return false;
    }
}



/**
 * POLLER START
 */

$debug = false;

function usage($defaultConfigFile)
{
    echo("Usage: php ". basename(__FILE__) . " [-h] [-d] [-c <path to JSON config file>] -j '{inline JSON listing activities to handle}' -a <path to JSON config file listing activities to handle>\n");
    echo("-h: Print this help\n");
    echo("-d: Debug mode\n");
    echo("-c <file path>: Optional parameter to override the default configuration file: '$defaultConfigFile'.\n");
    echo("-a <file path>: Specify the path to JSON config file containing the list of activities this ActivityPoller can handle. (see: config/\n");
    echo("-j '{JSON}': Specify JSON config as text inline.\n");
    exit(0);
}

function check_input_parameters(&$defaultConfigFile)
{
    global $debug;

    // Handle input parameters
    $options = getopt("j:c:a:hd");
    if (!count($options) || isset($options['h']))
        usage($defaultConfigFile);

    if (isset($options['d']))
        $debug = true;
  
    if (!isset($options['j']) &&
        !isset($options['a']))
    {
        log_out("ERROR", basename(__FILE__), "You must provide JSON input containing the list of activities this ActivityPoller can handle. Use -a or -j option!");
        usage($defaultConfigFile);
    }
  
    if (isset($options['j']) &&
        isset($options['a']))
    {
        log_out("ERROR", basename(__FILE__), "Provide only one JSON input listing of activities this ActivityPoller can handle. Can't provide both -a or -j options!");
        usage($defaultConfigFile);
    }

    if (isset($options['j']))
        if (!($activities = json_decode($options['j'], true)))
            throw new Exception("JSON provide as part of -j option is invalid!");
    
    if (isset($options['a']))
        if (!($activities = json_decode(file_get_contents($options['a']), true)))
            throw new Exception("JSON provide as part of -a option is invalid!");

    if (isset($options['c']))
    {
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Custom config file provided: '" . $options['c'] . "'"
        );
        $defaultConfigFile = $options['c'];
    }
  
    return $activities;
}



// Get config file
$defaultConfigFile = realpath(dirname(__FILE__)) . "/../config/cloudTranscodeConfig.json";
// Check input parameters
$activities = check_input_parameters($defaultConfigFile);
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
log_out("INFO", basename(__FILE__), $config['clients']);

// Instantiate AcivityPoller
$activityPoller = new ActivityPoller($config, $activities);

// Start polling loop
log_out(
    "INFO", 
    basename(__FILE__), 
    "Starting activity tasks polling"
);
while (1)
{
    if (!$activityPoller->poll_for_activities())
    {
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Polling for activities finished !"
        );
        exit(1);
    }

    sleep(4);
} 
