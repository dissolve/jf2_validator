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


/*
function do_validate($input, $fix_quotes = false, $jf2feed = false)
{
    if($jf2feed){

        return jf2feed_validate($input, $fix_quotes);
    } else {
        return jf2_validate($input, $fix_quotes);
    }
}
function jf2feed_validate($input, $fix_quotes = false)
{
}
 */

class JF2Validator
{
    public function validate($input, $fix_quotes = false)
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
        if(!isset($parsed['type']) && (!isset($parsed['children']) || count($parsed) != 1)){
            $results[] = new ResultMessage(P_WARN, '"type" field missing. This is not recommended unless it includes only the attribute "children", and nothing else');
        }

        if(isset($parsed['type']) && (is_array($parsed['type']) || !is_string($parsed['type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'type field must be a single string', '"type" : ' . print_r($parsed['type'], true));
        }

        if(isset($parsed['children']) && (!is_array($parsed['children']) || $this->is_hash($parsed['children']))){
            $results[] = new ResultMessage(P_ERROR, 'children must be serialized as an array []', '"children" : ' . print_r($parsed['children'], true));
        } elseif (isset($parsed['children'])){
            $all_are_hashes = true;
            $error_lines = array();
            foreach($parsed['children'] as $child){
                if(!$this->is_hash($child)){
                    $all_are_hashes = false;
                    $error_lines[] = $child;
                }
            }
            
            if(!$all_are_hashes){
                $results[] = new ResultMessage(P_ERROR, 'children array must contain only objects', '"children" : ' . print_r($error_lines, true));
            }

        }

        //TODO: top level ONLY
        if(isset($parsed['references']) && (is_array($parsed['references']) || !is_string($parsed['references'] ))){
            $results[] = new ResultMessage(P_ERROR, 'references must be serialized as a hash', '"references" : ' . print_r($parsed['references'], true));
        } 
        //TODO check possible values
        if(isset($parsed['lang']) && (is_array($parsed['lang']) || !is_string($parsed['lang'] ))){
            $results[] = new ResultMessage(P_ERROR, 'lang field must be a single string', '"lang" : ' . print_r($parsed['lang'], true));
        }
        //TODO: top level ONLY
        if(isset($parsed['@context']) && (is_array($parsed['@context']) || !is_string($parsed['@context'] ))){
            $results[] = new ResultMessage(P_ERROR, '@context field must be a single string', '"@context" : ' . print_r($parsed['@context'], true));
        }
        if(isset($parsed['@context']) && trim($parsed['@context']) != 'http://www.w3.org/ns/jf2') {
            $results[] = new ResultMessage(P_ERROR, '@context field if present, must be "http://www.w3.org/ns/jf2"', '"@context" : ' . print_r($parsed['@context'], true));
        }

        if(isset($parsed['value']) && (is_array($parsed['value']) || !is_string($parsed['value'] ))){
            $results[] = new ResultMessage(P_ERROR, 'value field must be a single string', '"value" : ' . print_r($parsed['value'], true));
        }

        //TODO these doesn't make sense on top level items
        if(isset($parsed['content-type']) && (is_array($parsed['content-type']) || !is_string($parsed['content-type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'content-type field must be a single string', '"content-type" : ' . print_r($parsed['content-type'], true));
        }
        if(isset($parsed['text']) && (is_array($parsed['text']) || !is_string($parsed['text'] ))){
            $results[] = new ResultMessage(P_ERROR, 'text field must be a single string', '"text" : ' . print_r($parsed['text'], true));
        }
        if(isset($parsed['html']) && (is_array($parsed['html']) || !is_string($parsed['html'] ))){
            $results[] = new ResultMessage(P_ERROR, 'html field must be a single string', '"html" : ' . print_r($parsed['html'], true));
        }


        $results = array_merge($results, $this->descend_and_recurse($parsed));

        return $results;

    }

    private function descend_and_recurse($parsed)
    {
        $results = array();
        if($this->is_hash($parsed)){
            foreach($parsed as $key => $val){
                if($this->is_hash($val)){
                    $results = array_merge($results, $this->recursive_validate($val));
                } elseif(is_array($val)){
                    $results = array_merge($results, $this->descend_and_recurse($val));
                }
            }

        } elseif(is_array($parsed)){
            foreach($parsed as $val){
                if($this->is_hash($val)){
                    $results = array_merge($results, $this->recursive_validate($val));
                } elseif(is_array($val)){
                    $results = array_merge($results, $this->descend_and_recurse($val));
                }
            }
        }
        return $results;
        //var_dump($parsed);
    }

    private function recursive_validate($parsed)
    {
        $results = array();

        if(isset($parsed['references']) ){
            $results[] = new ResultMessage(P_ERROR, 'references is only allowed at the top level', '"references" : ' . print_r($parsed['references'], true));
        } 
        if(isset($parsed['@context']) ){
            $results[] = new ResultMessage(P_ERROR, '@context is only allowed at the top level', '"@context" : ' . print_r($parsed['@context'], true));
        }

        if(isset($parsed['type']) && (is_array($parsed['type']) || !is_string($parsed['type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'type field must be a single string', '"type" : ' . print_r($parsed['type'], true));
        }

        if(isset($parsed['children']) && (!is_array($parsed['children']) || $this->is_hash($parsed['children']))){
            $results[] = new ResultMessage(P_ERROR, 'children must be serialized as an array []', '"children" : ' . print_r($parsed['children'], true));
        } elseif (isset($parsed['children'])){
            $all_are_hashes = true;
            $error_lines = array();
            foreach($parsed['children'] as $child){
                if(!$this->is_hash($child)){
                    $all_are_hashes = false;
                    $error_lines[] = $child;
                }
            }
            
            if(!$all_are_hashes){
                $results[] = new ResultMessage(P_ERROR, 'children array must contain only objects', '"children" : ' . print_r($error_lines, true));
            }

        }

        //TODO check possible values
        if(isset($parsed['lang']) && (is_array($parsed['lang']) || !is_string($parsed['lang'] ))){
            $results[] = new ResultMessage(P_ERROR, 'lang field must be a single string', '"lang" : ' . print_r($parsed['lang'], true));
        }

        if(isset($parsed['value']) && (is_array($parsed['value']) || !is_string($parsed['value'] ))){
            $results[] = new ResultMessage(P_ERROR, 'value field must be a single string', '"value" : ' . print_r($parsed['value'], true));
        }
        if(isset($parsed['content-type']) && (is_array($parsed['content-type']) || !is_string($parsed['content-type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'content-type field must be a single string', '"content-type" : ' . print_r($parsed['content-type'], true));
        }
        if(isset($parsed['text']) && (is_array($parsed['text']) || !is_string($parsed['text'] ))){
            $results[] = new ResultMessage(P_ERROR, 'text field must be a single string', '"text" : ' . print_r($parsed['text'], true));
        }
        if(isset($parsed['html']) && (is_array($parsed['html']) || !is_string($parsed['html'] ))){
            $results[] = new ResultMessage(P_ERROR, 'html field must be a single string', '"html" : ' . print_r($parsed['html'], true));
        }

        $results = array_merge($results, $this->descend_and_recurse($parsed));
        
        return $results;
    }

    private function is_hash($in)
    {
        if(!is_array($in)){
            return false;
        }
        return is_array($in) && count(array_filter(array_keys($in), 'is_string')) > 0;
    }
}

