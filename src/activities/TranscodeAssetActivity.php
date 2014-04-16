<?php

require_once __DIR__ . '/transcoders/BasicTranscoder.php';

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
        $pathToInputFile = $this->get_file_to_process($task, $input->{'input_json'});
        
        
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
            $input->{'output'}->{'preset_values'} = $videoTranscoder->get_preset_values($input->{'output'});
                
            // Perform transcoding
            $pathToOutputFile = $videoTranscoder->transcode_asset($pathToInputFile,
                $input->{'input_asset_info'}, 
                $input->{'output'},
                $task,
                $this);            
            break;
        case self::IMAGE:
                
            break;
        case self::AUDIO:
                
            break;
        case self::DOC:
                
            break;
        }

        // *********************
        // Upload resulting file
            
        // XXX
        // XXX. HERE, Notify upload starting through SQS !
        // XXX

        
        
        // Prepare S3 options
        $options = array("rrs" => false, "encrypt" => false);
        if (isset($input->{'output'}->{'s3_rrs'}) &&
            $input->{'output'}->{'s3_rrs'} == true)
            $options['rrs'] = true;
        if (isset($input->{'output'}->{'s3_encrypt'}) &&
            $input->{'output'}->{'s3_encrypt'} == true)
            $options['encrypt'] = true;
        
        // Sanitize output bucket path "/"
        $outputBucket = str_replace("//","/",
            $input->{'output'}->{"output_bucket"}."/".$task["workflowExecution"]["workflowId"]);

        // Send output file to S3 bucket
        $s3Utils = new S3Utils();
        log_out("INFO", basename(__FILE__), 
            "Uploading '" . $pathToOutputFile . "' into '" . $outputBucket . "/" . $input->{'output'}->{'output_file'}  . "' ...",
            $this->activityLogKey);
        $s3Output = $s3Utils->put_file_into_s3($outputBucket, 
            $input->{'output'}->{'output_file'}, $pathToOutputFile,
            $options, array($this, "s3_put_processing_callback"), $task);
        
        log_out("INFO", basename(__FILE__), 
            $s3Output['msg'],
            $this->activityLogKey);
        
        log_out("INFO", basename(__FILE__), 
            "Output file successfully uploaded into S3 bucket '$outputBucket' !",
            $this->activityLogKey);
    }
}


