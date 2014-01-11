<?php

// This class serves as a skeletton for classes impleting actual activity
class GridXTranscodeAssetActivity extends GridXBasicActivity
{
	private $ffmpegValidationOutput;
	private $inputFileDuration;

	// Perform the activity
	public function do_activity($task)
	{
		global $swf;

		log_out("INFO", basename(__FILE__), "Starting Transcoding Asset ...");
		
		// Processing input variables
		$input = json_decode($task->get("input"));
		$inputFile = $input->{"input_file"};
		$inputConfig = $input->{"input_config"};
		$this->ffmpegValidationOutput = $input->{"ffmpeg_validation_output"};
		$this->inputFileDuration = $input->{"input_file_duration"};
		$output = $input->{"output"};

		log_out("INFO", basename(__FILE__), "Start transcoding input file: $inputFile");

		// Setup transcoding commands
		$outputPath = "/tmp/";
		$outputFile = $output->{"file"};
		$ffmpegArgs = "-i $inputFile -y -s " . $output->{'size'} . " -vcodec " . $output->{'video_codec'} . " -acodec " . $output->{'audio_codec'} . " -b " . $output->{'video_bitrate'} . " -bufsize " . $output->{'buffer_size'} . " -ab " . $output->{'audio_bitrate'} . " ${outputPath}${outputFile}";
		$ffmpegCmd = "ffmpeg $ffmpegArgs 2>&1";
		log_out("INFO", basename(__FILE__), "FFMPEG CMD: $ffmpegCmd\n");

		// Exec command and capture output
		$handle = popen($ffmpegCmd, 'r');
		$content = "";
		$i = 0;
		while (1) {
			// If program is over
			if (feof($handle)) {
				$progress = $this->captureProgression($content);
				$this->reportProgress($progress);
				if (!$this->sendHeartbeat($task, $progress))
					return false;
				break;
			}
			// REad prog output
			$out = fread($handle, 8192);
			$content .= $out;

			// Get progression and notify SWF with heartbeat
			if ($i == 10) {
				$progress = $this->captureProgression($content);
				$this->reportProgress($progress);
				if (!$this->sendHeartbeat($task, $progress))
					return false;
				$i = 0;
			}

			sleep(1);
			$i++;
		}
		fclose($handle);

		log_out("INFO", basename(__FILE__), "Transcoding asset '$inputFile' is DONE !");

		return ($task->get("input"));
	}

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

	private function reportProgress($progress)
	{
		// NEED TO REPORT PROGRESS TO DB
		// Prog starting the WF will pull progress information from DB
	}

	private function sendHeartbeat($task, $progress)
	{
		global $swf;

		try {
			$taskToken = $task->get("taskToken");
			log_out("INFO", basename(__FILE__), "Sending heartbeat to SWF ...");
			$info = $swf->recordActivityTaskHeartbeat(array(
				"details"   => "$progress",
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


