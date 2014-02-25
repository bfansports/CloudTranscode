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
        
        // USELESS ACTIVITY ???
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

