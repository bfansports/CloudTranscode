<?php

require __DIR__ . "/../vendor/autoload.php";


function start_job($args)
{
    global $CTCom;
    global $clientInfoDecoded;

    if (count($args) != 2)
    {
        print("[ERROR] Invalid args! Please provide a filename after the command!\n");
        return;
    }
    if (!file_exists($args[1]))
    {
        print("[ERROR] The file provided doesn't exists!\n");
        return;
    }
    if (!($content = file_get_contents($args[1])))
    {
        print("[ERROR] Unable to read file '$args[1]'!\n");
        return;
    }
    
    try {
        print("[INFO] Starting a new job!\n");
        $CTCom->start_job($clientInfoDecoded, $content);
    }
    catch (Exception $e) {
        print("[ERROR] " . $e->getMessage() . "\n");
    }
}


/**
 * CLIENT COMMANDER
 */

$help = <<<EOF

Use the following commands to send messages to the stack.
You can create a new job for example.

Commands:
start_job <filepath>: Start a new job. Pass a JSON file containing the instruction (see: input_samples folder)
[more commands to come]


EOF;

function usage()
{
    global $help;
    
    echo("Usage: php ". basename(__FILE__) . " [-h] -k <key> -s <secret> -r <region>\n");
    echo("-h: Print this help\n");
    echo("-d: Debug mode\n");
    echo("-k <AWS key>: \n");
    echo("-s <AWS secret>: \n");
    echo("-r <AWS region>: \n\n");
    echo($help);
    exit(0);
}

function check_input_parameters()
{
    global $region;
    global $secret;
    global $key;
    global $debug;
    
    // Handle input parameters
    if (!($options = getopt("k:s:r:hd")))
        usage();
    if (isset($options['h']))
        usage();
    
    if (isset($options['d']))
        $debug = true;
  
    if (isset($options['k']))
        $key = $options['k'];
    if (!$key)
        throw new Exception("Please provide your AWS key!");
    
    if (isset($options['s']))
        $secret = $options['s'];
    if (!$secret)
        throw new Exception("Please provide your AWS secret!");

    if (isset($options['r']))
        $region = $options['r'];
    if (!$region)
        throw new Exception("Please provide your AWS region!");
}

check_input_parameters();

// Instanciate ComSDK to communicate with the stack
$CTCom = new SA\CTComSDK($key, $secret, $region, $debug);

// Example of the data you should provide to get identified
// The role and the queues should be created by the stack owner
// The owner should entitle the client by creating the proper roles and queues
// Use AWS IAM roles to do so
// As a client you MUST keep this info safely and provide it when you COM with the stack
$clientInfo = <<<EOF
{
    "name": "NicoMencie",
    "externalId": "CT-NicoMencie",
    "role": "arn:aws:iam::686112866222:role/CT-NicoMencie",
    "queues": {
       "input": "https://sqs.us-east-1.amazonaws.com/686112866222/CT-NicoMencie-InputQueue",
       "output": "https://sqs.us-east-1.amazonaws.com/686112866222/CT-NicoMencie-OutputQueue"
       }
    }
EOF;
// You must JSON decode it
$clientInfoDecoded = json_decode($clientInfo);

$commandMap = [
    "start_job" => "start_job",
];

// Look for input commands
print($help);
while (42)
{
    $line = readline("Command [enter]: ");
    if (!$line)
        continue;
    readline_add_history($line);

    $args = explode(" ", $line);
    if (!isset($commandMap[$args[0]]))
        print "[ERROR] Command not found!\n";
    else 
        $commandMap[$args[0]]($args);
}