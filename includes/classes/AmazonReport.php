<?php
/**
 * Fetches a report from Amazon
 * 
 * This Amazon Reports Core object retrieves the results of a report from Amazon.
 * In order to do this, a report ID is required. The results of the report can
 * then be saved to a file.
 */
class AmazonReport extends AmazonReportsCore{
    private $rawreport;
    
    /**
     * Fetches a report from Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $id = null, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if($id){
            $this->setReportId($id);
        }
        
        $this->options['Action'] = 'GetReport';
        
        $this->throttleLimit = $throttleLimitReport;
        $this->throttleTime = $throttleTimeReport;
    }
    
    /**
     * Sets the report ID for the next request
     * @param integer $n
     * @return boolean false if improper input
     */
    public function setReportId($n){
        if (is_numeric($n)){
            $this->options['ReportId'] = $n;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon for a report
     * @return boolean false on failure
     */
    public function fetchReport(){
        if (!array_key_exists('ReportId',$this->options)){
            $this->log("Report ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $this->rawreport = $this->fetchMockFile(false);
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $this->rawreport = $response['body'];
        }
        
    }
    
    /**
     * Saves the raw report data to a path you specify
     * @param string $path filename to save the file in
     */
    public function saveReport($path){
        if (!isset($this->rawreport)){
            return false;
        }
        try{
            file_put_contents($path, $this->rawreport);
            $this->log("Successfully saved report #".$this->options['ReportId']." at $path");
        } catch (Exception $e){
            $this->log("Unable to save report #".$this->options['ReportId']." at $path: $e",'Urgent');
        }
    }
    
}
?>
