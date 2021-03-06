<?php
    /*
    * This file is part of the akana framework files.
    *
    * (c) Kubwacu Entreprise
    *
    * @author (kalculata) Huzaifa Nimushimirimana <nprincehuzaifa@gmail.com>
    *
    */
    namespace Akana;

    use Akana\Exceptions\JSONException;

    abstract class Utils{
        static public function stop_error_handler($errno, $errstr, $errfile, $errline){
            if (0 === error_reporting()) return false; 

            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        static private function json_valid($data=NULL): bool{
            if(!empty($data)) {
                json_decode($data);
                return (json_last_error() === JSON_ERROR_NONE);
            }
            return true;
        }

        static public function get_request_data(): array{
            $json_data = file_get_contents('php://input');

            if(self::json_valid($json_data) == false)
                throw new JsonException("your json request data contain errors");
        
            $request_data = json_decode($json_data, true);

            if(empty($request_data) && !empty($_POST))
                $request_data = $_POST;  

            return ($request_data != null)? $request_data : [];
        }

        static function remove_char(string $word, $index=0): string{
            $output = "";
            $word_length = strlen($word);
            $last_index = $word_length - 1;
            
            if(is_numeric($index)){
                if($index == -1) $index = $last_index;

                for($i=0; $i<$word_length; $i++)
                    if($i != $index) $output .= $word[$i];
                
            }

            else if(is_array($index)){
                if(in_array(-1, $index))
                    $index[array_search(-1, $index)] = $last_index;

                for($i=0; $i<$word_length; $i++){
                    if(!in_array($i, $index)) $output .= $word[$i];
                }
            }
 
            return $output;
        }

        static function get_keys($array){
            $keys = [];

            foreach($array as $k => $v)
                array_push($keys, $k);

            return $keys;
        }
        
        static function get_values($array){
            $values = [];

            foreach($array as $k => $v)
                array_push($values, $v);

            return $values;
        }

        static function generate_token($table): string{
            $token_table = $table."__token";
            $chars = ['0','1','2','3','4','5','6','7','8','9',
            'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
            'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
            
            do{
                $token = "";

                while(strlen($token) < 50){
                    $index = rand(0, count($chars) - 1);
                    $token .= $chars[$index];
                }

                $db_con = new Database();
                $db_con = $db_con->get_database_con();
                $q = $db_con->query("SELECT * FROM ".$token_table." WHERE token='".$token."'");
            } while($q->rowCount() > 0);

            return $token;
        }

        static function get_auth_user(){
            require_once API_ROOT.'/'.AUTHENTIFICATION['file'];

            $auth_class = AUTHENTIFICATION['model'];
            $auth_table = ModelUtils::get_table_name($auth_class);
            $auth_table_token = $auth_table.'__token';

            if(empty(AUTH_USER_TOKEN)) return null;
            
            $token = explode(" ", AUTH_USER_TOKEN)[1];

            return call_user_func_array(array($auth_class, 'exec_sql'), ["select * from ".$auth_table." where token in (select pk from ".$auth_table_token." where token='".$token."');"]);
        }
        static function PUT(string $name): string{
            $lines = file('php://input');
            $keyLinePrefix = 'Content-Disposition: form-data; name="';
    
            $PUT =[];
            $findLineNum = null;
    
            foreach($lines as $num => $line){
                if(strpos($line, $keyLinePrefix) !== false){
                    if($findLineNum){ break; }
                    if($name !== substr($line, 38, -3)){ continue; }
                    $findLineNum = $num;
                } else if($findLineNum){
                    $PUT[] = $line;
                }
            }
    
            array_shift($PUT);
            array_pop($PUT);
    
            return mb_substr(implode('', $PUT), 0, -2, 'UTF-8');
        }
    }

    abstract class URI{
        static function extract_resource(string $uri): string{
            return explode('/', $uri)[1];
        }

        static function extract_endpoint(string $resource, string $uri): string{
            $uri_in_part = explode('/', $uri);
            $endpoint = '';
            
            foreach($uri_in_part as $value){
                if($value != $resource AND !empty($value))
                    $endpoint .= '/' . $value;
                
            }

            return $endpoint . '/';
        }
    }

    abstract class Resource{
        static function is_exist(string $resource_name): bool{
            return in_array($resource_name, APP_RESOURCES);
        }
    }

    abstract class Endpoint{
        static function details(string $resource, string $endpoint): array{
            require API_ROOT.'/res/'. $resource . '/endpoints.php';
            
            $auth_state = AUTHENTIFICATION['state'];

            foreach(ENDPOINTS as $ep => $controller){
                if(isset($controller[1]) && is_bool($controller[1])){
                    $auth_state = $controller[1];
                }

                if(self::is_dynamic($ep) == true){
                    if(preg_match_all('#^'. self::to_regex($ep) .'$#', $endpoint, $data)){
                        return [
                            "controller" => $controller[0], 
                            "auth_state" => $auth_state,
                            "args" => self::get_args($ep, $data)
                        ]; 
                    }  
                }

                else{
                    if($ep == $endpoint){
                        return ["controller" => $controller[0], "auth_state" => $auth_state, "args" =>  []];
                    }
                }
            }

            return [];
        }
        static function is_dynamic(string $endpoint): bool{
            return preg_match('#\([a-zA-Z0-9_]+:int\)|\([a-zA-Z0-9_]+:str\)+#', $endpoint);
        }
        static function get_args($native_endpoint, $pattern_matches): array{
            $pattern = "#\([a-zA-Z0-9_]+:#";
            $data = [];
            $args = [];

            if(preg_match_all($pattern, $native_endpoint, $data)) 
                $data = $data[0];

            for($i = 0; $i<count($data); $i++) 
                $data[$i] = Utils::remove_char($data[$i],[0,-1]);

            for($i = 0; $i<count($data); $i++){
                $args[$i] = $pattern_matches[$data[$i]][0];
                $args[$i] = (is_numeric($args[$i]))? intval($args[$i]) : $args[$i];
            }

            return $args;
        }
        static function to_regex(string $dynamic_endpoint): string{
            $regex = $dynamic_endpoint;
            
            $regex = preg_replace('#\/#', '\/', $regex);
            $regex = preg_replace('#\(([a-zA-Z0-9_]+):int\)#', '(?<$1>[0-9]+)', $regex);
            $regex = preg_replace('#\(([a-zA-Z0-9_]+):str\)#', '(?<$1>[a-zA-Z0-9_-]+)', $regex);

            return $regex;
        }
    }

    abstract class Validator{
        static public function email($email): bool{
            return preg_match('#^[a-zA-Z0-9_\-]+@[a-zA-Z0-9_\-]+\.[a-zA-Z]{3}$#', $email);;
        }
    }
