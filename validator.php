<?php
define('P_WARN', 1);
define('P_ERROR', 2);

class ResultMessage
{
    public $type;
    public $message;
    public $line;

    public function __construct($type, $message, $line = null){
        $this->type = $type;
        $this->message = $message;
        $this->line = $line;
    }
    
}

function do_validate($input, $fix_quotes = false)
{
    $results = array();

    if($fix_quotes){
        $input = str_replace("'", '"', $input);
    }
    $parsed = json_decode($input, true);

	$last_err = json_last_error();

	if($last_err){

		$constants = get_defined_constants(true);
		$json_errors = array();
		foreach ($constants["json"] as $name => $value) {
			if (!strncmp($name, "JSON_ERROR_", 11)) {
				$json_errors[$value] = $name;
			}
		}

        $results[] = new ResultMessage(P_ERROR, 'JSON decode error "' . $json_errors[$last_err]. '". Parsing stopped.');
        return $results;
	}
    if(!isset($parsed['type']) && count($parsed) != 1 && !isset($parsed['children'])){
        $results[] = new ResultMessage(P_WARN, '"type" field missing. This is not recommended unless it includes only the attribute "children", and nothing else');
    }
    if(is_array($parsed['type'])){
        $results[] = new ResultMessage(P_WARN, 'type field must be a single string', '"type" : ' . print_r($parsed['type'], true));
    }
    if(!is_string($parsed['type'])){
        $results[] = new ResultMessage(P_WARN, 'type field must be a string', '"type" : ' . print_r($parsed['type'], true));
    }

    //var_dump($parsed);


	return $results;

}

