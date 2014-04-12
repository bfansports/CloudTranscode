<?php

require __DIR__ . '/transcoders/BasicTranscoder.php';

/**
 * This class handle the transcoding activity
 */
class TranscodeAssetActivity extends BasicActivity
{
    // Perform the activity
    public function do_activity($task)
    {
        // XXX
        // XXX. HERE, Notify transcode task initializing through SQS !
        // XXX
        
        $activityId   = $task->get("activityId");
        $activityType = $task->get("activityType");
        // Create a key workflowId:activityId to put in logs
        $this->activityLogKey = $task->get("workflowExecution")['workflowId'] . ":$activityId";
        
        // Perfom input validation
        $input = $this->do_input_validation($task, $activityType["name"]);
        
        // Create TMP folder and download the input file
        $pathToFile = $this->get_file_to_process($task, $input->{'input_json'});

        
        /**
         * TRANSCODE INPUT FILE
         */

        log_out("INFO", basename(__FILE__), "Preparing Asset transcoding ...",
            $this->activityLogKey);

        switch ($input->{'input_asset_type'}) 
        {
        case self::VIDEO:
            require_once __DIR__ . '/transcoders/VideoTranscoder.php';
            $videoTranscoder = new VideoTranscoder($this->activityLogKey);
            
            // Check preset file, read its content and add it to ouput 
            $input->{'output'}->{'preset_values'} = 
                $videoTranscoder->get_preset_values($input->{'output'});
                
            // Perform transcoding
            $videoTranscoder->transcode_asset($pathToFile,
                $input->{'input_asset_info'}, 
                $input->{'output'},
                $task);
            break;
        case self::IMAGE:
                
            break;
        case self::AUDIO:
                
            break;
        case self::DOC:
                
            break;
        }
    }
}


