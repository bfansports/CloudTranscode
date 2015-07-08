<?php

/**
 * This class validate input assets and get metadata about them 
 * It makes sure the input files to be transcoded exists and is valid.
 * Based on the input file type we lunch the proper transcoder
 */

require_once __DIR__ . '/BasicActivity.php';

class ValidateInputAndAssetActivity extends BasicActivity
{
    // Perform the activity
    public function do_activity($task)
    {
        $this->cpeLogger->log_out(
            "INFO", 
            basename(__FILE__), 
            "Preparing Asset validation ...",
            $this->activityLogKey
        );
        
        // Call parent do_activity:
        // It download the input file we will process.
        parent::do_activity($task);
        
        // Load the right transcoder base on input_type
        // Get asset detailed info
        switch ($this->data->{'input_type'}) 
        {
        case self::VIDEO:
            require_once __DIR__ . '/transcoders/VideoTranscoder.php';
            
            // Initiate transcoder obj
            $videoTranscoder = new VideoTranscoder($this, $task);
            // Get input video information
            $assetInfo = $videoTranscoder->get_asset_info($this->pathToInputFile);
            break;
        case self::IMAGE:
                
            break;
        case self::AUDIO:
                
            break;
        case self::DOC:
                
            break;
        default:
            throw new CpeSdk\CpeException("Unknown 'input_type'! Abording ...", 
                self::UNKOWN_INPUT_TYPE);
        }
        
        return [ "result" => $assetInfo ];
    }
}
