<?php

/**
 * This class validate input assets and get metadata about them
 * It makes sure the input files to be transcoded exists and is valid.
 * Based on the input file type we lunch the proper transcoder
 */

require_once __DIR__.'/BasicActivity.php';

use Guzzle\Http\EntityBody;
use SA\CpeSdk;
use Aws\S3\S3Client;

class ValidateAssetActivity extends BasicActivity
{
    private $finfo;
    private $s3;

    public function __construct($params, $debug, $cpeLogger = null)
    {
        parent::__construct($params, $debug, $cpeLogger);
        $this->finfo = new \finfo(FILEINFO_MIME_TYPE);
        $this->s3 = S3Client::factory();
    }

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

        // Fetch first 1 KiB of file
        $this->send_heartbeat($task);
        $tmpFile = tempnam(sys_get_temp_dir(), 'ct');
        $obj = $this->s3->getObject([
            'Bucket' => $this->input->{'input_asset'}->{'bucket'},
            'Key' => $this->input->{'input_asset'}->{'file'},
            'Range' => 'bytes=0-1024'
        ]);
        $this->send_heartbeat($task);

        // Determine file type
        file_put_contents($tmpFile, (string) $obj['Body']);
        $mime = trim((new CommandExecuter($this->cpeLogger))->execute(
            'file -b --mime-type ' . escapeshellarg($tmpFile))['out']);
        $type = substr($mime, 0, strpos($mime, '/'));

        $this->cpeLogger->log_out(
            "DEBUG",
            basename(__FILE__),
            "File meta information gathered. Mime: $mime | Type: $type",
            $this->activityLogKey
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
            $assetInfo = $videoTranscoder->get_asset_info($this->pathToInputFile);

            // Liberate memory
            unset($videoTranscoder);
        }

        if ($mime === 'application/octet-stream' && isset($asset['streams'])) {
            // Check all stream types
            foreach ($asset['streams'] as $stream) {
                if ($stream['codec_type'] === 'video') {
                    // For a video type, set type to video and break
                    $type = 'video';
                    break;
                } elseif ($stream['codec_type'] === 'audio') {
                    // For an audio type, set to audio, but don't break
                    // in case there's a video stream later
                    $type = 'audio';
                }
            }
        }

        $assetInfo->mime = $mime;
        $assetInfo->type = $type;

        return $assetInfo;
    }
}
