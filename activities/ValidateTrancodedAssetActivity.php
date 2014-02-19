<?php

// This class serves as a skeletton for classes impleting actual activity
class ValidateTranscodedAssetActivity extends BasicActivity
{
	// Perform the activity
	public function do_activity($task)
	{
        // Perfom input validation
		if (($validation = $this->input_validator($task)) &&
            $validation['status'] == "ERROR")
            return $validation;
        $input = $validation['input'];
        
        // Create a key workflowId:activityId to put in logs
        $this->activityLogKey = $task->get("workflowExecution")['workflowId'] . ":" . $task->get("activityId");

        
        /**
         * PROCESS
         */

        log_out("INFO", basename(__FILE__), 
            "Starting validation of transcoded files ...",
            $this->activityLogKey);

        // Generated outputs
        $outputs = $input->{'input_json'}->{'outputs'};
        
        // Prepare output result
        $result = [
            "status" => '',
            "data"   => [
                "input_file" => $input->{"input_file"},
                "outputs"    => []
            ]
        ];
        
        $i = 0;
        foreach ($outputs as $output)
        {
            if (!isset($output->{"status"}))
            {
                $output->{"status"} = "FAILED";
                $output->{"reason"} = "Output task has no status! All completed task should have a status !";
            }
            else if (file_exists_in_S3($output->{'output_bucket'}, $ouput->{'file'}))
            {
                // If no fail status in output yet!
                if (!isset($output->{"status"}) ||
                    $output->{"status"} != "FAILED")
                {
                    $output->{"status"} = "FAILED";
                    $output->{"reason"} = "Output file '" . $ouput->{'file'} . "' cannot be found in S3 !";                                             
                }
            }
            
            if ($output->{"status"} = "FAILED")
                $i++;
            
            array_push($result["data"]["outputs"], $output);
        }

        if (($i > 0 && $i == count($outputs)) ||
            !count($outputs))
            $result["status"] = "FAILED";
        else if ($i > 0 && $i < count($outputs))
            $result["status"] = "PARTIAL";
        else if (!$i)
            $result["status"] = "COMPLETED";
                
		return $result;
	}

    // Validate input
	protected function input_validator($task)
	{
        if (($input = $this->check_task_basics($task)) &&
            $input['status'] == "ERROR") 
        {
            log_out("ERROR", basename(__FILE__), 
                $input['details'],
                $this->activityLogKey);
            return ($input);
        }
        
        // Return input
        return $input;
	}
}

