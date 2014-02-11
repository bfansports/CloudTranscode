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
	const EXEC_FAIL      = "EXEC_FAIL";
	const TRANSCODE_FAIL = "TRANSCODE_FAIL";
	const S3_UPLOAD_FAIL = "S3_UPLOAD_FAIL";

	// Perform the activity
	public function do_activity($task)
	{
		global $swf;
        
        //print_r($task);

		/**
		 * Send first heartbeat to initiate status
		 */
		if (!$this->sendHeartbeat($task))
            return false;
        
		// Processing input variables
		$input           = json_decode($task->get("input"));
        $this->inputFile = $input->{"input_file"};
		$this->inputJSON = $input->{"input_json"};
        
		// Setup transcoding command and parameters
		$inputFilepath = $input->{"input_file"}->{"filepath"};
		$outputConfig  = $input->{"output"}; // JSON description of the transcode to do
		$outputPath    = "/tmp/";
		$outputFile    = $outputConfig->{"file"};
		$ffmpegArgs    = "-i $inputFilepath -y -threads 0 -s " . $outputConfig->{'size'} . " -vcodec " . $outputConfig->{'video_codec'} . " -acodec " . $outputConfig->{'audio_codec'} . " -b:v " . $outputConfig->{'video_bitrate'} . " -bufsize " . $outputConfig->{'buffer_size'} . " -b:a " . $outputConfig->{'audio_bitrate'} . " ${outputPath}${outputFile}";
		$ffmpegCmd     = "ffmpeg $ffmpegArgs";
        
        // Print info
		log_out("INFO", basename(__FILE__), "FFMPEG CMD:\n$ffmpegCmd\n");
		log_out("INFO", basename(__FILE__), "Start Transcoding Asset '$inputFilepath' to '${outputPath}${outputFile}' ...");
		log_out("INFO", basename(__FILE__), "Video duration (sec): " . $this->inputFile->{'duration'});
        
        // Command output capture 
		$descriptorSpecs = array(  
            2 => array("pipe", "w") 
        );
        // Start execution
		if (!($process = proc_open($ffmpegCmd, $descriptorSpecs, $pipes)))
            return [
                "status"  => "ERROR",
                "error"   => self::EXEC_FAIL,
                "details" => "Unable to execute command:\n$ffmpegCmd"
            ];
        // Is resource valid ?
		if (!is_resource($process))
            return [
                "status"  => "ERROR",
                "error"   => self::EXEC_FAIL,
                "details" => "Process execution has failed:\n$ffmpegCmd"
            ];

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
                $progress = $this->captureProgression($ffmpegOut);

                // XXX Notify progress through SQS

                // Notify SWF that we are still running !
                if (!$this->sendHeartbeat($task))
                    return false;
                
                $i = 0;
            }
                    
            // Get latest status
            $procStatus = proc_get_status($process);

            // Print progression
            echo ".";
            flush();

            $i++;
        }
        echo "\n";
            
        // FFMPEG process is over
        proc_close($process);
        
        // Test if we have an output file !
        if (!file_exists($outputPath . $outputFile) || 
        !filesize($outputPath . $outputFile))
            return [
                "status"  => "ERROR",
                "error"   => self::TRANSCODE_FAIL,
                "details" => "Output file ${outputPath}${outputFile} hasn't been created successfully or is empty !"
            ];
        
        // No error. Transcode successful
        log_out("INFO", basename(__FILE__), "Transcoding successfull !");
        
        // Send output file to S3
        /* if (($err = $this->sendOutputToS3($outputPath . $outputFile))) */
        /*     return [ */
        /*         "status"  => "ERROR", */
        /*         "error"   => self::S3_UPLOAD_FAIL, */
        /*         "details" => $err */
        /*     ]; */

		return [
            "status"  => "SUCCESS",
            "details" => "'$inputFilepath' transcoded successfully!",
            "data"    => [
                "input_json" => $input,
                "input_file" => $this->inputFile
            ]
        ];
	}

	// REad ffmpeg output and calculate % progress
	private function captureProgression($out)
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
            $progress = round(($done/$this->inputFile->{"duration"})*100);
		log_out("INFO", basename(__FILE__), "Progress: $done / $progress%");

		return ($progress);
	}

	/**
	 * Send heartbeat to SWF to keep the task alive.
	 * Timeout is configurable at the Activity level
     */
	private function sendHeartbeat($task)
	{
		global $swf;

		try {
			$taskToken = $task->get("taskToken");
			log_out("INFO", basename(__FILE__), "Sending heartbeat to SWF ...");
            
			$info = $swf->recordActivityTaskHeartbeat(array(
				"details"   => "",
				"taskToken" => $taskToken));

			// Workflow returns if this task should be canceled
			if ($info->get("cancelRequested") == true)
                {
                    log_out("WARNING", basename(__FILE__), "Cancel has been requested for this task '" . $task->get("activityId") . "' ! Killing task ...");
                    return false;
                }
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "Unable to send heartbeat ! " . $e->getMessage());
			return false;
		}
        
        return true;
	}

}


