<?php

class AmazonReportRequestCounter extends AmazonReportsCore{
    private $count;
    
    /**
     * Sends a report request to Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        $this->options['Action'] = 'GetReportRequestCount';
        
        $this->throttleLimit = $throttleLimitReportRequestList;
        $this->throttleTime = $throttleTimeReportRequestList;
        $this->throttleGroup = 'GetReportRequestCount';
    }
    
    /**
     * sets the request type(s) to be used in the next request
     * @param array|string $s array of Report Types or single type
     * @return boolean false if failure
     */
    public function setReportTypes($s){
        if (is_string($s)){
            $this->resetReportTypes();
            $this->options['ReportTypeList.Type.1'] = $s;
        } else if (is_array($s)){
            $this->resetReportTypes();
            $i = 1;
            foreach ($s as $x){
                $this->options['ReportTypeList.Type.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes type options
     */
    public function resetReportTypes(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportTypeList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sets the report status(es) to be used in the next request
     * @param array|string $s array of Report Types or single type
     * @return boolean false if failure
     */
    public function setReportStatuses($s){
        if (is_string($s)){
            $this->resetRequestIds();
            $this->options['ReportProcessingStatusList.Status.1'] = $s;
        } else if (is_array($s)){
            $this->resetRequestIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['ReportProcessingStatusList.Status.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes status options
     */
    public function resetReportStatuses(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportProcessingStatusList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the time frame for the count
     * @param string $s start time passed through strtotime, set to null to ignore
     * @param string $e end time passed through strtotime
     */
    public function setTimeLimits($s = null,$e = null){
        if ($s && is_string($s)){
            $times = $this->genTime($s);
            $this->options['RequestedFromDate'] = $times;
        }
        if ($e && is_string($e)){
            $timee = $this->genTime($e);
            $this->options['RequestedToDate'] = $timee;
        }
    }
    
    /**
     * removes time frame limits
     */
    public function resetTimeLimits(){
        unset($this->options['RequestedFromDate']);
        unset($this->options['RequestedToDate']);
    }
    
    /**
     * Fetches the count from Amazon
     */
    public function fetchCount(){
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = 'GetReportRequestCountResult';
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $this->parseXML($xml);
        
    }
    
    /**
     * loads XML response into variable
     * @param SimpleXMLObject $xml XML from response
     * @return boolean false on failure
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        
        $this->count = (string)$xml->Count;
    }
    
    /**
     * gets the count, if it exists
     * @return array|boolean Response array, or false on failure
     */
    public function getCount(){
        if (!isset($this->count)){
            return false;
        } else {
            return $this->count;
        }
    }
    
}
?>