<?php

require __DIR__ . '/CommandExecuter.php';

class S3Utils
{
    const S3_OPS_FAILED        = "S3_OPS_FAILED";
    const NO_OUTPUT_DATA       = "NO_OUTPUT_DATA";
    
    // External S3 Scripts
    const GET_FROM_S3 = "/../scripts/getFromS3.php";
    const PUT_IN_S3   = "/../scripts/putInS3.php";
    
    // Get a file from S3 using external script localted in "scripts" folder
    public function get_file_from_s3($bucket, $filename, $saveFileTo,
        $callback = false, $callbackParams = false)
    {   
        $cmd = "php " . __DIR__ . self::GET_FROM_S3;
        $cmd .= " --bucket $bucket";
        $cmd .= " --file $filename";
        $cmd .= " --to $saveFileTo";
    
        // HAndle execution
        return ($this->handle_s3_ops(self::GET_FROM_S3, $cmd, 
                $callback, $callbackParams));
    }

    // Get a file from S3 using external script localted in "scripts" folder
    public function put_file_into_s3($bucket, $filename, 
        $pathToFileToSend, $options, 
        $callback = false, $callbackParams = false)
    {
        $cmd = "php " . __DIR__ . self::PUT_IN_S3;
        $cmd .= " --bucket $bucket";
        $cmd .= " --file $filename";
        $cmd .= " --from $pathToFileToSend";
        if ($options['rrs'])
            $cmd .= " --rrs";
        if ($options['encrypt'])
            $cmd .= " --encrypt";
    
        // HAndle execution
        return ($this->handle_s3_ops(self::PUT_IN_S3, $cmd, 
                $callback, $callbackParams));
    }

    // Execute S3 $cmd and capture output
    private function handle_s3_ops($caller, $cmd, $callback, $callbackParams)
    {
        // Use executer to start external S3 script
        // The array request listening to 1 (STDOUT) and 2 (STDERR)
        $executer = new CommandExecuter();
        $out = $executer->execute($cmd, 2,
            array(1 => array("pipe", "w"), 2 => array("pipe", "w")),
            $callback, $callbackParams, 
            true, 5);
        
        if ($out['outErr'])
            throw new CTException($out['outErr'],
                self::S3_OPS_FAILED);

        if (!$out['out'])
            throw new CTException("Script '$caller' didn't return any data !",
                self::NO_OUTPUT_DATA);
    
        if (!($decoded = json_decode($out['out'], true)))
            throw new CTException($out['out'],
                self::S3_OPS_FAILED);
    
        if ($decoded["status"] == "ERROR")
            throw new CTException($decoded["msg"],
                self::S3_OPS_FAILED);

        return ($decoded);
    }

}