<?php

require_once __DIR__ . '/BasicActivity.php';
require_once __DIR__ . '/transcoders/BasicTranscoder.php';

/**
 * This class validate JSON input. 
 * Makes sure the input files to be transcoded exists and is valid.
 */
class ValidateInputAndAssetActivity extends BasicActivity
{
    // Perform the activity
    public function do_activity($task)
    {
        // Check input task. Set $this->input_str String
        parent::do_task_check($task);
        
        // Init Activity
        parent::do_init($task);
        
        // Validate JSON. Set $this->input JSON object
        parent::do_input_validation(
            $task, 
            $this->activityType["name"]
        );
        
        // Call parent method for initialization.
        // Setup TMP folder
        // Send starting SQS message
        // Download input file from S3
        parent::do_activity($task);
        
        /**
         * PROCESS FILE
         */
        
        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($this->data->{'input_type'}) 
        {
        case self::VIDEO:
            require_once __DIR__ . '/transcoders/VideoTranscoder.php';
            
            // Initiate transcoder obj
            $videoTranscoder = new VideoTranscoder($this, $task);
            // Get input video information
            $assetInfo = $videoTranscoder->get_asset_info($this->pathToInputFile);
            break;
        case self::IMAGE:
                
            break;
        case self::AUDIO:
                
            break;
        case self::DOC:
                
            break;
        }
        
        return ["result" => $assetInfo ];
    }
}
