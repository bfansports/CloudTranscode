<?php

// Composer for loading dependices: http://getcomposer.org/
require "./vendor/autoload.php";

// Amazon library
use Aws\Common\Aws;
use Aws\Swf\Exception;

$root = realpath(dirname(__FILE__));
// Create AWS SDK instance
$aws = Aws::factory("$root/config/awsConfig.json");
// SWF client
$swf = $aws->get('Swf');
$sqs = $aws->get('Sqs');

// Defines
define('DOMAIN', 'CloudTranscode');
define('TASK_LIST', 'CloudTranscodeTaskList');
// TRanscoding workflow
define('TRANSCODE_WORKFLOW', 'basic_workflow');
define('TRANSCODE_WORKFLOW_VERS', 'v1');
define('TRANSCODE_WORKFLOW_DESC', 'Cloud Transcode Basic Workflow');

// Activities handled by the decider and activityPoller
// !! IMPORTANT: Keep execution order !!
$activities = array(
	[
	"name"        => "ValidateInputAndAsset", 
	"version"     => "v1",
	"description" => "Check input command and asset to be transcoded.",
	"file"        => "/activities/ValidateInputAndAssetActivity.php",
	"class"       => "ValidateInputAndAssetActivity"
	],
	[
	"name"    	  => "TranscodeAsset",
	"version" 	  => "v1",
	"description" => "Perform transcoding on the asset and generate output file(s)",
	"file"    	  => "/activities/TranscodeAssetActivity.php",
	"class"   	  => "TranscodeAssetActivity"
	],
	[
	"name"    	  => "ValidateTrancodedAsset",
	"version" 	  => "v1",
	"description" => "Make sure the transcoding has been performed properly",
	"file"    	  => "/activities/ValidateTrancodedAssetActivity.php",
	"class"   	  => "ValidateTranscodedAssetActivity"
	]);

// Log to STDOUT
function log_out($type, $source, $message)
{
	echo "[$type] [$source] $message\n";
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
		echo "Domain doesn't exists. Creating it ...\n";
	} catch (Exception $e) {
		echo "Unable to get domain list ! " . $e->getMessage() . "\n";
		return false;
	}

	// Create domain if not existing
	try 
	{
		$swf->registerDomain(array(
			"name" => $domainName,
			"description" => "Cloud Transcode Domain",
			"workflowExecutionRetentionPeriodInDays" => 1
			));
		return true;
	} catch (Exception $e) {
		echo 'Unable to create the domain !' . $e->getMessage() . "\n";
		return false;
	}
}

function init_workflow($params)
{
	global $swf;

	// Save WF info
	$workflowType = array(
		"name"    => $params["name"],
		"version" => $params["version"]);

		// Get existing workflows
	try {
		$swf->describeWorkflowType(array(
			"domain"       => $params["domain"],
			"workflowType" => $workflowType
			));
		return true;
	} catch (\Aws\Swf\Exception\UnknownResourceException $e) {
		echo "Workflow doesn't exists. Creating it ...\n";
	} catch (Exception $e) {
		echo "Unable to describe the workflow ! " . $e->getMessage() . "\n";
		return false;
	}

		// If not registered, we register the WF
	try {
		$swf->registerWorkflowType($params);
		return true;
	} catch (Exception $e) {
		echo "Unable to register new workflow ! " . $e->getMessage() . "\n";
		return false;
	}
}
