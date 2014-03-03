<?php

$root = realpath(dirname(__FILE__));

require "$root/../Utils.php";

function usage()
{
    echo("Usage: php ". basename(__FILE__) . " [-h] [--no_redundant::] [--encrypt::] --bucket <s3 bucket> --file <filename> --from <filepath>\n");
    echo("--help, -h: Print this help\n");
    echo("--bucket <s3 bucket>: Name of the S3 bucket\n");
    echo("--file <filename>: Name of the file in the S3 bucket. You can override local filename.\n");
    echo("--from <filepath>: Full path to file to send to S3\n");
    echo("--no_redundant: Activate type of storage in S3: REDUCED_REDUNDANCY\n");
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
        "no_redundant::", 
        "encrypt::"]);
check_input_parameters($options);

try {
    // Get S3 client
    $s3 = $aws->get('S3');
    $params = array(
        'Bucket'               => $options['bucket'],
        'Key'                  => $options['file'],
        'SourceFile'           => $options['from'],
    );

    // StorageClass and Encryption ?
    if (isset($options['no_redundant']))
        $params['StorageClass'] = 'REDUCED_REDUNDANCY';
    if (isset($options['encrypt']))
        $params['ServerSideEncryption'] = 'AES256';
    

    // Upload and Save file to S3
    $s3->putObject($params); 
    
    // Print JSON error output
    print json_encode([ "status" => "SUCCESS",
            "msg" => "Upload '" . $options['from'] . "' to '" . $options['bucket'] . "/" . $options['file']  . "' successful !" ]);
} 
catch (Exception $e) {
    $err = "Unable to put file '" . $options['from']  . "' into S3: '" . $options['bucket'] . "/" . $options['file']  . "'! " . $e->getMessage();
    
    // Print JSON error output
    print json_encode([ "status" => "ERROR",
            "msg" => $err ]);
}
