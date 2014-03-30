<?php

/**
 * This class validate the JSON input. 
 * Makes sure the input files to be transcoded exists and is valid.
 */

class ValidateInputAndAssetActivity extends BasicActivity
{
    // Errors
    const EXEC_FOR_INFO_FAILED = "EXEC_FOR_INFO_FAILED";
  
    // Perform the activity
    public function do_activity($task)
    {
        // XXX
        // XXX. HERE, Notify validation task starts through SQS !
        // XXX
        
        $activityId   = $task->get("activityId");
        $activityType = $task->get("activityType");
        
        // Perfom input validation
        $input = $this->do_input_validation($task, $activityType["name"]);
    
        // Create a key workflowId:activityId to put in logs
        $this->activityLogKey = $task->get("workflowExecution")['workflowId'] . ":$activityId";
    
        /**
         * INIT
         */
        // Create TMP storage to put the file to validate. 
        $inputFileInfo = pathinfo($input->{'input_file'});
        $localPath = 
            $this->create_tmp_local_storage($task["workflowExecution"]["workflowId"],
                $inputFileInfo['dirname']);
        $pathToFile = $localPath . $inputFileInfo['basename'];
    
        // Get file from S3 or local copy if any
        $this->get_file_from_s3($task, $input, $pathToFile);
    
        /**
         * PROCESS FILE
         */
        log_out("INFO", basename(__FILE__), "Starting Asset validation ...",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Finding information about input file '$pathToFile' - Type: " . $input->{'input_type'},
            $this->activityLogKey);
    
        // Capture input file details about format, duration, size, etc.
        $fileinfo = $this->get_file_details($pathToFile, $input->{'input_type'});
    
        // XXX
        // XXX. HERE, Notify validation task success through SQS !
        // XXX

        // Create result object to be passed to next activity in the Workflow as input
        $result = [
            "input_json"     => $input, // Original JSON
            "input_fileinfo" => $fileinfo, // Input file detailed info
            "outputs"        => $input->{'outputs'} // Outputs to generate
        ];
    
        return $result;
    }
  
    // Execute ffmpeg -i to get info about the file
    private function get_file_details($pathToFile, $type)
    {
        $fileinfo = array();
    
        // Get video information
        if ($type == self::VIDEO)
        {
            log_out("INFO", basename(__FILE__), 
                "Running FFMPEG validation test on '" . $pathToFile . "'",
                $this->activityLogKey);
            // Execute FFMpeg
            if (!($handle = popen("ffmpeg -i $pathToFile 2>&1", 'r')))
                throw new CTException("Unable to get information about the video file '$pathToFile' !",
                    self::EXEC_FOR_INFO_FAILED);
      
            // Get output
            if (!($ffmpegInfoOut = stream_get_contents($handle)))
                throw new CTException("Unable to read FFMpeg output !",
                    self::EXEC_FOR_INFO_FAILED);

            // get Duration
            if (!$this->get_duration($ffmpegInfoOut, $fileinfo))
                throw new CTException("Unable to extract video duration !",
                    self::EXEC_FOR_INFO_FAILED);
      
            // get Video info
            if (!$this->get_video_info($ffmpegInfoOut, $fileinfo))
                throw new CTException("Unable to find video information !",
                    self::EXEC_FOR_INFO_FAILED);
      
            // get Audio Info
            if (!$this->get_audio_info($ffmpegInfoOut, $fileinfo))
                throw new CTException("Unable to find audio information !",
                    self::EXEC_FOR_INFO_FAILED);
      
            fclose($handle);
        }
    
        return ($fileinfo);
    }

    // Extract video info
    private function get_video_info($ffmpegInfoOut, &$fileinfo)
    {
        preg_match("/: Video: (.+?) .+?, (.+?), (.+?), (.+?), (.+?),/", $ffmpegInfoOut, $matches);
        if ($matches) {
            $fileinfo['vcodec'] = $matches[1];
            $fileinfo['color'] = $matches[2];
            $fileinfo['size'] = $matches[3];
            $fileinfo['vbitrate'] = $matches[4];
            $fileinfo['fps'] = $matches[5];
      
            // Calculate ratio
            $sizeSplit = explode("x", $fileinfo['size']);
            $fileinfo['ratio'] = number_format($sizeSplit[0] / $sizeSplit[1], 1);

            return true;
        }
    
        return false;
    }

    // Extract audio info
    private function get_audio_info($ffmpegInfoOut, &$fileinfo)
    {
        preg_match("/: Audio: (.+?) .+?, (.+?), (.+?), (.+?), ([0-9]+ kb\/s).*?/", $ffmpegInfoOut, $matches);
        if ($matches) {
            $fileinfo['acodec'] = $matches[1];
            $fileinfo['freq'] = $matches[2];
            $fileinfo['mode'] = $matches[3];
            // Ignore match 4
            $fileinfo['abitrate'] = $matches[5];

            return true;
        }
    
        return false;
    }
  
    // Extract Duration
    private function get_duration($ffmpegInfoOut, &$fileinfo)
    {
        preg_match("/Duration: (.*?), start:/", $ffmpegInfoOut, $matches);
        if (!$matches)
            return false;

        $rawDuration = $matches[1];
        $ar = array_reverse(explode(":", $rawDuration));
        $duration = floatval($ar[0]);
        if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
        if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;
        $fileinfo['duration'] = $duration;

        return true;
    }
  
}
