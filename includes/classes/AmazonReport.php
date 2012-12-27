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
     * AmazonReport fetches a report from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param string $id [optional] <p>The report ID to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
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
        
        $this->throttleLimit = THROTTLE_LIMIT_REPORT;
        $this->throttleTime = THROTTLE_TIME_REPORT;
    }
    
    /**
     * Sets the report ID. (Required)
     * 
     * This method sets the report ID to be sent in the next request.
     * This parameter is required for fetching the report from Amazon.
     * @param string|integer $n <p>Must be numeric</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setReportId($n){
        if (is_numeric($n)){
            $this->options['ReportId'] = $n;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon for a report.
     * 
     * Submits a <i>GetReport</i> request to Amazon. In order to do this,
     * a report ID is required. Amazon will send
     * the data back as a response, which can be saved using <i>saveReport</i>.
     * @return boolean <p><b>FALSE</b> if something goes wrong</p>
     */
    public function fetchReport(){
        if (!array_key_exists('ReportId',$this->options)){
            $this->log("Report ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        if ($this->mockMode){
           $this->rawreport = $this->fetchMockFile(false);
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $this->rawreport = $response['body'];
        }
        
    }
    
    /**
     * Saves the raw report data to a path you specify
     * @param string $path <p>filename to save the file in</p>
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
