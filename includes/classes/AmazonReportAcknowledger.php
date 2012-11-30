<?php

class AmazonReportScheduleCounter extends AmazonReportsCore implements Iterator{
    private $count;
    private $index = 0;
    private $i = 0;
    private $reportList;
    
    /**
     * Sends a report count request to Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        try {
            include($this->config);
        }catch(Exception $e){
            return false;
        }
        
        $this->options['Action'] = 'UpdateReportAcknowledgements';
        
        $this->throttleLimit = $throttleLimitReportSchedule;
        $this->throttleTime = $throttleTimeReportSchedule;
        $this->throttleGroup = 'UpdateReportAcknowledgements';
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
     * Sets the maximum response count for the next request
     * @param string $s "All", "true", or "false"
     * @return boolean false if improper input
     */
    public function setAcknowledgedFilter($s){
        if ($s == 'All' || $s == 'true' || $s == 'false'){
            $this->options['Acknowledged'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sends an acknowledgement requst to Amazon and retrieves a list of relevant reports
     */
    public function acknowledgeReports(){
        if (!array_key_exists('ReportTypeList.Type.1',$this->options)){
            $this->log("Report Types must be set in order to acknowledge reports!",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = 'UpdateReportAcknowledgementsResult';
        
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
     * converts XML to array
     * @param SimpleXMLObject $xml
     */
    protected function parseXML($xml){
        foreach($xml->children() as $key=>$x){
            $i = $this->index;
            if ($key != 'ReportInfo'){
                continue;
            }
            
            $this->reportList[$i]['ReportId'] = (string)$x->ReportId;
            $this->reportList[$i]['ReportType'] = (string)$x->ReportType;
            $this->reportList[$i]['ReportRequestId'] = (string)$x->ReportRequestId;
            $this->reportList[$i]['AvailableToDate'] = (string)$x->AvailableToDate;
            $this->reportList[$i]['Acknowledged'] = (string)$x->Acknowledged;
            $this->reportList[$i]['AcknowledgedDate'] = (string)$x->AvailableToDate;
            
            $this->index++;
        }
    }
    
    /**
     * Returns the report ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean report ID, or False if Non-numeric index
     */
    public function getReportId($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['ReportId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the report type for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean report type, or False if Non-numeric index
     */
    public function getReportType($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['ReportType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the report request ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean report request ID, or False if Non-numeric index
     */
    public function getReportRequestId($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['ReportRequestId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date available to for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean date, or False if Non-numeric index
     */
    public function getAvailableToDate($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['AvailableToDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns whether or not the specified entry is scheduled, defaults to 0
     * @param int $i index
     * @return boolean true or false, or false if Non-numeric index
     */
    public function getIsAcknowledged($i = 0){
        if (is_numeric($i)){
            if ($this->reportList[$i]['Acknowledged'] == 'true'){
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date acknowledged for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean date acknowledged, or False if Non-numeric index
     */
    public function getAcknowledgedDate($i = 0){
        if (is_numeric($i)){
            return $this->reportList[$i]['AcknowledgedDate'];
        } else {
            return false;
        }
    }
    
    /**
     * gets the count, if it exists
     * @return string|boolean number, or false on failure
     */
    public function getCount(){
        if (!isset($this->count)){
            return false;
        } else {
            return $this->count;
        }
    }
    
    /**
     * Returns the list of report arrays
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