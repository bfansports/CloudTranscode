<?php

// This class validate the JSON input. Makes sure the input files to be transcoded exists and is valid.
class ValidateInputAndAssetActivity extends BasicActivity
{
	// Errors
	const NO_INPUT             = "NO_INPUT";
	const INPUT_INVALID        = "INPUT_INVALID";
	const NO_INPUT_FILE        = "NO_INPUT_FILE";
	const GET_OBJECT_FAILED    = "GET_OBJECT_FAILED";
	const EXEC_FOR_INFO_FAILED = "EXEC_FOR_INFO_FAILED";
	const TMP_FOLDER_FAIL      = "TMP_FOLDER_FAIL";

	// File types
	const VIDEO = "VIDEO";

	// Perform the activity
	public function do_activity($task)
	{
		global $aws;
		global $swf;

		log_out("INFO", basename(__FILE__), "Starting transcoding input and Asset validation ...");

		if (!isset($task["input"]) || !$task["input"] || $task["input"] == "")
            return [
                "status"  => "ERROR",
                "error"   => self::NO_INPUT,
                "details" => "No input provided to 'ValidateInputAndAsset'"
            ];
        
		// Validate JSON data and Decode as an Object
		if (!($input = json_decode($task["input"])))
            return [
                "status"  => "ERROR",
                "error"   => self::INPUT_INVALID,
                "details" => "JSON input is invalid !"
            ];

		// Perfom JSON input validation
        // XXX need to perfrom input validation over the JSON input format
        // XXX JSON format needs to be defined and implemented completly
		if (($err = $this->input_validator($input)))
            return [
                "status"  => "ERROR",
                "error"   => self::INPUT_INVALID,
                "details" => $err
            ];
        
		// Download input file from S3 and prepare for ffmpeg validation test
		try {
            // Create local temporary folder to store video to validate
            // We keep the file in TMP folder so we can save time if activity using this file runs on the same machine.
            // If same file and workflow, we don't need to download from S3, we use local copy
            // XXX cleanup those folders regularly or we'll run out of space !!!
			$localPath = '/tmp/CloudTranscode/' . $task["workflowExecution"]["workflowId"] . "/";
            if (!file_exists($localPath))
                {
                    if (!mkdir($localPath, 0750, true))
                        return [
                            "status"  => "ERROR",
                            "error"   => self::TMP_FOLDER_FAIL,
                            "details" => "Unable to create temporary folder to store asset to validate !"
                        ];
                }
			$localCopy = $localPath . $input->{'input_file'};
            // Local copy exists ? If not we download the file from S3
			if (!file_exists($localCopy) || !filesize($localCopy))
                {
                    log_out("INFO", basename(__FILE__), "Downloading input file from S3. Bucket: '" . $input->{'input_bucket'} . "' File: '" . $input->{'input_file'} . "'");

                    // Get S3 client
                    $s3 = $aws->get('S3');
                    // Download and Save object to a local file.
                    $object = $s3->getObject(array(
                        'Bucket' => $input->{'input_bucket'},
                        'Key'    => $input->{'input_file'},
                        'SaveAs' => $localCopy
					));
                }
			else 
				log_out("INFO", basename(__FILE__), "Using local copy of input file: '" . $localCopy . "'");

		} catch (Exception $e) {
            return [
                "status"  => "ERROR",
                "error"   => self::GET_OBJECT_FAILED,
                "details" => "Unable to get input file from S3 ! " . $e->getMessage()
            ];
        }
        
		log_out("INFO", basename(__FILE__), "Finding information about input file '$localCopy' - Type: " . $input->{'input_type'});
		// Capture input file details about format, duration, size, etc.
		if (!($fileDetails = $this->get_file_details($localCopy, $input->{'input_type'})))
            return false;

        $fileDetails['filepath'] = $localCopy;
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
	private function get_file_details($localCopy, $type)
	{
        $fileDetails = array();
        
        // Get video information
		if ($type == self::VIDEO)
            {
                log_out("INFO", basename(__FILE__), "Running FFMPEG validation test on '" . $localCopy . "'");
                // Execute FFMpeg
                if (!($handle = popen("ffmpeg -i $localCopy 2>&1", 'r')))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to get information about the video file '$localCopy' !");
                        return false;
                    }
                // Get output
                $ffmpegInfoOut = stream_get_contents($handle);
                if (!$ffmpegInfoOut)
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to read FFMpeg output !");
                        return false;
                    }

                // get Duration
                if (!$this->get_duration($ffmpegInfoOut, $fileDetails))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to extract video duration !");
                        return false;
                    }
                // get Video info
                if (!$this->get_video_info($ffmpegInfoOut, $fileDetails))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to find video information !");
                        return false;
                    }
                // get Audio Info
                if (!$this->get_audio_info($ffmpegInfoOut, $fileDetails))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to find audio information !");
                        return false;
                    }
                
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
    
    // XXX TODO
    // Validate JSON input format
	private function input_validator()
	{

	}

}
