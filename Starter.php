<?php

/**
 * This script listen to AWS SQS for incoming input commands (TODO: Input Sanity check)
 * It opens the JSON input and starts a new Workflow with it
 */

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

/** FROM Utils.php **/
if (!init_domain($config['SWF']['domain']))
	throw new Exception("[ERROR] Unable to init the domain !\n");
if (!init_workflow($config['SWF']))
	throw new Exception("[ERROR] Unable to init the workflow !\n");

// Get SQS queue URL - "input" queue. (There is also an "output" queue)
$inputQueue = $sqs->getQueueUrl(array('QueueName' => $config['SQS']['input']));

// Start polling loop - Listen for messages
log_out("INFO", basename(__FILE__), "Starting SQS message polling");
while (1)
{
	log_out("INFO", basename(__FILE__), "Polling ...");

	// Check for new msg, poll for 10sec, then retry
	$result = $sqs->receiveMessage(array(
		'QueueUrl'        => $inputQueue['QueueUrl'],
		'WaitTimeSeconds' => 10,
		));

	$messages = $result->get('Messages');
	if ($messages) {

		// Check incoming message
		foreach ($messages as $msg) {
    		// Msg body
			// log_out("INFO", basename(__FILE__), "New Input: ");
			// print($msg['Body'] . "\n");

			try {
				log_out("INFO", basename(__FILE__), "Requesting new workflow to process input ...");

				/**
				 * We start a WF with user input
				 * !! SECURITY ISSUE !!
				 * NO INPUT sanity check !! 
				 */
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
			
			// Delete msg from SQS queue
			$sqs->deleteMessage(array(
				'QueueUrl'        => $inputQueue['QueueUrl'],
				'ReceiptHandle'   => $msg['ReceiptHandle']));
		}
	}
} 

