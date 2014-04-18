<?php

require __DIR__ . '/transcoders/BasicTranscoder.php';

/**
 * This class validate JSON input. 
 * Makes sure the input files to be transcoded exists and is valid.
 */
class ValidateInputAndAssetActivity extends BasicActivity
{
  
    // Perform the activity
    public function do_activity($task)
    {
        // XXX
        // XXX. HERE, Notify validation task starts through SQS !
        // XXX
        
        $activityId   = $task->get("activityId");
        $activityType = $task->get("activityType");
        // Create a key workflowId:activityId to put in logs
        $this->activityLogKey = $task->get("workflowExecution")['workflowId'] . ":$activityId";
        
        // Perfom input validation
        $input = $this->do_input_validation($task, $activityType["name"]);
        
        // Create TMP folder and download the input file
        $pathToFile = $this->get_file_to_process($task, $input);
        
        /**
         * PROCESS FILE
         */
        log_out("INFO", basename(__FILE__), "Starting Asset validation ...",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Gathering information about input file '$pathToFile' - Type: " . $input->{'input_type'},
            $this->activityLogKey);
        
        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($input->{'input_type'}) 
        {
        case self::VIDEO:
            require_once __DIR__ . '/transcoders/VideoTranscoder.php';
            
            // Initiate transcoder obj
            $videoTranscoder = new VideoTranscoder($this, $task);
            // Validate all outputs presets before checking input
            foreach ($input->{'outputs'} as $ouput)
                $videoTranscoder->validate_preset($ouput);
            // Get input video information
            $assetInfo = $videoTranscoder->get_asset_info($pathToFile);
            break;
        case self::IMAGE:
                
            break;
        case self::AUDIO:
                
            break;
        case self::DOC:
                
            break;
        }
    
        // XXX
        // XXX. HERE, Notify validation task success through SQS !
        // XXX

        // Create result object to be passed to next activity in the Workflow as input
        $result = [
            "input_json"       => $input, // Original JSON
            "input_asset_type" => $input->{'input_type'}, // Input asset detailed info
            "input_asset_info" => $assetInfo, // Input asset detailed info
            "outputs"          => $input->{'outputs'} // Outputs to generate
        ];
    
        return $result;
    }

    
}
