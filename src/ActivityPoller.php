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
    private $taskList;
    private $activityName;
    private $activityVersion;
    private $activityHandler;
  
    const EMPTY_RESULT    = "EMPTY_RESULT";
    const ACTIVITY_FAILED = "ACTIVITY_FAILED";
  
    function __construct($config)
    {
        global $debug;
        global $domain;
        global $taskList;
        global $activityName;
        global $activityVersion;
        
        $this->debug    = $debug;
        $this->domain   = $domain;
        $this->taskList = $taskList;
        $this->activityName    = $activityName;
        $this->activityVersion = $activityVersion;
        $this->knownActivities = $config->{'activities'};
        
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
        if ($this->debug)
            log_out(
                "DEBUG", 
                basename(__FILE__), 
                "Polling activity taskList '" . $this->taskList  . "' ... "
            );
            
        try {
            // Call SWF and poll for incoming tasks
            $activityTask = $swf->pollForActivityTask([
                    "domain"   => $this->domain,
                    "taskList" => array("name" => $this->taskList)
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
        
        return true;
    }

    // Process the new task using one of the activity handler classes registered
    private function process_activity_task($activityTask)
    {
        // Get activityType and WorkflowExecution info
        if (!($activityType      = $activityTask->get("activityType")) ||
            !($workflowExecution = $activityTask->get("workflowExecution")))
        {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                "No Activity type nor Workflow execution data."
            );
            return false;
        }
        
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Starting activity: name=" 
            . $activityType['name'] . ",version=" . $activityType['version'],
            $workflowExecution['workflowId']
        );

        // Has activity handler object been instantiated ?
        if (!isset($this->activityHandler)) 
        {
            log_out(
                "ERROR", 
                basename(__FILE__),
                "The activity handler class for this activity type is not instantiated !",
                $workflowExecution['workflowId']
            );
            return false;
        }

        // Run activity task
        $reason = 0;
        $details = 0;
        try {
            $result = $this->activityHandler->do_activity($activityTask);
        } catch (CTException $e) {
            $reason  = $e->ref;
            $details = $e->getMessage();
        } catch (Exception $e) {
            $reason  = self::ACTIVITY_FAILED;
            $details = $e->getMessage();
        } finally {
            if ($reason && $details)
            {
                $this->activityHandler->activity_failed(
                    $activityTask, 
                    $reason, 
                    $details
                );
                return false;
            }
        }
    
        // Send completion msg
        $this->activityHandler->activity_completed($activityTask, $result);
        return true;
    }
  
    // Register and instantiate activities handlers classes
    private function register_activities()
    {
        $registered = 0;

        print(">>> name: $this->activityName || version: $this->activityVersion\n");
        
        foreach ($this->knownActivities as $knownActivity)
        {
            if ($this->activityName == $knownActivity->{"name"} &&
                $this->activityVersion == $knownActivity->{"version"})
            {
                $activityToHandle = $knownActivity;
                
                // Load the file representing the activity
                $file = dirname(__FILE__) . $activityToHandle->{"file"};
                require_once $file;
                
                // Instantiate the Activity class that will process Tasks
                $this->activityHandler = 
                    new $activityToHandle->{"class"}(
                        [
                            "domain"  => $this->domain,
                            "name"    => $activityToHandle->{"name"},
                            "version" => $activityToHandle->{"version"}
                        ], 
                        $this->debug
                    );

                    log_out(
                        "INFO", 
                        basename(__FILE__), 
                        "Activity handler registered: name=" 
                        . $activityToHandle->{"name"} . ",version=" 
                        . $activityToHandle->{"version"}
                    );
                
                    return true;
            }
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
    echo("Usage: php ". basename(__FILE__) . " -D <domain> -T <task_list> -A <activity_name> -V <activity_version> [-h] [-d] [-c <path to JSON config file>]\n");
    echo("-h: Print this help\n");
    echo("-d: Debug mode\n");
    echo("-c <file path>: Optional parameter to override the default configuration file: '$defaultConfigFile'.\n");
    echo("-D <domain>: SWF domain for the workflow\n");
    echo("-T <task list>: Specify the Activity Task List this activity will listen to. An Activity Task list is the queue your Activity poller will listen to for new input tasks.\n");
    echo("-A <activity name>: Activity name this Poller can handle. This will be used to load the proper Activity code.");
    echo("-V <activity version>: Activity version this Poller can handle. This will be used to load the proper Activity code.");
    exit(0);
}

function check_input_parameters(&$defaultConfigFile)
{
    global $debug;
    global $domain;
    global $taskList;
    global $activityName;
    global $activityVersion;
    
    // Handle input parameters
    if (!($options = getopt("D:T:A:V:c:hd")))
        usage($defaultConfigFile);
    if (!count($options) || isset($options['h']))
        usage($defaultConfigFile);

    // Debug
    if (isset($options['d']))
        $debug = true;

    // OVerrride config file
    if (isset($options['c']))
    {
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Custom config file provided: '" . $options['c'] . "'"
        );
        $defaultConfigFile = $options['c'];
    }

    // Domain
    if (!isset($options['D']))
    {
        log_out("ERROR", basename(__FILE__), "You must provide a Domain");
        usage($defaultConfigFile);
    }
    $domain = $options['D'];

    // Tasklist
    if (!isset($options['T']))
    {
        log_out("ERROR", basename(__FILE__), "You must provide a TaskList");
        usage($defaultConfigFile);
    }
    $taskList = $options['T'];

    // Activity name
    if (!isset($options['A']))
    {
        log_out("ERROR", basename(__FILE__), "You must provide an Activity name");
        usage($defaultConfigFile);
    }
    $activityName = $options['A'];
    
    // Activity version
    if (!isset($options['V']))
    {
        log_out("ERROR", basename(__FILE__), "You must provide an Activity version");
        usage($defaultConfigFile);
    }
    $activityVersion = $options['V'];

    // Check config file
    if (!($config = json_decode(file_get_contents($defaultConfigFile))))
    {
        log_out(
            "FATAL", 
            basename(__FILE__), 
            "Configuration file '$defaultConfigFile' invalid!"
        );
        exit(1);
    }

    # Validate against JSON Schemas
    if (($err = validate_json($config, "config/mainConfig.json")))
        exit("JSON main configuration file invalid! Details:\n".$err);

    return $config;
}



// Get config file
$defaultConfigFile = realpath(dirname(__FILE__)) . "/../config/cloudTranscodeConfig.json";
// Check input parameters
$config = check_input_parameters($defaultConfigFile);

# Load AWS credentials in env vars if any
load_aws_creds($config);
# Init AWS connection
init_aws();

log_out(
    "INFO", 
    basename(__FILE__), 
	"Domain: '$domain'"
);
log_out("INFO", basename(__FILE__), $config->{'clients'});

// Instantiate AcivityPoller
try {
    $activityPoller = new ActivityPoller($config);
} 
catch (CTException $e) {
    log_out(
        "FATAL", 
        basename(__FILE__), 
        $e->getMessage()
    );
    exit(1);
}

log_out(
    "INFO", 
    basename(__FILE__), 
    "Starting activity tasks polling"
);
// Start polling loop
while (42)
    $activityPoller->poll_for_activities();
