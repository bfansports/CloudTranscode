<?php

require "Utils.php";
require "./activities/BasicActivity.php";

Class WorkflowActivityPoller
{
	private $domain;
	private $taskList;

	function __construct($config)
	{
		global $activities;

		$this->domain   = $config['SWF']['domain'];
		$this->taskList = array("name" => $config['taskList']);

		if (!init_domain($this->domain))
			throw new Exception("Unable to init the domain !\n");
		
		// Dynamically load classes responsible for handling each activity.
		// See utils.php for the list
		foreach ($activities as &$activity)
		{
			// Load the file representing the activity
			$file = dirname(__FILE__) . $activity["file"];
			require_once $file;

			// Instantiate the class
			$activity["object"] = new $activity["class"](array(
				"domain"  => $this->domain,
				"name"    => $activity["name"],
				"version" => $activity["version"]
				));

			log_out("INFO", basename(__FILE__), "Activity handler registered: " . $activity["name"]);
		}
	}	

	public function poll_for_activities()
	{
		global $swf;

		// Initiate polling
		try {
			log_out("INFO", basename(__FILE__), "Polling ... ");
			$activityTask = $swf->pollForActivityTask(array(
				"domain"   => $this->domain,
				"taskList" => $this->taskList
				));

			// Polling timeout, we return for another round
			if (!($activityType = $activityTask->get("activityType")))
				return true;

			//print_r($activityTask);

		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to poll activity tasks ! " . $e->getMessage());
			return true;
		}

		// Can activity be handled by this poller ?
		if (!($activity = $this->get_activity($activityType["name"]))) 
		{
			log_out("ERROR", basename(__FILE__), "This activity type is unknown ! Skipping ...");
			log_out("ERROR", basename(__FILE__), "Detail: ");
			print_r($activity);
			return true;
		}
		
		if (!isset($activity["object"])) {
			log_out("ERROR", basename(__FILE__),"The activity handler for this activity is not instantiated !");
			return true;
		}

		// Run activity task
		$result = $activity["object"]->do_activity($activityTask);
		if ($result["STATUS"] != "SUCCESS")
		{
			$activity["object"]->activity_failed($activityTask, $result);
			return false;
		}

		$activity["object"]->activity_completed($activityTask, $result);
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
 * POLLER
 */

// Get config file
$config = json_decode(file_get_contents(dirname(__FILE__) . "/config/cloudTranscodeConfig.json"), true);
log_out("INFO", basename(__FILE__), "Domain: '" . $config['SWF']['domain'] . "'");
log_out("INFO", basename(__FILE__), "TaskList: '" . $config['taskList'] . "'");

try {
	$wfActivityPoller = new WorkflowActivityPoller($config);
} catch (Exception $e) {
	log_out("ERROR", basename(__FILE__), "Unable to create WorkflowActivityPoller ! " . $e->getMessage());
	exit (1);
}

// Start polling loop
log_out("INFO", basename(__FILE__), "Starting activity tasks polling");
while (1)
{
	if (!$wfActivityPoller->poll_for_activities())
	{
		log_out("INFO", basename(__FILE__), "Polling for activities finished !");
		exit (1);
	}

	sleep(4);
} 
