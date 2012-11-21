<?php
/**
 * A simple object that fetches the status of the Inbound API from Amazon.
 * 
 * Please note that it has a 5 minute throttle time.
 */
class AmazonInboundServiceStatus extends AmazonInboundCore{
    private $lastTimestamp;
    private $status;
    
    /**
     * A simple object that fetches the status of the Inbound API from Amazon
     * @param string $s store name, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        include($this->config);
        
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
            $xml = $this->fetchMockFile();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            $xml = simplexml_load_string($response['body']);
        }
        
        if ($response['code'] != 200){
            $this->log("Status not OK: ".$response['code'],'Warning');
        }
        
        
        
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
