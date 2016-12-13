<?php

/*
 *   Base class for all transcoders. 
 *   You must extend this class to create a new transcoder
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

require_once __DIR__."/../../../vendor/autoload.php";
require_once __DIR__.'/../../utils/S3Utils.php';

use SA\CpeSdk;

class BasicTranscoder 
{
    public $activityObj; // Calling activity object
    public $task;        // Activity TASK
    public $logKey;      // Valling activity loggin key

    public $cpeLogger;   // Logger
    public $s3Utils;     // Used to manipulate S3
    public $executer;    // Executer obj

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
        $this->logKey           = $activityObj->logKey;
        $this->task             = $task;

        $this->cpeLogger        = $activityObj->cpeLogger;
        $this->executer         = new CommandExecuter($activityObj->cpeLogger, $this->logKey);
        $this->s3Utils          = new S3Utils($activityObj->cpeLogger);
    }

    public function isDirEmpty($dir)
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
    public function getAssetInfo($inputFilePath)
    {
        $inputFilePath = escapeshellarg($inputFilePath);
        $ffprobeCmd = "ffprobe -v quiet -of json -show_format -show_streams $inputFilePath";
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
            $this->cpeLogger->logOut(
                "ERROR", 
                basename(__FILE__), 
                "Execution of command '".$ffprobeCmd."' failed.",
                $this->activityLogKey
            );
            return false;
        }
        
        if (empty($out)) {
            throw new CpeSdk\CpeException("Unable to execute FFProbe to get information about '$inputFilePath'!",
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