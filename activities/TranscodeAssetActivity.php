<?php

/**
 * This class performs transcoding
 * FFMpeg only for now
 */
class TranscodeAssetActivity extends BasicActivity
{
	private $ffmpegValidationOutput;
	private $inputFileDuration;
	private $started;
	private $details;

	// Perform the activity
	public function do_activity($task)
	{
		global $swf;
		$this->started = time();
		// Array returned by this function back to the decider
		// We also send it as hearbeat data "json encoded"
		$this->details = array(
			"workflowId"   => $task->get("workflowExecution"),
			"activityType" => $task->get("activityType"),
			"activityId"   => $task->get("activityId"),
			"status"       => "STARTING",
			"started"      => $this->started,
			"duration"     => 0,
			"progress"     => 0,
			"msg"          => "Transcoding process starting ...");


		log_out("INFO", basename(__FILE__), "Starting Transcoding Asset ...");
		
		/**
		 * Send first heartbeat to initiate status
		 */
		if (!$this->sendHeartbeat($task, $this->details)) {
			$this->details["status"]   = "ERROR";
			$this->details["msg"]      = "Unable to send to send heartbeat !";
			return $this->details;
		}

		// Processing input variables
		$input       = json_decode($task->get("input"));
		$inputFile   = $input->{"input_file"};
		$inputConfig = $input->{"input_config"};
		$output      = $input->{"output"};
		$this->ffmpegValidationOutput = $input->{"ffmpeg_validation_output"};
		$this->inputFileDuration = $input->{"input_file_duration"};

		log_out("INFO", basename(__FILE__), "Start transcoding input file: $inputFile");

		// Setup transcoding commands
		$outputPath = "/tmp/";
		$outputFile = $output->{"file"};
		$ffmpegArgs = "-i $inputFile -y -s " . $output->{'size'} . " -vcodec " . $output->{'video_codec'} . " -acodec " . $output->{'audio_codec'} . " -b:v " . $output->{'video_bitrate'} . " -bufsize " . $output->{'buffer_size'} . " -b:a " . $output->{'audio_bitrate'} . " ${outputPath}${outputFile}";
		$ffmpegCmd  = "ffmpeg $ffmpegArgs";
		log_out("INFO", basename(__FILE__), "FFMPEG CMD: $ffmpegCmd\n");

		// Exec command and capture output
		$descriptorSpecs = array(
			0 => array("pipe", "r"),  
			1 => array("pipe", "w"),  
			2 => array("file", "/tmp/cloudtranscode/${inputFile}.err") 
			);

		$handle = proc_open($ffmpegCmd, $descriptorSpecs, $pipes);
		$content = "";
		$i = 0;
		if (is_resource($handle))
		{
			$procStatus = proc_get_status($handle);

			// While process running, we read output
			while ($procStatus['running']) {
				// REad prog output
				$out = fread($handle, 8192);
				$content .= $out;

				// Get progression and notify SWF with heartbeat
				if ($i == 10) {
					$progress = $this->captureProgression($content);
					$this->details["status"]   = "PROCESSING";
					$this->details["duration"] = time() - $this->started;
					$this->details["progress"] = $progress;
					$this->details["msg"]      = "Video '${inputFile}' is being transocoded ...";

					if (!$this->sendHeartbeat($task, $this->details)) {
						$this->details["status"]  = "ERROR";
						$this->details["msg"]     = "Unable to send to send heartbeat !";
						return $this->details;
					}
					$i = 0;
				}

				sleep(1);
				$i++;

				// Get latest status
				$procStatus = proc_get_status( $process );
			}

			// FFMPEG process is over
			$return_value = proc_close($process);
			// Error in processing
			if ($return_value)
			{
				$error = file_get_contents("/tmp/cloudtranscode/${inputFile}.err");
				$this->details["status"]   = "ERROR";
				$this->details["duration"] = time() - $this->started;
				$this->details["progress"] = 0;
				$this->details["msg"]      = "Error transcoding '$inputFile': $error";

				return $this->details;
			}

			// No error. Transcode successful
			log_out("INFO", basename(__FILE__), "Transcoding asset '$inputFile' is DONE !");
			$this->details["status"]   = "SUCCESS";
			$this->details["duration"] = time() - $this->started;
			$this->details["progress"] = 100;
			$this->details["msg"]      = "Transcoding successful for '$inputFile'";
		}
		else
		{
			$this->details["status"]  = "ERROR";
			$this->details["msg"]     = "Unable to execute ffmpeg command to transcode '$inputFile' !";
		}

		return $this->details;
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
		$progress = $done/$this->inputFileDuration;
		log_out("INFO", basename(__FILE__), "[DURATION] $this->inputFileDuration");
		log_out("INFO", basename(__FILE__), "[DONE] $done");
		log_out("INFO", basename(__FILE__), "[PROGRESS] $progress");

		return ($progress);
	}

	/**
	 * Send heartbeat to SWF to keep the task alive.
	 * Timeout is configurable at the Activity level
	*/
	private function sendHeartbeat($task, $details)
	{
		global $swf;

		try {
			$taskToken = $task->get("taskToken");
			log_out("INFO", basename(__FILE__), "Sending heartbeat to SWF ...");

			/*
			 * FEATURE REQUEST:
			 * AWS doesn't give access to the heartbeata data sent here: "details"   => json_encode($details)
			 * Data becomes available only if the task timeout.
			 * We need to have access to the heartbeat data. Through the WF history or on demand using an activityID, which I is less overhead.
			 * We could only access the last heatbeat data for example, keeping the resources necessary for this feature minimal.
			 * Without this feature we can't capture the status/progress of the current task.
			 * https://forums.aws.amazon.com/thread.jspa?messageID=516823&#516823
			 */
			$info = $swf->recordActivityTaskHeartbeat(array(
				"details"   => json_encode($details),
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
	}

}


