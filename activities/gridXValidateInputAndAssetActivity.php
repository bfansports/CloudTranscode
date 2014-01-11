<?php

require_once dirname(__FILE__).'/../gridXUtils.php';
require_once 'gridXBasicActivity.php';

// This class serves as a skeletton for classes impleting actual activity
class GridXValidateInputAndAssetActivity extends GridXBasicActivity
{
	const NO_INPUT = "NO_INPUT";
	const INPUT_INVALID = "INPUT_INVALID";
	const NO_INPUT_FILE = "NO_INPUT_FILE";
	const GET_OBJECT_FAILED = "GET_OBJECT_FAILED";

	// Perform the activity
	public function do_activity($task)
	{
		global $aws;
		global $swf;

		log_out("INFO", basename(__FILE__), "Validate transcoding input and Asset ...");

		if (!isset($task["input"]) || !$task["input"] || $task["input"] == "")
		{
			log_out("ERROR", basename(__FILE__), "Validate transcoding input and Asset !");
			$this->activity_failed($task, "NO_INPUT", "Task has no input data !");
			return false;
		}

		// Validate JSON data and Decode as an Object
		if (!($input = json_decode($task["input"])))
		{
			log_out("ERROR", basename(__FILE__), "JSON input is invalid !");
			$this->activity_failed($task, "INPUT_INVALID", "JSON input is invalid !");
			return false;
		}

		/**
		 * Perform more input validation ! TODO
		 */
		if (!isset($input->{'input_bucket'}) || !$input->{'input_bucket'} || $input->{'input_bucket'} == "" ||
			!isset($input->{'input_file'}) || !$input->{'input_file'} || $input->{'input_file'} == "")
		{
			log_out("ERROR", basename(__FILE__), "No input_bucket of input_file specified !");
			$this->activity_failed($task, "NO_INPUT_FILE", "No input_bucket of input_file specified !");
			return false;
		}

		/**
		 * Download input file from S3 and prepare for ffmpeg validation test
		 */
		try {
			$localPath = '/tmp/';
			$localCopy = $localPath . $input->{'input_file'};
			$localCopyInfoLogs = $localPath . $input->{'input_file'} . ".log";
			
			if (!file_exists($localCopy))
			{
				log_out("INFO", basename(__FILE__), "Downloading input file from S3. Bucket: '" . $input->{'input_bucket'} . "' Key: '" . $input->{'input_file'} . "'");
				
				// S3 client
				$s3 = $aws->get('S3');
				// Download and Save object to a file.
				$object = $s3->getObject(array(
					'Bucket' => $input->{'input_bucket'},
					'Key'    => $input->{'input_file'},
					'SaveAs' => $localCopy
					));
			}
			else 
				log_out("INFO", basename(__FILE__), "Using local copy of input file: '" . $localCopy . "'");
			
		} catch (Exception $e) {
			log_out("ERROR", basename(__FILE__), "No input file specified !");
			$this->activity_failed($task, "GET_OBJECT_FAILED", "No input file specified !");
			return false;
		}

		/**
		 * Perform FFMpeg file validation. We capture output and errors
		 * We make sure the file is conform and understandable by ffmpeg
		 */
		// Get input video information and capture output
		log_out("INFO", basename(__FILE__), "Running FFMPEG validation test on: '" . $localCopy . "'");
		$handle = popen("ffmpeg -i $localCopy 2>&1", 'r');
		$ffmpegValidationOutput = stream_get_contents($handle);
		fclose($handle);

		//echo($ffmpegValidationOutput);

		// Warning if we can't save the STDOUT in log file
		if (!file_put_contents($localCopyInfoLogs, $ffmpegValidationOutput))
		{
			log_out("WARNING", basename(__FILE__), "Can't create file to transcode's log file !");
			$this->activity_failed($task, "GET_OBJECT_FAILED", "No input file specified !");
		}

		// Capture input file duration
		$fileDuration = $this->getFileDuration($ffmpegValidationOutput);

		log_out("INFO", basename(__FILE__), "Generating result out");
		// Create result object to be passed to next activity in the Workflow as input
		$result = array(
			"input_file" 			   => $localCopy,
			"input_config"             => $input,
			"outputs"                  => $input->{'outputs'},
			"ffmpeg_validation_output" => $ffmpegValidationOutput,
			"input_file_duration"      => $fileDuration,
			);

		return $result;
	}

	private function getFileDuration($ffmpegValidationOutput)
	{
		preg_match("/Duration: (.*?), start:/", $ffmpegValidationOutput, $matches);
		$rawDuration = $matches[1];
		$ar = array_reverse(explode(":", $rawDuration));
		$duration = floatval($ar[0]);
		if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
		if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;

		return $duration;
	}

}


/**
 * TEST PROGRAM
 */

// $domainName = "SA_TEST2";
// $jsonInput = file_get_contents(dirname(__FILE__) . "/../config/input.json");

// $inputValidator = new GridXValidateInputAndAssetActivity(array(
// 	"domain"  => $domainName,
// 	"name"    => "TestActivity",
// 	"version" => "v1"
// 	));
// $inputValidator->do_activity(array(
// 	"input" => $jsonInput
// 	));

