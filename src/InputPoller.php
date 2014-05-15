<?php

/**
 * This script listen to AWS SQS queues for incoming input commands
 * It opens the JSON input and starts a execute a callback correcponding to the command
 */

require __DIR__ . '/utils/Utils.php';

class InputPoller
{
    private $debug;
    private $config;
    private $domain;
    private $commandsMap;
    private $CTCom;
    
    function __construct($config)
    {
        global $debug;

        $this->debug  = $debug;
        $this->config = $config;
        $this->domain = $config->{'cloudTranscode'}->{'workflow'}->{'domain'};

        // Init domain. see: Utils.php
        if (!init_domain($this->domain))
            throw new Exception("Unable to init the domain !\n");
        
        // Init workflow. see: Utils.php
        if (!init_workflow($this->config->{'cloudTranscode'}->{'workflow'}))
            throw new Exception("Unable to init the workflow !\n");
        
        // Init eventMap. Maps events with callback functions.
        $this->typesMap = [
            'START_JOB'            => 'start_job',
            'CANCEL_JOB'           => 'cancel_job',
            'CANCEL_ACTIVITY'      => 'cancel_activity',
            'GET_JOB_LIST'         => 'get_job_list',
            'GET_ACTIVITY_LIST'    => 'get_activity_list',
            'GET_JOB_STATUS'       => 'get_job_status',
            'GET_ACTIVITY_STATUS'  => 'get_activity_status',
        ];

        // Instantiating CloudTranscode Communication SDK.
        // See: https://github.com/sportarchive/CloudTranscodeComSDK
        $this->CTCom = new SA\CTComSDK(false, false, false, $this->debug);
    }

    // Poll from the 'input' SQS queue of all clients
    // If a msg is received, we pass it to 'handle_input' for processing
    public function poll_SQS_queues()
    {
        // For all clients in config files
        // We poll from queues
        foreach ($this->config->{'clients'} as $client)
        {
            // Long Polling messages from client input queue
            $queue = $client->{'queues'}->{'input'};
            try {
                if ($msg = $this->CTCom->receive_message(false, $queue, 1))
                {
                    // Message polled. We delete it from SQS
                    $this->CTCom->delete_message(false, $queue, $msg);

                    if (!($decoded = json_decode($msg['Body'])))
                        log_out(
                            "ERROR", 
                            basename(__FILE__), 
                            "JSON data invalid in queue: '$queue'");
                    else                    
                        $this->handle_message($decoded, $client);
                }
            } catch (Exception $e) {
                log_out(
                    "ERROR", 
                    basename(__FILE__), 
                    $e->getMessage());
            }
        }
    }

    // Receive an input, check if we know the command and exec the callback
    public function handle_message($message, $client)
    {
        $this->validate_message($message);

        // Do we know this input ?
        if (!isset($this->typesMap[$message->{"type"}]))
        {
            log_out(
                "ERROR", 
                basename(__FILE__), 
                "Command '" . $message->{"type"} . "' is unknown! Ignoring ..."
            );
            return;
        }

        log_out(
            "INFO", 
            basename(__FILE__), 
            "Received message '" . $message->{"type"}  . "'"
        );
        if ($this->debug)
            log_out(
                "INFO", 
                basename(__FILE__), 
                "Details:\n" . json_encode($message, JSON_PRETTY_PRINT)
            );

        // We call the callback function that handles this message  
        $this->{$this->typesMap[$message->{"type"}]}($message, $client);
    }

    
    /** 
     * CALLBACKS
     */

    // Start a new workflow in SWF to initiate new transcoding job
    private function start_job($message, $client)
    {
        // SWF client
        global $swf;

        if ($this->debug)
            log_out(
                "DEBUG",
                basename(__FILE__),
                "Starting new workflow!"
            );

        // Workflow info
        $workflowType = array(
            "name"    => $this->config->{'cloudTranscode'}->{'workflow'}->{"name"},
            "version" => $this->config->{'cloudTranscode'}->{'workflow'}->{"version"});
        
        // Append client info to message data
        $message->{"client"} = $client;

        // Request start SWF workflow
        try {
            $workflowRunId = $swf->startWorkflowExecution(array(
                    "domain"       => $this->config->{'cloudTranscode'}->{'workflow'}->{'domain'},
                    "workflowId"   => uniqid('', true),
                    "workflowType" => $workflowType,
                    "taskList"     => array("name" => $this->config->{'cloudTranscode'}->{'workflow'}->{'decisionTaskList'}),
                    "input"        => json_encode($message)
                ));
        } catch (\Aws\Swf\Exception\SwfException $e) {
            log_out(
                "ERROR",
                basename(__FILE__),
                "Unable to start workflow!"
                . $e->getMessage());
        }
    }

    /**
     * UTILS
     */ 

    private function validate_message($message)
    {
        if (!isset($message) || 
            !isset($message->{"time"})   || $message->{"time"} == "" || 
            !isset($message->{"job_id"}) || $message->{"job_id"} == "" || 
            !isset($message->{"type"})   || $message->{"type"} == "" || 
            !isset($message->{"data"})   || $message->{"data"} == "")
            throw new Exception("'time', 'type', 'job_id' or 'data' fields missing in JSON message file!");
    }
}


/**
 * INPUT POLLER START
 */

$input_file = "";
$debug = false;

function usage($defaultConfigFile)
{
    echo("Usage: php ". basename(__FILE__) . " [-h] [-c <path to JSON config file>]\n");
    echo("-h: Print this help\n");
    echo("-c <file path>: Optional parameter to override the default configuration file: '$defaultConfigFile'.\n");
    exit(0);
}

function check_input_parameters(&$defaultConfigFile)
{
    global $input_file;
    global $debug;
    global $argv;
    
    if (count($argv) == 1)
        return;

    // Handle input parameters
    if (!($options = getopt("c:hd")))
        usage($defaultConfigFile);
    
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
if (!($config = json_decode(file_get_contents($defaultConfigFile))))
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
    "Domain: '" . $config->{'cloudTranscode'}->{'workflow'}->{'domain'} . "'"
);
log_out(
    "INFO", 
    basename(__FILE__), 
    "TaskList: '" . $config->{'cloudTranscode'}->{'workflow'}->{'decisionTaskList'} . "'"
);
log_out("INFO", basename(__FILE__), $config->{'clients'});

// Create InputPoller object
try {
    $inputPoller = new InputPoller($config);
} 
catch (Exception $e) {
    log_out(
        "FATAL", 
        basename(__FILE__), 
        $e->getMessage()
    );
    exit(1);
}

// Start polling loop to get incoming commands from SQS input queues
while (42)
    $inputPoller->poll_SQS_queues();
