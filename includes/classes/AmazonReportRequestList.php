<?php

class AmazonReportRequestList extends AmazonReportsCore implements Iterator{
    private $tokenFlag;
    private $tokenUseFlag;
    private $index = 0;
    private $reportList;
    
    /**
     * Sends a report request to Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        
        $this->throttleLimit = $throttleLimitReportRequestList;
        $this->throttleTime = $throttleTimeReportRequestList;
    }
    
    /**
     * Returns whether or not the Participation List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the Participation List should automatically use tokens if it receives one.
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * sets the request ID(s) to be used in the next request
     * @param array|string $s array of Report Request IDs or single ID
     * @return boolean false if failure
     */
    public function setRequestIds($s){
        if (is_string($s)){
            $this->resetRequestIds();
            $this->options['ReportRequestIdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetRequestIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['ReportRequestIdList.Id.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes ID options
     */
    public function resetRequestIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportRequestIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sets the request type(s) to be used in the next request
     * @param array|string $s array of Report Types or single type
     * @return boolean false if failure
     */
    public function setRequestTypes($s){
        if (is_string($s)){
            $this->resetRequestIds();
            $this->options['ReportTypeList.Type.1'] = $s;
        } else if (is_array($s)){
            $this->resetRequestIds();
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
    public function resetRequestTypes(){
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
     * Sets the maximum response count for the next request
     * @param string $s number from 1 to 100
     * @return boolean false if improper input
     */
    public function setMaxCount($s){
        if (is_numeric($s) && $s >= 1 && $s <= 100){
            $this->options['MaxCount'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the Start Time and End Time filters for the report list
     * @param string $s passed through strtotime, set to null to ignore
     * @param string $e passed through strtotime
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
     * Fetches the participation list from Amazon, using a token if available
     * @param boolean $refresh set false to preserve current list (for internal use)
     */
    public function fetchReportList(){
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareToken();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = $this->options['Action'].'Result';
        
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
        
        if ((string)$xml->NextToken == 'true'){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Reports");
            $this->fetchReportList();
        }
        
    }
    
    /**
     * Sets up token stuff
     */
    protected function prepareToken(){
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'GetReportRequestListByNextToken';
            $this->throttleGroup = 'GetReportRequestListByNextToken';
            $this->resetRequestIds();
            $this->resetRequestTypes();
            $this->resetReportStatuses();
            unset($this->options['MaxCount']);
            unset($this->options['RequestedFromDate']);
            unset($this->options['RequestedToDate']);
        } else {
            $this->options['Action'] = 'GetReportRequestList';
            $this->throttleGroup = 'GetReportRequestList';
            unset($this->options['NextToken']);
            $this->reportList = array();
        }
    }
    
    /**
     * Returns the list of orders
     * @return array Array of AmazonOrder objects
     */
    public function getList(){
        return $this->orderList;
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->orderList[$this->i]; 
    }

    /**
     * Iterator function
     */
    public function rewind(){
        $this->i = 0;
    }

    /**
     * Iterator function
     * @return type
     */
    public function key() {
        return $this->i;
    }

    /**
     * Iterator function
     */
    public function next() {
        $this->i++;
    }

    /**
     * Iterator function
     * @return type
     */
    public function valid() {
        return isset($this->orderList[$this->i]);
    }
    
}
?>
