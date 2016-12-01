<?php

/**
 * Script used to put a file in AWS S3
 **/

require __DIR__ . "/../../vendor/autoload.php";

function usage()
{
    echo("Usage: php ". basename(__FILE__) . " [-h] [--rrs::] [--encrypt::] --bucket <s3 bucket> --file <filename> --from <filepath>\n");
    echo("--help, -h: Print this help\n");
    echo("--bucket <s3 bucket>: Name of the S3 bucket\n");
    echo("--file <filename>: Name of the file to create in bucket. You can override local filename.\n");
    echo("--from <filepath>: Full path to file to send to S3\n");
    echo("--rrs: Activate type of storage in S3: REDUCED_REDUNDANCY\n");
    echo("--encrypt: Activate Server encryption: AES256\n\n");
    exit(0);
}

function check_input_parameters(&$options)
{
    if (!count($options) || isset($options['h']) ||
        isset($options['help']))
        usage();
    
    if (!isset($options['bucket']) || !isset($options['file']) ||
        !isset($options['from']))
    {
        print "Error: Missing mandatory parameter !\n";
        usage();
    }

    $options['bucket'] = rtrim( $options['bucket'], "/");
}

$options = getopt("h", [
        "bucket:", 
        "file:", 
        "from:", 
        "force::", 
        "help::", 
        "rrs::", 
        "encrypt::"]);
check_input_parameters($options);

try {
    // Get S3 client
    $s3 = new \Aws\S3\S3Client([
            'version' => 'latest'
        ]);
    
    $params = array(
        'Bucket'     => $options['bucket'],
        'Key'        => $options['file'],
        'SourceFile' => $options['from'],
    );

    // StorageClass and Encryption ?
    if (isset($options['rrs']))
        $params['StorageClass'] = 'REDUCED_REDUNDANCY';
    if (isset($options['encrypt']))
        $params['ServerSideEncryption'] = 'AES256';
    
    // Upload and Save file to S3
    $s3->putObject($params); 
    
    // Print JSON error output
    print json_encode([ "status" => "SUCCESS",
            "msg" => "[".__FILE__."] Upload '" . $options['from'] . "' to '" . $options['bucket'] . "/" . $options['file']  . "' successful !" ]);
} 
catch (Exception $e) {
    $err = "Unable to put file '" . $options['from']  . "' into S3: '" . $options['bucket'] . "/" . $options['file']  . "'! " . $e->getMessage();
    
    // Print JSON error output
    print json_encode([ "status" => "ERROR",
            "msg" => "[".__FILE__."] $err" ]);

    die("[".__FILE__."] $err");
}
