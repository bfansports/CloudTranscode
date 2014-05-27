<?php

class InputValidator
{
    private $input;
  
    const INPUT_INVALID  = "INPUT_INVALID";
    const FORMAT_INVALID = "FORMAT_INVALID";

    function __construct() { }

    // Decode provided JSON
    public function decode_json_format($input)
    {
        // Validate JSON data and Decode as an array !
        if (!($decoded = json_decode($input)))
            throw new CTException("JSON input is invalid !", 
			    self::INPUT_INVALID); 
        
        return $decoded;
    }

    // Validate JSON input against schemas
    public function validate_input($decoded, $taskType)
    {
        // From Utils.php
        if (!($err = validate_json($decoded, "activities/$taskType.json")))
            return true;

        throw new CTException("JSON input format is not valid! Details:\n".$err, 
            self::FORMAT_INVALID);
    }
}
