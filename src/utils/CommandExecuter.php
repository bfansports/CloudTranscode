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
    private $logKey;

    const EXEC_FAILED = "EXEC_FAILED";

    public function __construct($cpeLogger, $logKey = null)
    {
        $this->cpeLogger = $cpeLogger;
        $this->logKey    = $logKey;
    }

    public function execute(
        $cmd,
        $sleep = 1,
        $descriptors = array(
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        ),
        $progressCallback = null,
        $progressCallbackParams = null,
        $showProgress = false,
        $callbackTurns = 0,
        $logKey = null)
    {
        if ($logKey)
            $this->logKey = $logKey;
        
        $this->cpeLogger->logOut("INFO", basename(__FILE__), "Executing: $cmd", $this->logKey);

        // Start execution of $cmd
        if (!($process = proc_open($cmd, $descriptors, $pipes)) ||
            !is_resource($process)) {
            $this->cpeLogger->logOut("ERROR",
                                     basename(__FILE__), "Unable to execute command:\n$cmd",
                                     $this->logKey);
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
        do {
            sleep($sleep);

            // If callback only after N turns
            if ( !$callbackTurns || in_array($i, array(0, $callbackTurns)) )
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
            if ($showProgress) {
                echo ".";
                flush();
            }
            
            // Read prog output
            if (isset($pipes[1]) && $pipes[1]) {
                $out = stream_get_contents($pipes[1], -1);
                $allOut .= $out;
            }

            // Read prog errors
            if (isset($pipes[2]) && $pipes[2]) {
                $outErr = stream_get_contents($pipes[2], -1);
                $allOutErr .= $outErr;
            }

            $i++;
        } while ($procStatus['running']);

        if (isset($pipes[1]))
            fclose($pipes[1]);
        if (isset($pipes[2]))
            fclose($pipes[2]);

        if ($procStatus['exitcode'] > 0)
        {
            $this->cpeLogger->logOut("ERROR",
                                     basename(__FILE__),
                                     "Can't execute: $cmd. Exit Code: ".$procStatus['exitcode'],
                                     $this->logKey);
            if ($allOut)
                $this->cpeLogger->logOut("ERROR",
                                         basename(__FILE__), "COMMAND STDOUT: ".$allOut,
                                         $this->logKey);
            if ($allOutErr)
                $this->cpeLogger->logOut("ERROR",
                                         basename(__FILE__), "COMMAND STDERR: ".$allOutErr,
                                         $this->logKey);
        }

        if ($showProgress) {
            echo "\n";
        }

        // Process is over
        proc_close($process);

        return array('out' => $allOut, 'outErr' => $allOutErr);
    }
}
