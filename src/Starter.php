<?php

/**
 * This script listen to AWS SQS for incoming input commands
 * It opens the JSON input and starts a new Workflow with it
 */

require 'Utils.php';

$input_file = "";

function usage()
{
    echo("Usage: php ". basename(__FILE__) . " [-h] -c <path to JSON input file>\n");
    echo("-h: Print this help\n");
    echo("-c <file path>: Specify a JSON input file to simulate input from SQS. Useful for testing Input JSON files and performing tests !\n");
    exit(0);
}

function check_input_parameters()
{
    global $input_file;
    
    // Handle input parameters
    $options = getopt("c:h");
    
    if (isset($options['h']))
        usage();
    
    if (isset($options['c']))
    {
        $input_file = $options['c'];
        if (!($input = file_get_contents($input_file)))
        {
            log_out("ERROR", basename(__FILE__), "Invalid JSON file! Falling back on SQS queue ...");
            return false;
        }
        else
            return $input;
    }

    return false;
}

function start_workflow($config, $input)
{
    // SWF client
    global $swf;

    // Workflow info
    $workflowType = array(
        "name"    => $config['cloudTranscode']['workflow']["name"],
        "version" => $config['cloudTranscode']['workflow']["version"]);

    log_out("INFO", basename(__FILE__), "Requesting new workflow to process input ...");
    $workflowRunId = $swf->startWorkflowExecution(array(
            "domain"       => $config['cloudTranscode']['workflow']['domain'],
            "workflowId"   => uniqid(),
            "workflowType" => $workflowType,
            "taskList"     => array("name" => $config['cloudTranscode']['workflow']['decisionTaskList']),
            "input"        => $input
        ));
}

function init_sqs_queues($config)
{
    // SQS client
    global $sqs;
    
    $inputQueues = array();
    foreach ($config['clients'] as $client)
    {
        $queueName = $client['SQS']['input'];
        try {
            $queueUrl = $sqs->getQueueUrl(['QueueName' => $queueName])['QueueUrl'];
        } catch (\Aws\Sqs\Exception\SqsException $e) {
            log_out("ERROR", basename(__FILE__), "Can't get SQS queue '$queueName' ! Creating queue '$queueName' ...");
            try {
                $newQueue = $sqs->createQueue(['QueueName' => $queueName]);
                $queueUrl = $newQueue['QueueUrl'];
            } catch (\Aws\Sqs\Exception\SqsException $e) {
                log_out("ERROR", basename(__FILE__), "Unable to create the new queue '$queueName' ... skipping");
                continue;
            }
        }
  
        array_push($inputQueues, $queueUrl);
    }

    return $inputQueues;
}

function poll_sqs_messages($config, $inputQueues)
{
    // SQS client
    global $sqs;
    
    log_out("INFO", basename(__FILE__), "Polling ...");

    foreach ($inputQueues as $queueUrl)
    {                
        // Check for new msg, poll for 5 sec, then retry
        $result = $sqs->receiveMessage(array(
                'QueueUrl'        => $queueUrl,
                'WaitTimeSeconds' => 5,
            ));

        $messages = $result->get('Messages');
        if ($messages) 
        {
            // Check incoming message
            foreach ($messages as $msg) 
            {
                try {	
                    log_out("INFO", basename(__FILE__), "New SQS input msg. Checking JSON format ...");		
                    if (!json_decode($msg['Body']))
                        log_out("ERROR", basename(__FILE__), "Input received from SQS queue has an invalid JSON format ! Discarding ...");
                    else 
                        start_workflow($config, $msg['Body']);
                } catch (Exception $e) {
                    log_out("ERROR", basename(__FILE__), "Unable to start workflow execution  ! " . $e->getMessage());
                }
        
                // Delete msg from SQS queue
                log_out("INFO", basename(__FILE__), "Deleting msg from SQS queue ...");
                $sqs->deleteMessage(array(
                        'QueueUrl'        => $queueUrl,
                        'ReceiptHandle'   => $msg['ReceiptHandle']));

                // XXX 
                // Send message back in SQS to tell the WorkflowID for tracking !
            }
        }
    }
}


// Check input parameters
$input = check_input_parameters();
print_r($input);

// Get config file
$config = json_decode(file_get_contents(dirname(__FILE__) . "/../config/cloudTranscodeConfig.json"), true);
log_out("INFO", basename(__FILE__), "Domain: '" . $config['cloudTranscode']['workflow']['domain'] . "'");
log_out("INFO", basename(__FILE__), "TaskList: '" . $config['cloudTranscode']['workflow']['decisionTaskList'] . "'");
log_out("INFO", basename(__FILE__), "Clients: ");
print_r($config['clients']);

/** FROM Utils.php **/
if (!init_domain($config['cloudTranscode']['workflow']['domain']))
    throw new Exception("[ERROR] Unable to init the domain !\n");
if (!init_workflow($config['cloudTranscode']['workflow']))
    throw new Exception("[ERROR] Unable to init the workflow !\n");

// Get SQS queue URL - "input" queue
if (!$input)
{
    $inputQueues = init_sqs_queues($config);
    log_out("INFO", basename(__FILE__), "Starting SQS message polling from all queues");
    while (1)
        poll_sqs_messages($config, $inputQueues);
}
else
{
    while (1)
    {
        $line = readline("Submit input [enter]");

        // Re-read file in case of change ! :)
        $input = file_get_contents($input_file);
        start_workflow($config, $input);
    }
}


