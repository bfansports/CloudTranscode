<?php

// Interface with SQS

// Amazon libraries
use Aws\Common\Aws;
use Aws\Sqs;
use Aws\Sts;

class SQSUtils
{
    private $region;
    private $aws;
    private $sqs;
    private $sts;
    
    const JOB_STARTED        = "JOB_STARTED";
    const JOB_COMPLETED      = "JOB_COMPLETED";
    const JOB_FAILED         = "JOB_FAILED";
    const ACTIVITY_STARTED   = "ACTIVITY_STARTED";
    const ACTIVITY_FAILED    = "ACTIVITY_FAILED";
    const ACTIVITY_TIMEOUT   = "ACTIVITY_TIMEOUT";
    const ACTIVITY_COMPLETED = "ACTIVITY_COMPLETED";
    const ACTIVITY_PROGRESS  = "ACTIVITY_PROGRESS";
    const ACTIVITY_PREPARING = "ACTIVITY_PREPARING";
    const ACTIVITY_FINISHING = "ACTIVITY_FINISHING";
    
    function __construct($debug)
    {
        $this->debug  = $debug;

        // Create AWS SDK instance
        $this->aws = Aws::factory(array(
                'region' => getenv("AWS_DEFAULT_REGION")
            ));
        $this->sts = $this->aws->get('Sts');
        $this->sqs = $this->aws->get('Sqs');
    }

    // Poll one message at a time from the provided SQS queue
    public function receive_message($queue, $timeout)
    {
        if ($this->debug)
            log_out(
                "DEBUG", 
                basename(__FILE__),
                "Polling from '$queue' ..."
            );
            
        // Poll from SQS to check for new message 
        $result = $this->sqs->receiveMessage(array(
                'QueueUrl'        => $queue,
                'WaitTimeSeconds' => $timeout,
            ));
        
        // Get the message if any and return it to the caller
        if (($messages = $result->get('Messages')) &&
            count($messages))
        {
            if ($this->debug)
                log_out(
                    "DEBUG", 
                    basename(__FILE__),
                    "New messages recieved in queue: '$queue'"
                );
            
            return $messages[0];
        }
    }

    // Delete a message from SQS queue
    public function delete_message($queue, $msg)
    {
        $this->sqs->deleteMessage(array(
                'QueueUrl'        => $queue,
                'ReceiptHandle'   => $msg['ReceiptHandle']));
        
        return true;
    }

    /**
     * SEND FROM CloudTranscode
     */

    public function job_queued()
    {
    }

    public function job_scheduled()
    {
    }

    public function job_started($workflowExecution, $workflowInput)
    {
        $this->send_job_updates(
            $workflowExecution,
            $workflowInput,
            self::JOB_STARTED,
            true
        );
    }

    public function job_completed($workflowExecution, $workflowInput)
    {
        $this->send_job_updates(
            $workflowExecution,
            $workflowInput,
            self::JOB_COMPLETED
        );
    }

    public function job_failed($workflowExecution, $workflowInput, $reason, $details)
    {
        $this->send_job_updates(
            $workflowExecution,
            $workflowInput,
            self::JOB_FAILED,
            false,
            [
                "reason"  => $reason,
                "details" => $details
            ]
        );
    }

    public function job_timeout()
    {
    }

    public function job_canceled()
    {
    }

    public function activity_started($task)
    {
        // last param to 'true' to force sending 'input' info back to client
        $this->send_activity_updates(
            $task, 
            self::ACTIVITY_STARTED,
            true
        );
    }

    public function activity_completed($task, $result = null)
    {
        $this->send_activity_updates(
            $task, 
            self::ACTIVITY_COMPLETED,
            false,
            $result
        );
    }

    public function activity_failed($task, $reason, $details)
    {
        $this->send_activity_updates(
            $task, 
            self::ACTIVITY_FAILED,
            false,
            [
                "reason"  => $reason,
                "details" => $details
            ]
        );
    }

    public function activity_timeout($workflowExecution, $workflowInput, $activity)
    {
        $this->validate_workflow_input($workflowInput);
        
        $job_id = $workflowInput->{"job_id"};
        $client = $workflowInput->{"client"};
        
        $activityData = [
            'activityId'   => $activity["activityId"],
            'activityType' => $activity["activityType"]
        ];
        
        $msg = $this->craft_new_msg(
            self::ACTIVITY_TIMEOUT,
            $job_id,
            array(
                'workflow' => $workflowExecution,
                'activity' => $activity
            )
        );

        $this->sqs->sendMessage(array(
                'QueueUrl'    => $client->{'queues'}->{'output'},
                'MessageBody' => json_encode($msg),
            ));
    }

    public function activity_canceled()
    {
    }

    public function activity_progress($task, $progress)
    {
        $this->send_activity_updates(
            $task, 
            self::ACTIVITY_PROGRESS, 
            false,
            $progress
        );
    }

    public function activity_preparing($task)
    {
        $this->send_activity_updates(
            $task, 
            self::ACTIVITY_PREPARING
        );
    }

    public function activity_finishing($task)
    {
        $this->send_activity_updates(
            $task, 
            self::ACTIVITY_FINISHING
        );
    }

    /**
     * UTILS
     */

    private function craft_new_msg($type, $jobId, $data)
    {
        $msg = array(
            'time'   => microtime(true),
            'type'   => $type,
            'job_id' => $jobId,
            'data'   => $data
        );

        return $msg;
    }

    private function send_activity_updates(
        $task, 
        $type, 
        $sendInput = false, 
        $extra = false)
    {
        if (!($input = json_decode($task->get('input'))))
            throw new \Exception("Task input JSON is invalid!\n".$task->get('input'));
        $job_id = $input->{"job_id"};
        $client = $input->{"client"};
        
        $activityType = $task->get('activityType');
        $activity = [
            'activityId'   => $task->get('activityId'),
            'activityType' => $activityType
        ];
        
        if ($sendInput)
            $activity['input'] = $input->{"data"};
        
        // Add extra data to $data
        if ($extra && is_array($extra) && count($extra))
            foreach ($extra as $key => $value)
                $activity[$key] = $value;
        
        // Prepare data to be send out to client
        $data = array(
            'workflow' => $task->get('workflowExecution'),
            'activity' => $activity
        );
        
        $msg = $this->craft_new_msg(
            $type,
            $job_id,
            $data
        );

        $this->sqs->sendMessage(array(
                'QueueUrl'    => $client->{'queues'}->{'output'},
                'MessageBody' => json_encode($msg),
            ));
    }

    private function send_job_updates(
        $workflowExecution, 
        $workflowInput, 
        $type, 
        $sendInput = false, 
        $extra = false)
    {
        $this->validate_workflow_input($workflowInput);
        
        $job_id = $workflowInput->{"job_id"};
        $client = $workflowInput->{"client"};

        if ($sendInput)
            $workflowExecution['input'] = $workflowInput->{"data"};
        
        $data = array(
            'workflow' => $workflowExecution,
        );
        
        if ($extra && is_array($extra) && count($extra))
            foreach ($extra as $key => $value)
                $data[$key] = $value;
        
        $msg = $this->craft_new_msg(
            $type,
            $workflowInput->{'job_id'},
            $data
        );

        $this->sqs->sendMessage(array(
                'QueueUrl'    => $client->{'queues'}->{'output'},
                'MessageBody' => json_encode($msg),
            ));
    }
    
    private function validate_workflow_input($input)
    {
        if (!isset($input->{"client"}))
            throw new \Exception("No 'client' provided in job input!");
        if (!isset($input->{"job_id"}))
            throw new \Exception("No 'job_id' provided in job input!");
        if (!isset($input->{"data"}))
            throw new \Exception("No 'data' provided in job input!");
    }
}
