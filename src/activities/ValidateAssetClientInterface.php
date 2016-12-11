<?php

/**
 * Implement this \CpeSdk\CpeClientInterface and pass it to the constructor of your activity
 * This allows you to interact with your application: Store data in your DB, or anything else
 * that you see fit to send to your app when something happens: start, fail, success, heartbeat
 */

require_once __DIR__."/../../vendor/autoload.php";

use SA\CpeSdk;

class ValidateAssetClientInterface implements SA\CpeSdk\CpeClientInterface 
{
    public $cpeLogger; // Logger
    
    public function __construct($cpeLogger) {
        $this->cpeLogger = $cpeLogger;
    }
    
    /*
     * Called right before initiating your Activity callback function in do_activity
     * $task contains the return value of Snf `getActivityTask` method
     */
    public function onStart($task) {
        
    }
    
    /*
     * Called right after notifying Snf that your activity has failed
     */
    public function onFail($taskToken, $error = "", $cause = "") {
        
    }
    
    /*
     * Called right after notifying Snf that your activity has succeeded
     */
    public function onSuccess($taskToken, $output = '') {
        
    }
    
    /*
     * Called right after notifying Snf that your activity is alive with a heartbeat
     * We forward the `$data` you passed to `activity_heartbeat` method so you can 
     * use it in your client application. Like a progress status for example :)
     */
    public function onHeartbeat($taskToken, $data = null) {
        
    }

    /*
     * Called if there is an Exception with Snf.
     * This way you can flag your job accordingly in your client app
     * and send a SNS notification, alert, etc
     */
    public function onException($context, \Exception $exception) {
        
    }
    
}
