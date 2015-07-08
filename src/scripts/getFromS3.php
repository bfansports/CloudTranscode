<?php

/**
 * Script used to get a file in AWS S3
 **/

require __DIR__ . "/../../vendor/autoload.php";

use Aws\S3\S3Client;

function usage()
{
    echo("Usage: php ". basename(__FILE__) . " [-h] [--force] --bucket <s3 bucket> --file <filename> --to <filepath>\n");
    echo("--help, -h: Print this help\n");
    echo("--bucket <s3 bucket>: Name of the S3 bucket\n");
    echo("--file <filename>: Name of the file in the S3 bucket\n");
    echo("--to <filepath>: Full path to file where to save. You can override original filename.\n");
    echo("--force: Force download even if file exists locally\n\n");
    exit(0);
}

function check_input_parameters($options)
{
    if (!count($options) || isset($options['h']) ||
        isset($options['help']))
        usage();
    
    if (!isset($options['bucket']) || !isset($options['file']) ||
        !isset($options['to']))
    {
        print "Error: Missing mandatory parameter !\n";
        usage();
    }
}

$options = getopt("h", array("bucket:", "file:", "to:", "force::", "help::"));
check_input_parameters($options);

// If local file already exists. We don't download unless --force
if (!isset($options['force']) && 
    file_exists($options['to']) &&
    filesize($options['to']))
{
    print json_encode([ "status" => "SUCCESS",
            "msg" => "[".__FILE__."] Using local copy: '" . $options['to']  . "'" ]);
    exit(0);
}

try {
    // Get S3 client
    $s3 = S3Client::factory();
    
    // Download and Save object to a local file.
    $s3->getObject(array(
            'Bucket' => $options['bucket'],
            'Key'    => $options['file'],
            'SaveAs' => $options['to']
        ));

    // Print JSON error output
    print json_encode([ "status" => "SUCCESS",
            "msg" => "[".__FILE__."] Download '" . $options['bucket'] . "/" . $options['file'] . "' successful !" ]);
} 
catch (Exception $e) {
    $err = "Unable to get '" . $options['bucket'] . "/" . $options['file'] . "' file from S3 ! " . $e->getMessage();
    // Print JSON error output
    print json_encode([ "status" => "ERROR",
            "msg" => "[".__FILE__."] $err" ]);
    
    die("[".__FILE__."] $err");
}
