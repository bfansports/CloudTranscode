<?php


// Composer for loading dependices: http://getcomposer.org/
require __DIR__ . "/../../vendor/autoload.php";

// Amazon library
use Aws\Common\Aws;
use Aws\Swf\Exception;

# AWS variables
$aws;
$swf;

// File types 
define('VIDEO', 'VIDEO');
define('AUDIO', 'AUDIO');
define('IMAGE', 'IMAGE');
define('DOC'  , 'DOC');
define('THUMB', 'THUMB');


// Log to STDOUT
function log_out($type, $source, $message, $workflowId = 0)
{
    global $argv;
    
    $log = [
        "time"    => time(),
        "source"  => $source,
        "type"    => $type,
        "message" => $message
    ];
    
    if ($workflowId)
        $log["workflowId"] = $workflowId;
    
    if (!openlog ($argv[0], LOG_CONS|LOG_PID, LOG_LOCAL1))
        throw new CTException("Unable to connect to Syslog!", 
            "OPENLOG_ERROR");
    
    switch ($type)
    {
    case "INFO":
        $priority = LOG_INFO;
        break;
    case "ERROR":
        $priority = LOG_ERR;
        break;
    case "FATAL":
        $priority = LOG_ALERT;
        break;
    case "WARNING":
        $priority = LOG_WARNING;
        break;
    case "DEBUG":
        $priority = LOG_DEBUG;
        break;
    default:
        throw new CTException("Unable to connect to Syslog!", 
            "LOG_TYPE_ERROR");
    }
    
    $out = json_encode($log);
    
    if (!is_string($log['message']))
        $log['message'] = json_encode($log['message']);
    $toPrint = $log['time'] . " [" . $log['type'] . "] [" . $log['source'] . "] ";
    if ($workflowId)
        $toPrint .= "[$workflowId] ";
    $toPrint .= $log['message'] . "\n";
            
    echo($toPrint);
    
    syslog($priority, $out);
}

// Check if directory is empty
function is_dir_empty($dir) {
    if (!is_readable($dir)) 
        return false; 
    $handle = opendir($dir);
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            return false;
        }
    }
    return true;
}

// Validate main configuration file against JSONM schemas
function validate_json($decoded, $schemas)
{
    $retriever = new JsonSchema\Uri\UriRetriever;
    $schema = $retriever->retrieve('file://' . __DIR__ . "/../../json_schemas/$schemas");

    $refResolver = new JsonSchema\RefResolver($retriever);
    $refResolver->resolve($schema, 'file://' . __DIR__ . "/../../json_schemas/");

    $validator = new JsonSchema\Validator();
    $validator->check($decoded, $schema);

    if ($validator->isValid())
        return false;
    
    $details = "";
    foreach ($validator->getErrors() as $error) {
        $details .= sprintf("[%s] %s\n", $error['property'], $error['message']);
    }
    
    return $details;
}

// Load AWS vars from config file to env vars
function load_aws_creds($config)
{
    if (!$config)
        throw new \Exception("No config data provided to load AWS creds from!");
    
    if (!isset($config->{"aws"})) {
        print("No AWS creds in config file\n");
        return;
    }
    
    if (isset($config->{"aws"}->{"region"}) &&
        $config->{"aws"}->{"region"} != "") {
        putenv("AWS_DEFAULT_REGION=".$config->{"aws"}->{"region"});
    }
    if (isset($config->{"aws"}->{"key"}) &&
        $config->{"aws"}->{"key"} != "")
    {
        putenv("AWS_ACCESS_KEY_ID=".$config->{"aws"}->{"key"});
        putenv("AWS_ACCESS_KEY=".$config->{"aws"}->{"key"});
    }
    if (isset($config->{"aws"}->{"secret"}) &&
        $config->{"aws"}->{"secret"} != "")
    {
        putenv("AWS_SECRET_ACCESS_KEY=".$config->{"aws"}->{"secret"});
        putenv("AWS_SECRET_KEY=".$config->{"aws"}->{"secret"});
    }
}

function init_aws()
{
    global $aws;
    global $swf; 
    
    # Check if preper env vars are setup
    if (!($region = getenv("AWS_DEFAULT_REGION")))
        exit("Set 'AWS_DEFAULT_REGION' environment variable!");
    
    // Create AWS SDK instance
    $aws = Aws::factory(array(
            'region' => $region,
        ));

    // SWF client
    $swf = $aws->get('Swf');
}

// Custom exception class for Cloud Transcode
class CTException extends \Exception
{
    public $ref;
    
    // Redefine the exception so message isn't optional
    public function __construct($message, $ref = "", $code = 0, \Exception $previous = null) {
        $this->ref = $ref;
    
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
  
    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->ref}]: {$this->message}\n";
    }
}

