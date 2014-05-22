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
    global $clientInfo;
    
    // Handle input parameters
    if (!($options = getopt("c:k:s:r:hd")))
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

try {
    check_input_parameters();
} 
catch (Exception $e) {
    print "[ERROR] " . $e->getMessage() . "\n";
    exit(2);
}

if (!($clientInfoDecoded = json_decode($clientInfo)))
{
    print "[ERROR] invalid JSON format in config file!\n";
    exit(2);
}

// Instanciate ComSDK to communicate with the stack
$CTCom = new SA\CTComSDK($key, $secret, $region, $debug);

// Commands mapping
$commandMap = [
    "start_job" => "start_job",
];

// Look for input commands
print($help);
while (42)
{
    // Prompt (<3 php)
    $line = readline("Command [enter]: ");
    if (!$line)
        continue;
    readline_add_history($line);

    // Process user input
    $args = explode(" ", $line);
    if (!isset($commandMap[$args[0]]))
        print "[ERROR] Command not found!\n";
    else 
        $commandMap[$args[0]]($args);
}