<?php

require 'gridXUtils.php';

class GridXWorkflowStarter
{
	private $domain;
	private $taskList;

	private $workflowType;
	private $workflowRunId;

	function __construct($domainName, $taskList, $params)
	{
		$this->domain   = $domainName;
		$this->taskList = $taskList;

		if (!isset($params["name"]) || !$params["name"]) {
			echo "[ERROR] Can't register workflow: 'name' is not provided or empty !\n";
			return false;
		}

		if (!isset($params["version"]) || !$params["version"]) {
			echo "[ERROR] Can't register workflow: 'version' is not provided or empty !\n";
			return false;
		}

		// Init domain
		if (!init_domain($domainName))
			throw new Exception("Unable to init the domain !\n");

		// Init workflow
		if (!$this->init_workflow($params))
			throw new Exception("Unable to init the worklow !\n");
	}

	private function init_workflow($params)
	{
		global $swf;

		

		// Save WF info
		$this->workflowType = array(
			"name"    => $params["name"],
			"version" => $params["version"]);

		// Get existing workflows
		try {
			$swf->describeWorkflowType(array(
				"domain"       => $this->domain,
				"workflowType" => $this->workflowType
				));
			return true;
		} catch (Aws\Swf\Exception\UnknownResourceException $e) {
			echo "Workflow doesn't exists. Creating it ...\n";
		} catch (Exception $e) {
			echo "Unable to describe the workflow ! " . $e->getMessage() . "\n";
			return false;
		}

		// If not registered, we register the WF
		try {
			print_r($params);
			$swf->registerWorkflowType($params);
			return true;
		} catch (Exception $e) {
			echo "Unable to register new workflow ! " . $e->getMessage() . "\n";
			return false;
		}
	}

	// Launch workflow execution
	public function start_execution($input)
	{
		global $swf;

		try {
			$this->workflowRunId = $swf->startWorkflowExecution(array(
				"domain"       => $this->domain,
				"workflowId"   => uniqid(),
				"workflowType" => $this->workflowType,
				"taskList"     => $this->taskList,
				"input"        => $input
				));
			return true;
		} catch (Exception $e) {
			echo "Unable to start workflow execution  ! " . $e->getMessage() . "\n";
			return false;
		}
	}
}



/**
 * TEST PROGRAM
 */

$domainName = "SA_TEST2";
$taskList = array("name" => "GridXTranscodingTaskList");

try {

	// Create workflow object. 
	// Will register the workflow if not existing
	$workflowStarter = new GridXWorkflowStarter($domainName, $taskList, 
		array(
			"domain"      => $domainName,
			"name"        => "gridx_basic_workflow",
			"version"     => "v1",
			"description" => "GridX Basic transcoding workflow",
			"defaultTaskStartToCloseTimeout"      => 3600, # Tasks timeout
			"defaultExecutionStartToCloseTimeout" => 24 * 3600, # WF timeout
			"defaultChildPolicy" => "TERMINATE"
			));

} catch (Exception $e) {
	echo "Unable to create GridXWorkflowStarter ! " . $e->getMessage() . "\n";
	exit (1);
}

// Start the workflow with input.json input data for transcoding
// https://app.zencoder.com/docs/api/encoding
$workflowStarter->start_execution(file_get_contents(dirname(__FILE__) . "/config/input.json"));
