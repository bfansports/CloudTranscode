<?php

require __DIR__ . '/transcoders/BasicTranscoder.php';

/**
 * This class validate JSON input. 
 * Makes sure the input files to be transcoded exists and is valid.
 */
class ValidateInputAndAssetActivity extends BasicActivity
{
    private $data;
    private $client;
    private $job_id;

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
        $this->job_id = $input->{'job_id'};         
        $this->data   = $input->{'data'};  
        // We may not have 'client' if the InputPoller receive input from cmd line.
        // Testing purposes. No client == No SQS communication.
        if (isset($input->{'client'}))
            $this->client = $input->{'client'};

        log_out(
            "INFO", 
            basename(__FILE__), "Preparing Asset validation ...",
            $this->activityLogKey
        );

        // Create TMP storage to store input file to transcode 
        $inputFileInfo = pathinfo($this->data->{'input_file'});
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
                $this->data->{'input_bucket'},
                $this->data->{'input_file'},
                $saveFileTo
            );
        
        /**
         * PROCESS FILE
         */
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Gathering information about input file '$pathToInputFile' - Type: " 
            . $this->data->{'input_type'},
            $this->activityLogKey
        );
        
        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($this->data->{'input_type'}) 
        {
        case VIDEO:
            require_once __DIR__ . '/transcoders/VideoTranscoder.php';
            
            // Initiate transcoder obj
            $videoTranscoder = new VideoTranscoder($this, $task);
            // Validate all outputs presets before checking input
            foreach ($this->data->{'outputs'} as $output) {
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
            "job_id"           => $this->job_id,
            "input_json"       => $this->data, // Original JSON
            "input_asset_type" => $this->data->{'input_type'}, // Input asset detailed info
            "input_asset_info" => $assetInfo, // Input asset detailed info
            "outputs"          => $this->data->{'outputs'} // Outputs to generate
        ];

        if (isset($input->{'client'}))
            $result["client"] = $input->{'client'};
    
        return $result;
    }

    
}
