<?php

// Composer for loading dependices: http://getcomposer.org/
require 'vendor/autoload.php';

// Amazon library
use Aws\Common\Aws;
use Aws\Swf\Exception;

$root = realpath(dirname(__FILE__));
// Create AWS SDK instance
$aws = Aws::factory("$root/config/config.json");
// SWF client
$swf = $aws->get('Swf');

// Defines
define('DOMAIN', 'GridX');
define('TASK_LIST', 'GridXTranscodingTaskList');
// TRanscoding workflow
define('TRANSCODE_WORKFLOW', 'gridx_basic_workflow');
define('TRANSCODE_WORKFLOW_VERS', 'v1');
define('TRANSCODE_WORKFLOW_DESC', 'GridX Basic transcoding workflow');

// Activities handled by the decider and activityPoller
// !! IMPORTANT: Keep execution order !!
$activities = array(
	[
	"name"        => "ValidateInputAndAsset", 
	"version"     => "v1",
	"description" => "Check input command and asset to be transcoded.",
	"file"        => "/activities/gridXValidateInputAndAssetActivity.php",
	"class"       => "GridXValidateInputAndAssetActivity"
	],
	[
	"name"    	  => "TranscodeAsset",
	"version" 	  => "v1",
	"description" => "Perform transcoding on the asset and generate output file(s)",
	"file"    	  => "/activities/gridXTranscodeAssetActivity.php",
	"class"   	  => "GridXTranscodeAssetActivity"
	],
	[
	"name"    	  => "ValidateTrancodedAsset",
	"version" 	  => "v1",
	"description" => "Make sure the transcoding has been performed properly",
	"file"    	  => "/activities/gridXValidateTrancodedAssetActivity.php",
	"class"   	  => "GridXValidateTranscodedAssetActivity"
	]);

// Initialize the domain. Create it if needed
function init_domain($domainName)
{
	global $swf;

	// Get existing domain list
	try
	{
		$swf->describeDomain(array("name" => $domainName));
		return true;
	} catch (Aws\Swf\Exception\UnknownResourceException $e) {
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
			"description" => "GridX domain",
			"workflowExecutionRetentionPeriodInDays" => 1
			));
		return true;
	} catch (Exception $e) {
		echo 'Unable to create the domain !' . $e->getMessage() . "\n";
		return false;
	}
}

// Log to STDOUT
function log_out($type, $source, $message)
{
	echo "[$type] [$source] $message\n";
}