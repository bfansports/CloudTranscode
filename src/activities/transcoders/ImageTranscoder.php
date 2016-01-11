<?php

/**
 * This class handled Images transcoding
 * Here we the input image
 * We transcode and generate an output image using ImageMagic (convert)
 */


require_once __DIR__.'/BasicTranscoder.php';

use SA\CpeSdk;

class ImageTranscoder extends BasicTranscoder
{
    /***********************
     * TRANSCODE INPUT IMAGE
     * Below is the code used to transcode images based on $outputWanted. 
     **********************/

    // $metadata should contain the ffprobe video stream array.

    // Start FFmpeg for output transcoding
    public function transcode_asset(
        $tmpPathInput,
        $pathToInputFile, 
        $pathToOutputFiles,
        $metadata = null, 
        $outputWanted)
    {
        if ($metadata) {
            // Extract an sanitize metadata
            $metadata = $this->_extractFileInfo($metadata);
        }
        $this->cpeLogger->log_out(
            "INFO",
            basename(__FILE__),
            "CONVERT CMD:\n$convertCmd\n",
            $this->activityLogKey
        );

        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Start Transcoding Asset '$pathToInputFile' ...",
            $this->activityLogKey
        );

        if ($metadata)
            $this->cpeLogger->log_out(
                "INFO", 
                basename(__FILE__), 
                "Input Video metadata: " . print_r($metadata, true),
                $this->activityLogKey
            );

        try {
            $convertCmd = "convert ";

            // Custom command
            if (isset($outputWanted->{'custom_cmd'}) &&
                $outputWanted->{'custom_cmd'}) {
                $ffmpegCmd = $this->craft_convert_custom_cmd(
                    $tmpPathInput,
                    $pathToInputFile,
                    $pathToOutputFiles,
                    $metadata, 
                    $outputWanted
                );
            }

            // Use executer to start FFMpeg command
            // Use 'capture_progression' function as callback
            // Pass video 'duration' as parameter
            // Sleep 1sec between turns and callback every 10 turns
            // Output progression logs (true)
            $this->executer->execute(
                $convertCmd, 
                1, 
                array(2 => array("pipe", "w")),
                array($this, "capture_progression"), 
                $metadata['duration'], 
                true, 
                10
            );

            // Test if we have an output file !
            if (!file_exists($pathToOutputFiles) || 
                $this->is_dir_empty($pathToOutputFiles)) {
                throw new CpeSdk\CpeException(
                    "Output file '$pathToOutputFiles' hasn't been created successfully or is empty !",
                    self::TRANSCODE_FAIL
                );
            }

            // FFProbe the output file and return its information
            $output_info =
                $this->get_asset_info($pathToOutputFiles."/".$outputWanted->{'output_file_info'}['basename']);
        }
        catch (\Exception $e) {
            $this->cpeLogger->log_out(
                "ERROR", 
                basename(__FILE__), 
                "Execution of command '".$convertCmd."' failed: " . print_r($metadata, true). ". ".$e->getMessage(),
                $this->activityLogKey
            );
            return false;
        }

        // No error. Transcode successful
        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Transcoding successfull !",
            $this->activityLogKey
        );

        return $output_info;
    }

    // Craft custom command
    private function craft_ffmpeg_custom_cmd(
        $tmpPathInput,
        $pathToInputFile,
        $pathToOutputFiles,
        $metadata, 
        $outputWanted)
    {
        $convertCmd = $outputWanted->{'custom_cmd'};
        
        return ($convertCmd);
    }
}