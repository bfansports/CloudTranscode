<?php

/**
 * This class handles the transcoding activity
 * Based on the input file type we lunch the proper transcoder
 */

require_once __DIR__.'/BasicActivity.php';
require_once __DIR__.'/TranscodeAssetClientInterface.php';

use SA\CpeSdk;

class TranscodeAssetActivity extends BasicActivity
{
    const CONVERSION_TYPE_ERROR = "CONVERSION_TYPE_ERROR";
    const TMP_PATH_OPEN_FAIL    = "TMP_PATH_OPEN_FAIL";

    private $output;
    private $outputFilesPath;
    
    // Perform the activity
    public function process($task)
    {
        // Save output object
        $this->output = $this->input->{'output_asset'};
        
        // Custom validation for transcoding. Set $this->output
        $this->validateInput();
        
        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Preparing Asset transcoding ...",
            $task['token']
        );
        
        // Call parent do_activity:
        // It download the input file we will process.
        parent::process($task);
        
        // Set output path to store result files
        $this->setOutputPath($task);

        // Result output
        $result = null;

        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($this->input->{'input_asset'}->{'type'}) 
        {
        case self::VIDEO:
            require_once __DIR__.'/transcoders/VideoTranscoder.php';

            // Instanciate transcoder to output Videos
            $videoTranscoder = new VideoTranscoder($this, $task);
            
            // Check preset file, read its content and add its data to output object
            if ($this->output->{'type'} == self::VIDEO &&
                isset($this->output->{'preset'}))
            {
                // Validate output preset
                $videoTranscoder->validate_preset($this->output);

                // Set preset value
                $this->output->{'preset_values'} =
                    $videoTranscoder->get_preset_values($this->output);
            }

            # If we have metadata, we expect the output of ffprobe
            $metadata = null;
            if (isset($this->input->{'input_asset_metadata'})) 
                $metadata = $this->input->{'input_asset_metadata'};
                
            // Perform transcoding
            $result = $videoTranscoder->transcode_asset(
                $this->tmpPathInput,
                $this->inputFilePath,
                $this->outputFilesPath,
                $metadata, 
                $this->output
            );

            unset($videoTranscoder);

            break;
        case self::IMAGE:
            require_once __DIR__.'/transcoders/ImageTranscoder.php';
            
            // Instanciate transcoder to output Images
            $imageTranscoder = new ImageTranscoder($this, $task);

            # If we have metadata, we expect the output of ffprobe
            $metadata = null;
            if (isset($this->input->{'input_asset_metadata'})) 
                $metadata = $this->input->{'input_asset_metadata'};
            
            // Perform transcoding
            $result = $imageTranscoder->transcode_asset(
                $this->tmpPathInput,
                $this->inputFilePath,
                $this->outputFilesPath,
                $metadata, 
                $this->output
            );
            
            unset($imageTranscoder);
                
            break;
        case self::AUDIO:
                
            break;
        case self::DOC:
                
            break;
        default:
            throw new CpeSdk\CpeException("Unknown input asset 'type'! Abording ...", 
                self::UNKOWN_INPUT_TYPE);
        }
        
        // Upload resulting file
        $this->uploadResultFiles($task);
        
        return $result;
    }

    // Upload all output files to destination S3 bucket
    private function uploadResultFiles($task)
    {
        // Sanitize output bucket and file path "/"
        $s3Bucket = str_replace("//", "/",
            $this->output->{"bucket"});

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
        
        // Open '$outputFilesPath' to read it and send all files to S3 bucket
        if (!$handle = opendir($this->outputFilesPath)) {
            throw new CpeSdk\CpeException("Can't open tmp path '$this->outputFilesPath'!", 
                self::TMP_PATH_OPEN_FAIL);
        }
        
        // Upload all resulting files sitting in $outputFilesPath to S3
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
                "$this->outputFilesPath/$entry", 
                $options, 
                array($this, "s3_put_processing_callback"), 
                $task
            );
            // We delete the TMP file once uploaded
            unlink("$this->outputFilesPath/$entry");
            
            $this->cpeLogger->log_out("INFO", basename(__FILE__), 
                $s3Output['msg'],
                $task['token']);
        }
    }

    private function setOutputPath($task)
    {
        $this->outputFilesPath = self::TMP_FOLDER 
            . $task["workflowExecution"]["workflowId"]."/output/" 
            . $this->activityId;
        
        // Create TMP folder for output files
        $outputFileInfo = pathinfo($this->output->{'file'});
        $this->output->{'output_file_info'} = $outputFileInfo;
        $this->outputFilesPath .= "/".$outputFileInfo['dirname'];
        
        if (!file_exists($this->outputFilesPath)) 
        {
            if ($this->debug)
                $this->cpeLogger->log_out("INFO", basename(__FILE__), 
                    "Creating TMP output folder '".$this->outputFilesPath."'",
                    $task['token']);

            if (!mkdir($this->outputFilesPath, 0750, true))
                throw new CpeSdk\CpeException(
                    "Unable to create temporary folder '$this->outputFilesPath' !",
                    self::TMP_FOLDER_FAIL
                );
        }
    }
    
    // Perform custom validation on JSON input
    // Callback function used in $this->do_input_validation
    private function validateInput()
    {
        
        if ((
                $this->input->{'input_asset'}->{'type'} == self::VIDEO &&
                $this->output->{'type'} != self::VIDEO &&
                $this->output->{'type'} != self::THUMB &&
                $this->output->{'type'} != self::AUDIO
            )
            ||
            (
                $this->input->{'input_asset'}->{'type'} == self::IMAGE &&
                $this->output->{'type'} != self::IMAGE
            )
            ||
            (
                $this->input->{'input_asset'}->{'type'} == self::AUDIO &&
                $this->output->{'type'} != self::AUDIO
            )
            ||
            (
                $this->input->{'input_asset'}->{'type'} == self::DOC &&
                $this->output->{'type'} != self::DOC
            ))
        {
            throw new CpeSdk\CpeException("Can't convert that input asset 'type' (".$this->input->{'input_asset'}->{'type'}.") into this output asset 'type' (".$this->output->{'type'}.")! Abording.", 
                self::CONVERSION_TYPE_ERROR);
        }
    }
}


