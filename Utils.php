<?php

$root = realpath(dirname(__FILE__));

// Composer for loading dependices: http://getcomposer.org/
require "$root/vendor/autoload.php";

// Amazon library
use Aws\Common\Aws;
use Aws\Swf\Exception;

// Create AWS SDK instance
$aws = Aws::factory("$root/config/awsConfig.json");
// SWF client
$swf = $aws->get('Swf');
// SQS Client
$sqs = $aws->get('Sqs');

// Log to STDOUT
function log_out($type, $source, $message, $workflowId = 0)
{
    $log = time() . " [$type] [$source]";
    if ($workflowId)
        $log .= " [$workflowId]";

	echo "$log $message\n";
}

// Initialize the domain. Create it if needed
function init_domain($domainName)
{
	global $swf;

	// Get existing domain list
	try
    {
        $swf->describeDomain(array("name" => $domainName));
        return true;
    } catch (\Aws\Swf\Exception\UnknownResourceException $e) {
		log_out("INFO", basename(__FILE__), "Domain doesn't exists. Creating it ...");
	} catch (Exception $e) {
		log_out("ERROR", basename(__FILE__), "Unable to get domain list ! " . $e->getMessage());
		return false;
	}

	// Create domain if not existing
	try 
    {
        $swf->registerDomain(array(
                "name"        => $domainName,
                "description" => "Cloud Transcode Domain",
                "workflowExecutionRetentionPeriodInDays" => 1
			));
        return true;
    } catch (Exception $e) {
		log_out("ERROR", basename(__FILE__), "Unable to create the domain !" . $e->getMessage());
		return false;
	}
}

// Init the workflow. Create one if needed
function init_workflow($params)
{
	global $swf;

	// Save WF info
	$workflowType = array(
		"name"    => $params["name"],
		"version" => $params["version"]);

    // Check if a workflow of this type already exists
	try {
		$swf->describeWorkflowType([
                "domain"       => $params["domain"],
                "workflowType" => $workflowType
            ]);
		return true;
	} catch (\Aws\Swf\Exception\UnknownResourceException $e) {
		log_out("ERROR", basename(__FILE__), "Workflow doesn't exists. Creating it ...");
	} catch (Exception $e) {
		log_out("ERROR", basename(__FILE__), "Unable to describe the workflow ! " . $e->getMessage());
		return false;
	}

    // If not registered, we register this type of workflow
	try {
		$swf->registerWorkflowType($params);
		return true;
	} catch (Exception $e) {
		log_out("ERROR", basename(__FILE__), "Unable to register new workflow ! " . $e->getMessage());
		return false;
	}
}


