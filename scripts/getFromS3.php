<?php

$root = realpath(dirname(__FILE__));

require "$root/../Utils.php";

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
            "msg" => "Using local copy: '" . $options['to']  . "'" ]);
    return;
}

try {
    // Get S3 client
    $s3 = $aws->get('S3');
    // Download and Save object to a local file.
    $s3->getObject(array(
            'Bucket' => $options['bucket'],
            'Key'    => $options['file'],
            'SaveAs' => $options['to']
        ));

    // Print JSON error output
    print json_encode([ "status" => "SUCCESS",
            "msg" => "Download '" . $options['bucket'] . "':'" . $options['file'] . "' successful !" ]);
} 
catch (Exception $e) {
    $err = "Unable to get '" . $options['bucket'] . "':'" . $options['file'] . "' file from S3 ! " . $e->getMessage();
    log_out("ERROR", basename(__FILE__), $err);
    
    // Print JSON error output
    print json_encode([ "status" => "ERROR",
            "msg" => $err ]);
}
