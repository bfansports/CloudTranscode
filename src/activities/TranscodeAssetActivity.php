<?php

/**
 * This class performs transcoding
 * FFMpeg only for now
 */
class TranscodeAssetActivity extends BasicActivity
{
    private $inputFile;
    private $inputJSON;

    // Errors
    const EXEC_FAIL       = "EXEC_FAIL";
    const TRANSCODE_FAIL  = "TRANSCODE_FAIL";
    const S3_UPLOAD_FAIL  = "S3_UPLOAD_FAIL";
    const TMP_FOLDER_FAIL = "TMP_FOLDER_FAIL";

    // Perform the activity
    public function do_activity($task)
    {
        // XXX
        // XXX. HERE, Notify transcode task initializing through SQS !
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
        // Referencing input variables
        $this->inputFileinfo = $input->{"input_fileinfo"}; // Input file details
        $this->inputJSON     = $input->{"input_json"};     // Original JSON input
    
        // Create TMP storage to put the file to validate. See: ActivityUtils.php
        // XXX cleanup those folders regularly or we'll run out of space !!!
        $localPath = $this->create_tmp_local_storage($task["workflowExecution"]["workflowId"]);
        $pathToFile = $localPath . $this->inputJSON['input_file'];
    
        // Get file from S3 or local copy if any
        $this->get_file_from_s3($task, $this->inputJSON, $pathToFile);
    
    
        /**
         * PROCESS
         */
        // Setup transcoding command and parameters
        $outputConfig  = $input->{"output"}; // JSON description of the transcode to do
        $outputPathToFile = $localPath . "transcode/" . $outputConfig->{"file"};
        // Create FFMpeg command
        $ffmpegArgs    = "-i $pathToFile -y -threads 0 -s " . $outputConfig->{'size'} . " -vcodec " . $outputConfig->{'video_codec'} . " -acodec " . $outputConfig->{'audio_codec'} . " -b:v " . $outputConfig->{'video_bitrate'} . " -bufsize " . $outputConfig->{'buffer_size'} . " -b:a " . $outputConfig->{'audio_bitrate'} . " $outputPathToFile";
        $ffmpegCmd     = "ffmpeg $ffmpegArgs";
    
        // Print info
        log_out("INFO", basename(__FILE__), 
            "FFMPEG CMD:\n$ffmpegCmd\n",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Start Transcoding Asset '$pathToFile' to '$outputPathToFile' ...",
            $this->activityLogKey);
        log_out("INFO", basename(__FILE__), 
            "Video duration (sec): " . $this->inputFileinfo->{'duration'},
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
                $progress = $this->capture_progression($ffmpegOut);

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
        $outputBucket = str_replace("//","/",$outputConfig->{"output_bucket"}."/".$task["workflowExecution"]["workflowId"]);
    
        log_out("INFO", basename(__FILE__), 
            "Start uploading '$outputPathToFile' to S3 bucket '$outputBucket' ...",
            $this->activityLogKey);
        // Send output file to S3 bucket
        $this->put_file_into_s3($task, $outputBucket, 
            $outputConfig->{'file'}, $pathToFile);
    
        // Return success !
        $msg = "'$pathToFile' successfully transcoded and uploaded into S3 bucket '$outputBucket' !";
        log_out("INFO", basename(__FILE__), $msg,
            $this->activityLogKey);
        return [
            "status"  => "SUCCESS",
            "details" => $msg,
            "data"    => [
                "input_json"     => $this->inputJSON,
                "input_fileinfo" => $this->inputFileinfo
            ]
        ];
    }

    // REad ffmpeg output and calculate % progress
    private function capture_progression($out)
    {
        // # get the current time
        preg_match_all("/time=(.*?) bitrate/", $out, $matches); 

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
            $progress = round(($done/$this->inputFileinfo->{"duration"})*100);
        log_out("INFO", basename(__FILE__), "Progress: $done / $progress%",
            $this->activityLogKey);

        return ($progress);
    }

}


