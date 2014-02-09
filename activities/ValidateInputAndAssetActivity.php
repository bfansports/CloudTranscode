<?php

require_once dirname(__FILE__).'/../Utils.php';
require_once 'BasicActivity.php';

// This class validate the JSON input. Makes sure the input files to be transcoded exists and is valid.
class ValidateInputAndAssetActivity extends BasicActivity
{
	// Errors
	const NO_INPUT = "NO_INPUT";
	const INPUT_INVALID = "INPUT_INVALID";
	const NO_INPUT_FILE = "NO_INPUT_FILE";
	const GET_OBJECT_FAILED = "GET_OBJECT_FAILED";
	const EXEC_FOR_INFO_FAILED = "EXEC_FOR_INFO_FAILED";

	// File types
	const VIDEO = "VIDEO";

	// Perform the activity
	public function do_activity($task)
	{
		global $aws;
		global $swf;

		log_out("INFO", basename(__FILE__), "Starting transcoding input and Asset validation ...");

		if (!isset($task["input"]) || !$task["input"] || $task["input"] == "")
            {
                log_out("ERROR", basename(__FILE__), "Validate transcoding input and Asset !");
                $this->activity_failed($task, self::NO_INPUT, "Task has no input data !");
                return false;
            }

		// Validate JSON data and Decode as an Object
		if (!($input = json_decode($task["input"])))
            {
                log_out("ERROR", basename(__FILE__), "JSON input is invalid !");
                $this->activity_failed($task, self::INPUT_INVALID, "JSON input is invalid !");
                return false;
            }

		// Perfom JSON input validation
		if (($err = $this->inputValidator($input)))
            {
                log_out("ERROR", basename(__FILE__), $err);
                $this->activity_failed($task, self::INPUT_INVALID, $err);
                return false;
            }
        
		// Download input file from S3 and prepare for ffmpeg validation test
		try {
			$localPath = '/tmp/';
			$localCopy = $localPath . $input->{'input_file'};
			$localCopyInfoLogs = $localPath . $input->{'input_file'} . ".log";

			if (!file_exists($localCopy) || !filesize($localCopy))
                {
                    log_out("INFO", basename(__FILE__), "Downloading input file from S3. Bucket: '" . $input->{'input_bucket'} . "' File: '" . $input->{'input_file'} . "'");

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
			$this->activity_failed($task, self::GET_OBJECT_FAILED, "Unable to get input file from S3 ! " . $e->getMessage());
			return false;
		}

		log_out("INFO", basename(__FILE__), "Finding information about input file '$localCopy' - Type: " . $input->{'input_type'});
		// Capture input file details about format, duration, size, etc.
		if (!($fileDetails = $this->getFileDetails($localCopy, $input->{'input_type'})))
            return false;

        $fileDetails['filepath'] = $localCopy;
		// Create result object to be passed to next activity in the Workflow as input
		$result = [
			"input_json"             	=> $input,
			"input_file" 			  	=> $fileDetails,
			"outputs"                  	=> $input->{'outputs'}
        ];
        
		return $result;
	}

	private function getFileDetails($localCopy, $type)
	{
        $fileDetails = array();
        
        # Get video information
		if ($type == self::VIDEO)
            {
                log_out("INFO", basename(__FILE__), "Running FFMPEG validation test on '" . $localCopy . "'");
                if (!($handle = popen("ffmpeg -i $localCopy 2>&1", 'r')))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to get information about the video file '$localCopy' !");
                        return false;
                    }
                $ffmpegInfoOut = stream_get_contents($handle);
                if (!$ffmpegInfoOut)
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to read FFMpeg output !");
                        return false;
                    }

                # Duration
                if (!$this->getDuration($ffmpegInfoOut, $fileDetails))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to extract video duration !");
                        return false;
                    }
                # Video info
                if (!$this->getVideoInfo($ffmpegInfoOut, $fileDetails))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to find video information !");
                        return false;
                    }
                # Audio Info
                if (!$this->getAudioInfo($ffmpegInfoOut, $fileDetails))
                    {
                        $this->activity_failed($task, self::EXEC_FOR_INFO_FAILED, "Unable to find audio information !");
                        return false;
                    }
                
                fclose($handle);
            }
        
        return ($fileDetails);
	}

    # Extract video info
    private function getVideoInfo($ffmpegInfoOut, &$fileDetails)
    {
        preg_match("/: Video: (.+?) .+?, (.+?), (.+?), (.+?), (.+?),/", $ffmpegInfoOut, $matches);
        if ($matches) {
            $fileDetails['vcodec'] = $matches[1];
            $fileDetails['color'] = $matches[2];
            $fileDetails['size'] = $matches[3];
            $fileDetails['vbitrate'] = $matches[4];
            $fileDetails['fps'] = $matches[5];
            
            # Calculate ratio
            $sizeSplit = explode("x", $fileDetails['size']);
            $fileDetails['ratio'] = number_format($sizeSplit[0] / $sizeSplit[1], 1);

            return true;
        }
        
        return false;
    }

    # Extract audio info
    private function getAudioInfo($ffmpegInfoOut, &$fileDetails)
    {
        preg_match("/: Audio: (.+?) .+?, (.+?), (.+?), (.+?), ([0-9]+ kb\/s).*?/", $ffmpegInfoOut, $matches);
        if ($matches) {
            $fileDetails['acodec'] = $matches[1];
            $fileDetails['freq'] = $matches[2];
            $fileDetails['mode'] = $matches[3];
            # Ignore match 4
            $fileDetails['abitrate'] = $matches[5];

            return true;
        }
        
        return false;
    }
    
    # Extract Duration
    private function getDuration($ffmpegInfoOut, &$fileDetails)
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

    # Validate JSON input format
	private function inputValidator()
	{

	}

}


/**
 * TEST PROGRAM
 */

// $domainName = "SA_TEST2";
// $jsonInput = file_get_contents(dirname(__FILE__) . "/../config/input.json");

// $inputValidator = new ValidateInputAndAssetActivity(array(
// 	"domain"  => $domainName,
// 	"name"    => "TestActivity",
// 	"version" => "v1"
// 	));
// $inputValidator->do_activity(array(
// 	"input" => $jsonInput
// 	));

