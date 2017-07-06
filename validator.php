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

        return $this->validate_top_level_object($parsed);
    }


    protected function validate_top_level_object($parsed)
    {
        $results = array();

        if(!isset($parsed['type']) && (!isset($parsed['children']) || count($parsed) != 1)){
            $results[] = new ResultMessage(P_WARN, '"type" field missing. This is not recommended unless it includes only the attribute "children", and nothing else');
        }

        if(isset($parsed['type']) && (is_array($parsed['type']) || !is_string($parsed['type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'type field must be a single string', '"type" : ' . json_encode($parsed['type']));
        }

        if(isset($parsed['children']) && (!is_array($parsed['children']) || $this->is_hash($parsed['children']))){
            $results[] = new ResultMessage(P_ERROR, 'children must be serialized as an array []', '"children" : ' . json_encode($parsed['children']));
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
                $results[] = new ResultMessage(P_ERROR, 'children array must contain only objects', '"children" : ' . json_encode($error_lines));
            }

        }

        //TODO check possible values
        if(isset($parsed['lang']) && (is_array($parsed['lang']) || !is_string($parsed['lang'] ))){
            $results[] = new ResultMessage(P_ERROR, 'lang field must be a single string', '"lang" : ' . json_encode($parsed['lang']));
        }

        if(isset($parsed['@context']) && trim($parsed['@context']) != 'http://www.w3.org/ns/jf2') {
            $results[] = new ResultMessage(P_ERROR, '@context field if present, must be "http://www.w3.org/ns/jf2"', '"@context" : ' . json_encode($parsed['@context']));
        }

        if(isset($parsed['value']) && (is_array($parsed['value']) || !is_string($parsed['value'] ))){
            $results[] = new ResultMessage(P_ERROR, 'value field must be a single string', '"value" : ' . json_encode($parsed['value']));
        }

        // top level ONLY
        if(isset($parsed['references']) && !$this->is_hash($parsed['references'])) {
            $results[] = new ResultMessage(P_ERROR, 'references must be serialized as a hash', '"references" : ' . json_encode($parsed['references']));
        } 
        if(isset($parsed['@context']) && (is_array($parsed['@context']) || !is_string($parsed['@context'] ))){
            $results[] = new ResultMessage(P_ERROR, '@context field must be a single string', '"@context" : ' . json_encode($parsed['@context']));
        }

        // these doesn't make sense on top level items
        if(isset($parsed['content-type'])){
            $results[] = new ResultMessage(P_WARN, 'content-type field should not be defined on the top level object', '"content-type" : ' . json_encode($parsed['content-type']));
        }
        if(isset($parsed['content-type']) && (is_array($parsed['content-type']) || !is_string($parsed['content-type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'content-type field must be a single string', '"content-type" : ' . json_encode($parsed['content-type']));
        }
        if(isset($parsed['text'])){
            $results[] = new ResultMessage(P_WARN, 'text field should not be defined on the top level object', '"text" : ' . json_encode($parsed['text']));
        }
        if(isset($parsed['text']) && (is_array($parsed['text']) || !is_string($parsed['text'] ))){
            $results[] = new ResultMessage(P_ERROR, 'text field must be a single string', '"text" : ' . json_encode($parsed['text']));
        }
        if(isset($parsed['html'])){
            $results[] = new ResultMessage(P_WARN, 'html field should not be defined on the top level object', '"html" : ' . json_encode($parsed['html']));
        }
        if(isset($parsed['html']) && (is_array($parsed['html']) || !is_string($parsed['html'] ))){
            $results[] = new ResultMessage(P_ERROR, 'html field must be a single string', '"html" : ' . json_encode($parsed['html']));
        }

        $results = array_merge($results, $this->descend_and_recurse($parsed));

        return $results;

    }

    protected function descend_and_recurse($parsed)
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
            if(count($parsed) == 1){
                $results[] = new ResultMessage(P_WARN, 'Arrays of a single item should be just a single item', json_encode($parsed));
            }
            foreach($parsed as $val){
                if($this->is_hash($val)){
                    $results = array_merge($results, $this->recursive_validate($val));
                } elseif(is_array($val)){
                    $results = array_merge($results, $this->descend_and_recurse($val));
                }
            }
        }
        return $results;
    }

    protected function recursive_validate($parsed)
    {
        $results = array();

        if(isset($parsed['references']) ){
            $results[] = new ResultMessage(P_ERROR, 'references is only allowed at the top level', '"references" : ' . json_encode($parsed['references']));
        } 
        if(isset($parsed['@context']) ){
            $results[] = new ResultMessage(P_ERROR, '@context is only allowed at the top level', '"@context" : ' . json_encode($parsed['@context']));
        }

        if(isset($parsed['type']) && (is_array($parsed['type']) || !is_string($parsed['type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'type field must be a single string', '"type" : ' . json_encode($parsed['type']));
        }

        if(isset($parsed['children']) && (!is_array($parsed['children']) || $this->is_hash($parsed['children']))){
            $results[] = new ResultMessage(P_ERROR, 'children must be serialized as an array []', '"children" : ' . json_encode($parsed['children']));
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
                $results[] = new ResultMessage(P_ERROR, 'children array must contain only objects', '"children" : ' . json_encode($error_lines));
            }

        }

        //TODO check possible values
        if(isset($parsed['lang']) && (is_array($parsed['lang']) || !is_string($parsed['lang'] ))){
            $results[] = new ResultMessage(P_ERROR, 'lang field must be a single string', '"lang" : ' . json_encode($parsed['lang']));
        }

        if(isset($parsed['value']) && (is_array($parsed['value']) || !is_string($parsed['value'] ))){
            $results[] = new ResultMessage(P_ERROR, 'value field must be a single string', '"value" : ' . json_encode($parsed['value']));
        }
        if(isset($parsed['content-type']) && (is_array($parsed['content-type']) || !is_string($parsed['content-type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'content-type field must be a single string', '"content-type" : ' . json_encode($parsed['content-type']));
        }
        if(isset($parsed['text']) && (is_array($parsed['text']) || !is_string($parsed['text'] ))){
            $results[] = new ResultMessage(P_ERROR, 'text field must be a single string', '"text" : ' . json_encode($parsed['text']));
        }
        if(isset($parsed['html']) && (is_array($parsed['html']) || !is_string($parsed['html'] ))){
            $results[] = new ResultMessage(P_ERROR, 'html field must be a single string', '"html" : ' . json_encode($parsed['html']));
        }

        $results = array_merge($results, $this->descend_and_recurse($parsed));
        
        return $results;
    }

    protected function is_hash($in)
    {
        if(!is_array($in)){
            return false;
        }
        return is_array($in) && count(array_filter(array_keys($in), 'is_string')) > 0;
    }
}

class JF2FeedValidator extends JF2Validator
{

    protected function validate_top_level_object($parsed)
    {
        $results = array();

        if(!isset($parsed['type']) ){
            $results[] = new ResultMessage(P_ERROR, '"type" field missing. "type":"feed" is required on the top level object');
        } elseif($parsed['type'] != 'feed'){
            $results[] = new ResultMessage(P_ERROR, '"type" field must be "feed" on the top level object');
        }

        if(!isset($parsed['name'])){
            $results[] = new ResultMessage(P_ERROR, '"name" field is required on the top level object');
        } elseif(!is_string($parsed['name'])){
            $results[] = new ResultMessage(P_ERROR, '"name" field must be a single string', '"name" : ' . json_encode($parsed['name']));
        }

        if(!isset($parsed['url'])){
            $results[] = new ResultMessage(P_WARN, '"url" field Should be present on the top level object');
        } elseif(!is_string($parsed['url'])){
            $results[] = new ResultMessage(P_ERROR, '"url" field must be a single string', '"url" : ' . json_encode($parsed['url']));
        }

        if(isset($parsed['photo']) && !is_string($parsed['photo'])){
            $results[] = new ResultMessage(P_ERROR, '"photo" field must be a single string', '"photo" : ' . json_encode($parsed['photo']));
        }

        if(isset($parsed['author']) ){
            if(!$this->is_hash($parsed['author'])){
                $results[] = new ResultMessage(P_ERROR, '"author" on top level object be an object', '"author" : ' . json_encode($parsed['author']));
            } else {
                if(!isset($parsed['author']['url']) && !isset($parsed['author']['name']) && !isset($parsed['author']['photo'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" must contain at least "url", "name", or "photo"', '"author" : ' . json_encode($parsed['author']));
                }
                if(isset($parsed['author']['url']) && !is_string($parsed['author']['url'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" > "url" must be a single string"', '"author" : ' . json_encode($parsed['author']));
                }
                if(isset($parsed['author']['name']) && !is_string($parsed['author']['name'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" > "name" must be a single string"', '"author" : ' . json_encode($parsed['author']));
                }
                if(isset($parsed['author']['photo']) && !is_string($parsed['author']['photo'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" > "photo" must be a single string"', '"author" : ' . json_encode($parsed['author']));
                }
            }
        }


        if(!isset($parsed['children'])){
            $results[] = new ResultMessage(P_WARN, '"children" array missing. It seems likely this is an error.');
        }

        if(isset($parsed['children']) && (!is_array($parsed['children']) || $this->is_hash($parsed['children']))){
            $results[] = new ResultMessage(P_ERROR, 'children must be serialized as an array []', '"children" : ' . json_encode($parsed['children']));
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
                $results[] = new ResultMessage(P_ERROR, 'children array must contain only objects', '"children" : ' . json_encode($error_lines));
            }

        }

        //TODO check possible values
        if(isset($parsed['lang']) && (is_array($parsed['lang']) || !is_string($parsed['lang'] ))){
            $results[] = new ResultMessage(P_ERROR, 'lang field must be a single string', '"lang" : ' . json_encode($parsed['lang']));
        }

        if(isset($parsed['@context']) && trim($parsed['@context']) != 'http://www.w3.org/ns/jf2') {
            $results[] = new ResultMessage(P_ERROR, '@context field if present, must be "http://www.w3.org/ns/jf2"', '"@context" : ' . json_encode($parsed['@context']));
        }

        if(isset($parsed['value']) && (is_array($parsed['value']) || !is_string($parsed['value'] ))){
            $results[] = new ResultMessage(P_ERROR, 'value field must be a single string', '"value" : ' . json_encode($parsed['value']));
        }


        // top level ONLY
        if(isset($parsed['references']) && !$this->is_hash($parsed['references'])) {
            $results[] = new ResultMessage(P_ERROR, 'references must be serialized as a hash', '"references" : ' . json_encode($parsed['references']));
        } 
        if(isset($parsed['@context']) && (is_array($parsed['@context']) || !is_string($parsed['@context'] ))){
            $results[] = new ResultMessage(P_ERROR, '@context field must be a single string', '"@context" : ' . json_encode($parsed['@context']));
        }


        // these doesn't make sense on top level items
        if(isset($parsed['content-type'])){
            $results[] = new ResultMessage(P_WARN, 'content-type field should not be defined on the top level object', '"content-type" : ' . json_encode($parsed['content-type']));
        }
        if(isset($parsed['content-type']) && (is_array($parsed['content-type']) || !is_string($parsed['content-type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'content-type field must be a single string', '"content-type" : ' . json_encode($parsed['content-type']));
        }
        if(isset($parsed['text'])){
            $results[] = new ResultMessage(P_WARN, 'text field should not be defined on the top level object', '"text" : ' . json_encode($parsed['text']));
        }
        if(isset($parsed['text']) && (is_array($parsed['text']) || !is_string($parsed['text'] ))){
            $results[] = new ResultMessage(P_ERROR, 'text field must be a single string', '"text" : ' . json_encode($parsed['text']));
        }
        if(isset($parsed['html'])){
            $results[] = new ResultMessage(P_WARN, 'html field should not be defined on the top level object', '"html" : ' . json_encode($parsed['html']));
        }
        if(isset($parsed['html']) && (is_array($parsed['html']) || !is_string($parsed['html'] ))){
            $results[] = new ResultMessage(P_ERROR, 'html field must be a single string', '"html" : ' . json_encode($parsed['html']));
        }


        //$results = array_merge($results, $this->descend_and_recurse($parsed));
        foreach($parsed as $key => $val){
            if($key == 'children'){ 
                //TODO
                if(is_array($val) && !$this->is_hash($val)) { // should have checked this earlier
                    foreach($val as $child){
                        $results = array_merge($results, $this->validate_child_items($child));
                    }
                }
            } else {
                if($this->is_hash($val)){
                    $results = array_merge($results, $this->recursive_validate($val));
                } elseif(is_array($val)){
                    $results = array_merge($results, $this->descend_and_recurse($val));
                }
            }
        }

        return $results;

    }
    protected function validate_child_items($parsed)
    {
        $results = array();

        if(!isset($parsed['type']) ){
            $results[] = new ResultMessage(P_ERROR, '"type" field missing. "type":"item" is required on the second level item objects', json_encode($parsed));
        } elseif($parsed['type'] != 'item'){
            $results[] = new ResultMessage(P_ERROR, '"type" must be "item" on second level item objects', json_encode($parsed));
        }

        if(!isset($parsed['name'])){
            $results[] = new ResultMessage(P_WARN, '"name" field should be on second level item objects', json_encode($parsed));
        } elseif(!is_string($parsed['name'])){
            $results[] = new ResultMessage(P_ERROR, '"name" field must be a single string', '"name" : ' . json_encode($parsed['name']));
        }

        if(!isset($parsed['uid'])){
            $results[] = new ResultMessage(P_ERROR, '"uid" field is required on second level item objects', json_encode($parsed));
        } elseif(!is_string($parsed['uid'])){
            $results[] = new ResultMessage(P_ERROR, '"uid" field must be a single string', '"uid" : ' . json_encode($parsed['uid']));
        }

        if(isset($parsed['url']) && !is_string($parsed['url'])){
            $results[] = new ResultMessage(P_ERROR, '"url" field must be a single string', '"url" : ' . json_encode($parsed['url']));
        }

        if(isset($parsed['photo']) && !is_string($parsed['photo'])){
            $results[] = new ResultMessage(P_ERROR, '"photo" field must be a single string', '"photo" : ' . json_encode($parsed['photo']));
        }

        if(isset($parsed['author']) ){
            if(!$this->is_hash($parsed['author'])){
                $results[] = new ResultMessage(P_ERROR, '"author" on second level item objects must be an object', '"author" : ' . json_encode($parsed['author']));
            } else {
                if(!isset($parsed['author']['url']) && !isset($parsed['author']['name']) && !isset($parsed['author']['photo'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" must contain at least "url", "name", or "photo"', '"author" : ' . json_encode($parsed['author']));
                }
                if(isset($parsed['author']['url']) && !is_string($parsed['author']['url'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" > "url" must be a single string"', '"author" : ' . json_encode($parsed['author']));
                }
                if(isset($parsed['author']['name']) && !is_string($parsed['author']['name'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" > "name" must be a single string"', '"author" : ' . json_encode($parsed['author']));
                }
                if(isset($parsed['author']['photo']) && !is_string($parsed['author']['photo'])){
                    $results[] = new ResultMessage(P_ERROR, '"author" > "photo" must be a single string"', '"author" : ' . json_encode($parsed['author']));
                }
            }
        }

        if(!isset($parsed['published'])){
            $results[] = new ResultMessage(P_WARN, '"published" should be set on any second level item object', '"published" : ' . json_encode($parsed['published']));
        } elseif (!is_string($parsed['published'])){
            $results[] = new ResultMessage(P_ERROR, '"published" field must be a single string', '"published" : ' . json_encode($parsed['published']));
        }

        if(isset($parsed['updated']) && !is_string($parsed['updated'])){
            $results[] = new ResultMessage(P_ERROR, '"updated" field must be a single string', '"updated" : ' . json_encode($parsed['updated']));
        }
        if(isset($parsed['summary']) && !is_string($parsed['summary'])){
            $results[] = new ResultMessage(P_ERROR, '"summary" field must be a single string', '"summary" : ' . json_encode($parsed['summary']));
        }

        if(isset($parsed['category'])){
           if(!is_array($parsed['category']) || $this->is_hash($parsed['category'])){
               $results[] = new ResultMessage(P_ERROR, '"category" field must be an array of single strings', '"category" : ' . json_encode($parsed['category']));
           } else {
               $good = true;
               foreach($parsed['category'] as $category){
                   if(!is_string($category)){
                       $good = false;
                   } 
               }
               if(!$good){
                   $results[] = new ResultMessage(P_ERROR, '"category" field must be an array of single strings', '"category" : ' . json_encode($parsed['category']));
               }
           }
        }

        if(isset($parsed['content'])){
           if(!$this->is_hash($parsed['content']) || !isset($parsed['content']['url']) || !is_string($parsed['content']['url'])){
               $results[] = new ResultMessage(P_ERROR, '"content" field must be an object and contain a "text" field with a single string', '"content" : ' . json_encode($parsed['content']));
           }
        }

        if(isset($parsed['video'])){
           if(!is_array($parsed['video']) || $this->is_hash($parsed['video'])){
               $results[] = new ResultMessage(P_ERROR, '"video" field must be an array of objects', '"video" : ' . json_encode($parsed['video']));
           } else {
               $good = true;
               foreach($parsed['video'] as $video){
                   if(!$this->is_hash($video)){
                       $good = false;
                       if(!isset($video['url']) || !is_string($video['url'])){
                           $results[] = new ResultMessage(P_ERROR, '"video" field entries must have a "url" field with a single string', '"video" : ' . json_encode($parsed['video']));
                       }
                   } 
               }
               if(!$good){
                   $results[] = new ResultMessage(P_ERROR, '"video" field must be an array of objects', '"video" : ' . json_encode($parsed['video']));
               }
           }
        }
        
        if(isset($parsed['audio'])){
           if(!is_array($parsed['audio']) || $this->is_hash($parsed['audio'])){
               $results[] = new ResultMessage(P_ERROR, '"audio" field must be an array of objects', '"audio" : ' . json_encode($parsed['audio']));
           } else {
               $good = true;
               foreach($parsed['audio'] as $audio){
                   if(!$this->is_hash($audio)){
                       $good = false;
                       if(!isset($audio['url']) || !is_string($audio['url'])){
                           $results[] = new ResultMessage(P_ERROR, '"audio" field entries must have a "url" field with a single string', '"audio" : ' . json_encode($parsed['audio']));
                       }
                   } 
               }
               if(!$good){
                   $results[] = new ResultMessage(P_ERROR, '"audio" field must be an array of objects', '"audio" : ' . json_encode($parsed['audio']));
               }
           }
        }

        
        if(isset($parsed['references']) ){
            $results[] = new ResultMessage(P_ERROR, 'references is only allowed at the top level', '"references" : ' . json_encode($parsed['references']));
        } 
        if(isset($parsed['@context']) ){
            $results[] = new ResultMessage(P_ERROR, '@context is only allowed at the top level', '"@context" : ' . json_encode($parsed['@context']));
        }

        if(isset($parsed['type']) && (is_array($parsed['type']) || !is_string($parsed['type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'type field must be a single string', '"type" : ' . json_encode($parsed['type']));
        }

        if(isset($parsed['children']) && (!is_array($parsed['children']) || $this->is_hash($parsed['children']))){
            $results[] = new ResultMessage(P_ERROR, 'children must be serialized as an array []', '"children" : ' . json_encode($parsed['children']));
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
                $results[] = new ResultMessage(P_ERROR, 'children array must contain only objects', '"children" : ' . json_encode($error_lines));
            }

        }

        //TODO check possible values
        if(isset($parsed['lang']) && (is_array($parsed['lang']) || !is_string($parsed['lang'] ))){
            $results[] = new ResultMessage(P_ERROR, 'lang field must be a single string', '"lang" : ' . json_encode($parsed['lang']));
        }

        if(isset($parsed['value']) && (is_array($parsed['value']) || !is_string($parsed['value'] ))){
            $results[] = new ResultMessage(P_ERROR, 'value field must be a single string', '"value" : ' . json_encode($parsed['value']));
        }
        if(isset($parsed['content-type']) && (is_array($parsed['content-type']) || !is_string($parsed['content-type'] ))){
            $results[] = new ResultMessage(P_ERROR, 'content-type field must be a single string', '"content-type" : ' . json_encode($parsed['content-type']));
        }
        if(isset($parsed['text']) && (is_array($parsed['text']) || !is_string($parsed['text'] ))){
            $results[] = new ResultMessage(P_ERROR, 'text field must be a single string', '"text" : ' . json_encode($parsed['text']));
        }
        if(isset($parsed['html']) && (is_array($parsed['html']) || !is_string($parsed['html'] ))){
            $results[] = new ResultMessage(P_ERROR, 'html field must be a single string', '"html" : ' . json_encode($parsed['html']));
        }

        $results = array_merge($results, $this->descend_and_recurse($parsed));
        
        return $results;
    }

}
