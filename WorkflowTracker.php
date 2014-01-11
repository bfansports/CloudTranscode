<?php

// Depends on Utils.php. Should be included in caller.

class WorkflowTracker
{
	private	$domainName;
	private	$workflowExecution;

	// Execution tracker
	private $excutionTracker;

	function __construct($domainName)
	{
		if (!$domainName)
			throw new Exception("Domain is null !\n");

		$this->domainName = $domainName;

		$this->excutionTracker = array();
	}	

	// Return workflow input data from history
	public function get_workflow_input($workflowExecution, $events = null)
	{
		// If you already have the event list. Just pass it and we will look for the 'input' field.
		// If not, then we will query the history for you.
		if (!$events) {
			if (!($events = $this->get_workflow_history_events($workflowExecution))) {
				echo "[ERROR] Unable to get workflow Input !\n";
				return false;
			}
		}

		// Find the Execution started event which contain the 'input' value
		foreach ($events as $event) 
		{
			if (isset($event["workflowExecutionStartedEventAttributes"]["input"]))
				return ($event["workflowExecutionStartedEventAttributes"]["input"]);
		}

		echo "[ERROR] Input value cannot be retrived from workflow events history ! \n";
		return false;
	}

	// Get workflow history
	public function get_workflow_history_events($workflowExecution)
	{
		global $swf;

		try {
			$history = $swf->getWorkflowExecutionHistory(array(
				"domain"    => $this->domainName,
				"execution" => $workflowExecution
				));
		} catch (Aws\Swf\Exception\UnknownResourceException $e) {
			echo "[ERROR] Unable to find the workflow '" . $workflowExecution['workflowId'] . "'. Can't get workflow history. " . $e->getMessage() . "\n";
			return false;
		} catch (Exception $e) {
			echo "[ERROR] Unable to get workflow history ! " . $e->getMessage() . "\n";
			return false;
		}

		return $history->get("events");
	}

	// Register a workflow in the tracker for further use
	// We register the workflow execution and its activity list
	// See Utils.php for activity list
	public function register_workflow_in_tracker($workflowExecution, $activities) 
	{
		if (!$workflowExecution) {
			log_out("ERROR", basename(__FILE__), "'workflowExecution' variable is null !");
			return false;
		}
		if (!$activities) {
			log_out("ERROR", basename(__FILE__), "'activities' variable is null !");
			return false;
		}

		// New execution. We don't have track yet. Registering...
		if (!$this->is_workflow_tracked($workflowExecution))
		{
			log_out("INFO", basename(__FILE__), "Registering workflow '" . $workflowExecution["workflowId"] . "' in the workflow tracker !");
			$this->executionTracker[$workflowExecution["workflowId"]] = array(
				"step"       => 0,
				"activities" => $activities
				);
		}

		return true;
	}

	// Return the next activity to process based on the current step
	public function get_current_activity($workflowExecution)
	{
		if (!$this->is_workflow_tracked($workflowExecution))
		{
			log_out("ERROR", basename(__FILE__), "WorkflowID '" . $workflowExecution["workflowId"] . "' is not being tracked by the workflow tracker !");
			return false;
		}

		$tracker = $this->executionTracker[$workflowExecution["workflowId"]];
		$step    = $this->executionTracker[$workflowExecution["workflowId"]]["step"];
		
		return ($tracker["activities"][$step]);
	}

	// Return the previous activity based on the current step
	public function get_previous_activity($workflowExecution)
	{
		if (!$this->is_workflow_tracked($workflowExecution))
		{
			log_out("ERROR", basename(__FILE__), "WorkflowID '" . $workflowExecution["workflowId"] . "' is not being tracked by the workflow tracker !");
			return false;
		}

		$tracker = $this->executionTracker[$workflowExecution["workflowId"]];
		$step    = $this->executionTracker[$workflowExecution["workflowId"]]["step"];
		if (!$step)
			return ($tracker["activities"][$step]);
		
		return ($tracker["activities"][$step-1]);
	}

	// Get current step
	public function get_current_step($workflowExecution)
	{
		if (!$this->is_workflow_tracked($workflowExecution))
		{
			log_out("ERROR", basename(__FILE__), "WorkflowID '" . $workflowExecution["workflowId"] . "' is not being tracked by the workflow tracker !");
			return false;
		}

		return ($this->executionTracker[$workflowExecution["workflowId"]]["step"]);
	}

	// Increment step to the next activity
	public function move_to_next_activity($workflowExecution)
	{
		if (!$this->is_workflow_tracked($workflowExecution))
		{
			log_out("ERROR", basename(__FILE__), "WorkflowID '" . $workflowExecution["workflowId"] . "' is not being tracked by the workflow tracker !");
			return false;
		}

		$tracker = $this->executionTracker[$workflowExecution["workflowId"]];
		// Increment step
		$this->executionTracker[$workflowExecution["workflowId"]]["step"] += 1;
		return true;
	}

	// Is the workflow over ?
	public function is_workflow_finished($workflowExecution)
	{
		if (!$this->is_workflow_tracked($workflowExecution))
		{
			log_out("ERROR", basename(__FILE__), "WorkflowID '" . $workflowExecution["workflowId"] . "' is not being tracked by the workflow tracker !");
			return false;
		}

		$tracker = $this->executionTracker[$workflowExecution["workflowId"]];
		$step    = $this->executionTracker[$workflowExecution["workflowId"]]["step"];

		// Are we at the last step ?
		if (count($tracker["activities"]) == $step+1)
			return true;

		return false;
	}

	// Is the workflow tracked by this tracker ?
	public function is_workflow_tracked($workflowExecution)
	{
		if (!isset($this->executionTracker[$workflowExecution["workflowId"]]))
			return false;

		return true;
	}

}

