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
        
        log_out(
            "INFO", 
            basename(__FILE__), "Preparing Asset validation ...",
            $this->activityLogKey
        );

        // Create TMP storage to store input file to transcode 
        $inputFileInfo = pathinfo($input->{'input_file'});
        // Use workflowID to generate a unique TMP folder localy.
        $tmpPathInput = self::TMP_FOLDER 
            . $task["workflowExecution"]["workflowId"] . "/" 
            . $inputFileInfo['dirname'];
        if (!file_exists($tmpPathInput))
            if (!mkdir($tmpPathInput, 0750, true))
                throw new CTException(
                    "Unable to create temporary folder '$tmpPathInput' !",
                    self::TMP_FOLDER_FAIL
                );

        // Download input file and store it in TMP folder
        $saveFileTo = $tmpPathInput . "/" . $inputFileInfo['basename'];
        $pathToInputFile = 
            $this->get_file_to_process(
                $task, 
                $input,
                $saveFileTo
            );
        
        /**
         * PROCESS FILE
         */
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Gathering information about input file '$pathToInputFile' - Type: " 
            . $input->{'input_type'},
            $this->activityLogKey
        );
        
        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($input->{'input_type'}) 
        {
        case VIDEO:
            require_once __DIR__ . '/transcoders/VideoTranscoder.php';
            
            // Initiate transcoder obj
            $videoTranscoder = new VideoTranscoder($this, $task);
            // Validate all outputs presets before checking input
            foreach ($input->{'outputs'} as $output) {
                // Presets are only for VIDEO
                if ($output->{'output_type'} == VIDEO)
                    $videoTranscoder->validate_preset($output);
            }
            // Get input video information
            $assetInfo = $videoTranscoder->get_asset_info($pathToInputFile);
            break;
        case IMAGE:
                
            break;
        case AUDIO:
                
            break;
        case DOC:
                
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
