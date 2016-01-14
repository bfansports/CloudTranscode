<?php

/**
 * Interface for any transcoder
 * You must extend this class to create a new transcoder
 */

require_once __DIR__."/../../../vendor/autoload.php";

require_once __DIR__.'/../../utils/S3Utils.php';

use SA\CpeSdk;

class BasicTranscoder 
{
    public $activityLogKey; // Valling activity loggin key
    public $activityObj; // Calling activity object
    public $task; // Activity TASK

    public $cpeLogger; // Logger
    public $cpeSqsWriter; // SQS write for sending msgs to client
    public $cpeJsonValidator; // SQS write for sending msgs to client
    public $s3Utils; // Used to manipulate S3
    public $executer; // Executer obj

    const EXEC_VALIDATE_FAILED  = "EXEC_VALIDATE_FAILED";
    const TRANSCODE_FAIL        = "TRANSCODE_FAIL";
    
    // Types
    const VIDEO = "VIDEO";
    const THUMB = "THUMB";
    const AUDIO = "AUDIO";
    const DOC   = "DOC";
    const IMAGE = "IMAGE";
    
    public function __construct($activityObj, $task) 
    { 
        $this->activityObj      = $activityObj;
        $this->activityLogKey   = $activityObj->activityLogKey;
        $this->task             = $task;

        $this->cpeLogger        = $activityObj->cpeLogger;
        $this->cpeSqsWriter     = $activityObj->cpeSqsWriter;
        $this->cpeJsonValidator = $activityObj->cpeJsonValidator;
        $this->executer         = new CommandExecuter($activityObj->cpeLogger);
        $this->s3Utils          = new S3Utils($activityObj->cpeLogger);
    }

    public function is_dir_empty($dir)
    {
        if (!is_readable($dir)) return null; 
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry !== '.' && $entry !== '..') { 
                return false;
            }
        }
        closedir($handle); 
        return true;
    }

    
    /**************************************
     * GET ASSET METADATA INFO
     * The methods below are used to run ffprobe on assets
     * We capture as much info as possible on the input asset
     */

    // Execute FFPROBE to get asset information
    public function get_asset_info($pathToInputFile)
    {
        $pathToInputFile = escapeshellarg($pathToInputFile);
        $ffprobeCmd = "ffprobe -v quiet -of json -show_format -show_streams $pathToInputFile";
        try {
            // Execute FFMpeg to validate and get information about input video
            $out = $this->executer->execute(
                $ffprobeCmd,
                1, 
                array(
                    1 => array("pipe", "w"),
                    2 => array("pipe", "w")
                ),
                false, false, 
                false, 1
            );
        }
        catch (\Exception $e) {
            $this->cpeLogger->log_out(
                "ERROR", 
                basename(__FILE__), 
                "Execution of command '".$ffprobeCmd."' failed.",
                $this->activityLogKey
            );
            return false;
        }
        
        if (empty($out)) {
            throw new CpeSdk\CpeException("Unable to execute FFProbe to get information about '$pathToInputFile'!",
                self::EXEC_VALIDATE_FAILED);
        }
        
        // FFmpeg writes on STDERR ...
        if (!($assetInfo = json_decode($out['out']))) {
            throw new CpeSdk\CpeException("FFProbe returned invalid JSON!",
                self::EXEC_VALIDATE_FAILED);
        }
        
        return ($assetInfo);
    }
}