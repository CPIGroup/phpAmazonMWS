<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * The main core of the Amazon class.
 * 
 * The Amazon classes are divided up into groups, with each group
 * having its own abstract core class. This core is the class that
 * each of the other cores extend from. It contains a number of
 * methods shared by all cores, such as logging, throttling, and
 * signature generation.
 * 
 * The general flow for using a class in this library is as follows:
 * <ol>
 * <li>Create an object (or objects) of the desired type.</li>
 * <li>Set the request parameters using "set_____" functions.
 * Some classes allow you to set parameters when constructing the object.
 * Some classes don't need any parameters, and a few don't have any at all.</li>
 * <li>Send the request to Amazon using the class's unique function. (Usually "fetch___")
 * If not enough parameters have been set, the request will not go through.</li>
 * <li>Retrieve the data received with "get____" functions. Some classes can
 * be iterated through using foreach.</li>
 * <li>Repeat. Please note that performing Amazon actions sometimes alters or
 * removes parameters previously set, so it is recommended that you set all of the
 * desired parameters again before making a second action, or better yet, use a new object.
 * The exception to this is follow-up actions, which rely on the data previously
 * received from Amazon and do not require any parameters.</li>
 * </ol>
 * While there are a lot of functions, they all share one of the structures listed below.
 * Once you know how to use one class, you should be able to use the other classes.
 * <ul>
 * <li><b>Constructor</b> - Some classes let you pass an extra value when creating the class
 * in order to automatically set one of the parameters necessary for the class. Other
 * than that, all of the classes are created the same way and have the same options
 * for setting mock mode and other testing features.</li>
 * <li><b>Set an Option Flag</b> - These are functions for toggling a setting that only has an
 * On or Off setting. The single value they take is usually a boolean (or sometimes
 * a string with the words "true" or "false") and the value is often optional. If
 * no value is passed, the setting will be enabled. Passing a value of false is
 * the only way to deactivate the option afterwards.</li>
 * <li><b>Set Single Value</b> - These are functions meant for setting a parameter that
 * uses only a single value. For example, setting a shipment ID to
 * receive the items for. They typically require only a single parameter, usually a string.
 * Occasionally, the function will require a number, or a specific string value. In
 * these cases, the function will not set the parameter if the value is incorrect.</li>
 * <li><b>Set Multiple Values</b> - These are functions for setting options that can take
 * a list of values. These functions can take either an array of values, or a single
 * string. If this function is used a second time, the first list of values will be
 * completely removed and replaced with the new values.</li>
 * <li><b>Set Time Options</b> - A number of classes have functions for setting time limit
 * options. This is typically a pair of time points, but depending on the class, it
 * may only need one. All values passed to these functions are passed through <i>strtotime</i>
 * before being used, so a wide variety of values is accepted. For more information on
 * what is acceptible, see the documentation for <i>strtotime</i>.</li>
 * <li><b>Amazon Actions</b> - These are functions with names like "fetch____" or "cancel___",
 * and they are what send the request to Amazon. No parameter is ever needed, and the output
 * is always only to indicate if the action was successful.</li>
 * <li><b>Retrieve Value from a Single Object</b> - These functions are for retrieving
 * data sent by Amazon from a class that is not dedicated to a list of information.
 * No parameters are needed.</li>
 * <li><b>Retrieve Value from a List Object</b> - These functions are also for retrieving data,
 * but from classes that contain a list of different information sets. These functions can
 * take an integer for a list index, which then returns the value from the specified entry.
 * If no index is given, it defaults to returning the first entry in the list. In the case
 * of complex lists, sometimes a second index may be used.</li>
 * <li><b>Retrieve a List Entry</b> - These functions return either part of or all of
 * a class object's data list. An optional index can be passed to return a particular
 * data set. If no index is given, the entire list of data is returned. Keep in mind
 * that the arrays returned by these functions are usually pretty large.</li>
 * <li><b>Follow-Up Actions</b> - There are only a few of these functions, and are mostly
 * "fetchItems" functions for lists of orders or shipments. These functions send a request
 * to Amazon for every entry in the object's data list. Please note that these functions
 * will generally take a while to perform and will return a lot of data. These are the
 * only non-"get" functions that will return the information.</li>
 * </ul>
 */
abstract class AmazonCore{
    protected $urlbase;
    protected $urlbranch;
    protected $throttleLimit;
    protected $throttleTime;
    protected $throttleSafe;
    protected $throttleGroup;
    protected $throttleStop = false;
    protected $storeName;
    protected $options;
    protected $config;
    protected $mockMode = false;
    protected $mockFiles;
    protected $mockIndex = 0;
    protected $logpath;
    protected $env;
    protected $rawResponses = array();
    
    /**
     * AmazonCore constructor sets up key information used in all Amazon requests.
     * 
     * This constructor is called when initializing all objects in this library.
     * The parameters are passed by the child objects' constructors.
     * @param string $s [optional] <p>Name for the store you want to use as seen in the config file.
     * If there is only one store defined in the config file, this parameter is not necessary.
     * If there is more than one store and this is not set to a valid name, none of these objects will work.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * When this is set to <b>TRUE</b>, the object will fetch responses from
     * files you specify instead of sending the requests to Amazon.
     * The log will indicate whether mock mode is on or off each time
     * an object is initialized. This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.
     * When Mock Mode is enabled, the object will retrieve one of these files
     * from the list to use as a response. See <i>setMock</i> for more information.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    protected function __construct($s = null, $mock = false, $m = null, $config = null){
        if (is_null($config)){
            $config = __DIR__.'/../../amazon-config.php';
        }
        $this->setConfig($config);
        $this->setStore($s);
        $this->setMock($mock,$m);
        
        $this->env=__DIR__.'/../../environment.php';
        $this->options['SignatureVersion'] = 2;
        $this->options['SignatureMethod'] = 'HmacSHA256';
    }
    
    /**
     * Enables or disables Mock Mode for the object.
     * 
     * Use this method when you want to test your object without sending
     * actual requests to Amazon. When Mock Mode is enabled, responses are
     * pulled from files you specify instead of sending the request.
     * Be careful, as this means that the parameters you send will not
     * necessarily match the response you get back. The files are pulled in order
     * of the list, looping back to the first file after the last file is used.
     * The log records every time a file is set or used, or if the file is missing.
     * This method is also used to set response codes used by certain functions.
     * Mock Mode is particularly useful when you need
     * to test functions such as canceling orders or adding new products.
     * @param boolean $b [optional] <p>When set to <b>TRUE</b>, Mock Mode is
     * enabled for the object. Defaults to <b>TRUE</b>.</p>
     * @param array|string|integer $files [optional] <p>The list of files (or single file)
     * to be used with Mock Mode. If a single string is given, this method will
     * put it into an array. Integers can also be given, for use in <i>fetchMockResponse</i>.
     * These numbers should only be response codes, such as <b>200</b> or <b>404</b>.</p>
     */
    public function setMock($b = true,$files = null){
        if (is_bool($b)){
            $this->resetMock(true);
            $this->mockMode = $b;
            if ($b){
                $this->log("Mock Mode set to ON");
            }
            
            if (is_string($files)){
                $this->mockFiles = array();
                $this->mockFiles[0] = $files;
                $this->log("Single Mock File set: $files");
            } else if (is_array($files)){
                $this->mockFiles = $files;
                $this->log("Mock files array set.");
            } else if (is_numeric($files)){
                $this->mockFiles = array();
                $this->mockFiles[0] = $files;
                $this->log("Single Mock Response set: $files");
            }
        }
    }
    
    /**
     * Fetches the given mock file, or attempts to.
     * 
     * This method is only called when Mock Mode is enabled. This is where
     * files from the mock file list are retrieved and passed back to the caller.
     * The success or failure of the operation will be recorded in the log,
     * including the name and path of the file involved. For retrieving response
     * codes, see <i>fetchMockResponse</i>.
     * @param boolean $load [optional] <p>Set this to <b>FALSE</b> to prevent the
     * method from loading the file's contents into a SimpleXMLElement. This is
     * for when the contents of the file are not in XML format, or if you simply
     * want to retrieve the raw string of the file.</p>
     * @return SimpleXMLElement|string|boolean <p>A SimpleXMLElement holding the
     * contents of the file, or a string of said contents if <i>$load</i> is set to
     * <b>FALSE</b>. The return will be <b>FALSE</b> if the file cannot be
     * fetched for any reason.</p>
     */
    protected function fetchMockFile($load = true){
        if(!is_array($this->mockFiles) || !array_key_exists(0, $this->mockFiles)){
            $this->log("Attempted to retrieve mock files, but no mock files present",'Warning');
            return false;
        }
        if(!array_key_exists($this->mockIndex, $this->mockFiles)){
            $this->log("End of Mock List, resetting to 0");
            $this->resetMock();
        }
        //check for absolute/relative file paths
        if (strpos($this->mockFiles[$this->mockIndex], '/') === 0 || strpos($this->mockFiles[$this->mockIndex], '..') === 0){
            $url = $this->mockFiles[$this->mockIndex];
        } else {
            $url = 'mock/'.$this->mockFiles[$this->mockIndex];
        }
        $this->mockIndex++;
        
        
        if(file_exists($url)){
            
            try{
                $this->log("Fetched Mock File: $url");
                if ($load){
                    $return = simplexml_load_file($url);
                } else {
                    $return = file_get_contents($url);
                }
                return $return;
            } catch (Exception $e){
                $this->log("Error when opening Mock File: $url - ".$e->getMessage(),'Warning');
                return false;
            }
            
        } else {
            $this->log("Mock File not found: $url",'Warning');
            return false;
        }
        
    }
    
    /**
     * Sets mock index back to 0.
     * 
     * This method is used for returning to the beginning of the mock file list.
     * @param boolean $mute [optional]<p>Set to <b>TRUE</b> to prevent logging.</p>
     */
    protected function resetMock($mute = false){
        $this->mockIndex = 0;
        if (!$mute){
            $this->log("Mock List index reset to 0");
        }
    }
    
    /**
     * Generates a fake HTTP response using the mock file list.
     * 
     * This method uses the response codes in the mock file list to generate an
     * HTTP response. The success or failure of this operation will be recorded
     * in the log, including the response code returned. This is only used by
     * a few operations. The response array will contain the following fields:
     * <ul>
     * <li><b>head</b> - ignored, but set for the sake of completion</li>
     * <li><b>body</b> - empty XML, also ignored</li>
     * <li><b>code</b> - the response code fetched from the list</li>
     * <li><b>answer</b> - answer message</li>
     * <li><b>error</b> - error message, same value as answer, not set if status is 200</li>
     * <li><b>ok</b> - 1 or 0, depending on if the status is 200</li>
     * </ul>
     * @return boolean|array An array containing the HTTP response, or simply
     * the value <b>FALSE</b> if the response could not be found or does not
     * match the list of valid responses.
     */
    protected function fetchMockResponse(){
        if(!is_array($this->mockFiles) || !array_key_exists(0, $this->mockFiles)){
            $this->log("Attempted to retrieve mock responses, but no mock responses present",'Warning');
            return false;
        }
        if(!array_key_exists($this->mockIndex, $this->mockFiles)){
            $this->log("End of Mock List, resetting to 0");
            $this->resetMock();
        }
        if (!is_numeric($this->mockFiles[$this->mockIndex])){
            $this->log("fetchMockResponse only works with response code numbers",'Warning');
            return false;
        }
        
        $r = array();
        $r['head'] = 'HTTP/1.1 200 OK';
        $r['body'] = '<?xml version="1.0"?><root></root>';
        $r['code'] = $this->mockFiles[$this->mockIndex];
        $this->mockIndex++;
        if ($r['code'] == 200){
            $r['answer'] = 'OK';
            $r['ok'] = 1;
        } else if ($r['code'] == 404){
            $r['answer'] = 'Not Found';
            $r['error'] = 'Not Found';
            $r['ok'] = 0;
        } else if ($r['code'] == 503){
            $r['answer'] = 'Service Unavailable';
            $r['error'] = 'Service Unavailable';
            $r['ok'] = 0;
        } else if ($r['code'] == 400){
            $r['answer'] = 'Bad Request';
            $r['error'] = 'Bad Request';
            $r['ok'] = 0;
        }
        
        if ($r['code'] != 200){
            $r['body'] = '<?xml version="1.0"?>
<ErrorResponse xmlns="http://mws.amazonaws.com/doc/2009-01-01/">
  <Error>
    <Type>Sender</Type>
    <Code>'.$r['error'].'</Code>
    <Message>'.$r['answer'].'</Message>
  </Error>
  <RequestID>123</RequestID>
</ErrorResponse>';
        }
        
        
        $r['headarray'] = array();
        $this->log("Returning Mock Response: ".$r['code']);
        return $r;
    }
    
    /**
     * Checks whether or not the response is OK.
     * 
     * Verifies whether or not the HTTP response has the 200 OK code. If the code
     * is not 200, the incident and error message returned are logged.
     * @param array $r <p>The HTTP response array. Expects the array to have
     * the fields <i>code</i>, <i>body</i>, and <i>error</i>.</p>
     * @return boolean <b>TRUE</b> if the status is 200 OK, <b>FALSE</b> otherwise.
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
            $this->log("Bad Response! ".$r['code']." ".$r['error'].": ".$xml->Code." - ".$xml->Message,'Urgent');
            return false;
        }
    }
    
    /**
     * Set the config file.
     * 
     * This method can be used to change the config file after the object has
     * been initiated. The file will not be set if it cannot be found or read.
     * This is useful for testing, in cases where you want to use a different file.
     * @param string $path <p>The path to the config file.</p>
     * @throws Exception If the file cannot be found or read.
     */
    public function setConfig($path){
        if (file_exists($path) && is_readable($path)){
            include($path);
            $this->config = $path;
            $this->setLogPath($logpath);
            if (isset($AMAZON_SERVICE_URL)) {
                $this->urlbase = rtrim($AMAZON_SERVICE_URL, '/') . '/';
            }
        } else {
            throw new Exception("Config file does not exist or cannot be read! ($path)");
        }
    }
    
    /**
     * Set the log file path.
     * 
     * Use this method to change the log file used. This method is called
     * each time the config file is changed.
     * @param string $path <p>The path to the log file.</p>
     * @throws Exception If the file cannot be found or read.
     */
    public function setLogPath($path){
        if (file_exists($path) && is_readable($path)){
            $this->logpath = $path;
        } else {
            throw new Exception("Log file does not exist or cannot be read! ($path)");
        }
        
    }
    
    /**
     * Sets the store values.
     * 
     * This method sets a number of key values from the config file. These values
     * include your Merchant ID, Access Key ID, and Secret Key, and are critical
     * for making requests with Amazon. If the store cannot be found in the
     * config file, or if any of the key values are missing,
     * the incident will be logged.
     * @param string $s [optional] <p>The store name to look for.
     * This parameter is not required if there is only one store defined in the config file.</p>
     * @throws Exception If the file can't be found.
     */
    public function setStore($s=null){
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception("Config file does not exist!");
        }
        
        if (empty($store) || !is_array($store)) {
            throw new Exception("No stores defined!");
        }
        
        if (!isset($s) && count($store)===1) {
            $s=key($store);
        }
        
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
            if(!array_key_exists('secretKey', $store[$s])){
                $this->log("Secret Key is missing!",'Warning');
            }
            if (!empty($store[$s]['serviceUrl'])) {
                $this->urlbase = $store[$s]['serviceUrl'];
            }
            if (!empty($store[$s]['MWSAuthToken'])) {
                $this->options['MWSAuthToken'] = $store[$s]['MWSAuthToken'];
            }
            
        } else {
            $this->log("Store $s does not exist!",'Warning');
        }
    }
    
    /**
     * Enables or disables the throttle stop.
     * 
     * When the throttle stop is enabled, throttled requests will not  be repeated.
     * This setting is off by default.
     * @param boolean $b <p>Defaults to <b>TRUE</b>.</p>
     */
    public function setThrottleStop($b=true) {
        $this->throttleStop=!empty($b);
    }
    
    /**
     * Writes a message to the log.
     * 
     * This method adds a message line to the log file defined by the config.
     * This includes the priority level, user IP, and a backtrace of the call.
     * @param string $msg <p>The message to write to the log.</p>
     * @param string $level [optional] <p>The priority level of the message.
     * This is merely for the benefit of the user and does not affect how
     * the code runs. The values used in this library are "Info", "Warning",
     * "Urgent", and "Throttle".</p>
     * @return boolean <b>FALSE</b> if the message is empty, NULL if logging is muted
     * @throws Exception If the file can't be written to.
     */
    protected function log($msg, $level = 'Info'){
        if ($msg != false) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            
            if (file_exists($this->config)){
                include($this->config);
            } else {
                throw new Exception("Config file does not exist!");
            }
            if (isset($logfunction) && $logfunction != '' && function_exists($logfunction)){
                switch ($level){
                   case('Info'): $loglevel = LOG_INFO; break; 
                   case('Throttle'): $loglevel = LOG_INFO; break; 
                   case('Warning'): $loglevel = LOG_NOTICE; break; 
                   case('Urgent'): $loglevel = LOG_ERR; break; 
                   default: $loglevel = LOG_INFO;
                }
                call_user_func($logfunction,$msg,$loglevel);
            }
            
            if (isset($muteLog) && $muteLog == true){
                return;
            }
            
            if(isset($userName) && $userName != ''){ 
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
            if (!file_exists($this->logpath)) {
                //attempt to create the file if it does not exist
                file_put_contents($this->logpath, "This is the Amazon log, for Amazon classes to use.\n");
            }
            if (file_exists($this->logpath) && is_writable($this->logpath)){
                $str = "[$level][" . date("Y/m/d H:i:s") . " $name@$ip $fileName:$line $function] " . $msg;
                $fd = fopen($this->logpath, "a+");
                fwrite($fd,$str . "\r\n");
                fclose($fd);
            } else {
                throw new Exception('Error! Cannot write to log! ('.$this->logpath.')');
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns options array.
     * 
     * Gets the options for the object, for debugging or recording purposes.
     * Note that this also includes key information such as your Amazon Access Key ID.
     * @return array All of the options for the object.
     */
    public function getOptions(){
        return $this->options;
    }
    
    /**
     * Generates timestamp in ISO8601 format.
     * 
     * This method creates a timestamp from the provided string in ISO8601 format.
     * The string given is passed through <i>strtotime</i> before being used. The
     * value returned is actually two minutes early, to prevent it from tripping up
     * Amazon. If no time is given, the current time is used.
     * @param string|int $time [optional] <p>The time to use. Since any string values are
     * passed through <i>strtotime</i> first, values such as "-1 hour" are fine.
     * Unix timestamps are also allowed. Purely numeric values are treated as unix timestamps.
     * Defaults to the current time.</p>
     * @return string Unix timestamp of the time, minus 2 minutes.
     * @throws InvalidArgumentException
     */
    protected function genTime($time=false){
        if (!$time){
            $time = time();
        } else if (is_numeric($time)) {
            $time = (int)$time;
        } else if (is_string($time)) {
            $time = strtotime($time);
        } else {
            throw new InvalidArgumentException('Invalid time input given');
        }
        return date('Y-m-d\TH:i:sO',$time-120);
            
    }
    
    /**
     * Handles generation of the signed query string.
     * 
     * This method uses the secret key from the config file to generate the
     * signed query string.
     * It also handles the creation of the timestamp option prior.
     * @return string query string to send to cURL
     * @throws Exception if config file or secret key is missing
     */
    protected function genQuery(){
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception("Config file does not exist!");
        }
        
        if (array_key_exists($this->storeName, $store) && array_key_exists('secretKey', $store[$this->storeName])){
            $secretKey = $store[$this->storeName]['secretKey'];
        } else {
            throw new Exception("Secret Key is missing!");
        }
        
        unset($this->options['Signature']);
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Signature'] = $this->_signParameters($this->options, $secretKey);
        return $this->_getParametersAsString($this->options);
    }
    
    /**
     * Sends a request to Amazon via cURL
     * 
     * This method will keep trying if the request was throttled.
     * @param string $url <p>URL to feed to cURL</p>
     * @param array $param <p>parameter array to feed to cURL</p>
     * @return array cURL response array
     */
    protected function sendRequest($url,$param){
        $this->log("Making request to Amazon: ".$this->options['Action']);
        $response = $this->fetchURL($url,$param);
        
        while ($response['code'] == '503' && $this->throttleStop==false){
            $this->sleep();
            $response = $this->fetchURL($url,$param);
        }
        
        $this->rawResponses[]=$response;
        return $response;
    }
    
    /**
     * Gives the latest response data received from Amazon.
     * Response arrays contain the following keys:
     * <ul>
     * <li><b>head</b> - The raw HTTP head, including the response code and content length</li>
     * <li><b>body</b> - The raw HTTP body, which will almost always be in XML format</li>
     * <li><b>code</b> - The HTTP response code extracted from the head for convenience</li>
     * <li><b>answer</b> - The HTTP response message extracted from the head for convenience</li>
     * <li><b>ok</b> - Contains a <b>1</b> if the response was normal, or <b>0</b> if there was a problem</li>
     * <li><b>headarray</b> - An associative array of the head data, for convenience</li>
     * </ul>
     * @param int $i [optional] <p>If set, retrieves the specific response instead of the last one.
     * If the index for the response is not used, <b>FALSE</b> will be returned.</p>
     * @return array associative array of HTTP response or <b>FALSE</b> if not set yet
     */
    public function getLastResponse($i=NULL) {
        if (!isset($i)) {
            $i=count($this->rawResponses)-1;
        }
        if ($i >= 0 && isset($this->rawResponses[$i])) {
            return $this->rawResponses[$i];
        } else {
            return false;
        }
    }
    
    /**
     * Gives all response code received from Amazon.
     * @return array list of associative arrays of HTTP response or <b>FALSE</b> if not set yet
     * @see getLastResponse
     */
    public function getRawResponses() {
        if (!empty($this->rawResponses)) {
            return $this->rawResponses;
        } else {
            return false;
        }
    }

    /**
     * Gives the response code from the last response.
     * This data can also be found in the array given by getLastResponse.
     * @return string|int standard REST response code (200, 404, etc.) or <b>NULL</b> if no response
     * @see getLastResponse
     */
    public function getLastResponseCode() {
        $last = $this->getLastResponse();
        if (!empty($last['code'])) {
            return $last['code'];
        }
    }

    /**
     * Gives the last response with an error code.
     * This may or may not be the same as the last response if multiple requests were made.
     * @return array associative array of HTTP response or <b>NULL</b> if no error response yet
     * @see getLastResponse
     */
    public function getLastErrorResponse() {
        if (!empty($this->rawResponses)) {
            foreach (array_reverse($this->rawResponses) as $x) {
                if (isset($x['error'])) {
                    return $x;
                }
            }
        }
    }

    /**
     * Gives the Amazon error code from the last error response.
     * The error code uses words rather than numbers. (Ex: "InvalidParameterValue")
     * This data can also be found in the XML body given by getLastErrorResponse.
     * @return string Amazon error code or <b>NULL</b> if not set yet or no error response yet
     * @see getLastErrorResponse
     */
    public function getLastErrorCode() {
        $last = $this->getLastErrorResponse();
        if (!empty($last['body'])) {
            $xml = simplexml_load_string($last['body']);
            if (isset($xml->Error->Code)) {
                return $xml->Error->Code;
            }
        }
    }

    /**
     * Gives the error message from the last error response.
     * Not all error responses will have error messages.
     * This data can also be found in the XML body given by getLastErrorResponse.
     * @return string Amazon error code or <b>NULL</b> if not set yet or no error response yet
     * @see getLastErrorResponse
     */
    public function getLastErrorMessage() {
        $last = $this->getLastErrorResponse();
        if (!empty($last['body'])) {
            $xml = simplexml_load_string($last['body']);
            if (isset($xml->Error->Message)) {
                return $xml->Error->Message;
            }
        }
    }
    
    /**
     * Sleeps for the throttle time and records to the log.
     */
    protected function sleep(){
        flush();
        $s = ($this->throttleTime == 1) ? '' : 's';
        $this->log("Request was throttled, Sleeping for ".$this->throttleTime." second$s",'Throttle');
        sleep($this->throttleTime);
    }
    
    /**
     * Checks for a token and changes the proper options
     * @param SimpleXMLElement $xml <p>response data</p>
     * @return boolean <b>FALSE</b> if no XML data
     */
    protected function checkToken($xml){
        if ($xml && $xml->NextToken && (string)$xml->HasNext != 'false' && (string)$xml->MoreResultsAvailable != 'false'){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
    }
    
    //Functions from Athena:
       /**
        * Get url or send POST data
        * @param string $url 
        * @param array  $param['Header']
        *               $param['Post']
        * @return array $return['ok'] 1  - success, (0,-1) - fail
        *               $return['body']  - response
        *               $return['error'] - error, if "ok" is not 1
        *               $return['head']  - http header
        */
       function fetchURL ($url, $param) {
        $return = array();
        
        $ch = curl_init();
        
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_TIMEOUT, 0);
        curl_setopt($ch,CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch,CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch,CURLOPT_HEADER, 1);
        curl_setopt($ch,CURLOPT_URL,$url);
        if (!empty($param)){
            if (!empty($param['Header'])){
                curl_setopt($ch,CURLOPT_HTTPHEADER, $param['Header']);
            }
            if (!empty($param['Post'])){
                curl_setopt($ch,CURLOPT_POSTFIELDS, $param['Post']);
            }
        }
        
        $data = curl_exec($ch);
        if ( curl_errno($ch) ) {
                $return['ok'] = -1;
                $return['error'] = curl_error($ch);
                return $return;
        }
        
        if (is_numeric(strpos($data, 'HTTP/1.1 100 Continue'))) {
            $data=str_replace('HTTP/1.1 100 Continue', '', $data);
        }
        $data = preg_split("/\r\n\r\n/",$data, 2, PREG_SPLIT_NO_EMPTY);
        if (!empty($data)) {
                $return['head'] = ( isset($data[0]) ? $data[0] : null );
                $return['body'] = ( isset($data[1]) ? $data[1] : null );
        } else {
                $return['head'] = null;
                $return['body'] = null;
        }
        
        $matches = array();
        $data = preg_match("/HTTP\/[0-9.]+ ([0-9]+) (.+)\r\n/",$return['head'], $matches);
        if (!empty($matches)) {
                $return['code'] = $matches[1];
                $return['answer'] = $matches[2];
        }
        
        $data = preg_match("/meta http-equiv=.refresh. +content=.[0-9]*;url=([^'\"]*)/i",$return['body'], $matches);
        if (!empty($matches)) {
                $return['location'] = $matches[1];
                $return['code'] = '301';
        }
        
        if ( $return['code'] == '200' || $return['code'] == '302' ) {
                $return['ok'] = 1;
        } else {
                $return['error'] = (($return['answer'] and $return['answer'] != 'OK') ? $return['answer'] : 'Something wrong!');
                $return['ok'] = 0;
        }
        
        foreach (preg_split('/\n/', $return['head'], -1, PREG_SPLIT_NO_EMPTY) as $value) {
                $data = preg_split('/:/', $value, 2, PREG_SPLIT_NO_EMPTY);
                if (is_array($data) and isset($data['1'])) {
                        $return['headarray'][$data['0']] = trim($data['1']);
                }
        }
        
        curl_close($ch);
        
        return $return;
       }
    // End Functions from Athena
     
    // Functions from Amazon:
    /**
     * Reformats the provided string using rawurlencode while also replacing ~, copied from Amazon
     * 
     * Almost the same as using rawurlencode
     * @param string $value
     * @return string
     */
    protected function _urlencode($value) {
        return rawurlencode($value);
        //Amazon suggests doing this, but it seems to break things rather than fix them:
        //return str_replace('%7E', '~', rawurlencode($value));
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
    
    // -- End Functions from Amazon --
    
}

?>
