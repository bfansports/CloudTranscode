<?php

require 'Utils.php';
require './activities/BasicActivity.php';

Class WorkflowActivityPoller
{
	private $domainName;
	private $taskList;

	function __construct($domainName, $taskList)
	{
		global $activities;

		$this->domainName = $domainName;
		$this->taskList   = $taskList;

		if (!init_domain($domainName))
			throw new Exception("Unable to init the domain !\n");
		
		// Dynamically load classes responsible for handling each activity.
		// See utils.php for the list
		foreach ($activities as &$activity)
		{
			// Load the file
			$file = dirname(__FILE__) . $activity["file"];
			require_once $file;

			// Instantiate the class
			$activity["object"] = new $activity["class"](array(
				"domain"  => $domainName,
				"name"    => $activity["name"],
				"version" => $activity["version"]
				));

			echo "[INFO] Activity handler registered: " . $activity["name"] . "\n";
		}
	}	

	public function poll_for_activities()
	{
		global $swf;

		// Initiate polling
		try {
			echo "[INFO] Polling ... \n";
			$activityTask = $swf->pollForActivityTask(array(
				"domain"   => $this->domainName,
				"taskList" => $this->taskList
				));

			// Polling timeout, we return for another round
			if (!($activityType = $activityTask->get("activityType")))
				return true;

			//print_r($activityTask);

		} catch (Exception $e) {
			echo "Unable to poll activity tasks ! " . $e->getMessage() . "\n";
			return true;
		}

		// Can activity be handled by this poller ?
		if (!($activity = $this->get_activity($activityType["name"]))) 
		{
			echo "[ERROR] This activity type is unknown ! Skipping ...\n";
			echo "[ERROR] Detail: \n";
			print_r($activity);
			return true;
		}
		
		if (!isset($activity["object"])) {
			echo "[ERROR] The activity handler for this activity is not instantiated !\n";
			return true;
		}

		// Run activity task
		if (($result = $activity["object"]->do_activity($activityTask))) {
			$activity["object"]->activity_completed($activityTask, $result);
			return true;
		}

		return true;
	}

	private function get_activity($activityName)
	{
		global $activities;

		foreach ($activities as $activity)
		{
			if ($activity["name"] == $activityName)
				return ($activity);
		}

		return false;
	}
}



/**
 * TEST PROGRAM
 */

$domainName = "SA_TEST2";
$taskList = array("name" => "TranscodingTaskList");

echo "[INFO] Domain: '$domainName'\n";
echo "[INFO] TaskList:\n";
print_r($taskList);

try {
	$wfActivityPoller = new WorkflowActivityPoller($domainName, $taskList);
} catch (Exception $e) {
	echo "Unable to create WorkflowActivityPoller ! " . $e->getMessage() . "\n";
	exit (1);
}

// Start polling loop
echo "\n[INFO] Starting activity tasks polling \n";
while (1)
{
	if (!$wfActivityPoller->poll_for_activities())
	{
		echo "[INFO] Polling for activities finished !\n";
		exit (1);
	}

	sleep(4);
} 
