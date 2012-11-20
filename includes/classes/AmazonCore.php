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
    protected $throttleCount;
    protected $storeName;
    protected $secretKey;
    protected $options;
    protected $config;
    protected $mockMode = false;
    protected $mockFiles;
    protected $mockIndex = 0;
    
    /**
     * AmazonCore constructor sets up key information used in all Amazon requests
     * @param string $s Name for store as seen in config file
     * @param boolean $mock flag for enabling Mock Mode
     * @param array $m list of mock file URLs to provide to the Mock Server
     * @throws Exception if key config data is missing
     */
    protected function __construct($s, $mock=false, $m = null){
        $this->config = '/var/www/athena/plugins/newAmazon/amazon-config.php';
        
        include($this->config);
        
        if(array_key_exists($s, $store)){
            $this->storeName = $s;
            if(array_key_exists('merchantId', $store[$s])){
                $this->options['SellerId'] = $store[$s]['merchantId'];
            } else {
                throw new Exception('Merchant ID missing.');
            }
            if(array_key_exists('keyId', $store[$s])){
                $this->options['AWSAccessKeyId'] = $store[$s]['keyId'];
            } else {
                throw new Exception('Access Key ID missing.');
            }
            if(array_key_exists('secretKey', $store[$s])){
                $this->secretKey = $store[$s]['secretKey'];
            } else {
                throw new Exception('Access Key missing.');
            }
            
        } else {
            throw new Exception('Store does not exist.');
        }
        
        if (is_bool($mock)){
            $this->mockMode = $mock;
            if (is_string($m)){
                $this->mockFiles = array();
                $this->mockFiles[0] = $m;
            } else if (is_array($m)){
                $this->mockFiles = $m;
            }
        }
        
        
        $this->urlbase = $serviceURL;
        
        $this->options['SignatureVersion'] = 2;
        $this->options['SignatureMethod'] = 'HmacSHA256';
    }
    
    /**
     * Enables (or disables Mock Mode) for the object
     * @param boolean $b true = on, false = off
     * @param type $files filename(s) of the mock files to use
     */
    public function setMock($b = true,$files = null){
        if (is_bool($b)){
            $this->mockMode = $b;
        }
        if (is_string($files)){
            $this->mockFiles = array();
            $this->mockFiles[0] = $files;
        } else if (is_array($files)){
            $this->mockFiles = $files;
        }
    }
    
    public function fetchMockFile(){
        if(!array_key_exists(0, $this->mockFiles)){
            return false;
        }
        if(!array_key_exists($this->mockIndex, $this->mockFiles)){
            $this->mockIndex = 0;
        }
        $url = 'mock/'.$this->mockFiles[$this->mockIndex++];
        
        
        if(file_exists($url)){
            
            try{
                return simplexml_load_file($url);
            } catch (Exception $e){
                echo 'uh oh';
            }
            
        } else {
            throw new Exception('File '.$url.'not found!');
        }
        
    }
    
    /**
     * Sets mock index back to 0
     */
    public function resetMock(){
        $this->mockIndex = 0;
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
//        echo $this->throttleCount.'-->';
//        $this->throttleCount--;
//        if ($this->throttleCount < 1){
//            sleep($this->throttleTime);
//            $this->throttleCount++;
//        }
//        echo $this->throttleCount.'<br>';
        //database stuff goes here
        include('/var/www/athena/includes/config.php');
        //DB_PLUGINS;
        
        $sql = 'SELECT MAX(timestamp) as maxtime FROM `amazonRequestLog` WHERE `type` = ?';
        $value = array($this->options['Action']); //tokens...
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
    
    protected function logRequest(){
        include('/var/www/athena/includes/config.php');
        DB_PLUGINS;
        
        $sql = "INSERT INTO  `amazonRequestLog` (`id` ,`type` ,`timestamp`)VALUES (NULL ,  ?,  ?)";
        $value = array($this->options['Action'],time());
        
        $result = db::executeQuery($sql, $value, DB_PLUGINS);
        if (!$result){
            throw new Exception('write failed');
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
