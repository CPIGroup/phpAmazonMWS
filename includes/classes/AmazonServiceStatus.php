<?php
/**
 * A simple object that fetches the status of the Orders API from Amazon.
 * 
 * Please note that it has a 5 minute throttle time.
 */
class AmazonServiceStatus extends AmazonCore{
    private $lastTimestamp;
    private $status;
    
    /**
     * A simple object that fetches the status of the Orders API from Amazon
     * @param string $s store name, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     */
    public function __construct($s, $mock = false){
        parent::__construct($s, $mock);
        include($this->config);
        
        $this->urlbranch = 'Orders/2011-01-01';
        $this->options['Action'] = 'GetServiceStatus';
        
        $this->throttleLimit = $throttleLimitStatus;
        $this->throttleTime = $throttleTimeStatus;
    }
    
    /**
     * Fetches the status of the service from Amazon
     * @throws Exception on error, need to change this
     */
    public function fetchServiceStatus(){
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
            $response = $this->fetchMockFile();
        } else {
            $this->throttle();
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
        }
        
        if ($response['code'] != 200){
            throw new Exception('Still to do: handle this better...'.$response['code']);
        }
        
        $xml = simplexml_load_string($response['body']);
        
        $this->lastTimestamp = (string)$xml->GetServiceStatusResult->Timestamp;
        $this->status = (string)$xml->GetServiceStatusResult->Status;
    }
    
    /**
     * returns the fetched service status, and fetches it if it has not done so already
     * @return string
     */
    public function getServiceStatus(){
        if (!isset($this->status)){
            $this->fetchServiceStatus();
        }
        return $this->status;
    }
    
    /**
     * returns the timestamp of the last response, and fetches it if it has not done so already
     * @return string
     */
    public function getTimestamp(){
        if (!isset($this->status)){
            $this->fetchServiceStatus();
        }
        return $this->lastTimestamp;
    }
    
}

?>
