<?php

require_once __DIR__ . '/../../utils/S3Utils.php';
require_once __DIR__ . '/../../utils/CommandExecuter.php';

/**
 * This class handled Video transcoding
 * Here we have function to validate and extract info from input videos
 * We also have code to transcode and generate output videos
 * We use FFmpeg and Convert to transcode and manipulate videos and images (watermark)
 */

class VideoTranscoder extends BasicTranscoder
{
    // Errors
    const EXEC_VALIDATE_FAILED  = "EXEC_VALIDATE_FAILED";
    const GET_VIDEO_INFO_FAILED = "GET_VIDEO_INFO_FAILED";
    const GET_AUDIO_INFO_FAILED = "GET_AUDIO_INFO_FAILED";
    const GET_DURATION_FAILED   = "GET_DURATION_FAILED";
    const NO_OUTPUT             = "NO_OUTPUT";
    const NO_PRESET             = "NO_PRESET";
    const BAD_PRESETS_DIR       = "BAD_PRESETS_DIR";
    const UNKNOWN_PRESET        = "UNKNOWN_PRESET";
    const OPEN_PRESET_FAILED    = "OPEN_PRESET_FAILED";
    const BAD_PRESET_FORMAT     = "BAD_PRESET_FORMAT";
    const RATIO_ERROR           = "RATIO_ERROR";
    const ENLARGEMENT_ERROR     = "ENLARGEMENT_ERROR";
    const TRANSCODE_FAIL        = "TRANSCODE_FAIL";
    const WATERMARK_ERROR       = "WATERMARK_ERROR";
    
    const SNAPSHOT_SEC_DEFAULT  = 0;
    const INTERVALS_DEFAULT     = 10;
    
    
    /***********************
     * TRANSCODE INPUT VIDEO
     * Below is the code used to transcode videos based on the JSON format
     */

    // Start FFmpeg for output transcoding
    public function transcode_asset(
        $pathToInputFile, 
        $pathToOutputFiles,
        $inputAssetInfo, 
        $outputDetails)
    {
        // Generate formatted FFMpeg CMD for VIDEO or THUMB output
        if ($outputDetails->{'output_type'} == VIDEO)
            $ffmpegCmd = $this->craft_ffmpeg_cmd_video(
                $pathToInputFile,
                $pathToOutputFiles,
                $inputAssetInfo, 
                $outputDetails
            ); 
        else if ($outputDetails->{'output_type'} == THUMB)
            $ffmpegCmd = $this->craft_ffmpeg_cmd_thumb(
                $pathToInputFile,
                $pathToOutputFiles,
                $inputAssetInfo, 
                $outputDetails
            );
        
        /* log_out( */
        /*     "INFO",  */
        /*     basename(__FILE__),  */
        /*     "FFMPEG CMD:\n$ffmpegCmd\n", */
        /*     $this->activityLogKey */
        /* ); */
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Start Transcoding Asset '$pathToInputFile' ...",
            $this->activityLogKey
        );
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Video duration (sec): " . $inputAssetInfo->{'duration'},
            $this->activityLogKey
        );
    
        // Use executer to start FFMpeg command
        // Use 'capture_progression' function as callback
        // Pass video 'duration' as parameter
        // Sleep 1sec between turns and callback every 10 turns
        // Output progression logs (true)
        $out = $this->executer->execute(
            $ffmpegCmd, 
            1, 
            array(2 => array("pipe", "w")),
            array($this, "capture_progression"), 
            $inputAssetInfo->{'duration'}, 
            true, 
            10
        );
        
        // Test if we have an output file !
        if (!file_exists($pathToOutputFiles) || is_dir_empty($pathToOutputFiles))
            throw new CTException(
                "Output file '$pathToOutputFiles' hasn't been created successfully or is empty !",
                self::TRANSCODE_FAIL
            );
    
        // No error. Transcode successful
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Transcoding successfull !",
            $this->activityLogKey
        );
    }

    // Generate FFmpeg command for video transcoding
    private function craft_ffmpeg_cmd_video(
        $pathToInputFile,
        $pathToOutputFiles,
        $inputAssetInfo, 
        $outputDetails)
    {
        // Check if a size is provided to override preset size
        $size = $this->set_output_video_size($inputAssetInfo, $outputDetails);
        
        $videoCodec = $outputDetails->{'preset_values'}->{'video_codec'};
        if (isset($outputDetails->{'video_codec'}))
            $videoCodec = $outputDetails->{'video_codec'};
        
        $audioCodec = $outputDetails->{'preset_values'}->{'audio_codec'};
        if (isset($outputDetails->{'audio_codec'}))
            $audioCodec = $outputDetails->{'audio_codec'};

        $videoBitrate = $outputDetails->{'preset_values'}->{'video_bitrate'};
        if (isset($outputDetails->{'video_bitrate'}))
            $videoBitrate = $outputDetails->{'video_bitrate'};
        
        $audioBitrate = $outputDetails->{'preset_values'}->{'audio_bitrate'};
        if (isset($outputDetails->{'audio_bitrate'}))
            $audioBitrate = $outputDetails->{'audio_bitrate'};

        $frameRate = $outputDetails->{'preset_values'}->{'frame_rate'};
        if (isset($outputDetails->{'frame_rate'}))
            $frameRate = $outputDetails->{'frame_rate'};
        
        if (isset($outputDetails->{'preset_values'}->{'video_codec_options'}))
            $formattedOptions = 
                $this->set_output_video_codec_options($outputDetails->{'preset_values'}->{'video_codec_options'});
        
        // Process options for watermark
        if (isset($outputDetails->{'watermark'}) && $outputDetails->{'watermark'})
            $watermarkOptions = 
                $this->get_watermark_options($pathToInputFile,
                    $outputDetails->{'watermark'});
        
        // Create FFMpeg arguments
        $ffmpegArgs =  " -i $pathToInputFile -y -threads 0";
        $ffmpegArgs .= " -s $size";
        $ffmpegArgs .= " -vcodec $videoCodec";
        $ffmpegArgs .= " -acodec $audioCodec";
        $ffmpegArgs .= " -b:v $videoBitrate";
        $ffmpegArgs .= " -b:a $audioBitrate";
        $ffmpegArgs .= " -r $frameRate";
        $ffmpegArgs .= " $formattedOptions";
        $ffmpegArgs .= " $watermarkOptions";
        
        // Append output filename to path
        $pathToOutputFiles .= "/" . $outputDetails->{'output_file_info'}['basename'];
        // Final command
        $ffmpegCmd  = "ffmpeg $ffmpegArgs $pathToOutputFiles";
            
        return ($ffmpegCmd);
    }
    
    // Craft FFMpeg command to generate thumbnails
    private function craft_ffmpeg_cmd_thumb(
        $pathToInputFile,
        $pathToOutputFiles,
        $inputAssetInfo, 
        $outputDetails)
    {
        $outputFileInfo = pathinfo($outputDetails->{'output_file'});
        if ($outputDetails->{'mode'} == 'snapshot')
        {
            $snapshot_sec = self::SNAPSHOT_SEC_DEFAULT;
            if (isset($outputDetails->{'snapshot_sec'}) &&
                $outputDetails->{'snapshot_sec'} > 0)
                $snapshot_sec = $outputDetails->{'snapshot_sec'};
                
            $time = gmdate("H:i:s", $snapshot_sec) . ".000";
            $pathToOutputFiles .= "/" . $outputFileInfo['basename'];
            $frameOptions = " -vframes 1 -ss $time $pathToOutputFiles";
        }
        else if ($outputDetails->{'mode'} == 'intervals')
        {
            $intervals = self::INTERVALS_DEFAULT;
            if (isset($outputDetails->{'intervals'}) &&
                $outputDetails->{'intervals'} > 0)
                $intervals = $outputDetails->{'intervals'};
            
            $pathToOutputFiles .= "/" . $outputFileInfo['basename'] . "%06d." 
                . $outputFileInfo['extension'];
            $frameOptions = " -vf fps=fps=1/$intervals $pathToOutputFiles";
        }

        // Create FFMpeg arguments
        $ffmpegArgs =  " -i $pathToInputFile -y -threads 0 -f image2";
        $ffmpegArgs .= " -s " . $outputDetails->{'size'};
        $ffmpegArgs .= " $frameOptions";

        // Final command
        $ffmpegCmd  = "ffmpeg $ffmpegArgs $pathToOutputFiles";
        
        return ($ffmpegCmd);
    }

    // Get watermark info to generate overlay options for ffmpeg
    private function get_watermark_options(
        $pathToVideo,
        $watermarkOptions)
    {
        // Get info about the video in order to save the watermark in same location
        $videoFileInfo = pathinfo($pathToVideo);
        $watermarkFileInfo = pathinfo($watermarkOptions->{'input_file'});
        $watermarkPath = $videoFileInfo['dirname']."/".$watermarkFileInfo['basename'];
        $newWatermarkPath = $videoFileInfo['dirname']."/new-".$watermarkFileInfo['basename'];
        
        // Get watermark image from S3
        $s3Utils = new S3Utils();
        $s3Output = $s3Utils->get_file_from_s3($watermarkOptions->{'input_bucket'}, 
            $watermarkOptions->{'input_file'},
            $watermarkPath);
        log_out("INFO", basename(__FILE__), 
            $s3Output['msg'],
            $this->activityLogKey);

        // Transform watermark for opacity
        $convertCmd = "convert $watermarkPath -alpha on -channel A -evaluate Multiply " . $watermarkOptions->{'opacity'} . " +channel $newWatermarkPath";
        $out = $this->executer->execute($convertCmd, 1, 
            array(1 => array("pipe", "w"), 2 => array("pipe", "w")),
            false, false, 
            false, 1);
        if (isset($out['outErr']) && $out['outErr'] != "" &&
            (!file_exists($newWatermarkPath) || !filesize($newWatermarkPath)))
            throw new CTException(
                "Error transforming watermark file '$watermarkPath'!",
                self::WATERMARK_ERROR);
        
        // Format options for FFMpeg
        // XXX Work on watermark position !
        $size = explode('x', $watermarkOptions->{'size'});
        $width = $size[0];
        $height = $size[1];
        $positions = $this->get_watermark_position($watermarkOptions);
        $formattedOptions = "-vf \"movie=$newWatermarkPath, scale=$width:$height [wm]; [in][wm] overlay=" . $positions['x'] . ':' . $positions['y'] . " [out]\"";
        return ($formattedOptions);
    }

    // Generate the command line option to position the watermark
    private function get_watermark_position($watermarkOptions)
    {
        $positions = array('x' => 0, 'y' => 0);
        
        if ($watermarkOptions->{'x'} >= 0)
            $positions['x'] = $watermarkOptions->{'x'};
        if ($watermarkOptions->{'y'} >= 0)
            $positions['y'] = $watermarkOptions->{'y'};
        if ($watermarkOptions->{'x'} < 0)
            $positions['x'] = 'main_w-overlay_w' . $watermarkOptions->{'x'};
        if ($watermarkOptions->{'y'} < 0)
            $positions['y'] = 'main_h-overlay_h' . $watermarkOptions->{'y'};

        return ($positions);
    }

    // Get Video codec options and format the options properly for ffmpeg
    private function set_output_video_codec_options($videoCodecOptions)
    {
        $formattedOptions = "";
        $options = explode(",", $videoCodecOptions);
        foreach ($options as $option)
        {
            $keyVal = explode("=", $option);
            if ($keyVal[0] === 'Profile')
                $formattedOptions .= " -profile:v ".$keyVal[1];
            else if ($keyVal[0] === 'Level')
                $formattedOptions .= " -level ".$keyVal[1];
            else if ($keyVal[0] === 'MaxReferenceFrames')
                $formattedOptions .= " -refs ".$keyVal[1];
        }

        return ($formattedOptions);
    }

    // Verify Ratio and Size of output file to ensure it respect restrictions
    // Return the output video size
    private function set_output_video_size($inputAssetInfo, $outputDetails)
    {
        // Handle video size
        $size = $outputDetails->{'preset_values'}->{'size'};
        if (isset($outputDetails->{'size'}))
            $size = $outputDetails->{'size'};
        // Ratio check
        if (!isset($outputDetails->{'keep_ratio'}) || 
            $outputDetails->{'keep_ratio'} == 'true') {
            $outputRatio = floatval($this->get_ratio($size));
            $inputRatio = floatval($inputAssetInfo->{'ratio'});
            if ($outputRatio != $inputRatio)
                throw new CTException(
                    "Output video ratio is different from input video: input_ratio: '$inputRatio' / output_ratio: '$outputRatio'. 'keep_ratio' option is enabled (default). Disable it to allow ratio change.",
                    self::RATIO_ERROR
                );
        }
        // Enlargement check
        if (!isset($outputDetails->{'no_enlarge'}) || 
            $outputDetails->{'no_enlarge'} == 'true') {
            $inputSize = $inputAssetInfo->{'size'};
            $inputSizeSplit = explode("x", $inputSize);
            $outputSizeSplit = explode("x", $size);
            if (intval($outputSizeSplit[0]) > intval($inputSizeSplit[0]) ||
                intval($outputSizeSplit[1]) > intval($inputSizeSplit[1]))
                throw new CTException(
                    "Output video size is larger than input video: input_size: '$inputSize' / output_size: '$size'. 'no_enlarge' option is enabled (default). Disable it to allow enlargement.",
                    self::ENLARGEMENT_ERROR
                );
        }

        return ($size);
    }
    
    // REad ffmpeg output and calculate % progress
    // This is a callback called from 'CommandExecuter.php'
    // $out and $outErr contain FFmpeg output
    public function capture_progression($duration, $out, $outErr)
    {
        // We also call a callback here ... the 'send_hearbeat' function from the origin activity
        // This way we notify SWF that we are alive !
        call_user_func(array($this->activityObj, 'send_heartbeat'), 
            $this->task);

        // # get the current time
        preg_match_all("/time=(.*?) bitrate/", $outErr, $matches); 

        $last = array_pop($matches);
        // # this is needed if there is more than one match
        if (is_array($last))
            $last = array_pop($last);

        // Perform Time transformation to get seconds
        $ar = array_reverse(explode(":", $last));
        $done = floatval($ar[0]);
        if (!empty($ar[1])) $done += intval($ar[1]) * 60;
        if (!empty($ar[2])) $done += intval($ar[2]) * 60 * 60;

        // # finally, progress is easy
        $progress = 0;
        if ($done)
            $progress = round(($done/$duration)*100);
        log_out(
            "INFO", 
            basename(__FILE__), 
            "Progress: $done / $progress%",
            $this->activityLogKey
        );
    }

    // Combine preset and custom output settings to generate output settings
    public function get_preset_values($output_wanted)
    {
        if (!$output_wanted)
            throw new CTException("No output data provided to transcoder !",
                self::NO_OUTPUT);

        if (!isset($output_wanted->{"preset"}))
            throw new CTException("No preset selected for output !",
                self::BAD_PRESETS_DIR);
        
        $preset = $output_wanted->{"preset"};
        $presetPath = __DIR__ . '/../../../config/presets/';

        if (!($presetContent = file_get_contents($presetPath.$preset.".json")))
            throw new CTException("Can't open preset file !",
                self::OPEN_PRESET_FAILED);
        
        if (!($decodedPreset = json_decode($presetContent)))
            throw new CTException("Bad preset JSON format !",
                self::BAD_PRESET_FORMAT);
        
        return ($decodedPreset);
    }
    
    // Check if the preset exists
    public function validate_preset($output)
    {
        if (!isset($output->{"preset"}))
            throw new CTException("No preset selected for output !",
                self::BAD_PRESETS_DIR);

        $preset = $output->{"preset"};
        $presetPath = __DIR__ . '/../../../config/presets/';
        if (!($files = scandir($presetPath)))
            throw new CTException("Unable to open preset directory '$presetPath' !",
                self::BAD_PRESETS_DIR);
        
        foreach ($files as $presetFile)
        {
            if ($presetFile === '.' || $presetFile === '..') { continue; }
            if (is_file("$presetPath/$presetFile"))
            {
                if ($preset === pathinfo($presetFile)["filename"])
                    return true;
            }
        }
        
        throw new CTException("Unkown preset file '$preset' !",
            self::UNKNOWN_PRESET);
    }
    





    
    /**************************************
     * GET VIDEO INFORMATION AND VALIDATION
     * The methods below are used by the ValidationActivity
     * We capture as much info as possible on the input video
     */

    // Execute FFMpeg to get video information
    public function get_asset_info($pathToInputFile)
    {
        $assetInfo = array();
        
        // Execute FFMpeg to validate and get information about input video
        $out = $this->executer->execute("ffmpeg -i $pathToInputFile", 1, 
            array(2 => array("pipe", "w")),
            false, false, 
            false, 1);
        
        if (!$out['outErr'] && !$out['out'])
            throw new CTException("Unable to execute FFMpeg to get information about '$pathToInputFile'! FFMpeg didn't return anything!",
                self::EXEC_VALIDATE_FAILED);
        
        // FFmpeg writes on STDERR ...
        $ffmpegInfoOut = $out['outErr'];
        
        // get Duration
        if (!$this->get_duration($ffmpegInfoOut, $assetInfo))
            throw new CTException("Unable to extract video duration !",
                self::GET_DURATION_FAILED);
      
        // get Video info
        if (!$this->get_video_info($ffmpegInfoOut, $assetInfo))
            throw new CTException("Unable to find video information !",
                self::GET_VIDEO_INFO_FAILED);
      
        // get Audio Info
        if (!$this->get_audio_info($ffmpegInfoOut, $assetInfo))
            throw new CTException("Unable to find audio information !",
                self::GET_AUDIO_INFO_FAILED);

        return ($assetInfo);
    }

    // Extract video info
    private function get_video_info($ffmpegInfoOut, &$assetInfo)
    {
        preg_match("/: Video: (.+?) .+?, (.+?), (.+?), (.+?), (.+?),/", 
            $ffmpegInfoOut, $matches);
        if ($matches) {
            $assetInfo['vcodec'] = $matches[1];
            $assetInfo['color'] = $matches[2];
            $assetInfo['size'] = $matches[3];
            $assetInfo['vbitrate'] = $matches[4];
            $assetInfo['fps'] = $matches[5];
      
            $assetInfo['ratio'] = $this->get_ratio($assetInfo['size']);
                
            return true;
        }
    
        return false;
    }
    
    // Calculate ratio based on the size provided
    private function get_ratio($size)
    {
        // Calculate ratio
        $sizeSplit = explode("x", $size);
        return (number_format($sizeSplit[0] / $sizeSplit[1], 1));
    }
    
    // Extract audio info
    private function get_audio_info($ffmpegInfoOut, &$assetInfo)
    {
        preg_match("/: Audio: (.+?) .+?, (.+?), (.+?), (.+?), ([0-9]+ kb\/s).*?/", 
            $ffmpegInfoOut, $matches);
        if ($matches) {
            $assetInfo['acodec'] = $matches[1];
            $assetInfo['freq'] = $matches[2];
            $assetInfo['mode'] = $matches[3];
            // Ignore match 4
            $assetInfo['abitrate'] = $matches[5];

            return true;
        }
    
        return false;
    }
  
    // Extract Duration
    private function get_duration($ffmpegInfoOut, &$assetInfo)
    {
        preg_match("/Duration: (.*?), start:/", $ffmpegInfoOut, $matches);
        if (!$matches)
            return false;

        $rawDuration = $matches[1];
        $ar = array_reverse(explode(":", $rawDuration));
        $duration = floatval($ar[0]);
        if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
        if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;
        $assetInfo['duration'] = $duration;

        return true;
    }
}