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
	const NO_INPUT        = "NO_INPUT";
	const INPUT_INVALID   = "INPUT_INVALID";
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

        // Perfom input validation
		if (($validation = $this->input_validator($task)) &&
            $validation['status'] == "ERROR")
            return $validation;
        $input = $validation['input'];

        
        /**
         * INIT
         */

		// Referencing input variables
        $this->inputFile = $input->{"input_file"};
		$this->inputJSON = $input->{"input_json"};
        
        // Create TMP storage to put the file to validate. See: ActivityUtils.php
        // XXX cleanup those folders regularly or we'll run out of space !!!
        if (!($localPath = createTmpLocalStorage($task["workflowExecution"]["workflowId"])))
            return [
                "status"  => "ERROR",
                "error"   => self::TMP_FOLDER_FAIL,
                "details" => "Unable to create temporary folder to store asset to validate !"
            ];
        $pathToFile = $localPath . "/" . $this->inputJSON->{'input_file'};
        
        // Download file from S3 and save as$pathToFile . See: ActivityUtils.php
        if (($err = getFileFromS3($pathToFile, 
                    $this->inputJSON->{'input_bucket'}, 
                    $this->inputJSON->{'input_file'})))
            return [
                "status"  => "ERROR",
                "error"   => self::GET_OBJECT_FAILED,
                "details" => $err
            ];
        
        
        /**
         * PROCESS
         */

		// Setup transcoding command and parameters
        $outputConfig  = $input->{"output"}; // JSON description of the transcode to do
		$outputPathToFile = $localPath . "/" . $outputConfig->{"file"};
        // Create FFMpeg command
		$ffmpegArgs    = "-i $pathToFile -y -threads 0 -s " . $outputConfig->{'size'} . " -vcodec " . $outputConfig->{'video_codec'} . " -acodec " . $outputConfig->{'audio_codec'} . " -b:v " . $outputConfig->{'video_bitrate'} . " -bufsize " . $outputConfig->{'buffer_size'} . " -b:a " . $outputConfig->{'audio_bitrate'} . " $outputPathToFile";
		$ffmpegCmd     = "ffmpeg $ffmpegArgs";
        
        // Print info
		log_out("INFO", basename(__FILE__), "FFMPEG CMD:\n$ffmpegCmd\n");
		log_out("INFO", basename(__FILE__), "Start Transcoding Asset '$pathToFile' to '$outputPathToFile' ...");
		log_out("INFO", basename(__FILE__), "Video duration (sec): " . $this->inputFile->{'duration'});
        
        // Command output capture method: pipe STDERR (FFMpeg print out on STDERR)
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
        if (!file_exists($outputPathToFile) || 
            !filesize($outputPathToFile))
            return [
                "status"  => "ERROR",
                "error"   => self::TRANSCODE_FAIL,
                "details" => "Output file $outputPathToFile hasn't been created successfully or is empty !"
            ];
        
        // No error. Transcode successful
        log_out("INFO", basename(__FILE__), "Transcoding successfull !");

        // XXX
        // XXX. HERE, Notify upload starting through SQS !
        // XXX
        
        // Send output file to S3
        $outputBucket = str_replace("//","/",$outputConfig->{"output_bucket"}."/".$task["workflowExecution"]["workflowId"]);
        log_out("INFO", basename(__FILE__), "Start uploading '$outputPathToFile' to S3 bucket '$outputBucket' ...");
        if (($err = putFileToS3($outputPathToFile, 
                    $outputBucket,
                    $outputConfig->{'file'}
                )))
            return [
                "status"  => "ERROR",
                "error"   => self::S3_UPLOAD_FAIL,
                "details" => $err
            ];
        
        // XXX
        // XXX. HERE, Notify task success through SQS !
        // XXX

        // Return success !
        $msg = "'$pathToFile' transcoded and upload successfully into S3 bucket '$outputBucket' !";
        log_out("INFO", basename(__FILE__), $msg);
		return [
            "status"  => "SUCCESS",
            "details" => $msg,
            "data"    => [
                "input_json" => $this->inputJSON,
                "input_file" => $this->inputFile
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
            $progress = round(($done/$this->inputFile->{"duration"})*100);
		log_out("INFO", basename(__FILE__), "Progress: $done / $progress%");

		return ($progress);
	}

    // Validate input
	protected function input_validator($task)
	{
        // XXX need to perfrom input validation over the JSON input format
        // XXX JSON format needs to be defined and implemented completly
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
        
        // Return input
        return [
            "status" => "VALID",
            "input"  => $input
        ] ;
	}
}


