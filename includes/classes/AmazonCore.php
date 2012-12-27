<?php
/**
 * The main core of the Amazon class.
 * 
 * The Amazon classes are divided up into groups, with each group
 * having its own abstract core class. This core is the class that
 * each of the other cores extend from. It contains a number of
 * methods shared by all cores, such as logging, throttling, and
 * signature generation.
 */

abstract class AmazonCore{
    protected $urlbase;
    protected $urlbranch;
    protected $throttleLimit;
    protected $throttleTime;
    protected $throttleGroup;
    protected $storeName;
    protected $options;
    protected $config;
    protected $mockMode = false;
    protected $mockFiles;
    protected $mockIndex = 0;
    protected $logpath;
    
    /**
     * AmazonCore constructor sets up key information used in all Amazon requests.
     * 
     * This constructor is called when initializing all objects in this library.
     * The parameters are passed by the child objects' constructors.
     * @param string $s <p>Name for the store you want to use as seen in the config file.
     * If this is not set to a valid name, none of these objects will work.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * When this is set to <b>TRUE</b>, the object will fetch responses from
     * files you specify instead of sending the requests to Amazon.
     * The log will indicate whether mock mode is on or off each time
     * an object is initialized. This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.
     * When Mock Mode is enabled, the object will retrieve one of these files
     * from the list to use as a response. See <i>setMock</i> for more information.</p>
     */
    protected function __construct($s, $mock=false, $m = null){
        $this->setConfig('/var/www/athena/plugins/newAmazon/amazon-config.php');
        $this->setStore($s);
        $this->setMock($mock,$m);
        
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
     * enabled for the object.</p>
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
                $mode = 'ON';
            } else {
                $mode = 'OFF';
            }
            $this->log("Mock Mode set to $mode");
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
     * method from loading the file's contents into a SimpleXMLObject. This is
     * for when the contents of the file are not in XML format, or if you simply
     * want to retrieve the raw string of the file.</p>
     * @return SimpleXMLObject|string|boolean <p>A SimpleXMLObject holding the
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
        //Todo: prepare this for librarification
        $url = 'mock/'.$this->mockFiles[$this->mockIndex];
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
     * @return boolean|array <p>An array containing the HTTP response, or simply
     * the value <b>FALSE</b> if the response could not be found or does not
     * match the list of valid responses.</p>
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
     * @return boolean <p><b>TRUE</b> if the status is 200 OK, <b>FALSE</b> otherwise.</p>
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
     * @throws Exception <p>If the file cannot be found or read.</p>
     */
    public function setConfig($path){
        if (file_exists($path) && is_readable($path)){
            include($path);
            $this->config = $path;
            $this->setLogPath(AMAZON_LOG);
            $this->urlbase = AMAZON_SERVICE_URL;
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
     * @throws Exception <p>If the file cannot be found or read.</p>
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
     * @param string $s <p>The store name to look for.</p>
     * @throws Exception <p>If the file can't be found.</p>
     */
    public function setStore($s){
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception("Config file does not exist!");
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
            
        } else {
            $this->log("Store $s does not exist!",'Warning');
        }
    }
    
    /**
     * Manages the object's throttling.
     * 
     * This method reads from a database table to coordinate all requests sent
     * to Amazon to prevent the request from being rejected due to throttling.
     * @todo has Athena config... oh and uses Athena's  DB class
     * @todo also probably change to take into account the count
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
     * @return boolean <p><b>FALSE</b> if the message is empty</p>
     * @throws Exception <p>If the file can't be written to.</p>
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
     * @return array <p>All of the options for the object.</p>
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
     * @param string $time [optional] <p>The time to use. Since this value is
     * passed through <i>strtotime</i> first, values such as "-1 hour" are fine.
     * Defaults to the current time.</p>
     * @return string <p>Unix timestamp of the time, minus 2 minutes.</p>
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
     * Records the request made and the timestamp.
     * 
     * This method writes to the database table the action performed and the time
     * it was performed. This is used for throttling.
     * @todo ATHENA...
     */
    protected function logRequest(){
        include('/var/www/athena/includes/config.php');
        
        $sql = "INSERT INTO  `amazonRequestLog` (`id` ,`type` ,`timestamp`)VALUES (NULL ,  ?,  ?)";
        $value = array($this->options['Action'],time());
        $this->log("Logging action to database: ".$this->options['Action']);
        
        $result = db::executeQuery($sql, $value, DB_PLUGINS);
        if (!$result){
            $this->log("Could not write to database!",'Urgent');
        }
    }
    
    /**
     * Handles generation of the signed query string.
     * 
     * This method uses the secret key from the config file to generate the
     * signed query string.
     * It also handles the creation of the timestamp option prior.
     * @return string <p>query string to send to cURL</p>
     * @throws Exception <p>when config file or secret key is missing</p>
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
        
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Signature'] = $this->_signParameters($this->options, $secretKey);
        return $this->_getParametersAsString($this->options);
    }
    
    //Functions from Athena:
    /**
	 * Connect to database using PDO
	 * @global boold $mysql_ATTR_PERSISTENT_FALSE - need to set to 1 if fork() using
	 * @param string $dbName - datable
	 * @param string $username - username
	 * @param string $password - password
	 * @param string $hostname - hostname
	 * @return PDO 
	 */
	private static function dbConnect($dbName,$username=null,$password=null,$hostname=null){

		// globals vars
		global $mysql_ATTR_PERSISTENT_FALSE;
		if (defined("DB_PHPUNIT")) {			
			// rewrite it use config for test server
			$username = DB_TEST_USERNAME;
			$password = DB_TEST_PASSWORD;
			$hostname = DB_TEST_HOSTNAME;

			// @todo make it nice :)
			if ($dbName == DB_ATHENA) {
				$dbName = DB_TEST_ATHENA;
			} elseif ($dbName == DB_SERVER) {
				$dbName = DB_TEST_SERVER;
			} elseif ($dbName == DB_PLUGINS) {
				$dbName = DB_TEST_PLUGINS;
			}
		} else {
			// set variables by default if did't not set
			if (is_null($username))
				$username = DB_USERNAME;
			if (is_null($password))
				$password = DB_PASSWORD;
			if (is_null($hostname))
				$hostname = DB_HOSTNAME;
		}
		
		// error if not test database set and phpunit runnig 
		if (in_array($dbName, Array(DB_ATHENA, DB_SERVER, DB_PLUGINS)) and preg_match('/phpunit/i', $_SERVER['SCRIPT_FILENAME'])) {
			trigger_error('Access denied to connect to production server if phpunit running for security reason', E_USER_ERROR);
		}
		
		// config
		$config = Array(PDO::ATTR_PERSISTENT => true);
		if (isset($mysql_ATTR_PERSISTENT_FALSE) and $mysql_ATTR_PERSISTENT_FALSE == 1)
			$config = Array(PDO::ATTR_PERSISTENT => false);
		
		$config[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'UTF8'";
		
		// config line; may be in the future we would like use different driver?
		$connectline = "mysql:host=" . $hostname . ";dbname=" . $dbName;
		
		try {
			$PDO = new PDO($connectline, $username, $password, $config);
			$PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die ($e->getMessage());
		}
		
		// if phpunit test, change database!
		if (preg_match('/phpunit/i', $_SERVER['SCRIPT_FILENAME'])) {
			$q = $PDO->exec('USE `'.$dbName.'`;');
		}

		return $PDO;
	}
        
	/**
	 * Perform a PDO mysql query
	 * @global type $mysql_database
	 * @param string $sql SQL Statement containing ? ---> OR <--- :named values
	 * @param array $values Array of values in order of ?.  |OR|  If key 'bindValue' exsits do ['bindValue'] + ['parameter'], ['value'] , OPTIONAL: ['data_type']
	 * @param string $dbName - Optional: mysql database override
	 * @param bool $returnID - Should the function return the last inserted ID?
	 * @return mixed - PDO object by default, ID if $returnID set to TRUE;
	 */
	private static function executeQuery($sql, $values, $dbName=NULL, $returnID=null) {
		if (config::check('Maintenance mode'))
			die(''._('Server is in Maintenance Mode').'');
		
		if ($dbName == NULL)
			$dbName = DB_ATHENA;
		
		$conn = self::dbConnect($dbName);
		
		myLog("PDO: Sql:" . $sql . " - Values:" . print_r($values, TRUE), LOG_DEBUG);

		$q = $conn->prepare($sql);

		//BindValue Support
		if ( is_array($values) and isset($values['bindValue']) and is_array($values['bindValue'])) { //bindValue can not be mixed with ?
			foreach ($values['bindValue'] as $value) {
				$q->bindValue($value['parameter'], $value['value'], (isset($value['data_type']) ? $value['data_type'] : PDO::PARAM_STR));
			}
			$q->execute();
		} else {
			$q->execute($values);
		}

		if (!$returnID) {
			return $q;
		} else {
			return $conn->lastInsertId();
		}
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
    
    // -- End Functions from Amazon --
    
}

?>
