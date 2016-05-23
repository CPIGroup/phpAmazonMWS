<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Fetches a report from Amazon
 * 
 * This Amazon Reports Core object retrieves the results of a report from Amazon.
 * In order to do this, a report ID is required. The results of the report can
 * then be saved to a file.
 */
class AmazonReport extends AmazonReportsCore{
    protected $rawreport;
    
    /**
     * AmazonReport fetches a report from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param string $id [optional] <p>The report ID to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $id = null, $mock = false, $m = null, $config = null) {
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        
        if($id){
            $this->setReportId($id);
        }
        
        $this->options['Action'] = 'GetReport';
        
        if(isset($THROTTLE_LIMIT_REPORT)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORT;
        }
        if(isset($THROTTLE_TIME_REPORT)) {
            $this->throttleTime = $THROTTLE_TIME_REPORT;
        }
    }
    
    /**
     * Sets the report ID. (Required)
     * 
     * This method sets the report ID to be sent in the next request.
     * This parameter is required for fetching the report from Amazon.
     * @param string|integer $n <p>Must be numeric</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * @return boolean <b>FALSE</b> if something goes wrong
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
     * Gets the raw report data.
     * This method will return <b>FALSE</b> if the data has not yet been retrieved.
     * Please note that this data is often very large.
     * @param string $path <p>filename to save the file in</p>
     * @return string|boolean raw data string, or <b>FALSE</b> if data has not been retrieved yet
     */
    public function getRawReport() {
        if (!isset($this->rawreport)){
            return false;
        }
        return $this->rawreport;
    }
    
    /**
     * Saves the raw report data to a path you specify
     * @param string $path <p>filename to save the file in</p>
     * @return boolean <b>FALSE</b> if something goes wrong
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
            return false;
        }
    }
    
}
?>
