<?php

/**
 * This class handles the transcoding activity
 * Based on the input file type we lunch the proper transcoder
 */

require_once __DIR__.'/BasicActivity.php';

class TranscodeAssetActivity extends BasicActivity
{
    const CONVERSION_TYPE_ERROR = "CONVERSION_TYPE_ERROR";
    const TMP_PATH_OPEN_FAIL    = "TMP_PATH_OPEN_FAIL";

    private $output;
    private $pathToOutputFiles;
    
    // Perform the activity
    public function do_activity($task)
    {
        // Custom validation for transcoding. Set $this->output
        $this->validate_input();
        
        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Preparing Asset transcoding ...",
            $this->activityLogKey
        );
        
        // Call parent do_activity:
        // It download the input file we will process.
        parent::do_activity($task);

        // Set output path to store result files
        $this->set_output_path();
        
        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($this->data->{'input_type'}) 
        {
        case self::VIDEO:
            require_once __DIR__.'/transcoders/VideoTranscoder.php';

            // Instanciate transcoder to output Videos
            $videoTranscoder = new VideoTranscoder($this, $task);
            
            // Check preset file, read its content and add its data to output object
            if ($this->output->{'output_type'} == self::VIDEO)
            {
                // Validate output preset
                $videoTranscoder->validate_preset($this->output);

                // Set preset value
                $this->output->{'preset_values'} =
                    $videoTranscoder->get_preset_values($this->output);
            }
                
            // Perform transcoding
            $videoTranscoder->transcode_asset(
                $this->pathToInputFile,
                $this->pathToOutputFiles,
                $this->data->{'input_asset_info'}, 
                $this->output
            );            
            break;
        case self::IMAGE:
                
            break;
        case self::AUDIO:
                
            break;
        case self::DOC:
                
            break;
        default:
            throw new CpeSdk\CpeException("Unknown 'input_type'! Abording ...", 
                self::UNKOWN_INPUT_TYPE);
        }
        
        // Upload resulting file
        $this->upload_result_files($task);

        return null;
    }

    // Upload all output files to destination S3 bucket
    private function upload_result_files($task)
    {
        // Sanitize output bucket and file path "/"
        $s3Bucket = str_replace("//", "/",
            $this->output->{"output_bucket"});

        // XXXXXXXXXXXXXXXXXXXXXXXXXXXXX
        // XXX: Add tmp workflowID to output bucket to seperate upload
        // XXX: For testing only !
        // $s3Bucket .= "/".$task["workflowExecution"]["workflowId"];
        // XXXXXXXXXXXXXXXXXXXXXXXXXXXXX

        // Set S3 options
        $options = array("rrs" => false, "encrypt" => false);
        if (isset($this->output->{'s3_rrs'}) &&
            $this->output->{'s3_rrs'} == true) {
                    $options['rrs'] = true;
        }
        if (isset($this->output->{'s3_encrypt'}) &&
            $this->output->{'s3_encrypt'} == true) {
                    $options['encrypt'] = true;
        }
        
        // Open '$pathToOutputFiles' to read it and send all files to S3 bucket
        if (!$handle = opendir($this->pathToOutputFiles)) {
                    throw new CpeSdk\CpeException("Can't open tmp path '$this->pathToOutputFiles'!", 
                self::TMP_PATH_OPEN_FAIL);
        }
        
        // Upload all resulting files sitting in $pathToOutputFiles to S3
        while ($entry = readdir($handle)) {
            if ($entry == "." || $entry == "..") {
                            continue;
            }

            // Destination path on S3. Sanitizing
            $s3Location = $this->output->{'output_file_info'}['dirname']."/$entry";
            $s3Location = str_replace("//", "/", $s3Location);
            
            // Send to S3. We reference the callback s3_put_processing_callback
            // The callback ping back SWF so we stay alive
            $s3Output = $this->s3Utils->put_file_into_s3(
                $s3Bucket, 
                $s3Location,
                "$this->pathToOutputFiles/$entry", 
                $options, 
                array($this, "s3_put_processing_callback"), 
                $task
            );
        
            $this->cpeLogger->log_out("INFO", basename(__FILE__), 
                $s3Output['msg'],
                $this->activityLogKey);
        }
    }

    private function set_output_path()
    {
        // Create TMP folder for output files
        $outputFileInfo = pathinfo($this->output->{'output_file'});
        $this->output->{'output_file_info'} = $outputFileInfo;
        $this->pathToOutputFiles = $this->tmpPathInput."/output/" 
            . $this->activityId
            . "/".$outputFileInfo['dirname'];
        
        if (!file_exists($this->pathToOutputFiles)) {
                    if (!mkdir($this->pathToOutputFiles, 0750, true))
                throw new CpeSdk\CpeException(
                    "Unable to create temporary folder '$this->pathToOutputFiles' !",
                    self::TMP_FOLDER_FAIL
                );
        }
    }
    
    // Perform custom validation on JSON input
    // Callback function used in $this->do_input_validation
    private function validate_input()
    {
        $this->output = $this->data->{'output'};
        
        if ((
                $this->data->{'input_type'} == self::VIDEO &&
                $this->output->{'output_type'} != self::VIDEO &&
                $this->output->{'output_type'} != self::THUMB &&
                $this->output->{'output_type'} != self::AUDIO
            )
            ||
            (
                $this->data->{'input_type'} == self::IMAGE &&
                $this->output->{'output_type'} != self::IMAGE
            )
            ||
            (
                $this->data->{'input_type'} == self::AUDIO &&
                $this->output->{'output_type'} != self::AUDIO
            )
            ||
            (
                $this->data->{'input_type'} == self::DOC &&
                $this->output->{'output_type'} != self::DOC
            ))
        {
            throw new CpeSdk\CpeException("Can't convert that 'input_type' (".$this->data->{'input_type'}.") into this 'output_type' (".$this->output->{'output_type'}.")! Abording.", 
                self::CONVERSION_TYPE_ERROR);
        }
    }
}


