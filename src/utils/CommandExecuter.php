<?php

/**
 * This class help for executing external programs
 * This is necessary as PHP Threads are not very popular
 * It also requires an extra dependency
 * Relying on system execution
 */

use SA\CpeSdk;

class CommandExecuter
{
    private $cpeLogger;
    
    const EXEC_FAILED = "EXEC_FAILED";
    
    public function __construct($cpeLogger = null)
    {
        if (!$cpeLogger)
            $this->cpeLogger = new CpeSdk\CpeLogger(null, 'CommandExecuter');
        $this->cpeLogger = $cpeLogger;
    }
    
    public function execute(
        $cmd,
        $sleep,
        $descriptors,
        $progressCallback,
        $progressCallbackParams,
        $showProgress = false,
        $callbackTurns = 0)
    {
        $this->cpeLogger->log_out("INFO", basename(__FILE__), "Executing: $cmd");
        
        // Start execution of $cmd
        if (!($process = proc_open($cmd, $descriptors, $pipes)) ||
            !is_resource($process)) {
            throw new CpeSdk\CpeException("Unable to execute command:\n$cmd\n",
                self::EXEC_FAILED);
        }

        // Set the pipes as non-blocking
        if (isset($descriptors[1]) &&
            $descriptors[1]) {
            stream_set_blocking($pipes[1], FALSE);
        }
        if (isset($descriptors[2]) &&
            $descriptors[2]) {
            stream_set_blocking($pipes[2], FALSE);
        }
        
        $i = 0;
        
        // Used to store all output
        $allOut = "";
        $allOutErr = "";
        
        // Check process status at every turn
        $procStatus = proc_get_status($process);
        while ($procStatus['running']) 
        {
            // Read prog output
            if (isset($descriptors[1]) &&
                $descriptors[1])
            {
                $out = fread($pipes[1], 8192); 
                $allOut .= $out;
            }
            
            // Read prog errors
            if (isset($descriptors[2]) &&
                $descriptors[2])
            {
                $outErr = fread($pipes[2], 8192); 
                $allOutErr .= $outErr;
            }

            // If callback only after N turns
            if (!$callbackTurns ||
                $i == $callbackTurns)
            {
                if ($showProgress) {
                    echo ".\n";
                }

                // Call user provided callback.
                // Callback should be an array as per doc here: 
                // http://www.php.net/manual/en/language.types.callable.php
                // Type 3: Object method call
                if (isset($progressCallback) && $progressCallback) {
                    call_user_func($progressCallback, $progressCallbackParams, 
                        $allOut, $allOutErr);
                }
                    
                $i = 0;
            }

            // Get latest status
            $procStatus = proc_get_status($process);
            
            if ($showProgress)
            {
                echo ".";
                flush();
            }
            
            $i++;
            sleep($sleep);
        }

        if ($procStatus['exitcode'] > 0)
        {
            $this->cpeLogger->log_out("ERROR", basename(__FILE__), "Can't execute: $cmd. Exit Code: ".$procStatus['exitcode']);
            if ($allOut)
                $this->cpeLogger->log_out("ERROR", basename(__FILE__), "COMMAND STDOUT: ".$allOut);
            if ($allOutErr)
                $this->cpeLogger->log_out("ERROR", basename(__FILE__), "COMMAND STDERR: ".$allOutErr);
        }
        
        if ($showProgress) {
            echo "\n";
        }
    
        // Process is over
        proc_close($process);
        
        return array('out' => $allOut, 'outErr' => $allOutErr);
    }
}