<?php

require __DIR__ . "/../vendor/autoload.php";

function poll_SQS_queues($CTCom, $clientInfoEncoded)
{
    $queue = $clientInfoEncoded->{'queues'}->{'output'};
    try {
        // Will poll for 2 seconds
        if ($msg = $CTCom->receive_message($queue, 10))
        {
            if (!($decoded = json_decode($msg['Body'])))
                throw new Exception("JSON output data is invalid!");
            else                    
                handle_output($decoded);
                    
            // Message polled. We delete it from SQS
            $CTCom->delete_message($queue, $msg);
        }
    } catch (Exception $e) {
        print("[ERROR] " . $e->getMessage() . "\n");
    }
}

function handle_output($output)
{
    global $debug;
    
    if ($debug)
        print_r($output);
    
    if (isset($output->{'data'}->{'activity'}))
        print($output->{'time'} . " " . $output->{'type'}."(" 
            . $output->{'data'}->{'activity'}->{'activityId'}  . ")\n");
    else
        print($output->{'time'} . " " . $output->{'type'}."(" 
            . $output->{'data'}->{'workflow'}->{'workflowId'}  . ")\n");
}

/**
 * CLIENT POLLER START
 */

$debug  = false;
$region = getenv("AWS_REGION");
$key    = getenv("AWS_ACCESS_KEY_ID");
$secret = getenv("AWS_SECRET_KEY");

function usage()
{
    echo("Usage: php ". basename(__FILE__) . " [-h] -k <key> -s <secret> -r <region>\n");
    echo("-h: Print this help\n");
    echo("-d: Debug mode\n");
    echo("-k <AWS key>: \n");
    echo("-s <AWS secret>: \n");
    echo("-r <AWS region>: \n");
    exit(0);
}

function check_input_parameters()
{
    global $region;
    global $secret;
    global $key;
    global $debug;
    global $argv;
    
    // Handle input parameters
    if (count($argv) == 1)
        $options = array();
    else if (!($options = getopt("k::s::r::hd")))
        usage();
    if (isset($options['h']))
        usage();
    
    if (isset($options['d']))
        $debug = true;
  
    if (isset($options['k']))
        $key = $options['k'];
    else 
        $key = getenv("AWS_ACCESS_KEY_ID");
    if (!$key)
        throw new Exception("Please provide your AWS key!");
    
    if (isset($options['s']))
        $secret = $options['s'];
     else 
        $secret = getenv("AWS_SECRET_KEY");
    if (!$secret)
        throw new Exception("Please provide your AWS secret!");

    if (isset($options['r']))
        $region = $options['r'];
     else 
        $region = getenv("AWS_REGION");
    if (!$region)
        throw new Exception("Please provide your AWS region!");
}

check_input_parameters();

// Instanciate ComSDK to communicate with the stack
try {
    $CTCom = new SA\CTComSDK($key, $secret, $region, $debug);
} catch (Exception $e) {
    exit($e->getMessage());
}

// Example of the data you should provide to get identified
// The role and the queues should be created by the stack owner
// The owner must entitle the client by creating the proper roles and queues
// Create a new IAM roles to do so and a trust relationship with the client (this)
// As a client you MUST keep this info safely and provide it when you COM with the stack
$clientInfo = <<<EOF
{
    "name": "RsInTheCloud",
    "queues": {
       "input": "https://sqs.us-east-1.amazonaws.com/686112866222/CT-RSInTheCloud-InputQueue",
       "output": "https://sqs.us-east-1.amazonaws.com/686112866222/CT-RSInTheCloud-OutputQueue"
       }
    }
EOF;

// You must JSON decode it
$clientInfoDecoded = json_decode($clientInfo);

// Keep polling for output messages!
while (42)
    poll_SQS_queues($CTCom, $clientInfoDecoded);
