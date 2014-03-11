<?php

class InputValidator
{
    private $input;
    
	const INPUT_INVALID  = "INPUT_INVALID";
	const INVALID_FORMAT = "INVALID_FORMAT";

    function __construct()
	{
    }

    // Decode provided JSON
    public function decode_json_format($input)
    {
		// Validate JSON data and Decode as an Object
		if (!($decoded = json_decode($input, true)))
            return [
                "status"  => "ERROR",
                "error"   => self::INPUT_INVALID,
                "details" => "JSON input is invalid !"
            ];

        // XXX Check JSON using module
        // Check parameter formats: String, Integer, etc.
        // Assigned to: Ceach
    
        return [
            "status" => "SUCCESS",
            "input"  => $decoded
        ];
    }

    // Validate JSON input against schemas
    public function validate_input($decoded_input)
    {
        $retriever = new JsonSchema\Uri\UriRetriever;
        $root = realpath(dirname(__FILE__));
        $schema = $retriever->retrieve('file://' . realpath("$root/input_schema.json"));

        $refResolver = new JsonSchema\RefResolver($retriever);
        $refResolver->resolve($schema, 'file://' . __DIR__);

        $validator = new JsonSchema\Validator();
        $validator->check($decoded_input, $schema);

        if ($validator->isValid()) {
            echo "The supplied JSON validates against the schema.\n";
        } else {
            $details = "JSON format is not valid! Details\n";
            foreach ($validator->getErrors() as $error) {
                $details .= sprintf("[%s] %s\n", $error['property'], $error['message']);
            }

            return [
                "status"  => "ERROR",
                "error"   => self::INVALID_FORMAT,
                "details" => $details
            ];
        }
    }
}