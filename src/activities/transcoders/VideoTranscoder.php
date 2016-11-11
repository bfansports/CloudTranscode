<?php

/**
 * This class handled Video transcoding
 * Here we the input video
 * We transcode and generate output videos
 * We use ffprobe, ffmpeg and convert to analyse, transcode and manipulate videos and images (watermark)
 */

require_once __DIR__ . '/BasicTranscoder.php';

use SA\CpeSdk;

class VideoTranscoder extends BasicTranscoder
{
    // Errors
    const GET_VIDEO_INFO_FAILED = "GET_VIDEO_INFO_FAILED";
    const GET_AUDIO_INFO_FAILED = "GET_AUDIO_INFO_FAILED";
    const GET_DURATION_FAILED   = "GET_DURATION_FAILED";
    const NO_OUTPUT             = "NO_OUTPUT";
    const BAD_OUTPUT            = "BAD_OUTPUT";
    const NO_PRESET             = "NO_PRESET";
    const BAD_PRESETS_DIR       = "BAD_PRESETS_DIR";
    const UNKNOWN_PRESET        = "UNKNOWN_PRESET";
    const OPEN_PRESET_FAILED    = "OPEN_PRESET_FAILED";
    const BAD_PRESET_FORMAT     = "BAD_PRESET_FORMAT";
    const RATIO_ERROR           = "RATIO_ERROR";
    const ENLARGEMENT_ERROR     = "ENLARGEMENT_ERROR";
    const WATERMARK_ERROR       = "WATERMARK_ERROR";
    
    const SNAPSHOT_SEC_DEFAULT  = 0;
    const INTERVALS_DEFAULT     = 10;
    
    
    /***********************
     * TRANSCODE INPUT VIDEO
     * Below is the code used to transcode videos based on $outputWanted. 
     **********************/

    // $metadata should contain the ffprobe video stream array.

    // Start FFmpeg for output transcoding
    public function transcode_asset(
        $tmpPathInput,
        $pathToInputFile, 
        $pathToOutputFiles,
        $metadata = null, 
        $outputWanted)
    {
        /* if (!$metadata) */
        /*     throw new CpeSdk\CpeException( */
        /*         "NO Input Video metadata! We can't transcode an asset without probing it first. Use ValidateAsset activity to probe it and pass a 'metadata' field containing the input metadata to this TranscodeAsset activity.", */
        /*         self::TRANSCODE_FAIL */
        /*     ); */
        
        if ($metadata) {
            // Extract an sanitize metadata
            $metadata = $this->_extractFileInfo($metadata);
        }
        
        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Start Transcoding Asset '$pathToInputFile' ...",
            $this->activityLogKey
        );
        
        if ($metadata)
            $this->cpeLogger->log_out(
                "INFO", 
                basename(__FILE__), 
                "Input Video metadata: " . print_r($metadata, true),
                $this->activityLogKey
            );
        
        try {
            $ffmpegCmd = "";

            // Custom command
            if (isset($outputWanted->{'custom_cmd'}) &&
                $outputWanted->{'custom_cmd'}) {
                $ffmpegCmd = $this->craft_ffmpeg_custom_cmd(
                    $tmpPathInput,
                    $pathToInputFile,
                    $pathToOutputFiles,
                    $metadata, 
                    $outputWanted
                );
            } else if ($outputWanted->{'type'} == self::VIDEO) {
                $ffmpegCmd = $this->craft_ffmpeg_cmd_video(
                    $tmpPathInput,
                    $pathToInputFile,
                    $pathToOutputFiles,
                    $metadata, 
                    $outputWanted
                );
            } else if ($outputWanted->{'type'} == self::THUMB) {
                $ffmpegCmd = $this->craft_ffmpeg_cmd_thumb(
                    $tmpPathInput,
                    $pathToInputFile,
                    $pathToOutputFiles,
                    $metadata, 
                    $outputWanted
                );
            }
        
            $this->cpeLogger->log_out(
                "INFO",
                basename(__FILE__),
                "FFMPEG CMD:\n$ffmpegCmd\n",
                $this->activityLogKey
            );
            
            // Use executer to start FFMpeg command
            // Use 'capture_progression' function as callback
            // Pass video 'duration' as parameter
            // Sleep 1sec between turns and callback every 10 turns
            // Output progression logs (true)
            $this->executer->execute(
                $ffmpegCmd, 
                1, 
                array(2 => array("pipe", "w")),
                array($this, "capture_progression"), 
                $metadata['duration'], 
                true, 
                10
            );

            // Test if we have an output file !
            if (!file_exists($pathToOutputFiles) || 
                $this->is_dir_empty($pathToOutputFiles)) {
                throw new CpeSdk\CpeException(
                    "Output file '$pathToOutputFiles' hasn't been created successfully or is empty !",
                    self::TRANSCODE_FAIL
                );
            }

            // FFProbe the output file and return its information
            $output_info =
                $this->get_asset_info($pathToOutputFiles."/".$outputWanted->{'output_file_info'}['basename']);
        }
        catch (\Exception $e) {
            $this->cpeLogger->log_out(
                "ERROR", 
                basename(__FILE__), 
                "Execution of command '".$ffmpegCmd."' failed: " . print_r($metadata, true). ". ".$e->getMessage(),
                $this->activityLogKey
            );
            return false;
        }
        
        // No error. Transcode successful
        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Transcoding successfull !",
            $this->activityLogKey
        );

        return $output_info;
    }

    // Craft custom command
    private function craft_ffmpeg_custom_cmd(
        $tmpPathInput,
        $pathToInputFile,
        $pathToOutputFiles,
        $metadata, 
        $outputWanted)
    {
        $ffmpegCmd = $outputWanted->{'custom_cmd'};
        
        // Replace ${input_file} by input file path
        $pathToInputFile = escapeshellarg($pathToInputFile);
        $ffmpegCmd = preg_replace('/\$\{input_file\}/', $pathToInputFile, $ffmpegCmd);
        
        $watermarkOptions = "";
        // Process options for watermark
        if (isset($outputWanted->{'watermark'}) && $outputWanted->{'watermark'}) {
            $watermarkOptions = 
                $this->get_watermark_options(
                    $tmpPathInput,
                    $outputWanted->{'watermark'});
            // Replace ${watermark_options} by watermark options
            $ffmpegCmd = preg_replace('/\$\{watermark_options\}/', $watermarkOptions, $ffmpegCmd);
        }
        
        // Append output filename to path
        $pathToOutputFiles .= "/" . $outputWanted->{'output_file_info'}['basename'];
        // Replace ${output_file} by output filename and path to local disk
        $ffmpegCmd = preg_replace('/\$\{output_file\}/', $pathToOutputFiles, $ffmpegCmd);

        return ($ffmpegCmd);
    }
    
    // Generate FFmpeg command for video transcoding
    private function craft_ffmpeg_cmd_video(
        $tmpPathInput,
        $pathToInputFile,
        $pathToOutputFiles,
        $metadata, 
        $outputWanted)
    {
        // Check if a size is provided to override preset size
        $size = $this->set_output_video_size($metadata, $outputWanted);
        $pathToInputFile = escapeshellarg($pathToInputFile);
        
        $videoCodec = $outputWanted->{'preset_values'}->{'video_codec'};
        if (isset($outputWanted->{'video_codec'})) {
            $videoCodec = $outputWanted->{'video_codec'};
        }
        
        $audioCodec = $outputWanted->{'preset_values'}->{'audio_codec'};
        if (isset($outputWanted->{'audio_codec'})) {
            $audioCodec = $outputWanted->{'audio_codec'};
        }

        $videoBitrate = $outputWanted->{'preset_values'}->{'video_bitrate'};
        if (isset($outputWanted->{'video_bitrate'})) {
            $videoBitrate = $outputWanted->{'video_bitrate'};
        }
        
        $audioBitrate = $outputWanted->{'preset_values'}->{'audio_bitrate'};
        if (isset($outputWanted->{'audio_bitrate'})) {
            $audioBitrate = $outputWanted->{'audio_bitrate'};
        }

        $frameRate = $outputWanted->{'preset_values'}->{'frame_rate'};
        if (isset($outputWanted->{'frame_rate'})) {
            $frameRate = $outputWanted->{'frame_rate'};
        }

        $formattedOptions = "";
        if (isset($outputWanted->{'preset_values'}->{'video_codec_options'})) {
            $formattedOptions = 
                $this->set_output_video_codec_options($outputWanted->{'preset_values'}->{'video_codec_options'});
        }

        $watermarkOptions = "";
        // Process options for watermark
        if (isset($outputWanted->{'watermark'}) && $outputWanted->{'watermark'}) {
            $watermarkOptions = 
                $this->get_watermark_options(
                    $tmpPathInput,
                    $outputWanted->{'watermark'});
        }
        
        // Create FFMpeg arguments
        $ffmpegArgs =  " -i $pathToInputFile -y -threads 0";
        $ffmpegArgs .= " -vf scale=$size";
        $ffmpegArgs .= " -vcodec $videoCodec";
        $ffmpegArgs .= " -acodec $audioCodec";
        $ffmpegArgs .= " -b:v $videoBitrate";
        $ffmpegArgs .= " -b:a $audioBitrate";
        $ffmpegArgs .= " -r $frameRate";
        $ffmpegArgs .= " $formattedOptions";
        $ffmpegArgs .= " $watermarkOptions";
        
        // Append output filename to path
        $pathToOutputFiles .= "/" . $outputWanted->{'output_file_info'}['basename'];
        // Final command
        $ffmpegCmd  = "ffmpeg $ffmpegArgs $pathToOutputFiles";
            
        return ($ffmpegCmd);
    }
    
    // Craft FFMpeg command to generate thumbnails
    private function craft_ffmpeg_cmd_thumb(
        $tmpPathInput,
        $pathToInputFile,
        $pathToOutputFiles,
        $metadata, 
        $outputWanted)
    {
        // FIXME: Use $metadata to improve the FFMpeg command
        // inputAssetInfo contains FFprobe output

        $frameOptions   = "";
        $outputFileInfo = pathinfo($outputWanted->{'file'});
        $pathToInputFile = escapeshellarg($pathToInputFile);
        if ($outputWanted->{'mode'} == 'snapshot')
        {
            $snapshot_sec = self::SNAPSHOT_SEC_DEFAULT;
            if (isset($outputWanted->{'snapshot_sec'}) &&
                $outputWanted->{'snapshot_sec'} > 0) {
                $snapshot_sec = $outputWanted->{'snapshot_sec'};
            }
                
            $time = gmdate("H:i:s", $snapshot_sec) . ".000";
            $pathToOutputFiles .= "/" . $outputFileInfo['basename'];
            $frameOptions = " -ss $time -vframes 1";
        }
        else if ($outputWanted->{'mode'} == 'intervals')
        {
            $intervals = self::INTERVALS_DEFAULT;
            if (isset($outputWanted->{'intervals'}) &&
                $outputWanted->{'intervals'} > 0) {
                $intervals = $outputWanted->{'intervals'};
            }
            
            $pathToOutputFiles .= "/" . $outputFileInfo['filename'] . "%06d." 
                . $outputFileInfo['extension'];
            $frameOptions = " -vf fps=fps=1/$intervals";
        }

        // Create FFMpeg arguments
        $ffmpegArgs  =  " -i $pathToInputFile -y -threads 0";
        $ffmpegArgs .= " -vf scale=" . $outputWanted->{'size'};
        $ffmpegArgs .= " $frameOptions -f image2 -q:v 8";

        // Final command
        $ffmpegCmd   = "ffmpeg $ffmpegArgs $pathToOutputFiles";
        
        return ($ffmpegCmd);
    }

    // Get watermark info to generate overlay options for ffmpeg
    private function get_watermark_options(
        $tmpPathInput,
        $watermarkOptions)
    {
        // Get info about the video in order to save the watermark in same location
        $watermarkFileInfo = pathinfo($watermarkOptions->{'file'});
        $watermarkPath     = $tmpPathInput."/".$watermarkFileInfo['basename'];
        $newWatermarkPath  = $tmpPathInput."/new-".$watermarkFileInfo['basename'];
        
        // Get watermark image from S3
        $s3Output = $this->s3Utils->get_file_from_s3(
            $watermarkOptions->{'bucket'}, 
            $watermarkOptions->{'file'},
            $watermarkPath);
        
        $this->cpeLogger->log_out("INFO",
            basename(__FILE__), 
            $s3Output['msg'],
            $this->activityLogKey);

        // Transform watermark for opacity
        $convertCmd = "convert $watermarkPath -alpha on -channel A -evaluate Multiply " . $watermarkOptions->{'opacity'} . " +channel $newWatermarkPath";

        try {
            $out = $this->executer->execute($convertCmd, 1, 
                array(1 => array("pipe", "w"), 2 => array("pipe", "w")),
                false, false, 
                false, 1);
        }
        catch (\Exception $e) {
            $this->cpeLogger->log_out(
                "ERROR", 
                basename(__FILE__), 
                "Execution of command '".$convertCmd."' failed",
                $this->activityLogKey
            );
            return false;
        }
        
        // Any error ?
        if (isset($out['outErr']) && $out['outErr'] != "" &&
            (!file_exists($newWatermarkPath) || !filesize($newWatermarkPath))) {
            throw new CpeSdk\CpeException(
                "Error transforming watermark file '$watermarkPath'!",
                self::WATERMARK_ERROR);
        }
        
        // Format options for FFMpeg
        $size      = $watermarkOptions->{'size'};
        $positions = $this->get_watermark_position($watermarkOptions);
        $formattedOptions = "-vf \"movie=$newWatermarkPath, scale=$size [wm]; [in][wm] overlay=" . $positions['x'] . ':' . $positions['y'] . " [out]\"";
        
        return ($formattedOptions);
    }

    // Generate the command line option to position the watermark
    private function get_watermark_position($watermarkOptions)
    {
        $positions = array('x' => 0, 'y' => 0);
        
        if ($watermarkOptions->{'x'} >= 0) {
            $positions['x'] = $watermarkOptions->{'x'};
        }
        if ($watermarkOptions->{'y'} >= 0) {
            $positions['y'] = $watermarkOptions->{'y'};
        }
        if ($watermarkOptions->{'x'} < 0) {
            $positions['x'] = 'main_w-overlay_w' . $watermarkOptions->{'x'};
        }
        if ($watermarkOptions->{'y'} < 0) {
            $positions['y'] = 'main_h-overlay_h' . $watermarkOptions->{'y'};
        }

        return ($positions);
    }

    // Get Video codec options and format the options properly for ffmpeg
    private function set_output_video_codec_options($videoCodecOptions)
    {
        $formattedOptions = "";
        $options = explode(",", $videoCodecOptions);
        
        foreach ($options as $option)
        {
            $keyVal = explode("=", $option);
            if ($keyVal[0] === 'Profile') {
                $formattedOptions .= " -profile:v ".$keyVal[1];
            } else if ($keyVal[0] === 'Level') {
                $formattedOptions .= " -level ".$keyVal[1];
            } else if ($keyVal[0] === 'MaxReferenceFrames') {
                $formattedOptions .= " -refs ".$keyVal[1];
            }
        }

        return ($formattedOptions);
    }

    // Verify Ratio and Size of output file to ensure it respect restrictions
    // Return the output video size
    private function set_output_video_size(&$metadata, $outputWanted)
    {
        // Handle video size
        $size = $outputWanted->{'preset_values'}->{'size'};
        if (isset($outputWanted->{'size'})) {
            $size = $outputWanted->{'size'};
        }
        
        // Ratio check
        if (!isset($outputWanted->{'keep_ratio'}) || 
            $outputWanted->{'keep_ratio'} == 'true')
        {
            // FIXME: Improve ratio check
            
            /* $outputRatio = floatval($this->get_ratio($size)); */
            /* $inputRatio  = floatval($metadata->{'ratio'}); */

            /* if ($outputRatio != $inputRatio) */
            /*     throw new CpeSdk\CpeException( */
            /*         "Output video ratio is different from input video: input_ratio: '$inputRatio' / output_ratio: '$outputRatio'. 'keep_ratio' option is enabled (default). Disable it to allow ratio change.", */
            /*         self::RATIO_ERROR */
            /*     ); */
        }
        
        // Enlargement check
        if ($metadata &&
            (!isset($outputWanted->{'allow_upscale'})
                || $outputWanted->{'allow_upscale'} == 'false'))
        {
            $metadata['size'] = $metadata['video']['resolution'];
            $inputSize        = $metadata['size'];
            $inputSizeSplit   = explode("x", $inputSize);
            $outputSizeSplit  = explode("x", $size);

            if (intval($outputSizeSplit[0]) > intval($inputSizeSplit[0]) ||
                intval($outputSizeSplit[1]) > intval($inputSizeSplit[1])) {
                $this->cpeLogger->log_out(
                    "INFO", 
                    basename(__FILE__), 
                    "Requested transcode size is bigger than the original. `allow_upscale` option not provided",
                    $this->activityLogKey
                );
                $size = $metadata['size'];
            }
        }

        return (str_replace("x",":", $size));
    }
    
    // REad ffmpeg output and calculate % progress
    // This is a callback called from 'CommandExecuter.php'
    // $out and $outErr contain FFmpeg output
    public function capture_progression($duration, $out, $outErr)
    {
        // We also call a callback here ... the 'send_hearbeat' function from the origin activity
        // This way we notify SWF that we are alive !
        call_user_func(array($this->activityObj, 'send_heartbeat'), 
            $this->task);
        
        $progress = 0;

        // # get the current time
        preg_match_all("/time=(.*?) bitrate/", $outErr, $matches); 

        $last = array_pop($matches);
        // # this is needed if there is more than one match
        if (is_array($last)) {
            $last = array_pop($last);
        }

        // Perform Time transformation to get seconds
        $ar   = array_reverse(explode(":", $last));
        $done = floatval($ar[0]);
        if (!empty($ar[1])) {
            $done += intval($ar[1]) * 60;
        }
        if (!empty($ar[2])) {
            $done += intval($ar[2]) * 60 * 60;
        }

        // # finally, progress is easy
        if ($done && $duration) {
            $progress = round(($done/$duration)*100);
        }
        
        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Progress: $done / $progress%",
            $this->activityLogKey
        );

        // Send progress through SQSUtils to notify client of progress
        $this->cpeSqsWriter->activity_progress(
            $this->task, 
            [
                "duration" => $duration,
                "done"     => $done,
                "progress" => $progress
            ]
        );
    }

    // Combine preset and custom output settings to generate output settings
    public function get_preset_values($output_wanted)
    {
        if (!$output_wanted) {
            throw new CpeSdk\CpeException("No output data provided to transcoder !",
                self::NO_OUTPUT);
        }

        if (!isset($output_wanted->{"preset"})) {
            throw new CpeSdk\CpeException("No preset selected for output !",
                self::BAD_PRESETS_DIR);
        }
        
        $preset     = $output_wanted->{"preset"};
        $presetPath = __DIR__ . '/../../../presets/';

        if (!($presetContent = file_get_contents($presetPath.$preset.".json"))) {
            throw new CpeSdk\CpeException("Can't open preset file !",
                self::OPEN_PRESET_FAILED);
        }
        
        if (!($decodedPreset = json_decode($presetContent))) {
            throw new CpeSdk\CpeException("Bad preset JSON format !",
                self::BAD_PRESET_FORMAT);
        }
        
        return ($decodedPreset);
    }
    
    // Check if the preset exists
    public function validate_preset($output)
    {
        if (!isset($output->{"preset"})) {
            throw new CpeSdk\CpeException("No preset selected for output !",
                self::BAD_PRESETS_DIR);
        }

        $preset     = $output->{"preset"};
        $presetPath = __DIR__ . '/../../../presets/';
        
        if (!($files = scandir($presetPath))) {
            throw new CpeSdk\CpeException("Unable to open preset directory '$presetPath' !",
                self::BAD_PRESETS_DIR);
        }
        
        foreach ($files as $presetFile)
        {
            if ($presetFile === '.' || $presetFile === '..') { continue; }
            
            if (is_file("$presetPath/$presetFile"))
            {
                if ($preset === pathinfo($presetFile)["filename"])
                {
                    if (!($presetContent = file_get_contents("$presetPath/$presetFile"))) {
                        throw new CpeSdk\CpeException("Can't open preset file '$presetPath/$presetFile'!",
                            self::OPEN_PRESET_FAILED);
                    }
                    
                    if (!($decodedPreset = json_decode($presetContent))) {
                        throw new CpeSdk\CpeException("Bad preset JSON format '$presetPath/$presetFile'!",
                            self::BAD_PRESET_FORMAT);
                    }

                    # Validate against JSON Schemas
                    /* if (($err = ($decodedPreset, "presets.json"))) { */
                    /*     throw new CpeSdk\CpeException("JSON preset file '$presetPath/$presetFile' invalid! Details:\n".$err, */
                    /*         self::BAD_PRESET_FORMAT); */
                    /* } */
                    
                    return true;
                }
            }
        }
        
        throw new CpeSdk\CpeException("Unkown preset file '$preset' !",
            self::UNKNOWN_PRESET);
    }

    // Extract Metadata from ffprobe
    private function _extractFileInfo($metadata) {
        
        $videoStreams;
        $audioStreams;

        foreach ($metadata->streams as $key => $value) {
            if ($value->codec_type === 'video') {
                $videoStreams = $value;
            }
            else if ($value->codec_type === 'audio') {
                $audioStreams = $value;
            }
        }

        $analyse = [
            'duration' => isset($metadata->format->duration) ? (float)$metadata->format->duration : 0,
            'video' => empty($videoStreams) ? null : [
                'codec' => $videoStreams->codec_name,
                'color' => $videoStreams->color_space,
                'resolution' => $videoStreams->width . 'x' . $videoStreams->height,
                'sar' => $videoStreams->sample_aspect_ratio,
                'dar' => $videoStreams->display_aspect_ratio,
                'framerate' => $videoStreams->r_frame_rate,
                'bitrate' => isset($videoStreams->bit_rate) ? (int)$videoStreams->bit_rate : null
            ],
            'audio' => empty($audioStreams) ? null : [
                'codec' => $audioStreams->codec_name,
                'frequency' => $audioStreams->sample_rate,
                'channels' => (int)$audioStreams->channels,
                'depth' => $audioStreams->bits_per_sample,
                'bitrate' => (int)$audioStreams->bit_rate
            ]
        ];

        return $analyse;
    }
}