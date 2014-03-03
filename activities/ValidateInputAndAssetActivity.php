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

		// Perfom input validation
		if (($validation = $this->input_validator($task)) &&
            $validation['status'] == "ERROR")
            return $validation;
        $input = $validation['input'];
        
        // Create a key workflowId:activityId to put in logs
        $this->activityLogKey = $task->get("workflowExecution")['workflowId'] . ":" . $task->get("activityId");
        
        /**
         * INIT
         */
        
        // Create TMP storage to put the file to validate. See: ActivityUtils.php
        // XXX cleanup those folders regularly or we'll run out of space !!!
        if (!($localPath = 
                $this->create_tmp_local_storage($task["workflowExecution"]["workflowId"])))
            return [
                "status"  => "ERROR",
                "error"   => self::TMP_FOLDER_FAIL,
                "details" => "Unable to create temporary folder to store asset to validate !"
            ];
        $pathToFile = $localPath . $input->{'input_file'};
        
        // Get file from S3 or local copy if any
        if (($result = $this->get_file_from_s3($task, $input, $pathToFile))
            && $result["status"] == "ERROR")
            return $result;
        
        /**
         * PROCESS FILE
         */

        log_out("INFO", basename(__FILE__), "Starting Asset validation ...",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Finding information about input file '$pathToFile' - Type: " . $input->{'input_type'},
            $this->activityLogKey);
        
        // Capture input file details about format, duration, size, etc.
        if ($fileDetails = $this->get_file_details($pathToFile, $input->{'input_type'}))
        {
            // IF there is a status ERROR then it failed !
            if (isset($fileDetails["status"]) &&
                $fileDetails["status"] == "ERROR")
                return $fileDetails;
        }
        
        // XXX
        // XXX. HERE, Notify validation task success through SQS !
        // XXX

        // Create result object to be passed to next activity in the Workflow as input
        $result = [
            "status"  => "SUCCESS",
            "data"    => [
                "input_json" => $input,
                "input_file" => $fileDetails,
                "outputs"    => $input->{'outputs'}
            ]
        ];
        
        return $result;
    }
    
    // Execute ffmpeg -i to get info about the file
    private function get_file_details($pathToFile, $type)
    {
        $fileDetails = array();
        
        // Get video information
        if ($type == self::VIDEO)
        {
            log_out("INFO", basename(__FILE__), 
                "Running FFMPEG validation test on '" . $pathToFile . "'",
                $this->activityLogKey);
            // Execute FFMpeg
            if (!($handle = popen("ffmpeg -i $pathToFile 2>&1", 'r')))
                return [
                    "status"  => "ERROR",
                    "error"   => self::EXEC_FOR_INFO_FAILED,
                    "details" => "Unable to get information about the video file '$pathToFile' !"
                ];
            
            // Get output
            $ffmpegInfoOut = stream_get_contents($handle);
            if (!$ffmpegInfoOut)
                return [
                    "status"  => "ERROR",
                    "error"   => self::EXEC_FOR_INFO_FAILED,
                    "details" => "Unable to read FFMpeg output !"
                ];

            // get Duration
            if (!$this->get_duration($ffmpegInfoOut, $fileDetails))
                return [
                    "status"  => "ERROR",
                    "error"   => self::EXEC_FOR_INFO_FAILED,
                    "details" => "Unable to extract video duration !"
                ];
            
            // get Video info
            if (!$this->get_video_info($ffmpegInfoOut, $fileDetails))
                return [
                    "status"  => "ERROR",
                    "error"   => self::EXEC_FOR_INFO_FAILED,
                    "details" => "Unable to find video information !"
                ];
            
            // get Audio Info
            if (!$this->get_audio_info($ffmpegInfoOut, $fileDetails))
                return [
                    "status"  => "ERROR",
                    "error"   => self::EXEC_FOR_INFO_FAILED,
                    "details" => "Unable to find audio information !"
                ];
            
            fclose($handle);
        }
        
        return ($fileDetails);
    }

    // Extract video info
    private function get_video_info($ffmpegInfoOut, &$fileDetails)
    {
        preg_match("/: Video: (.+?) .+?, (.+?), (.+?), (.+?), (.+?),/", $ffmpegInfoOut, $matches);
        if ($matches) {
            $fileDetails['vcodec'] = $matches[1];
            $fileDetails['color'] = $matches[2];
            $fileDetails['size'] = $matches[3];
            $fileDetails['vbitrate'] = $matches[4];
            $fileDetails['fps'] = $matches[5];
            
            // Calculate ratio
            $sizeSplit = explode("x", $fileDetails['size']);
            $fileDetails['ratio'] = number_format($sizeSplit[0] / $sizeSplit[1], 1);

            return true;
        }
        
        return false;
    }

    // Extract audio info
    private function get_audio_info($ffmpegInfoOut, &$fileDetails)
    {
        preg_match("/: Audio: (.+?) .+?, (.+?), (.+?), (.+?), ([0-9]+ kb\/s).*?/", $ffmpegInfoOut, $matches);
        if ($matches) {
            $fileDetails['acodec'] = $matches[1];
            $fileDetails['freq'] = $matches[2];
            $fileDetails['mode'] = $matches[3];
            // Ignore match 4
            $fileDetails['abitrate'] = $matches[5];

            return true;
        }
        
        return false;
    }
    
    // Extract Duration
    private function get_duration($ffmpegInfoOut, &$fileDetails)
    {
        preg_match("/Duration: (.*?), start:/", $ffmpegInfoOut, $matches);
        if (!$matches)
            return false;

        $rawDuration = $matches[1];
        $ar = array_reverse(explode(":", $rawDuration));
        $duration = floatval($ar[0]);
        if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
        if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;
        $fileDetails['duration'] = $duration;

        return true;
    }
    
    // Validate input
    protected function input_validator($task)
    {
        if (($input = $this->check_task_basics($task)) &&
            $input['status'] == "ERROR") 
        {
            log_out("ERROR", basename(__FILE__), 
                $input['details'],
                $this->activityLogKey);
            return ($input);
        }

        // Return input
        return $input;
    }
}
