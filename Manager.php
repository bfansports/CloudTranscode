<?php

require 'Utils.php';

/**
 * workflow manager helps you manipulate 
 */
class Manager
{
	function __construct()
	{
		global $aws;
	}	

	/**
	 * Start a Transcoding workflow. 
	 * @param  [String] $input  [JSON string containing input and output information]
	 * @param  [Array] $params [Can override default workflow parameters below.]
	 * @return [Boolean][true:false - failure returns 'false']
	 */
	public function startWorkflow($input, $params)
	{
		global $swf;

		try {
			# Tasks timeout
			$defaultTaskStartToCloseTimeout = $params["defaultTaskStartToCloseTimeout"] ?: 3600;
			$defaultExecutionStartToCloseTimeout = $params["defaultExecutionStartToCloseTimeout"] ?: 24 * 3600; # WF timeout
			$defaultChildPolicy = $params["defaultChildPolicy"] ?: "TERMINATE";

			// Create workflow object. 
			// Will register the workflow if not existing
			$workflowStarter = new WorkflowStarter(DOMAIN, TASK_LIST, 
				array(
					"domain"      => DOMAIN,
					"name"        => TRANSCODE_WORKFLOW,
					"version"     => TRANSCODE_WORKFLOW_VERS,
					"description" => TRANSCODE_WORKFLOW_DESC,
					"defaultTaskStartToCloseTimeout"      => $defaultTaskStartToCloseTimeout, 
					"defaultExecutionStartToCloseTimeout" => $defaultExecutionStartToCloseTimeout, 
					"defaultChildPolicy"                  => $defaultChildPolicy
					));

			// Default input file. Format inspired from:
			// https://app.zencoder.com/docs/api/encoding
			if (!$input)
				$input = file_get_contents(dirname(__FILE__) . "/config/input.json");

			// Start the workflow with input.json input data for transcoding
			$workflowStarter->start_execution($input);

		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to create WorkflowStarter ! " . $e->getMessage() . "\n");
			return false;
		}

		return true;
	}

	/**
	 * Gracefuly request workflow cancelation. Decider will decide how to terminate.
	 * @param  [Array] $workflowExecution [Associative array (http://docs.aws.amazon.com/amazonswf/latest/apireference/API_WorkflowExecution.html)]
	 * @return [Boolean][true:false - failure returns 'false']
	 */
	public function cancelWorkflow($workflowExecution)
	{
		global $swf;

		try {
			$swf->requestCancelWorkflowExecution(array(
				"domain"     => DOMAIN,
				"workflowId" => $workflowExecution["workflowId"]
				));
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Cannot cancel the workflow '" . $workflowExecution["workflowId"] . "' !");
			return false;
		}

		return true;
	}

	/**
	 * Terminate (kill) a workflow
	 * @param  [Array] $workflowExecution [Associative array (http://docs.aws.amazon.com/amazonswf/latest/apireference/API_WorkflowExecution.html)]
	 * @param  [String] $reason [Termination reason] [optional]
	 * @param  [String] $details [Termination details] [optional]
	 * @return [Boolean][true:false - failure returns 'false']
	 */
	public function terminateWorkflow($workflowExecution, $reason = "", $details = "")
	{
		global $swf;

		try {
			$swf->terminateWorkflowExecution(array(
				"domain"     => DOMAIN,
				"workflowId" => $workflowExecution["workflowId"],
				"reason"     => $reason,
				"details"    => $details
				));
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Cannot terminate the workflow '" . $workflowExecution["workflowId"] . "' ! Something is messed up ...");
			return false;
		}

		return true;
	}

	/**
	 * Get workflow type information
	 * http://docs.aws.amazon.com/amazonswf/latest/apireference/API_DescribeWorkflowType.html
	 * @param  [Array] $workflowExecution [Associative array (http://docs.aws.amazon.com/amazonswf/latest/apireference/API_WorkflowExecution.html)]
	 * @return [String:false][JSON String:false - failure return false]
	 */
	public function getWorkflowType($workflowExecution)
	{
		global $swf;

		try {
			$info = $swf->describeWorkflowType(array(
				"domain"       => DOMAIN,
				"workflowType" => {
					"name"    => TRANSCODE_WORKFLOW,
					"version" => TRANSCODE_WORKFLOW_VERS
				}
				));
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Cannot get workflow '" . $workflowExecution["workflowId"] . "' type information !");
			return false;
		}

		return $info;
	}

	/**
	 * Get workflow excution information
	 * http://docs.aws.amazon.com/amazonswf/latest/apireference/API_DescribeWorkflowExecution.html
	 * @param  [Array] $workflowExecution [Associative array (http://docs.aws.amazon.com/amazonswf/latest/apireference/API_WorkflowExecution.html)]
	 * @return [String:false][JSON String:false - failure return false]
	 */
	public function getWorkflowExecStatus($workflowExecution)
	{
		global $swf;

		try {
			$info = $swf->describeWorkflowExecution(array(
				"domain"     => DOMAIN,
				"execution" => $workflowExecution
				));
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Cannot get workflow '" . $workflowExecution["workflowId"] . "' execution status !");
			return false;
		}

		return $info;
	}

	/**
	 * Get workflow execution history containing all executed task and their statuses
	 * @param  [Array] $workflowExecution [Associative array (http://docs.aws.amazon.com/amazonswf/latest/apireference/API_WorkflowExecution.html)]
	 * @return [String:false][JSON String:false - failure return false]
	 */
	public function getWorkflowExcutionHistory($workflowExecution)
	{
		global $swf;

		try {
			$info = $swf->getWorkflowExecutionHistory(array(
				"domain" => DOMAIN,
				"execution" => $workflowExecution
				));
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to get workflow '" . $workflowExecution["workflowId"] . "' execution history !");
			return false;
		}

		return $info;
	}

	/**
	 * Cancel an activity
	 * @param  [String] $taskToken  [taskToken needed to identify the task]
	 * @param  [String] $activityId [description]
	 * @return [Boolean]             [description]
	 */
	public function cancelActivity($taskToken, $activityId)
	{
		global $swf;

		try {
			$info = $swf->respondDecisionTaskCompleted(array(
				"taskToken" => $taskToken,
				"decision"  => array(
					"decisionType" => "RequestCancelActivityTask",
					"requestCancelActivityTaskDecisionAttributes" => array(
						"activityId" => $activityId
						)
					)
				));
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to cancel activity '" . $activityId . "' !");
			return false;
		}

		return $info;
	}
}