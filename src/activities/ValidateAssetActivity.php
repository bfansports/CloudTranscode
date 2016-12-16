#!/usr/bin/php

<?php

/*
 *   This class validate input assets and get mime type and metadata 
 *   Using the ValidateAsset activity you can confirm your asset can be transcoded
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

class ValidateAssetActivity extends BasicActivity
{
    private $finfo;
    private $s3;
    
    public function __construct($client = null, $params, $debug, $cpeLogger)
    {
        # Check if preper env vars are setup
        if (!($region = getenv("AWS_DEFAULT_REGION")))
            throw new CpeSdk\CpeException("Set 'AWS_DEFAULT_REGION' environment variable!");
        
        parent::__construct($client, $params, $debug, $cpeLogger);
        
        $this->finfo = new \finfo(FILEINFO_MIME_TYPE);
        
        $this->s3 = new \Aws\S3\S3Client([
            "version" => "latest",
            "region"  => $region
        ]);
    }

    // Perform the activity
    public function process($task)
    {
        $this->cpeLogger->logOut(
            "INFO",
            basename(__FILE__),
            "Preparing Asset validation ...",
            $this->logKey
        );

        // Call parent process:
        parent::process($task);
        
        // Fetch first 1 KiB of the file for Magic number validation
        $this->activityHeartbeat();
        $tmpFile = tempnam(sys_get_temp_dir(), 'ct');
        $obj = $this->s3->getObject([
                'Bucket' => $this->input->{'input_asset'}->{'bucket'},
                'Key' => $this->input->{'input_asset'}->{'file'},
                'Range' => 'bytes=0-1024'
            ]);
        $this->activityHeartbeat();

        // Determine file type
        file_put_contents($tmpFile, (string) $obj['Body']);
        $mime = trim((new CommandExecuter($this->cpeLogger, $this->logKey))->execute(
            'file -b --mime-type '.escapeshellarg($tmpFile))['out']);
        $type = substr($mime, 0, strpos($mime, '/'));

        if ($this->debug)
            $this->cpeLogger->logOut(
                "DEBUG",
                basename(__FILE__),
                "File meta information gathered. Mime: $mime | Type: $type",
                $this->logKey
        );

        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($type)
        {
        case 'audio':
        case 'video':
        case 'image':
        default:
            require_once __DIR__.'/transcoders/VideoTranscoder.php';

            // Initiate transcoder obj
            $videoTranscoder = new VideoTranscoder($this, $task);
            // Get input video information
            $assetInfo = $videoTranscoder->getAssetInfo($this->inputFilePath);

            // Liberate memory
            unset($videoTranscoder);
        }

        if ($mime === 'application/octet-stream' && isset($assetInfo->streams)) {
            // Check all stream types
            foreach ($assetInfo->streams as $stream) {
                if ($stream->codec_type === 'video') {
                    // For a video type, set type to video and break
                    $type = 'video';
                    break;
                } elseif ($stream->codec_type === 'audio') {
                    // For an audio type, set to audio, but don't break
                    // in case there's a video stream later
                    $type = 'audio';
                }
            }
        }
        
        $assetInfo->mime = $mime;
        $assetInfo->type = $type;

        $result['input_asset']     = $this->input->{'input_asset'};
        $result['input_metadata']  = $assetInfo;
        $result['output_assets']   = $this->input->{'output_assets'};
        
        return json_encode($result);
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
    if (!($options = getopt("A:l:C:hd")))
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
$name = 'ValidateAsset';
$clientClassPath = null;

check_activity_arguments();

$cpeLogger = new SA\CpeSdk\CpeLogger($name, $logPath);
$cpeLogger->logOut("INFO", basename(__FILE__),
                   "\033[1mStarting activity\033[0m: $name");

// We instanciate the Activity 'ValidateAsset' and give it a name for Snf
$activityPoller = new ValidateAssetActivity(
    $clientClassPath,
    [
        'arn'  => $arn,
        'name' => $name
    ],
    $debug,
    $cpeLogger);

// Initiate the polling loop and will call your `process` function upon trigger
$activityPoller->doActivity();


