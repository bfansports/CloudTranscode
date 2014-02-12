<?php

// This class serves as a skeletton for classes impleting actual activity
class ValidateTranscodedAssetActivity extends BasicActivity
{
	// Perform the activity
	public function do_activity($task)
	{
		global $swf;

		$input = json_decode($task->get("input"));
        
		log_out("INFO", basename(__FILE__), 
            "Validate Transcoded assets !");
        
        //print_r($input);

		return [
            "status"  => "SUCCESS",
            "data"    => [
                "input_json" => $input->{"input_json"},
                "input_file" => $input->{"input_file"}
            ]
        ];
	}
}

