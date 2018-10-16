<?php

/*
 *   This class handles Images transcoding
 *   We transcode and generate an output image using ImageMagic (convert)
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

require_once __DIR__.'/BasicTranscoder.php';

use SA\CpeSdk;

class ImageTranscoder extends BasicTranscoder
{
    /***********************
     * TRANSCODE INPUT IMAGE
     * Below is the code used to transcode images based on $outputWanted.
     **********************/

    // $metadata should contain the ffprobe video stream array.

    // Start Convert for output transcoding
    public function transcode_asset(
        $tmpPathInput,
        $pathToInputFile,
        $pathToOutputFiles,
        $metadata = null,
        $outputWanted)
    {
        if ($metadata) {
            // Extract an sanitize metadata
            $metadata = $this->_extractFileInfo($metadata);
        }

        $this->cpeLogger->logOut(
            "INFO",
            basename(__FILE__),
            "Start Transcoding Asset '$pathToInputFile' ...",
            $this->activityLogKey
        );

        if ($metadata)
            $this->cpeLogger->logOut(
                "INFO",
                basename(__FILE__),
                "Input Video metadata: " . print_r($metadata, true),
                $this->activityLogKey
            );

        try {
            $convertCmd = "";

            // Update output extension file if it ends with '.*'
            // Output will take the same extension as input
            $this->_updateOutputExtension(
                $pathToInputFile,
                $outputWanted);

            // Custom command
            if (isset($outputWanted->{'custom_cmd'}) &&
                $outputWanted->{'custom_cmd'}) {
                $convertCmd = $this->craft_convert_custom_cmd(
                    $tmpPathInput,
                    $pathToInputFile,
                    $pathToOutputFiles,
                    $metadata,
                    $outputWanted
                );
            }
            else {
                $convertCmd = $this->craft_convert_cmd(
                    $tmpPathInput,
                    $pathToInputFile,
                    $pathToOutputFiles,
                    $metadata,
                    $outputWanted
                );
            }

            $this->cpeLogger->logOut(
                "INFO",
                basename(__FILE__),
                "CONVERT CMD:\n$convertCmd\n",
                $this->activityLogKey
            );

            // Use executer to start Converter command
            // Use 'capture_progression' function as callback
            // Pass video 'duration' as parameter
            // Sleep 1sec between turns and callback every 10 turns
            // Output progression logs (true)
            $this->executer->execute(
                $convertCmd,
                1,
                array(2 => array("pipe", "w")),
                array($this->activityObj, "activityHeartbeat"),
                null,
                true,
                10
            );

            // Test if we have an output file !
            if (!file_exists($pathToOutputFiles) ||
                $this->isDirEmpty($pathToOutputFiles)) {
                throw new CpeSdk\CpeException(
                    "Output file '$pathToOutputFiles' hasn't been created successfully or is empty !",
                    self::TRANSCODE_FAIL
                );
            }

            // FFProbe the output file and return its information
            // XXX: Remove FFprobe for image convertion. Save time
            $outputInfo =
                $this->getAssetInfo($pathToOutputFiles."/".$outputWanted->{'output_file_info'}['basename']);
        }
        catch (\Exception $e) {
            $this->cpeLogger->logOut(
                "ERROR",
                basename(__FILE__),
                "Execution of command '".$convertCmd."' failed: " . print_r($metadata, true). ". ".$e->getMessage(),
                $this->activityLogKey
            );
            throw $e;
        }

        // No error. Transcode successful
        $this->cpeLogger->logOut(
            "INFO",
            basename(__FILE__),
            "Transcoding successfull !",
            $this->activityLogKey
        );

        return [
            "output"     => $outputWanted,
            "outputInfo" => $outputInfo
        ];
    }

    // Craft command based on JSON input
    private function craft_convert_cmd(
        $tmpPathInput,
        $pathToInputFile,
        $pathToOutputFiles,
        $metadata,
        $outputWanted)
    {
        $convertArgs = "$pathToInputFile ";

        if (isset($outputWanted->{'quality'})) {
            $quality = $outputWanted->{'quality'};
            $convertArgs .= "-quality $quality ";
        }

        if (isset($outputWanted->{'resize'})) {
            $resize = $outputWanted->{'resize'};
            $convertArgs .= "-resize $resize ";
        }

        if (isset($outputWanted->{'thumbnail'})) {
            $thumbnail = $outputWanted->{'thumbnail'};
            $convertArgs .= "-thumbnail $thumbnail ";
        }

        if (isset($outputWanted->{'crop'})) {
            $crop = $outputWanted->{'crop'};
            $convertArgs .= "-crop $crop ";
        }

        // Append output filename to path
        $pathToOutputFiles .=
            "/" . $outputWanted->{'output_file_info'}['basename'];

        $convertCmd = "convert $convertArgs $pathToOutputFiles";

        return ($convertCmd);
    }

    // Craft custom command
    private function craft_convert_custom_cmd(
        $tmpPathInput,
        $pathToInputFile,
        $pathToOutputFiles,
        $metadata,
        $outputWanted)
    {
        $convertCmd = $outputWanted->{'custom_cmd'};

        // Replace ${input_file} by input file path
        $pathToInputFile = escapeshellarg($pathToInputFile);
        $convertCmd = preg_replace('/\$\{input_file\}/',
            $pathToInputFile, $convertCmd);

        // Append output filename to path
        $pathToOutputFiles .= "/" . $outputWanted->{'output_file_info'}['basename'];
        // Replace ${output_file} by output filename and path to local disk
        $convertCmd = preg_replace('/\$\{output_file\}/',
            $pathToOutputFiles, $convertCmd);

        return ($convertCmd);
    }

    // Check if output file need an update on the extension
    private function _updateOutputExtension(
        $pathToInputFile,
        &$outputWanted)
    {
        $inputExtension =
            pathinfo($pathToInputFile)['extension'];

        // REplace output extension if == * with the input extension
        $outputPathInfo =
            pathinfo($outputWanted->{'output_file_info'}['basename']);
        $outputExtension = $outputPathInfo['extension'];
        if ($outputExtension == "*") {
            $outputWanted->{'output_file_info'}['basename'] = preg_replace(
                '/\*/',
                $inputExtension,
                $outputWanted->{'output_file_info'}['basename']);
        }
    }
}
