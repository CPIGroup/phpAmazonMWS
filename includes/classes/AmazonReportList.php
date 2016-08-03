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
 * Fetches list of reports available from Amazon.
 * 
 * This Amazon Reports Core object retrieves a list of available on Amazon.
 * No parameters are required, but a number of filters are available to
 * narrow the returned list. It can also retrieve a count of the feeds.
 * This object can use tokens when retrieving the list.
 */
class AmazonReportList extends AmazonReportsCore implements Iterator{
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $index = 0;
    protected $i = 0;
    protected $reportList;
    
    /**
     * AmazonReportList gets a list of reports from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $mock = false, $m = null, $config = null) {
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        
        if(isset($THROTTLE_LIMIT_REPORTLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTLIST;
        }
        if(isset($THROTTLE_TIME_REPORTLIST)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTLIST;
        }
    }
    
    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the object should automatically use tokens if it receives one.
     * 
     * If this option is set to <b>TRUE</b>, the object will automatically perform
     * the necessary operations to retrieve the rest of the list using tokens. If
     * this option is off, the object will only ever retrieve the first section of
     * the list.
     * @param boolean $b [optional] <p>Defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the report request ID(s). (Optional)
     * 
     * This method sets the list of report request IDs to be sent in the next request.
     * @param array|string $s <p>A list of report request IDs, or a single type string.</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Removes report request ID options.
     * 
     * Use this in case you change your mind and want to remove the Report Request ID
     * parameters you previously set.
     */
    public function resetRequestIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportRequestIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the report type(s). (Optional)
     * 
     * This method sets the list of report types to be sent in the next request.
     * @param array|string $s <p>A list of report types, or a single type string.</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Removes report type options.
     * 
     * Use this in case you change your mind and want to remove the Report Type
     * parameters you previously set.
     */
    public function resetReportTypes(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportTypeList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the maximum response count. (Optional)
     * 
     * This method sets the maximum number of Report Requests for Amazon to return.
     * If this parameter is not set, Amazon will send 100 at a time.
     * @param array|string $s <p>Positive integer from 1 to 100.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setMaxCount($s){
        if (is_int($s) && $s >= 1 && $s <= 100){
            $this->options['MaxCount'] = $s;
        } else {
            return false;
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
        } else if ($s == null){
            unset($this->options['Acknowledged']);
        } else {
            return false;
        }
    }
    
    /**
     * Sets the time frame options. (Optional)
     * 
     * This method sets the start and end times for the next request. If this
     * parameter is set, Amazon will only return Report Requests that were submitted
     * between the two times given. If these parameters are not set, Amazon will
     * only return Report Requests that were submitted within the past 90 days.
     * The parameters are passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * @param string $s [optional] <p>A time string for the earliest time.</p>
     * @param string $e [optional] <p>A time string for the latest time.</p>
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
        if (isset($this->options['AvailableFromDate']) && 
                isset($this->options['AvailableToDate']) && 
                $this->options['AvailableFromDate'] > $this->options['AvailableToDate']){
            $this->setTimeLimits($this->options['AvailableToDate'].' - 1 second');
        }
    }
    
    /**
     * Removes time limit options.
     * 
     * Use this in case you change your mind and want to remove the time limit
     * parameters you previously set.
     */
    public function resetTimeLimits(){
        unset($this->options['AvailableFromDate']);
        unset($this->options['AvailableToDate']);
    }
    
    /**
     * Fetches a list of Reports from Amazon.
     * 
     * Submits a <i>GetReportList</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getList</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchReportList($r = true){
        $this->prepareToken();
        
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
        
        $this->checkToken($xml);
        
        if ($this->tokenFlag && $this->tokenUseFlag && $r === true){
            while ($this->tokenFlag){
                $this->log("Recursively fetching more Reports");
                $this->fetchReportList(false);
            }
            
        }
        
    }
    
    /**
     * Sets up options for using tokens.
     * 
     * This changes key options for switching between simply fetching a list and
     * fetching the rest of a list using a token. Please note: because the
     * operation for using tokens does not use any other parameters, all other
     * parameters will be removed.
     */
    protected function prepareToken(){
        include($this->env);
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'GetReportListByNextToken';
            if(isset($THROTTLE_LIMIT_REPORTTOKEN)) {
                $this->throttleLimit = $THROTTLE_LIMIT_REPORTTOKEN;
            }
            if(isset($THROTTLE_TIME_REPORTTOKEN)) {
                $this->throttleTime = $THROTTLE_TIME_REPORTTOKEN;
            }
            $this->throttleGroup = 'GetReportListByNextToken';
            $this->resetRequestIds();
            $this->resetReportTypes();
            $this->resetTimeLimits();
            unset($this->options['MaxCount']);
            unset($this->options['Acknowledged']);
        } else {
            $this->options['Action'] = 'GetReportList';
            if(isset($THROTTLE_LIMIT_REPORTLIST)) {
                $this->throttleLimit = $THROTTLE_LIMIT_REPORTLIST;
            }
            if(isset($THROTTLE_TIME_REPORTLIST)) {
                $this->throttleTime = $THROTTLE_TIME_REPORTLIST;
            }
            $this->throttleGroup = 'GetReportList';
            unset($this->options['NextToken']);
            $this->reportList = array();
            $this->index = 0;
        }
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
            if ($key != 'ReportInfo'){
                continue;
            }
            
            $this->reportList[$i]['ReportId'] = (string)$x->ReportId;
            $this->reportList[$i]['ReportType'] = (string)$x->ReportType;
            $this->reportList[$i]['ReportRequestId'] = (string)$x->ReportRequestId;
            $this->reportList[$i]['AvailableDate'] = (string)$x->AvailableDate;
            $this->reportList[$i]['Acknowledged'] = (string)$x->Acknowledged;
            if (isset($x->AcknowledgedDate)) {
                $this->reportList[$i]['AcknowledgedDate'] = (string)$x->AcknowledgedDate;
            }
            
            $this->index++;
        }
    }
    
    /**
     * Fetches a count of Reports from Amazon.
     * 
     * Submits a <i>GetReportCount</i> request to Amazon. Amazon will send
     * the count back as a response, which can be retrieved using <i>getCount</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchCount(){
        $this->prepareCount();
        
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
        
        $this->count = (string)$xml->Count;
        
    }
    
    /**
     * Sets up options for using <i>fetchCount</i>.
     * 
     * This changes key options for using <i>fetchCount</i>. Please note: because the
     * operation for counting reports does not use all of the parameters, some of the
     * parameters will be removed. The following parameters are removed:
     * request IDs, max count, and token.
     */
    protected function prepareCount(){
        include($this->env);
        $this->options['Action'] = 'GetReportCount';
        if(isset($THROTTLE_LIMIT_REPORTREQUESTLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTREQUESTLIST;
        }
        if(isset($THROTTLE_TIME_REPORTREQUESTLIST)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTREQUESTLIST;
        }
        $this->throttleGroup = 'GetReportCount';
        unset($this->options['NextToken']);
        unset($this->options['MaxCount']);
        $this->resetRequestIds();
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
     * Returns whether or not the specified report has been acknowledged yet.
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
     * Returns the date the specified report was first acknowledged.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index or if the date is not set
     */
    public function getAcknowledgedDate($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i) && isset($this->reportList[$i]['AcknowledgedDate'])){
            return $this->reportList[$i]['AcknowledgedDate'];
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
     * @param int $i [optional] <p>List index of the report to return. Defaults to NULL.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
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
