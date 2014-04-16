<?php

class S3Utils
{
    private $root; // This file location

    const S3_OPS_FAILED        = "S3_OPS_FAILED";
    const NO_OUTPUT_DATA       = "NO_OUTPUT_DATA";
    
    // External S3 Scripts
    const GET_FROM_S3 = "scripts/getFromS3.php";
    const PUT_IN_S3   = "scripts/putInS3.php";
    
    function __construct() 
    {
        $this->root = realpath(dirname(__FILE__));
    }
    
    // Get a file from S3 using external script localted in "scripts" folder
    public function get_file_from_s3($bucket, $filename, $saveFileTo,
        $callback = false, $callbackParams = false)
    {   
        $cmd = "php " . $this->root . "/" . self::GET_FROM_S3;
        $cmd .= " --bucket $bucket";
        $cmd .= " --file $filename";
        $cmd .= " --to $saveFileTo";
    
        // HAndle execution
        return ($this->handle_s3_ops(self::GET_FROM_S3, $cmd, $callback, $callbackParams));
    }

    // Get a file from S3 using external script localted in "scripts" folder
    public function put_file_into_s3($bucket, $filename, 
        $pathToFileToSend, $options, 
        $callback = false, $callbackParams = false)
    {
        $cmd = "php " . $this->root . "/" . self::PUT_IN_S3;
        $cmd .= " --bucket $bucket";
        $cmd .= " --file $filename";
        $cmd .= " --from $pathToFileToSend";
        if ($options['rrs'])
            $cmd .= " --rrs";
        if ($options['encrypt'])
            $cmd .= " --encrypt";
    
        // HAndle execution
        return ($this->handle_s3_ops(self::PUT_IN_S3, $cmd, $callback, $callbackParams));
    }

    // Execute S3 $cmd and capture output
    private function handle_s3_ops($caller, $cmd, $callback, $callbackParams)
    {
        // Command output capture method
        $descriptorSpecs = array(  
            1 => array("pipe", "w"),
            2 => array("pipe", "w") 
        );
        
        log_out("INFO", basename(__FILE__), "Executing: $cmd");
        if (!($process = proc_open($cmd, $descriptorSpecs, $pipes)))
            throw new CTException("Unable to execute command:\n$cmd\n",
                self::S3_OPS_FAILED);
    
        // While process running, we send heartbeats
        $procStatus = proc_get_status($process);
        while ($procStatus['running']) 
        {
            // REad prog output
            $out    = fread($pipes[1], 8192);  
            $outErr = fread($pipes[2], 8192); 

            // Call user provided callback.
            // Callback should be an array as per doc here: 
            // http://www.php.net/manual/en/language.types.callable.php
            // Type 3: Object method call
            if (isset($callback) && $callback)
                call_user_func($callback, $callbackParams);
            
            // Get latest status
            $procStatus = proc_get_status($process);
            
            sleep(5);
        }

        if ($outErr)
            throw new CTException($outErr,
                self::S3_OPS_FAILED);

        if (!$out)
            throw new CTException("Script '$caller' didn't return any data !",
                self::NO_OUTPUT_DATA);
    
        if (!($decoded = json_decode($out, true)))
            throw new CTException($out,
                self::S3_OPS_FAILED);
    
        if ($decoded["status"] == "ERROR")
            throw new CTException($decoded["msg"],
                self::S3_OPS_FAILED);

        return ($decoded);
    }

}