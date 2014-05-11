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
        $retriever = new JsonSchema\Uri\UriRetriever;
        $root = realpath(dirname(__FILE__));
        $schema = $retriever->retrieve('file://' . realpath("$root/schemas/$taskType.json"));

        $refResolver = new JsonSchema\RefResolver($retriever);
        $refResolver->resolve($schema, 'file://' . realpath("$root/schemas/output/"));

        $validator = new JsonSchema\Validator();
        $validator->check($decoded, $schema);

        if ($validator->isValid())
            return true;
    
        $details = "JSON input format is not valid! Details:\n";
        foreach ($validator->getErrors() as $error) {
            $details .= sprintf("[%s] %s\n", $error['property'], $error['message']);
        }
        throw new CTException($details, 
            self::FORMAT_INVALID); 
    }
}
