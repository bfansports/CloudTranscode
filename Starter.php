<?php

require 'Utils.php';

// SWF client
global $swf;
// SQS client
global $sqs;

// Get config file
$config = json_decode(file_get_contents(dirname(__FILE__) . "/config/cloudTranscodeConfig.json"), true);
log_out("INFO", basename(__FILE__), "Domain: '" . $config['SWF']['domain'] . "'");
log_out("INFO", basename(__FILE__), "TaskList: '" . $config['taskList'] . "'");

// Workflow info
$workflowType = array(
	"name"    => $config['SWF']["name"],
	"version" => $config['SWF']["version"]);

if (!init_domain($config['SWF']['domain']))
	throw new Exception("[ERROR] Unable to init the domain !\n");

if (!init_workflow($config['SWF']))
	throw new Exception("[ERROR] Unable to init the workflow !\n");

// Get SQS queue URL
$queueURL = $sqs->getQueueUrl(array('QueueName' => $config['SQSQueue']));

// Start polling loop
log_out("INFO", basename(__FILE__), "Starting SQS message polling");
while (1)
{
	log_out("INFO", basename(__FILE__), "Polling ...");

	// Check for new msg, poll for 10sec
	$result = $sqs->receiveMessage(array(
		'QueueUrl'        => $queueURL['QueueUrl'],
		'WaitTimeSeconds' => 2,
		));

	$messages = $result->get('Messages');
	if ($messages) {

		// Check incoming message
		foreach ($messages as $msg) {
    		// Msg body
			log_out("INFO", basename(__FILE__), "New Input: ");
			print($msg['Body'] . "\n");

			try {
				log_out("INFO", basename(__FILE__), "Requesting new workflow to process input ...");

				// Start a WF with input
				$workflowRunId = $swf->startWorkflowExecution(array(
					"domain"       => $config['SWF']['domain'],
					"workflowId"   => uniqid(),
					"workflowType" => $workflowType,
					"taskList"     => array("name" => $config['taskList']),
					"input"        => $msg['Body']
					));
			} catch (Exception $e) {
				log_out("FATAL", basename(__FILE__), "Unable to start workflow execution  ! " . $e->getMessage());
			}
			
			// Delete msg from queue
			$sqs->deleteMessage(array(
				'QueueUrl'        => $queueURL['QueueUrl'],
				'ReceiptHandle'   => $msg['ReceiptHandle']));
		}
	}
} 

