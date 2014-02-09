<?php

/**
 * This class performs transcoding
 * FFMpeg only for now
 */
class TranscodeAssetActivity extends BasicActivity
{
	private $started;
	private $output;
    private $inputFile;

	// Perform the activity
	public function do_activity($task)
	{
		global $swf;
		$this->started = time();
		// Array returned by this function back to the activity poller
		// We also send it as hearbeat data "json encoded"
		$this->output = array(
			"workflowExecution" => $task->get("workflowExecution"),
			"activityType"      => $task->get("activityType"),
			"activityId"        => $task->get("activityId"),
			"status"            => "STARTING",
			"started"           => $this->started,
			"duration"          => 0,
			"progress"          => 0,
			"msg"               => "Transcoding process starting ...");
		$workflowId = $this->output['workflowExecution']['workflowId'];
        
        //print_r($task);

		/**
		 * Send first heartbeat to initiate status
		 */
		if (!$this->sendHeartbeat($task, $this->output))
            return false;
        
		// Processing input variables
		$input         = json_decode($task->get("input"));
        $this->inputFile = $input->{"input_file"};
		$inputFilepath = $this->inputFile->{"filepath"};
		$inputConfig   = $input->{"input_json"};
		$output        = $input->{"output"};
        
		// Setup transcoding commands
		$outputPath = "/tmp/";
		$outputFile = $output->{"file"};
		$ffmpegArgs = "-i $inputFilepath -y -threads 0 -s " . $output->{'size'} . " -vcodec " . $output->{'video_codec'} . " -acodec " . $output->{'audio_codec'} . " -b:v " . $output->{'video_bitrate'} . " -bufsize " . $output->{'buffer_size'} . " -b:a " . $output->{'audio_bitrate'} . " ${outputPath}${outputFile}";
		$ffmpegCmd  = "ffmpeg $ffmpegArgs";
        
        // Print info
		log_out("INFO", basename(__FILE__), "FFMPEG CMD:\n$ffmpegCmd\n");
		log_out("INFO", basename(__FILE__), "Start Transcoding Asset '$inputFilepath' to '${outputPath}${outputFile}' ...");
		log_out("INFO", basename(__FILE__), "Video duration (sec): " . $this->inputFile->{'duration'});
        
        // Command output capture 
		$descriptorSpecs = array(  
            2 => array("pipe", "w") 
        );
		if (!($process = proc_open($ffmpegCmd, $descriptorSpecs, $pipes)))
            {
                log_out("ERROR", basename(__FILE__), "Unable to execute command ! Abording ...");
                return false;
            }
        // Is resource valid ?
		if (!is_resource($process))
            {
                log_out("ERROR", basename(__FILE__), "Process execution has failed ! Abording ...");
                return false;
            }

        // While process running, we read output
		$content = "";
		$i = 0;
        $procStatus = proc_get_status($process);
        while ($procStatus['running']) {
            // REad prog output
            $out = fread($pipes[2], 8192);

            # Concat out
            $content .= $out;

            // Get progression and notify SWF with heartbeat
            if ($i == 10) {
                echo ".\n";
                $progress = $this->captureProgression($content);
                $this->output["status"]   = "PROCESSING";
                $this->output["duration"] = time() - $this->started;
                $this->output["progress"] = $progress;
                $this->output["msg"]      = "Video '${inputFilepath}' is being transocoded ...";

                // Notify SWF that we are still running !
                if (!$this->sendHeartbeat($task, $this->output))
                    return false;
                
                $i = 0;
            }
                    
            // Get latest status
            $procStatus = proc_get_status( $process );

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
            {
                log_out("ERROR", basename(__FILE__), "Output file ${outputPath}${outputFile} hasn't been created successfully or is empty !");
                return false;
            }

        // No error. Transcode successful
        log_out("INFO", basename(__FILE__), "Transcoding asset '$inputFilepath' is DONE !");
        $this->output["status"]   = "SUCCESS";
        $this->output["duration"] = time() - $this->started;
        $this->output["progress"] = 100;
        $this->output["msg"]      = "Transcoding successful for '$inputFilepath'";
        
        log_out("INFO", basename(__FILE__), "Transcoding successfull !");

		return $this->output;
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
	private function sendHeartbeat($task, $output)
	{
		global $swf;

		try {
			$taskToken = $task->get("taskToken");
			log_out("INFO", basename(__FILE__), "Sending heartbeat to SWF ...");

			/*
			 * FEATURE REQUEST:
			 * AWS doesn't give access to the heartbeata data sent here: "output"   => json_encode($output)
			 * Data becomes available only if the task timeout.
			 * We need to have access to the heartbeat data. Through the WF history or on demand using an activityID, which I is less overhead.
			 * We could only access the last heatbeat data for example, keeping the resources necessary for this feature minimal.
			 * Without this feature we can't capture the status/progress of the current task.
			 * https://forums.aws.amazon.com/thread.jspa?messageID=516823&#516823
			 */
			$info = $swf->recordActivityTaskHeartbeat(array(
				"output"    => json_encode($output),
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


