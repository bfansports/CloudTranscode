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
    const EXEC_FAILED = "EXEC_FAILED";
    
    public function execute(
        $cmd,
        $sleep,
        $descriptors,
        $progressCallback,
        $progressCallbackParams,
        $showProgress = false,
        $callbackTurns = 0)
    {
        $this->cpeLogger = new CpeSdk\CpeLogger();
        $this->cpeLogger->log_out("INFO", basename(__FILE__), "Executing: $cmd");
        
        // Start execution of $cmd
        if (!($process = proc_open($cmd, $descriptors, $pipes)) ||
            !is_resource($process))
            throw new CpeSdk\CpeException("Unable to execute command:\n$cmd\n",
                self::EXEC_FAILED);

        // Set the pipes as non-blocking
        if (isset($descriptors[1]) &&
                $descriptors[1])
            stream_set_blocking($pipes[1], FALSE);
        if (isset($descriptors[2]) &&
                $descriptors[2])
            stream_set_blocking($pipes[2], FALSE);
        
        if ($callbackTurns)
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
                $out    = fread($pipes[1], 8192); 
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
            if ($callbackTurns)
            {
                if ($i == $callbackTurns)
                {
                    if ($showProgress)
                        echo ".\n";

                    // Call user provided callback.
                    // Callback should be an array as per doc here: 
                    // http://www.php.net/manual/en/language.types.callable.php
                    // Type 3: Object method call
                    if (isset($progressCallback) && $progressCallback){
                        call_user_func($progressCallback, $progressCallbackParams, 
                            $allOut, $allOutErr);
                    }
                    
                    $i = 0;
                }
            }
            else {
                // Call user provided callback.
                // Callback should be an array as per doc here: 
                // http://www.php.net/manual/en/language.types.callable.php
                // Type 3: Object method call
                if (isset($progressCallback) && $progressCallback) {
                    call_user_func($progressCallback, $progressCallbackParams, 
                        $allOut, $allOutErr);
                }
            }

            // Get latest status
            $procStatus = proc_get_status($process);
            
            if ($showProgress)
            {
                echo ".";
                flush();
            }
            
            if ($callbackTurns)
                $i++;
            
            sleep($sleep);
        }
        
        if ($showProgress)
            echo "\n";
    
        // Process is over
        proc_close($process);
        
        return array('out' => $allOut, 'outErr' => $allOutErr);
    }
}