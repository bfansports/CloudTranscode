<?php

/**
 * workflow manager helps you manipulate Worflows.
 * Start, Stop, Cancel, etc ...
 * !! Depends on Utils.php. Should be included in parent caller.
 */
class WorkflowManager
{
	private	$domainName;
    
	function __construct($domainName)
	{
        if (!$domainName)
			throw new Exception("Domain is null !\n");

		$this->domainName = $domainName;
	}	
    
	/**
	 * Gracefuly request workflow cancelation. Decider will decide how to terminate.
	 * @param  [Array] $workflowExecution [Associative array (http://docs.aws.amazon.com/amazonswf/latest/apireference/API_WorkflowExecution.html)]
	 * @return [Boolean][true:false - failure returns 'false']
	 */
	public function cancel_workflow($workflowExecution)
	{
		global $swf;

		try {
			$swf->requestCancelWorkflowExecution([
				"domain"     => $this->domainName,
				"workflowId" => $workflowExecution["workflowId"]
            ]);
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
	public function terminate_workflow($workflowExecution, $reason = "", $details = "")
	{
		global $swf;

		try {
			$swf->terminateWorkflowExecution([
				"domain"     => $this->domainName,
				"workflowId" => $workflowExecution["workflowId"],
				"reason"     => $reason,
				"details"    => $details
            ]);
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
	public function get_workflow_type($workflowExecution)
	{
		global $swf;

		try {
			$info = $swf->describeWorkflowType([
				"domain"       => $this->domainName,
				"workflowType" => [
					"name"    => TRANSCODE_WORKFLOW,
                    "version" => TRANSCODE_WORKFLOW_VERS
                ]
            ]);
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
	public function get_workflow_exec_status($workflowExecution)
	{
		global $swf;

		try {
			$info = $swf->describeWorkflowExecution([
				"domain"     => $this->domainName,
				"execution" => $workflowExecution
            ]);
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
	public function get_workflow_excution_history($workflowExecution)
	{
		global $swf;

		try {
			$history = $swf->getWorkflowExecutionHistory([
				"domain" => $this->domainName,
				"execution" => $workflowExecution
            ]);
		} catch (\Aws\Swf\Exception\UnknownResourceException $e) {
			log_out("ERROR", basename(__FILE__), "Unable to find the workflow '" . $workflowExecution['workflowId'] . "'. Can't get workflow history. " . $e->getMessage());
			return false;
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to get workflow history ! " . $e->getMessage());
			return false;
		}

		return $history->get("events");
	}

	/**
	 * Cancel an activity
	 * @param  [String] $taskToken  [taskToken needed to identify the task]
	 * @param  [String] $activityId [description]
	 * @return [Boolean]             [description]
	 */
	public function cancel_activity($taskToken, $activityId)
	{
		global $swf;

		try {
			$swf->respondDecisionTaskCompleted([
				"taskToken" => $taskToken,
				"decision"  => [
					"decisionType" => "RequestCancelActivityTask",
					"requestCancelActivityTaskDecisionAttributes" => [
						"activityId" => $activityId
                    ]
                ]
            ]);
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to cancel activity '" . $activityId . "' !");
			return false;
		}

		return true;
	}

    /**
	 * Schedule new activities based on the list of decisions
	 * @param  [String] $taskToken  [taskToken needed to identify the task]
	 * @param  [String] $activityId [description]
	 * @return [Boolean]             [description]
	 */
    public function start_activities($taskToken, $decisions)
    {
        global $swf;

		try {
			$swf->respondDecisionTaskCompleted(array(
				"taskToken" => $taskToken,
				"decisions" => $decisions
            ));
		} catch (\Aws\Swf\Exception\UnknownResourceException $e) {
			log_out("ERROR", basename(__FILE__), "Resource Unknown ! " . $e->getMessage());
			return false;
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to respond to the decision task! Details: " . $e->getMessage());
			return false;
		}
        
		return true;
    }
}