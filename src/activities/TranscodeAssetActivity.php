<?php

require_once __DIR__ . '/transcoders/BasicTranscoder.php';

/**
 * This class handle the transcoding activity
 */
class TranscodeAssetActivity extends BasicActivity
{
    const CONVERSION_TYPE_ERROR = "CONVERSION_TYPE_ERROR";
    const TMP_PATH_OPEN_FAIL    = "TMP_PATH_OPEN_FAIL";
    const UNKOWN_OUTPUT_TYPE    = "UNKOWN_OUTPUT_TYPE";
    
    // Perform the activity
    public function do_activity($task)
    {
        $activityId   = $task->get("activityId");
        $activityType = $task->get("activityType");
        // Create a key workflowId:activityId to put in logs
        $this->activityLogKey = $task->get("workflowExecution")['workflowId'] 
            . ":$activityId";

        
        // Send started through CTCom to notify client
        $this->CTCom->activity_started($task);
        
        // Perfom input validation
        // Pass callback function 'validate_input' to perfrom custom validation
        $input = $this->do_input_validation(
            $task, 
            $activityType["name"],
            array($this, 'validate_input')
        );
        
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Preparing Asset transcoding ...",
            $this->activityLogKey
        );

        // Create TMP storage to store input file to transcode 
        $inputFileInfo = pathinfo($input->{'input_json'}->{'input_file'});
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
                $input->{'input_json'}->{'input_bucket'},
                $input->{'input_json'}->{'input_file'},
                $saveFileTo
            );
        
        // Create TMP folder for output files
        $outputFileInfo = pathinfo($input->{'output'}->{'output_file'});
        $input->{'output'}->{'output_file_info'} = $outputFileInfo;
        $pathToOutputFiles = $tmpPathInput . "/output/" 
            . $task['activityId']
            . "/" . $outputFileInfo['dirname'];
        if (!file_exists($pathToOutputFiles))
            if (!mkdir($pathToOutputFiles, 0750, true))
                throw new CTException(
                    "Unable to create temporary folder '$pathToOutputFiles' !",
                    self::TMP_FOLDER_FAIL
                );

        /**
         * TRANSCODE INPUT FILE
         */

        switch ($input->{'output'}->{'output_type'}) 
        {
        case VIDEO:
        case THUMB:
            require_once __DIR__ . '/transcoders/VideoTranscoder.php';

            // Instanciate transcoder to output Videos
            $videoTranscoder = new VideoTranscoder($this, $task);
            
            // Check preset file, read its content and add its data to output object
            // Only for VIDEO output. THUMB don't use presets
            if ($input->{'output'}->{'output_type'} == VIDEO)
                $input->{'output'}->{'preset_values'} = $videoTranscoder->get_preset_values($input->{'output'});
                
            // Perform transcoding
            $videoTranscoder->transcode_asset(
                $pathToInputFile,
                $pathToOutputFiles,
                $input->{'input_asset_info'}, 
                $input->{'output'}
            );            
            break;
        case IMAGE:
                
            break;
        case AUDIO:
                
            break;
        case DOC:
                
            break;
        default:
            throw new CTException("Unknown 'output_type'! Abording ...", 
                self::UNKOWN_OUTPUT_TYPE);
        }
        
        // Upload resulting file
        $this->upload_result_files($task, $input, $pathToOutputFiles, $outputFileInfo);
        
        $this->send_heartbeat($task);
        // Send progress through CTCom to notify client of finishing
        $this->CTCom->activity_finishing($task); 
    }

    // Upload all output files to destination S3 bucket
    private function upload_result_files(
        $task,
        $input, 
        $pathToOutputFiles, 
        $outputFileInfo)
    {
        // Sanitize output bucket path "/"
        $s3Bucket = str_replace("//", "/", $input->{'output'}->{"output_bucket"});

        // XXXXXXXXXXXXXXXXXXXXXXXXXXXXX
        // XXX: Add tmp workflowID to output bucket to seperate upload
        // XXX: For testing only !
        //$s3Bucket .= "/".$task["workflowExecution"]["workflowId"];
        // XXXXXXXXXXXXXXXXXXXXXXXXXXXXX

        // Prepare S3 options
        $options = array("rrs" => false, "encrypt" => false);
        if (isset($input->{'output'}->{'s3_rrs'}) &&
            $input->{'output'}->{'s3_rrs'} == true)
            $options['rrs'] = true;
        if (isset($input->{'output'}->{'s3_encrypt'}) &&
            $input->{'output'}->{'s3_encrypt'} == true)
            $options['encrypt'] = true;

        // S3 object to handle S3 put operations
        $s3Utils = new S3Utils();
        
        // Send all output files located in '$pathToOutputFiles' to S3 bucket
        if (!$handle = opendir($pathToOutputFiles))
            throw new CTException("Can't open tmp path '$pathToOutputFiles'!", 
                self::TMP_PATH_OPEN_FAIL);
        
        // Upload all resulting files sitting in same dir
        $i = 0;
        while ($entry = readdir($handle)) {
            if ($entry == "." || $entry == "..") 
                continue;

            // Destination path on S3. Sanitizing
            $s3Location = $outputFileInfo['dirname'] . "/$entry";
            $s3Location = str_replace("//", "/", $s3Location);
            
            // Send to S3
            $s3Output = $s3Utils->put_file_into_s3(
                $s3Bucket, 
                $s3Location,
                "$pathToOutputFiles/$entry", 
                $options, 
                array($this, "s3_put_processing_callback"), 
                $task
            );
        
            log_out("INFO", basename(__FILE__), 
                $s3Output['msg'],
                $this->activityLogKey);
            
            $i++;
            
            if ($i == 5)
            {
                $this->send_heartbeat($task);
                // Send progress through CTCom to notify client of finishing
                $this->CTCom->activity_finishing($task); 
                $i = 0;
            }
        }
    }
    
    // Perform custom validation on JSON input
    // Callback function used in $this->do_input_validation
    public function validate_input($input)
    {
        // VIDEO can only be transcoded into VIDEO or THUMB
        if ((
                $input->{'input_asset_type'} == VIDEO &&
                $input->{'output'}->{'output_type'} != VIDEO &&
                $input->{'output'}->{'output_type'} != THUMB &&
                $input->{'output'}->{'output_type'} != AUDIO
            )
            ||
            (
                $input->{'input_asset_type'} == IMAGE &&
                $input->{'output'}->{'output_type'} != IMAGE
            )
            ||
            (
                $input->{'input_asset_type'} == AUDIO &&
                $input->{'output'}->{'output_type'} != AUDIO
            )
            ||
            (
                $input->{'input_asset_type'} == DOC &&
                $input->{'output'}->{'output_type'} != DOC
            ))
            throw new CTException("Can't convert that 'input_type' (" . $input->{'input_asset_type'} . ") into this 'output_type' (" . $input->{'output'}->{'output_type'} . ")! Abording.", 
                self::CONVERSION_TYPE_ERROR);
    }
}


