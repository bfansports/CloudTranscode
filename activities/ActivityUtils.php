<?php

// Create a local TMP folder using the workflowID
function create_tmp_local_storage($workflowId)
{
    $tmpRoot = '/tmp/CloudTranscode/';
    
    $localPath = $tmpRoot . $workflowId . "/";
    if (!file_exists($localPath . "transcode/"))
    {
        if (!mkdir($localPath . "transcode/", 0750, true))
            return false;
    }
    
    return $localPath;
}

// Get a file from S3 and copy it in $pathToFile
function get_file_from_S3($pathToFile, $bucket, $filename)
{
    global $aws;

    // Local copy exists ? If not we download the file from S3
    if (!file_exists($pathToFile) || !filesize($pathToFile))
    {
        log_out("INFO", basename(__FILE__), 
            "Downloading input file from S3. Bucket: '$bucket' File: '$filename'");
        try {
			
            // Get S3 client
            $s3 = $aws->get('S3');
            // Download and Save object to a local file.
            //XXX Add callback to send heartbeat
            $object = $s3->getObject(array(
                    'Bucket' => $bucket,
                    'Key'    => $filename,
                    'SaveAs' => $pathToFile
                ));
            
            return false;

		} catch (Exception $e) {
            $err = "Unable to get input file from S3 ! " . $e->getMessage();
            log_out("ERROR", basename(__FILE__), $err);
            return $err;
        }
    }
    
    log_out("INFO", basename(__FILE__), "Using local copy of input file: '" . $pathToFile . "'");
    return false;
}

// Put a $fileToPath to S3 bucket
function put_file_to_S3($pathToFile, $bucket, $filename, $waitCallback, $task)
{
    global $aws;

    try {
        // Get S3 client
        $s3 = $aws->get('S3');
        
        $result = $s3->putObject(array(
                'Bucket'               => $bucket,
                'Key'                  => $filename,
                'SourceFile'           => $pathToFile,
                'ServerSideEncryption' => 'AES256',
                'StorageClass'         => 'REDUCED_REDUNDANCY'
            ));        
    } catch (Exception $e) {
        $err = "Unable to put file into S3 ! " . $e->getMessage();
        log_out("ERROR", basename(__FILE__), $err);
        return $err;
    }
}

// Get the first 8 byts of the file to see if it exists !
function file_exists_in_S3($bucket, $key)
{
    global $aws;

    try {
        // Get S3 client
        $s3 = $aws->get('S3');
        
        $result = $s3->getObject(array(
                'Bucket' => $bucket,
                'Key'    => $filename,
                'Range'  => 'bytes=0-8'
            ));

        if (isset($result['Body']))
            return false;
    } catch (Exception $e) {
        $err = "Unable to get input file from S3 ! " . $e->getMessage();
        log_out("ERROR", basename(__FILE__), $err);
        return $err;
    }

    $err = "Unable to get data from S3 file. Bucket: '$bucket', File: '$key' !";
    log_out("ERROR", basename(__FILE__), $err);
    return $err;
}