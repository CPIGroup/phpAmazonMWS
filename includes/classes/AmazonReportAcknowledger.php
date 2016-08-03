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
 * Acknowledges reports on Amazon.
 * 
 * This Amazon Reports Core object updates the acknowledgement status of
 * reports on Amazon. In order to do this, at least one Report ID is
 * required. A list of the affected reports is returned.
 */
class AmazonReportAcknowledger extends AmazonReportsCore implements Iterator{
    protected $count;
    protected $index = 0;
    protected $i = 0;
    protected $reportList;
    
    /**
     * AmazonReportAcknowledger sends a report acknowledgement request to Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param array|string $id [optional] <p>The report ID(s) to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $id = null, $mock = false, $m = null, $config = null) {
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        
        if ($id){
            $this->setReportIds($id);
        }
        
        $this->options['Action'] = 'UpdateReportAcknowledgements';
        
        if(isset($THROTTLE_LIMIT_REPORTSCHEDULE)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTSCHEDULE;
        }
        if(isset($THROTTLE_TIME_REPORTSCHEDULE)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTSCHEDULE;
        }
        $this->throttleGroup = 'UpdateReportAcknowledgements';
    }
    
    /**
     * sets the request ID(s). (Required)
     * 
     * This method sets the list of Report IDs to be sent in the next request.
     * @param array|string $s <p>A list of Report IDs, or a single ID string.</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Resets the ASIN options.
     * 
     * Since report ID is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetReportIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the report acknowledgement filter. (Optional)
     * 
     * Setting this parameter to <b>TRUE</b> lists only reports that have been
     * acknowledged. Setting this parameter to <b>FALSE</b> lists only reports
     * that have not been acknowledged yet.
     * @param string|boolean $s <p>"true" or "false", or boolean</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setAcknowledgedFilter($s){
        if ($s == 'true' || (is_bool($s) && $s == true)){
            $this->options['Acknowledged'] = 'true';
        } else if ($s == 'false' || (is_bool($s) && $s == false)){
            $this->options['Acknowledged'] = 'false';
        } else if (is_null($s)){
            unset($this->options['Acknowledged']);
        } else {
            return false;
        }
    }
    
    /**
     * Sends an acknowledgement requst to Amazon and retrieves a list of relevant reports.
     * 
     * Submits a <i>UpdateReportAcknowledgements</i> request to Amazon.
     * In order to do this, a list of Report IDs is required. Amazon will send
     * a list back as a response, which can be retrieved using <i>getList</i>.
     * Other methods are available for fetching specific values from the list.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function acknowledgeReports(){
        if (!array_key_exists('ReportIdList.Id.1',$this->options)){
            $this->log("Report IDs must be set in order to acknowledge reports!",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $this->parseXML($xml);
        
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
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
     * Returns the report ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the report type for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the report request ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the date the specified report was first available.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns whether or not the specified report is scheduled.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the date the specified report was acknowledged.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the report count.
     * 
     * This method will return <b>FALSE</b> if the count has not been set yet.
     * @return number|boolean number, or <b>FALSE</b> if count not set yet
     */
    public function getCount(){
        if (isset($this->count)){
            return $this->count;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the full list.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single report will have the following fields:
     * <ul>
     * <li><b>ReportId</b></li>
     * <li><b>ReportType</b></li>
     * <li><b>ReportRequestId</b></li>
     * <li><b>AvailableDate</b></li>
     * <li><b>Acknowledged</b></li>
     * <li><b>AcknowledgedDate</b></li>
     * </ul>
     * @return array|boolean array, multi-dimensional array, or <b>FALSE</b> if list not filled yet
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