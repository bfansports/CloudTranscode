<?php

class VideoTranscoder extends BasicTranscoder
{
    // Errors Validator
    const EXEC_VALIDATE_FAILED  = "EXEC_VALIDATE_FAILED";
    const GET_VIDEO_INFO_FAILED = "GET_VIDEO_INFO_FAILED";
    const GET_AUDIO_INFO_FAILED = "GET_AUDIO_INFO_FAILED";
    const GET_DURATION_FAILED   = "GET_DURATION_FAILED";
    const BAD_PRESETS_DIR       = "BAD_PRESETS_DIR";
    const UNKNOWN_PRESET        = "UNKNOWN_PRESET";
    // Error Transcoder
    const EXEC_FAIL       = "EXEC_FAIL";
    const TRANSCODE_FAIL  = "TRANSCODE_FAIL";
    const S3_UPLOAD_FAIL  = "S3_UPLOAD_FAIL";
    const TMP_FOLDER_FAIL = "TMP_FOLDER_FAIL";
    
    public function get_asset_info($pathToFile)
    {
        $assetInfo = array();
        
        log_out("INFO", basename(__FILE__), 
            "Running FFMPEG validation test on '" . $pathToFile . "'",
            $this->activityLogKey);
        // Execute FFMpeg
        if (!($handle = popen("ffmpeg -i $pathToFile 2>&1", 'r')))
            throw new CTException("Unable to execute FFMpeg to get information about '$pathToFile' !",
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

    public function transcode_asset($pathToFile, $inputAssetInfo, $outputDetails, $task)
    {
        // Setup transcoding command and parameters
        $outputPathToFile = $pathToFile . "transcode/" . $outputDetails->{"output_file"};
        // Create FFMpeg command
        $ffmpegArgs =  "-i $pathToFile -y -threads 0 -s " . $outputDetails->{'size'};
        $ffmpegArgs .= " -vcodec " . $outputDetails->{'video_codec'};
        $ffmpegArgs .= " -acodec " . $outputDetails->{'audio_codec'};
        $ffmpegArgs .= " -b:v " . $outputDetails->{'video_bitrate'};
        $ffmpegArgs .= " -bufsize " . $outputDetails->{'buffer_size'};
        $ffmpegArgs .= " -b:a " . $outputDetails->{'audio_bitrate'};
        $ffmpegCmd  = "ffmpeg $ffmpegArgs $outputPathToFile";
        
        // Print info
        log_out("INFO", basename(__FILE__), 
            "FFMPEG CMD:\n$ffmpegCmd\n",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Start Transcoding Asset '$pathToFile' to '$outputPathToFile' ...",
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
                if (!$this->send_heartbeat($task))
                    throw new CTException("Heartbeat failed !",
                        self::HEARTBEAT_FAILED);
                
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
        if (!file_exists($outputPathToFile) || !filesize($outputPathToFile))
            throw new CTException("Output file $outputPathToFile hasn't been created successfully or is empty !",
			    self::TRANSCODE_FAIL);
    
        // No error. Transcode successful
        log_out("INFO", basename(__FILE__), 
            "Transcoding successfull !",
            $this->activityLogKey);

        // XXX
        // XXX. HERE, Notify upload starting through SQS !
        // XXX
    
        // Sanitize output bucket "/"
        $outputBucket = str_replace("//","/",
            $outputDetails->{"output_bucket"}."/".$task["workflowExecution"]["workflowId"]);
    
        log_out("INFO", basename(__FILE__), 
            "Start uploading '$outputPathToFile' to S3 bucket '$outputBucket' ...",
            $this->activityLogKey);
        // Send output file to S3 bucket
        $this->put_file_into_s3($task, $outputBucket, 
            $outputDetails->{'output_file'}, $pathToFile);
        
        // Return success !
        log_out("INFO", basename(__FILE__), 
            "'$pathToFile' successfully transcoded and uploaded into S3 bucket '$outputBucket' !",
            $this->activityLogKey);
    }

    public function validate_preset($output)
    {
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
      
            // Calculate ratio
            $sizeSplit = explode("x", $assetInfo['size']);
            $assetInfo['ratio'] = number_format($sizeSplit[0] / $sizeSplit[1], 1);

            return true;
        }
    
        return false;
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