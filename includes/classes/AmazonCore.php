<?php
/*
 * Plan:
 * Database doubles as look-up table and record cache
 * -unique ID
 * -AmazonOrderID
 * -request type (either ListOrders/token or GetOrder)
 * -XML response, broken down into individual orders
 * -timestamp of request, used for throttling calculations
 * -status of order, used to determine which orders should be updated (eg Shipped is done with)
 * -flag for items for this order were ever retrieved
 * 
 * item table is similar
 * -unique ID
 * -id  corresponding to other table id
 * -timestamp
 * -even though it's dumb, store whether or not token was used via order status
 * -XML response broken into individual items
 * 
 * Need to find a way to connect to the database, check last timestamp of desired request type
 * for retrieving specific order information, check cache first to see if it was already received
 * functionality for updating orders
 * 
 * Get = fetch from cache or ?????
 * I'll probably have to make a new function for this, with a different name
 * 
 * caching is a great idea because it means information retrieval even if Amazon is down
 * 
 * need a function for Updating non-completed orders
 * 
 * oh and I still need Mock powers
 */

abstract class AmazonCore{
    //this is the abstract master class thing
    //track and do throttling
    //handle API and credentials
    protected $urlbase;
    protected $urlbranch;
    protected $throttleLimit;
    protected $throttleTime;
    protected $throttleGroup;
    protected $storeName;
    protected $secretKey;
    protected $options;
    protected $config;
    protected $mockMode = false;
    protected $mockFiles;
    protected $mockIndex = 0;
    protected $logpath;
    
    /**
     * AmazonCore constructor sets up key information used in all Amazon requests
     * @param string $s Name for store as seen in config file
     * @param boolean $mock flag for enabling Mock Mode
     * @param array|string $m list of mock file URLs to provide to the Mock Server
     */
    protected function __construct($s, $mock=false, $m = null){
        $this->config = '/var/www/athena/plugins/newAmazon/amazon-config.php';
        
        include($this->config);
        $this->logpath = $logpath;
        
        if(array_key_exists($s, $store)){
            $this->storeName = $s;
            if(array_key_exists('merchantId', $store[$s])){
                $this->options['SellerId'] = $store[$s]['merchantId'];
            } else {
                $this->log("Merchant ID is missing!",'Warning');
            }
            if(array_key_exists('keyId', $store[$s])){
                $this->options['AWSAccessKeyId'] = $store[$s]['keyId'];
            } else {
                $this->log("Access Key ID is missing!",'Warning');
            }
            if(array_key_exists('secretKey', $store[$s])){
                $this->secretKey = $store[$s]['secretKey'];
            } else {
                $this->log("Secret Key is missing!",'Warning');
            }
            
        } else {
            $this->log("Store $s does not exist!",'Warning');
        }
        
        if (is_bool($mock)){
            $this->mockMode = $mock;
            if ($mock){
                $this->log("Mock Mode set to true");
            }
            if (is_string($m)){
                $this->mockFiles = array();
                $this->mockFiles[0] = $m;
                $this->log("Single Mock File set: $m");
            } else if (is_array($m)){
                $this->mockFiles = $m;
                $this->log("Mock files array set.");
            }
        }
        
        
        $this->urlbase = $serviceURL;
        
        $this->options['SignatureVersion'] = 2;
        $this->options['SignatureMethod'] = 'HmacSHA256';
    }
    
    /**
     * Enables (or disables Mock Mode) for the object
     * @param boolean $b true = on, false = off
     * @param array|string $files filename(s) of the mock files to use
     */
    public function setMock($b = true,$files = null){
        if (is_bool($b)){
            $this->mockMode = $b;
            $this->log("Mock Mode set to $b");
        }
        if (is_string($files)){
            $this->mockFiles = array();
            $this->mockFiles[0] = $files;
            $this->log("Single Mock File set: $files");
        } else if (is_array($files)){
            $this->mockFiles = $files;
            $this->log("Mock files array set.");
        }
    }
    
    /**
     * Fetches the given mock file, or attempts to
     * @return SimpleXMLObject|boolean file, or false on failure
     */
    public function fetchMockFile(){
        if(!is_array($this->mockFiles) || !array_key_exists(0, $this->mockFiles)){
            $this->log("Attempted to retrieve mock files, but no mock files present",'Warning');
            return false;
        }
        if(!array_key_exists($this->mockIndex, $this->mockFiles)){
            $this->resetMock();
            $this->log("End of Mock List, resetting to 0");
        }
        $url = 'mock/'.$this->mockFiles[$this->mockIndex++];
        
        
        if(file_exists($url)){
            
            try{
                $this->log("Fetched Mock File: $url");
                return simplexml_load_file($url);
            } catch (Exception $e){
                $this->log("Error when opening Mock File: $url",'Warning');
            }
            
        } else {
            $this->log("Mock File not found: $url",'Warning');
        }
        
    }
    
    /**
     * Sets mock index back to 0
     */
    public function resetMock(){
        $this->mockIndex = 0;
        $this->log("Mock List index reset to 0");
    }
    
    /**
     * with MockFiles full of strings/numbers, this will generate a matching fake response
     * @return boolean|array response array, or false on failure
     */
    public function fetchMockResponse(){
        if(!is_array($this->mockFiles) || !array_key_exists(0, $this->mockFiles)){
            $this->log("Attempted to retrieve mock responses, but no mock responses present",'Warning');
            return false;
        }
        if(!array_key_exists($this->mockIndex, $this->mockFiles)){
            $this->resetMock();
            $this->log("End of Mock List, resetting to 0");
        }
        if (!is_numeric($this->mockFiles[$this->mockIndex])){
            $this->log("fetchMockResponse only works with error code numbers",'Warning');
            return false;
        }
        
        $r = array();
        $r['head'] = 'HTTP/1.1 200 OK';
        $r['body'] = '<?xml version="1.0"?><root></root>';
        $r['code'] = $this->mockFiles[$this->mockIndex];
        if ($this->mockFiles[$this->mockIndex] == 200){
            $r['answer'] = 'OK';
            $r['ok'] = 1;
        } else if ($this->mockFiles[$this->mockIndex] == 404){
            $r['answer'] = 'Not Found';
            $r['error'] = 'Not Found';
            $r['ok'] = 0;
        } else if ($this->mockFiles[$this->mockIndex] == 503){
            $r['answer'] = 'Service Unavailable';
            $r['error'] = 'Service Unavailable';
            $r['ok'] = 0;
        } else if ($this->mockFiles[$this->mockIndex] == 400){
            $r['answer'] = 'Bad Request';
            $r['error'] = 'Bad Request';
            $r['ok'] = 0;
        }
        
        $r['headarray'] = array();
        $this->log("Returning Mock Response: ".$r['code']);
        return $r;
    }
    
    /**
     * checks whether or not the response is OK
     * @param array $r response array
     * @return boolean true if OK, false otherwise
     */
    protected function checkResponse($r){
        if (!is_array($r) || !array_key_exists('code', $r)){
            $this->log("No Response found",'Warning');
            return false;
        }
        if ($r['code'] == 200){
            return true;
        } else {
            $xml = simplexml_load_string($r['body'])->Error;
            $this->log("Bad Response! ".$r['code'].": ".$xml->Code." - ".$xml->Message,'Urgent');
            return false;
        }
    }
    
    /**
     * Change the config file, for testing purposes
     * @param string $path
     */
    public function changeConfig($path){
        $this->config = $path;
        $this->__construct($this->storeName, $this->mockMode, $this->mockFiles);
    }
    
    /**
     * Change the config file, for testing purposes
     * @param string $path
     */
    public function changeLogPath($path){
        $this->logpath = $path;
    }
    
    /**
     * Skeleton function
     */
    protected function parseXML(){
        
    }
    
    /**
     * Manages the object's throttling
     */
    protected function throttle(){
        include('/var/www/athena/includes/config.php');
        
        if (!isset($this->throttleGroup)){
            $this->throttleGroup = $this->options['Action'];
            $this->log("Unable to find Throttle Group, setting to ".$this->options['Action'],'Warning');
        }
        
        $sql = 'SELECT MAX(timestamp) as maxtime FROM `amazonRequestLog` WHERE `type` = ?';
        $value = array($this->throttleGroup);
        $result = db::executeQuery($sql, $value, DB_PLUGINS)->fetchAll();
        if(!$result){
            return;
        }
        
        $maxtime = $result[0]['maxtime'];
        flush();
        while(true){
            $mintime = time()-$this->throttleTime;
            $timediff = $maxtime-$mintime;
            if($maxtime <= $mintime){
                flush();
                return;
            }
            flush();
            $this->log("Last request of this type: ".date("Y/m/d h:i:s", $maxtime).", Sleeping for $timediff seconds",'Throttle');
            sleep($timediff);
            $result = db::executeQuery($sql, $value, DB_PLUGINS)->fetchAll();
            $maxtime = $result[0]['maxtime'];
        }
        
        
        
//        $previous = time();
//        $refresh = 2;
//        $now = time();
//        
//        if($now-$previous < $refresh){
//            sleep($refresh);
//        }
        
        
    }
    
    /**
     * Resets throttle count
     * 
     * DEPRECATED?
     */
    protected function throttleReset(){
        $this->throttleCount = $this->throttleLimit;
    }
    
    /**
     * Returns all information for sake of convenience
     * @return array All information in an associative array
     */
    public function getAllDetails(){
        return $this->data;
    }
    
    /**
     * Writes to the log a message
     * @param string $msg message to write to log
     * @param string $level "Info", "Warning", "Urgent", "Throttle"
     * @return boolean false on failure
     */
    protected function log($msg, $level = 'Info'){
        if ($msg) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            if($userName){ 
                    $name = $userName;
            }else{
                    $name = 'guest';
            }

            if(isset($backtrace) && isset($backtrace[1]) && isset($backtrace[1]['file']) && isset($backtrace[1]['line']) && isset($backtrace[1]['function'])){
                    $fileName = basename($backtrace[1]['file']);
                    $file = $backtrace[1]['file'];
                    $line = $backtrace[1]['line'];
                    $function = $backtrace[1]['function'];
            }else{
                    $fileName = basename($backtrace[0]['file']);
                    $file = $backtrace[0]['file'];
                    $line = $backtrace[0]['line'];
                    $function = $backtrace[0]['function'];
            }
            if(isset($_SERVER['REMOTE_ADDR'])){
                    $ip = $_SERVER['REMOTE_ADDR'];
                    if($ip == '127.0.0.1')$ip = 'local';//save some char
            }else{
                    $ip = 'cli';
            }	
            if (file_exists($this->logpath) && is_writable($this->logpath)){
                $str = "[$level][" . date("Y/m/d h:i:s", mktime()) . " $name@$ip $fileName:$line $function] " . $msg;
                $fd = fopen($this->logpath, "a+");
                fwrite($fd,$str . "\r\n");
                fclose($fd);
            }
        } else {
            return false;
        }
    }
    
    /**
     * trying to generate a proper URL
     * 
     * DEPRECATED?
     * @return string
     */
    public function genRequest(){
        $query = '';
        uksort($this->options,'strcmp');
        foreach ($this->options as $i => $x){
                if (!$firstdone){
                    //$query .= '?';
                    $firstdone = true;
                } else {
                    $query .= '&';
                }
                
                $query .= $i.'='.$x;
            }
        
//            $queryParameters = array();
//        foreach ($parameters as $key => $value) {
//            $queryParameters[] = $key . '=' . $this->_urlencode($value);
//        }
//        return implode('&', $queryParameters);
            
        $sig = $this->genSig();
        
        var_dump($sig);
        
        $query .= '&Signature='.$sig;
        
        //$this->options['Signature'] = $sig;
        return $query;
        //return $sig;
    }
    
    /**
     * Generates the signature hash for signing the request
     * 
     * DEPRECATED?
     * @return string has string
     * @throws InvalidArgumentException if no options are detected
     */
    protected function genSig(){
        include($this->config);
        $query = 'POST';
        $query .= "\n";
        $endpoint = parse_url ($serviceURL);
        $query .= $endpoint['host'];
        $query .= "\n";
//        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;
//        if (!isset ($uri)) {
//        	$uri = "/";
//        }
//		$uriencoded = implode("/", explode("/", $uri));
//        $query .= $uriencoded;
        $query .= '/'.$this->urlbranch;
        $query .= "\n";
        
        
        
        if (is_array($this->options)){
            //combine query bits
            $queryParameters = array();
            foreach ($this->options as $key => $value) {
                $queryParameters[] = $key . '=' . $this->_urlencode($value);
            }
            $query = implode('&', $queryParameters);
//            //add query bits
//            foreach ($this->options as $i => $x){
//                if (!$firstdone){
//                    //$query .= '?';
//                    $firstdone = true;
//                } else {
//                    $query .= '&';
//                }
//                
//                $query .= $i.'='.$x;
//            }
        } else {
            throw new Exception('No query options set!');
        }
        
        
        return rawurlencode(base64_encode(hash_hmac('sha1', $query, $this->secretKey,true)));
    }
    
    /**
     * Generates timestamp in ISO8601 format, two minutes earlier than provided date
     * @param string $time time string that is fed through strtotime before being used
     * @return string time
     */
    protected function genTime($time=false){
        if (!$time){
            $time = time();
        } else {
            $time = strtotime($time);
            
        }
        return date('Y-m-d\TH:i:sO',$time-2*60);
            
    }
    
    /**
     * Writes to the database the request made and the timestamp
     */
    protected function logRequest(){
        include('/var/www/athena/includes/config.php');
        DB_PLUGINS;
        
        $sql = "INSERT INTO  `amazonRequestLog` (`id` ,`type` ,`timestamp`)VALUES (NULL ,  ?,  ?)";
        $value = array($this->options['Action'],time());
        $this->log("Logging action to database: ".$this->options['Action']);
        
        $result = db::executeQuery($sql, $value, DB_PLUGINS);
        if (!$result){
            $this->log("Could not write to database!",'Urgent');
        }
    }
    
    // -- test --
    /**
     * Reformats the provided string using rawurlencode while also replacing ~, copied from Amazon
     * 
     * Almost the same as using rawurlencode
     * @param string $value
     * @return string
     */
    protected function _urlencode($value) {
        return rawurlencode($value);
		return str_replace('%7E', '~', rawurlencode($value));
    }
    
    /**
     * Fuses all of the parameters together into a string, copied from Amazon
     * @param array $parameters
     * @return string
     */
    protected function _getParametersAsString(array $parameters) {
        $queryParameters = array();
        foreach ($parameters as $key => $value) {
            $queryParameters[] = $key . '=' . $this->_urlencode($value);
        }
        return implode('&', $queryParameters);
    }
    
    /**
     * validates signature and sets up signing of them, copied from Amazon
     * @param array $parameters
     * @param string $key
     * @return string signed string
     * @throws Exception
     */
    protected function _signParameters(array $parameters, $key) {
        $algorithm = $this->options['SignatureMethod'];
        $stringToSign = null;
        if (2 === $this->options['SignatureVersion']) {
            $stringToSign = $this->_calculateStringToSignV2($parameters);
//            var_dump($stringToSign);
        } else {
            throw new Exception("Invalid Signature Version specified");
        }
        return $this->_sign($stringToSign, $key, $algorithm);
    }
    
    /**
     * generates the string to sign, copied from Amazon
     * @param array $parameters
     * @return type
     */
    protected function _calculateStringToSignV2(array $parameters) {
        $data = 'POST';
        $data .= "\n";
        $endpoint = parse_url ($this->urlbase.$this->urlbranch);
        $data .= $endpoint['host'];
        $data .= "\n";
        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;
        if (!isset ($uri)) {
        	$uri = "/";
        }
		$uriencoded = implode("/", array_map(array($this, "_urlencode"), explode("/", $uri)));
        $data .= $uriencoded;
        $data .= "\n";
        uksort($parameters, 'strcmp');
        $data .= $this->_getParametersAsString($parameters);
        return $data;
    }
    /**
     * Runs the hash, copied from Amazon
     * @param string $data
     * @param string $key
     * @param string $algorithm 'HmacSHA1' or 'HmacSHA256'
     * @return string
     * @throws Exception
     */
     protected function _sign($data, $key, $algorithm)
    {
        if ($algorithm === 'HmacSHA1') {
            $hash = 'sha1';
        } else if ($algorithm === 'HmacSHA256') {
            $hash = 'sha256';
        } else {
            throw new Exception ("Non-supported signing method specified");
        }
        
        return base64_encode(
            hash_hmac($hash, $data, $key, true)
        );
    }
    
    // -- end test --
    
}

?>
