<?php

class VideoTranscoder extends BasicTranscoder
{
    // Errors Validator
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
    
    // Error Transcoder
    const EXEC_FAIL       = "EXEC_FAIL";
    const TRANSCODE_FAIL  = "TRANSCODE_FAIL";
    const S3_UPLOAD_FAIL  = "S3_UPLOAD_FAIL";
    const TMP_FOLDER_FAIL = "TMP_FOLDER_FAIL";
    
    public function get_asset_info($pathToInputFile)
    {
        $assetInfo = array();
        
        log_out("INFO", basename(__FILE__), 
            "Running FFMPEG validation test on '" . $pathToInputFile . "'",
            $this->activityLogKey);
        // Execute FFMpeg
        if (!($handle = popen("ffmpeg -i $pathToInputFile 2>&1", 'r')))
            throw new CTException("Unable to execute FFMpeg to get information about '$pathToInputFile' !",
                self::EXEC_VALIDATE_FAILED);
      
        // Get output
        if (!($ffmpegInfoOut = stream_get_contents($handle)))
            throw new CTException("Unable to read FFMpeg output !",
                self::EXEC_VALIDATE_FAILED);
        fclose($handle);

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

    // Generate FFmpeg command for output transcoding
    private function generate_ffmpeg_cmd($pathToInputFile,
        $inputAssetInfo, &$outputDetails)
    {
        $inputFileInfo = pathinfo($pathToInputFile);
        // TMP path to output file 
        $pathToOutputFile = $outputDetails->{'path_to_output_file'} = $inputFileInfo['dirname'] . "/transcode/" . $outputDetails->{"output_file"};
        
        $size = $this->get_video_size($inputAssetInfo, $outputDetails);
        
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
                $this->get_video_codec_options($outputDetails->{'preset_values'}->{'video_codec_options'});

        // Create FFMpeg arguments
        $ffmpegArgs =  "-i $pathToInputFile -y -threads 0 -s $size";
        $ffmpegArgs .= " -vcodec $videoCodec";
        $ffmpegArgs .= " -acodec $audioCodec";
        $ffmpegArgs .= " -b:v $videoBitrate";
        $ffmpegArgs .= " -b:a $audioBitrate";
        $ffmpegArgs .= " -r $frameRate";
        $ffmpegArgs .= " $formattedOptions";
        
        // Final command
        $ffmpegCmd  = "ffmpeg $ffmpegArgs $pathToOutputFile";
        
        return ($ffmpegCmd);
    }

    // Get Video codec options and format the options properly for ffmpeg
    private function get_video_codec_options($videoCodecOptions)
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
    private function get_video_size($inputAssetInfo, $outputDetails)
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
                throw new CTException("Output video ratio is different from input video: input_ratio: '$inputRatio' / output_ratio: '$outputRatio'. 'keep_ratio' option is enabled (default). Disable it to allow ratio change.",
                    self::RATIO_ERROR);
        }
        // Enlargement check
        if (!isset($outputDetails->{'no_enlarge'}) || 
            $outputDetails->{'no_enlarge'} == 'true') {
            $inputSize = $inputAssetInfo->{'size'};
            $inputSizeSplit = explode("x", $inputSize);
            $outputSizeSplit = explode("x", $size);
            if (intval($outputSizeSplit[0]) > intval($inputSizeSplit[0]) ||
                intval($outputSizeSplit[1]) > intval($inputSizeSplit[1]))
                throw new CTException("Output video size is larger than input video: input_size: '$inputSize' / output_size: '$size'. 'no_enlarge' option is enabled (default). Disable it to allow enlargement.",
                    self::ENLARGEMENT_ERROR);
        }

        return ($size);
    }

    // Start FFmpeg for output transcoding
    public function transcode_asset($pathToInputFile, $inputAssetInfo, 
        $outputDetails, $task, $activityObj)
    {
        $ffmpegCmd = $this->generate_ffmpeg_cmd($pathToInputFile,
            $inputAssetInfo, $outputDetails);

        $pathToOutputFile = $outputDetails->{'path_to_output_file'};
        
        // Print info
        log_out("INFO", basename(__FILE__), 
            "FFMPEG CMD:\n$ffmpegCmd\n",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Start Transcoding Asset '$pathToInputFile' to '$pathToOutputFile' ...",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Video duration (sec): " . $inputAssetInfo->{'duration'},
            $this->activityLogKey);
    
        // Command output capture method: pipe STDERR (FFMpeg print out on STDERR)
        $descriptorSpecs = array(  
            2 => array("pipe", "w") 
        );
        // Start execution
        if (!($process = proc_open($ffmpegCmd, $descriptorSpecs, $pipes)))
            throw new CTException("Unable to execute command:\n$ffmpegCmd\n",
                self::EXEC_FAIL);
        // Is resource valid ?
        if (!is_resource($process))
            throw new CTException("Process execution has failed:\n$ffmpegCmd\n",
                self::EXEC_FAIL);

        // XXX
        // XXX. HERE, Notify task start through SQS !
        // XXX

        // While process running, we read output
        $ffmpegOut = "";
        $i = 0;
        $procStatus = proc_get_status($process);
        while ($procStatus['running']) {
            // REad prog output
            $out = fread($pipes[2], 8192);

            # Concat out
            $ffmpegOut .= $out;

            // Get progression and notify SWF with heartbeat
            if ($i == 10) {
                echo ".\n";
                $progress = $this->capture_progression($ffmpegOut, 
                    $inputAssetInfo->{'duration'});

                // XXX
                // XXX. HERE, Notify task progress through SQS !
                // XXX

                // Notify SWF that we are still running !
                call_user_func(array($activityObj,
                        "send_heartbeat"), $task);
                
                $i = 0;
            }
      
            // Get latest status
            $procStatus = proc_get_status($process);
            
            // Print progression
            echo ".";
            flush();
            
            $i++;
            sleep(1);
        }
        echo "\n";
    
        // FFMPEG process is over
        proc_close($process);

        // Test if we have an output file !
        if (!file_exists($pathToOutputFile) || !filesize($pathToOutputFile))
            throw new CTException("Output file $pathToOutputFile hasn't been created successfully or is empty !",
                self::TRANSCODE_FAIL);
    
        // No error. Transcode successful
        log_out("INFO", basename(__FILE__), 
            "Transcoding successfull !",
            $this->activityLogKey);
        
        return ($pathToOutputFile);
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

    // REad ffmpeg output and calculate % progress
    private function capture_progression($ffmpegOut, $duration)
    {
        // # get the current time
        preg_match_all("/time=(.*?) bitrate/", $ffmpegOut, $matches); 

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
        log_out("INFO", basename(__FILE__), "Progress: $done / $progress%",
            $this->activityLogKey);

        return ($progress);
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