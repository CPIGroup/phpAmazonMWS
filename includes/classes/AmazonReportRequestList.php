<?php

class AmazonReportRequestList extends AmazonReportsCore implements Iterator{
    private $tokenFlag;
    private $tokenUseFlag;
    private $index = 0;
    private $i = 0;
    private $reportList;
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
     * Fetches the report request list from Amazon, using a token if available
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
        
        if ((string)$xml->HasNext == 'true'){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        $this->parseXML($xml);
        
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
            $this->index = 0;
        }
    }
    
    /**
     * converts XML to array
     * @param SimpleXMLObject $xml
     */
    protected function parseXML($xml){
        foreach($xml->children() as $key=>$x){
            $i = $this->index;
            if ($key == 'Count'){
                $this->count = (string)$x;
                $this->log("Successfully canceled $this->count report requests.");
            }
            if ($key != 'ReportRequestInfo'){
                continue;
            }
            
            $this->reportList[$i]['ReportRequestId'] = (string)$x->ReportRequestId;
            $this->reportList[$i]['ReportType'] = (string)$x->ReportType;
            $this->reportList[$i]['StartDate'] = (string)$x->StartDate;
            $this->reportList[$i]['EndDate'] = (string)$x->EndDate;
            $this->reportList[$i]['Scheduled'] = (string)$x->Scheduled;
            $this->reportList[$i]['SubmittedDate'] = (string)$x->SubmittedDate;
            $this->reportList[$i]['ReportProcessingStatus'] = (string)$x->ReportProcessingStatus;
            
            $this->index++;
        }
    }
    
    /**
     *Cancels the report requests that match the given parameters. Careful!
     */
    public function cancelRequests(){
        $this->options['Action'] = 'CancelReportRequests';
        $this->throttleGroup = 'CancelReportRequests';
        $this->options['Timestamp'] = $this->genTime();
        unset($this->options['MaxCount']);
        unset($this->options['NextToken']);
        
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
        
        $this->parseXML($xml);
        
    }
    
    /**
     * Returns the report request ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean report request ID, or False if Non-numeric index
     */
    public function getRequestId($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['ReportRequestId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the report type for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean report type, or False if Non-numeric index
     */
    public function getRequestType($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['ReportType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the start date for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean start date, or False if Non-numeric index
     */
    public function getStartDate($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['StartDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the end date for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean end date, or False if Non-numeric index
     */
    public function getEndDate($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['EndDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns whether or not the specified entry is scheduled, defaults to 0
     * @param int $i index
     * @return boolean true or false, or false if Non-numeric index
     */
    public function getIsScheduled($i = 0){
        if (is_numeric($i)){
            if ($this->reportList[$i]['Scheduled'] == 'true'){
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date submitted for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean date submitted, or False if Non-numeric index
     */
    public function getSubmittedDate($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['SubmittedDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the processing status for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean status, or False if Non-numeric index
     */
    public function getStatus($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['ReportProcessingStatus'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the list of report request arrays
     * @return array Array of arrays
     */
    public function getList(){
        return $this->reportList;
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->reportList[$this->i]; 
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
        return isset($this->reportList[$this->i]);
    }
    
}
?>
