<?php

/*
 *   This class serves as a skeleton for Cloud Transcode classes implementing actual activities
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

require_once __DIR__."/../../vendor/autoload.php";
require_once __DIR__.'/../utils/S3Utils.php';

use SA\CpeSdk;

class BasicActivity extends CpeSdk\CpeActivity
{
    public $tmpInputPath;    // Path to directory containing TMP file
    public $inputFilePath;   // Path to input file locally
    public $s3Utils;         // Used to manipulate S3. Download/Upload
  
    // Constants
    const TMP_FOLDER_FAIL      = "TMP_FOLDER_FAIL";
    const UNKOWN_INPUT_TYPE    = "UNKOWN_INPUT_TYPE";

    // JSON checks
    const FORMAT_INVALID       = "FORMAT_INVALID";

    // Types
    const VIDEO                = "VIDEO";
    const THUMB                = "THUMB";
    const AUDIO                = "AUDIO";
    const DOC                  = "DOC";
    const IMAGE                = "IMAGE";

    // XXX Use EFS for storage
    // Nico: Expensive though.
    // This is where we store temporary files for transcoding for now
    // Make sure your partition is big enough!
    const TMP_FOLDER = "/tmp/CloudTranscode/";
    
    public function __construct($client = null, $params, $debug, $cpeLogger)
    {
        parent::__construct($client, $params, $debug, $cpeLogger);
        
        // S3 utils
        $this->s3Utils = new S3Utils($this->cpeLogger);
    }

    // Perform the activity
    public function process($task)
    {
        // Use workflowID to generate a unique TMP folder localy.
        $this->tmpInputPath = self::TMP_FOLDER 
            . $this->logKey."/" 
            . "input";
        
        $inputFileInfo = null;
        if (isset($this->input->{'input_asset'}->{'file'})) {
            $this->input->{'input_asset'}->{'file'} = ltrim($this->input->{'input_asset'}->{'file'}, "/");
            $inputFileInfo = pathinfo($this->input->{'input_asset'}->{'file'});
        }
        
        // Create the tmp folder if doesn't exist
        if (!file_exists($this->tmpInputPath)) 
        {
            if ($this->debug)
                $this->cpeLogger->logOut("DEBUG", basename(__FILE__), 
                                         "Creating TMP input folder '".$this->tmpInputPath."'",
                                         $this->logKey);
            
            if (!mkdir($this->tmpInputPath, 0750, true))
                throw new CpeSdk\CpeException(
                    "Unable to create temporary folder '$this->tmpInputPath' !",
                    self::TMP_FOLDER_FAIL
                );
        }
            
        $this->inputFilePath = null;
        if (isset($this->input->{'input_asset'}->{'http'}))
        {
            // Pad HTTP input so it is cached in case of full encodes
            $this->inputFilePath = 'cache:' . $this->input->{'input_asset'}->{'http'};
        }
        else if (isset($this->input->{'input_asset'}->{'bucket'}) &&
                 isset($this->input->{'input_asset'}->{'file'}))
        {
            // Download input file and store it in TMP folder
            $saveFileTo = $this->tmpInputPath."/".$inputFileInfo['basename'];
            $this->inputFilePath = $this->getFileToProcess(
                $task, 
                $this->input->{'input_asset'}->{'bucket'},
                $this->input->{'input_asset'}->{'file'},
                $saveFileTo
            );
        }
    }
    
    /**
     * Custom code for Cloud Transcode
     */
    
    // Create TMP folder and download file to process
    public function getFileToProcess($task, $inputBuket, $inputFile, $saveFileTo)
    {        
        // Get file from S3 or local copy if any
        $this->cpeLogger->logOut("INFO", 
            basename(__FILE__), 
            "Downloading '$inputBuket/$inputFile' to '$saveFileTo' ...",
            $this->logKey);

        // Use the S3 utils to initiate the download
        $s3Output = $this->s3Utils->get_file_from_s3(
            $inputBuket, 
            $inputFile, 
            $saveFileTo,
            array($this, "activityHeartbeat"), 
            $task,
            $this->logKey
        );
        
        $this->cpeLogger->logOut("INFO", basename(__FILE__), 
            $s3Output['msg'],
            $this->logKey);
        
        $this->cpeLogger->logOut("INFO", basename(__FILE__), 
            "Input file successfully downloaded into local TMP folder '$saveFileTo' !",
            $this->logKey);
        
        return $saveFileTo;
    }
}

