<?php

class AmazonReportScheduleList extends AmazonReportsCore implements Iterator{
    private $tokenFlag;
    private $tokenUseFlag;
    private $index = 0;
    private $i = 0;
    private $scheduleList;
    
    /**
     * Sends a report request to Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        $this->throttleLimit = $throttleLimitReportSchedule;
        $this->throttleTime = $throttleTimeReportSchedule;
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
     * sets the report type(s) to be used in the next request
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
     * Fetches the report list from Amazon, using a token if available
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
            $this->log("Recursively fetching more Report Schedules");
            $this->fetchReportList();
        }
        
    }
    
    /**
     * Sets up token stuff
     */
    protected function prepareToken(){
        include($this->config);
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'GetReportScheduleListByNextToken';
            $this->throttleLimit = $throttleLimitReportToken;
            $this->throttleTime = $throttleTimeReportToken;
            $this->throttleGroup = 'GetReportScheduleListByNextToken';
            $this->resetRequestTypes();
        } else {
            $this->options['Action'] = 'GetReportScheduleList';
            $this->throttleLimit = $throttleLimitReportSchedule;
            $this->throttleTime = $throttleTimeReportSchedule;
            $this->throttleGroup = 'GetReportScheduleList';
            unset($this->options['NextToken']);
            $this->scheduleList = array();
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
            if ($key != 'ReportSchedule'){
                continue;
            }
            
            $this->scheduleList[$i]['ReportType'] = (string)$x->ReportType;
            $this->scheduleList[$i]['Schedule'] = (string)$x->Schedule;
            $this->scheduleList[$i]['ScheduledDate'] = (string)$x->ScheduledDate;
            
            $this->index++;
        }
    }
    
    /**
     * Returns the report type for the specified entry
     * @param int $i index, defaults to 0
     * @return string|boolean report type, or False if Non-numeric index
     */
    public function getReportType($i = 0){
        if (is_numeric($i)){
            return $this->scheduleList[$i]['ReportType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the schedule for the specified entry
     * @param int $i index, defaults to 0
     * @return string|boolean schedule, or False if Non-numeric index
     */
    public function getSchedule($i = 0){
        if (is_numeric($i)){
            return $this->scheduleList[$i]['Schedule'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date scheduled for the specified entry
     * @param int $i index, defaults to 0
     * @return string|boolean date scheduled, or False if Non-numeric index
     */
    public function getScheduledDate($i = 0){
        if (is_numeric($i)){
            return $this->scheduleList[$i]['ScheduledDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the list of report arrays
     * @return array Array of arrays
     */
    public function getList(){
        return $this->scheduleList;
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->scheduleList[$this->i]; 
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
        return isset($this->scheduleList[$this->i]);
    }
    
}
?>
