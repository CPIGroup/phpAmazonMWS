<?php
/**
 * Acknowledges reports on Amazon.
 * 
 * This Amazon Reports Core object updates the acknowledgement status of
 * reports on Amazon. In order to do this, at least one Report ID is
 * required. A list of the affected reports is returned.
 */
class AmazonReportAcknowledger extends AmazonReportsCore implements Iterator{
    private $count;
    private $index = 0;
    private $i = 0;
    private $reportList;
    
    /**
     * Sends a report acknowledgement request to Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $id, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if ($id){
            $this->setReportIds($id);
        }
        
        $this->options['Action'] = 'UpdateReportAcknowledgements';
        
        $this->throttleLimit = $throttleLimitReportSchedule;
        $this->throttleTime = $throttleTimeReportSchedule;
        $this->throttleGroup = 'UpdateReportAcknowledgements';
    }
    
    /**
     * sets the request ID(s) to be used in the next request
     * @param array|string $s array of Report IDs or single ID
     * @return boolean false if failure
     */
    public function setReportIds($s){
        if (is_string($s)){
            $this->resetReportIds();
            $this->options['ReportIdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetReportIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['ReportIdList.Id.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes report ID options
     */
    protected function resetReportIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the maximum response count for the next request
     * @param string $s "true" or "false", or null
     * @return boolean false if improper input
     */
    public function setAcknowledgedFilter($s){
        if ($s == 'true' || (is_bool($s) && $s == true)){
            $this->options['Acknowledged'] = 'true';
        } else if ($s == 'false' || (is_bool($s) && $s == false)){
            $this->options['Acknowledged'] = 'false';
        } else {
            return false;
        }
    }
    
    /**
     * Sends an acknowledgement requst to Amazon and retrieves a list of relevant reports
     */
    public function acknowledgeReports(){
        if (!array_key_exists('ReportIdList.Id.1',$this->options)){
            $this->log("Report IDs must be set in order to acknowledge reports!",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        
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
     * converts XML to array
     * @param SimpleXMLObject $xml
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        foreach($xml->children() as $key=>$x){
            $i = $this->index;
            if ($key == 'Count'){
                $this->count = (string)$x;
            }
            if ($key != 'ReportInfo'){
                continue;
            }
            
            $this->reportList[$i]['ReportId'] = (string)$x->ReportId;
            $this->reportList[$i]['ReportType'] = (string)$x->ReportType;
            $this->reportList[$i]['ReportRequestId'] = (string)$x->ReportRequestId;
            $this->reportList[$i]['AvailableDate'] = (string)$x->AvailableDate;
            $this->reportList[$i]['Acknowledged'] = (string)$x->Acknowledged;
            $this->reportList[$i]['AcknowledgedDate'] = (string)$x->AcknowledgedDate;
            
            $this->index++;
        }
    }
    
    /**
     * Returns the report ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean report ID, or False if Non-numeric index
     */
    public function getReportId($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
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
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
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
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
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
    public function getAvailableDate($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['AvailableDate'];
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
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['Acknowledged'];
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
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
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
        if (isset($this->count)){
            return $this->count;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the list of report arrays
     * @param int $i index
     * @return array Array of arrays
     */
    public function getList($i = null){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i];
        } else {
            return $this->reportList;
        }
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