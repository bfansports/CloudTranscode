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
$region = getenv("AWS_DEFAULT_REGION");
$key    = getenv("AWS_ACCESS_KEY_ID");
$secret = getenv("AWS_SECRET_KEY");

function usage()
{
    echo("Usage: php ". basename(__FILE__) . " -c configFile [-h] -k <key> -s <secret> -r <region>\n");
    echo("-h: Print this help\n");
    echo("-d: Debug mode\n");
    echo("-c: configFile\n");
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
    global $clientInfo;
    global $argv;
    
    // Handle input parameters
    if (count($argv) == 1)
        $options = array();
    else if (!($options = getopt("c:k::s::r::hd")))
        usage();
    if (isset($options['h']))
        usage();
    
    if (isset($options['d']))
        $debug = true;

    if (isset($options['c']))
    {
        $clientConfFile = $options['c'];
        if (!file_exists($clientConfFile))
            throw new Exception("The client config file is not valid!");
        if (!($clientInfo = file_get_contents($clientConfFile)))
            throw new Exception("Unable to read the file");
    }
    else
        throw new Exception("Please provide the client config file!");
  
    if (isset($options['k']))
        $key = $options['k'];
    else 
        $key = getenv("AWS_ACCESS_KEY_ID");
    
    if (isset($options['s']))
        $secret = $options['s'];
    else 
        $secret = getenv("AWS_SECRET_KEY");

    if (isset($options['r']))
        $region = $options['r'];
    else 
        $region = getenv("AWS_DEFAULT_REGION");
    if (!$region)
        throw new Exception("Please provide your AWS region as parameter or using AWS_DEFAULT_REGION env var !");
}

check_input_parameters();

// Instanciate ComSDK to communicate with the stack
try {
    $CTCom = new SA\CTComSDK($key, $secret, $region, $debug);
} catch (Exception $e) {
    exit($e->getMessage());
  }

// You must JSON decode it
$clientInfoDecoded = json_decode($clientInfo);

// Keep polling for output messages!
while (42)
    poll_SQS_queues($CTCom, $clientInfoDecoded);
