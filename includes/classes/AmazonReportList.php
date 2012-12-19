<?php
/**
 * Fetches list of reports available from Amazon.
 * 
 * This Amazon Reports Core object retrieves a list of available on Amazon.
 * No parameters are required, but a number of filters are available to
 * narrow the returned list.
 * This object can use tokens when retrieving the list.
 */
class AmazonReportList extends AmazonReportsCore implements Iterator{
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $index = 0;
    private $i = 0;
    private $reportList;
    
    /**
     * Sends a report request to Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->throttleLimit = $throttleLimitReportList;
        $this->throttleTime = $throttleTimeReportList;
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
                $i++;
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
                $i++;
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
     * @param string $s number from 1 to 100
     * @return boolean false if improper input
     */
    public function setMaxCount($s){
        if (is_int($s) && $s >= 1 && $s <= 100){
            $this->options['MaxCount'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the maximum response count for the next request
     * @param string $s "true" or "false"
     * @return boolean false if improper input
     */
    public function setAcknowledgedFilter($s){
        if ($s == 'true' || (is_bool($s) && $s == true)){
            $this->options['Acknowledged'] = 'true';
        } else if ($s == 'false' || (is_bool($s) && $s == false)){
            $this->options['Acknowledged'] = 'false';
        } else if ($s == null){
            unset($this->options['Acknowledged']);
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
            $this->options['AvailableFromDate'] = $times;
        }
        if ($e && is_string($e)){
            $timee = $this->genTime($e);
            $this->options['AvailableToDate'] = $timee;
        }
    }
    
    /**
     * removes time frame limits
     */
    public function resetTimeLimits(){
        unset($this->options['AvailableFromDate']);
        unset($this->options['AvailableToDate']);
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
            $this->log("Recursively fetching more Reports");
            $this->fetchReportList();
        }
        
    }
    
    /**
     * Sets up token stuff
     */
    protected function prepareToken(){
        include($this->config);
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'GetReportListByNextToken';
            $this->throttleLimit = $throttleLimitReportToken;
            $this->throttleTime = $throttleTimeReportToken;
            $this->throttleGroup = 'GetReportListByNextToken';
            $this->resetRequestIds();
            $this->resetReportTypes();
            $this->resetTimeLimits();
            unset($this->options['MaxCount']);
            unset($this->options['Acknowledged']);
        } else {
            $this->options['Action'] = 'GetReportList';
            $this->throttleLimit = $throttleLimitReportList;
            $this->throttleTime = $throttleTimeReportList;
            $this->throttleGroup = 'GetReportList';
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
            if ($key != 'ReportInfo'){
                continue;
            }
            
            $this->reportList[$i]['ReportId'] = (string)$x->ReportId;
            $this->reportList[$i]['ReportType'] = (string)$x->ReportType;
            $this->reportList[$i]['ReportRequestId'] = (string)$x->ReportRequestId;
            $this->reportList[$i]['AvailableDate'] = (string)$x->AvailableDate;
            $this->reportList[$i]['Acknowledged'] = (string)$x->Acknowledged;
            
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
     * Returns the date acknowledged for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean date acknowledged, or False if Non-numeric index
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
