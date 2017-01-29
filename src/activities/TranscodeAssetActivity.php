#!/usr/bin/php

<?php

/*
 *   This class handles the transcoding activity
 *   Based on the input file type we lunch the proper transcoder
 *
 *   Copyright (C) 2016  BFan Sports - Sport Archive Inc.
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License along
 *   with this program; if not, write to the Free Software Foundation, Inc.,
 *   51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once __DIR__.'/BasicActivity.php';

use SA\CpeSdk;

class TranscodeAssetActivity extends BasicActivity
{
    const CONVERSION_TYPE_ERROR = "CONVERSION_TYPE_ERROR";
    const TMP_PATH_OPEN_FAIL    = "TMP_PATH_OPEN_FAIL";

    private $output;
    private $outputFilesPath;

    public function __construct($client = null, $params, $debug, $cpeLogger)
    {
        parent::__construct($client, $params, $debug, $cpeLogger);
    }

    // Perform the activity
    public function process($task)
    {
        // Call parent do_activity:
        // It download the input file we will process.
        parent::process($task);

        // Save output object
        $this->outputs = $this->input->{'output_assets'};

        $this->cpeLogger->logOut(
            "INFO",
            basename(__FILE__),
            "Preparing Asset transcoding ...",
            $this->logKey
        );

        foreach ($this->outputs as $output)
        {
            $this->validateInput($output);
            
            // Set output path to store result files
            $this->outputFilesPath = $this->getOutputPath($output);
            
            // Load the right transcoder base on input_type
            // Get asset detailed info
            switch ($this->input->{'input_asset'}->{'type'})
            {
            case self::VIDEO:
                $result = $this->transcodeVideo($task, $output);
                break;
            case self::IMAGE:
                $result = $this->transcodeImage($task, $output);
                break;
            case self::AUDIO:
            case self::DOC:
                break;
            default:
                throw new CpeSdk\CpeException("Unknown input asset 'type'! Abording ...",
                                              self::UNKOWN_INPUT_TYPE);
            }

            // Upload resulting file
            $this->uploadResultFiles($task, $output);

            if ($this->client)
                $this->client->onTranscodeDone($this->token, $result);
        }

        return json_encode($result);
    }

    // Process INPUT IMAGE
    private function transcodeImage($task, $output)
    {
        require_once __DIR__.'/transcoders/ImageTranscoder.php';
        
        // Instanciate transcoder to output Images
        $imageTranscoder = new ImageTranscoder($this, $task);
        
        # If we have metadata, we expect the output of ffprobe
        $metadata = null;
        if (isset($this->input->{'input_metadata'}))
            $metadata = $this->input->{'input_metadata'};
        
        // Perform transcoding
        $result = $imageTranscoder->transcode_asset(
            $this->tmpInputPath,
            $this->inputFilePath,
            $this->outputFilesPath,
            $metadata,
            $output
        );
        
        unset($imageTranscoder);

        return ($result);
    }
    
    // Process INPUT VIDEO
    private function transcodeVideo($task, $output)
    {
        require_once __DIR__.'/transcoders/VideoTranscoder.php';
        
        // Instanciate transcoder to output Videos
        $videoTranscoder = new VideoTranscoder($this, $task);

        // Check preset file, read its content and add its data to output object
        if ($output->{'type'} == self::VIDEO &&
            isset($output->{'preset'}))
        {
            // Validate output preset
            $videoTranscoder->validate_preset($output);

            // Set preset value
            $output->{'preset_values'} = $videoTranscoder->get_preset_values($output);
        }

        # If we have metadata, we expect the output of ffprobe
        $metadata = null;
        if (isset($this->input->{'input_metadata'}))
            $metadata = $this->input->{'input_metadata'};

        // Perform transcoding
        $result = $videoTranscoder->transcode_asset(
            $this->tmpInputPath,
            $this->inputFilePath,
            $this->outputFilesPath,
            $metadata,
            $output
        );
        
        unset($videoTranscoder);

        return ($result);
    }

    // Upload all output files to destination S3 bucket
    private function uploadResultFiles($task, $output)
    {
        // Sanitize output bucket and file path "/"
        $s3Bucket = str_replace("//", "/",
                                $output->{"bucket"});

        // Set S3 options
        $options = array("rrs" => false, "encrypt" => false);
        if (isset($output->{'s3_rrs'}) &&
            $output->{'s3_rrs'} == true) {
            $options['rrs'] = true;
        }
        if (isset($output->{'s3_encrypt'}) &&
            $output->{'s3_encrypt'} == true) {
            $options['encrypt'] = true;
        }

        // Open '$outputFilesPath' to read it and send all files to S3 bucket
        if (!$handle = opendir($this->outputFilesPath)) {
            throw new CpeSdk\CpeException("Can't open tmp path '$this->outputFilesPath'!",
                                          self::TMP_PATH_OPEN_FAIL);
        }

        // Upload all resulting files sitting in $outputFilesPath to S3
        while ($entry = readdir($handle)) {
            if ($entry == "." || $entry == "..") {
                continue;
            }

            // Destination path on S3. Sanitizing
            $s3Location = $output->{'output_file_info'}['dirname']."/$entry";
            $s3Location = str_replace("//", "/", $s3Location);

            // Send to S3. We reference the callback s3_put_processing_callback
            // The callback ping back SWF so we stay alive
            $s3Output = $this->s3Utils->put_file_into_s3(
                $s3Bucket,
                $s3Location,
                "$this->outputFilesPath/$entry",
                $options,
                array($this, "activityHeartbeat"),
                null
            );
            // We delete the TMP file once uploaded
            unlink("$this->outputFilesPath/$entry");

            $this->cpeLogger->logOut("INFO", basename(__FILE__),
                                     $s3Output['msg'],
                                     $this->logKey);
        }
    }

    private function getOutputPath($output)
    {
        $outputFilesPath = self::TMP_FOLDER
                         . $this->name."/".$this->logKey;

        $output->{'key'} = $output->{'path'}."/".$output->{'file'};

        // Create TMP folder for output files
        $outputFileInfo = pathinfo($output->{'key'});
        $output->{'output_file_info'} = $outputFileInfo;
        $outputFilesPath .= $outputFileInfo['dirname'];

        if (!file_exists($outputFilesPath))
        {
            if ($this->debug)
                $this->cpeLogger->logOut("INFO", basename(__FILE__),
                                         "Creating TMP output folder '".$outputFilesPath."'",
                                         $this->logKey);

            if (!mkdir($outputFilesPath, 0750, true))
                throw new CpeSdk\CpeException(
                    "Unable to create temporary folder '$outputFilesPath' !",
                    self::TMP_FOLDER_FAIL
                );
        }

        return ($outputFilesPath);
    }

    // Perform custom validation on JSON input
    // Callback function used in $this->do_input_validation
    private function validateInput($output)
    {
        if ((
            $this->input->{'input_asset'}->{'type'} == self::VIDEO &&
            $output->{'type'} != self::VIDEO &&
            $output->{'type'} != self::THUMB &&
            $output->{'type'} != self::AUDIO
        )
            ||
            (
                $this->input->{'input_asset'}->{'type'} == self::IMAGE &&
                $output->{'type'} != self::IMAGE
            )
            ||
            (
                $this->input->{'input_asset'}->{'type'} == self::AUDIO &&
                $output->{'type'} != self::AUDIO
            )
            ||
            (
                $this->input->{'input_asset'}->{'type'} == self::DOC &&
                $output->{'type'} != self::DOC
            ))
        {
            throw new CpeSdk\CpeException("Can't convert that input asset 'type' (".$this->input->{'input_asset'}->{'type'}.") into this output asset 'type' (".$output->{'type'}.")! Abording.",
                                          self::CONVERSION_TYPE_ERROR);
        }
    }
}


/*
***************************
* Activity Startup SCRIPT
***************************
*/

// Usage
function usage()
{
    echo("Usage: php ". basename(__FILE__) . " -A <Snf ARN> [-C <client class path>] [-N <activity name>] [-h] [-d] [-l <log path>]\n");
    echo("-h: Print this help\n");
    echo("-d: Debug mode\n");
    echo("-l <log_path>: Location where logs will be dumped in (folder).\n");
    echo("-A <activity_name>: Activity name this Poller can process. Or use 'SNF_ACTIVITY_ARN' environment variable. Command line arguments have precedence\n");
    echo("-C <client class path>: Path to the PHP file that contains the class that implements your Client Interface\n");
    echo("-N <activity name>: Override the default activity name. Useful if you want to have different client interfaces for the same activity type.\n");
    exit(0);
}

// Check command line input parameters
function check_activity_arguments()
{
    // Filling the globals with input
    global $arn;
    global $logPath;
    global $debug;
    global $clientClassPath;
    global $name;

    // Handle input parameters
    if (!($options = getopt("N:A:l:C:hd")))
        usage();

    if (isset($options['h']))
        usage();

    // Debug
    if (isset($options['d']))
        $debug = true;

    if (isset($options['A']) && $options['A']) {
        $arn = $options['A'];
    } else if (getenv('SNF_ACTIVITY_ARN')) {
        $arn = getenv('SNF_ACTIVITY_ARN');
    } else {
        echo "ERROR: You must provide the ARN of your activity (Sfn ARN). Use option [-A <ARN>] or environment variable: 'SNF_ACTIVITY_ARN'\n";
        usage();
    }

    if (isset($options['C']) && $options['C']) {
        $clientClassPath = $options['C'];
    }

    if (isset($options['N']) && $options['N']) {
        $name = $options['N'];
    }

    if (isset($options['l']))
        $logPath = $options['l'];
}



/*
 * START THE SCRIPT ACTITIVY
 */

// Globals
$debug = false;
$logPath = null;
$arn;
$name = 'TranscodeAsset';
$clientClassPath = null;

check_activity_arguments();

$cpeLogger = new SA\CpeSdk\CpeLogger($name, $logPath);
$cpeLogger->logOut("INFO", basename(__FILE__),
                   "\033[1mStarting activity\033[0m: $name");

// We instanciate the Activity 'ValidateAsset' and give it a name for Snf
$activityPoller = new TranscodeAssetActivity(
    $clientClassPath,
    [
        'arn'  => $arn,
        'name' => $name
    ],
    $debug,
    $cpeLogger);

// Initiate the polling loop and will call your `process` function upon trigger
$activityPoller->doActivity();
