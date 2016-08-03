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
 * Fetches a list of report requests from Amazon.
 * 
 * This Amazon Reports Core Object retrieves a list of previously requested
 * reports from Amazon. No parameters are required, but a number of filters
 * are available to narrow the list of report requests that are returned.
 * This object can also count the number of report requests. This object can
 * use tokens when retrieving the list.
 */
class AmazonReportRequestList extends AmazonReportsCore implements Iterator{
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $index = 0;
    protected $i = 0;
    protected $reportList;
    protected $count;
    
    /**
     * AmazonReportRequestList fetches a list of report requests from Amazon.
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
        
        if(isset($THROTTLE_LIMIT_REPORTREQUESTLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTREQUESTLIST;
        }
        if(isset($THROTTLE_TIME_REPORTREQUESTLIST)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTREQUESTLIST;
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
     * Sets the report status(es). (Optional)
     * 
     * This method sets the list of report types to be sent in the next request.
     * @param array|string $s <p>A list of report types, or a single type string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setReportStatuses($s){
        if (is_string($s)){
            $this->resetReportStatuses();
            $this->options['ReportProcessingStatusList.Status.1'] = $s;
        } else if (is_array($s)){
            $this->resetReportStatuses();
            $i = 1;
            foreach ($s as $x){
                $this->options['ReportProcessingStatusList.Status.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Removes report status options.
     * 
     * Use this in case you change your mind and want to remove the Report Status
     * parameters you previously set.
     */
    public function resetReportStatuses(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ReportProcessingStatusList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the maximum response count. (Optional)
     * 
     * This method sets the maximum number of Report Requests for Amazon to return.
     * If this parameter is not set, Amazon will only send 10 at a time.
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
            $this->options['RequestedFromDate'] = $times;
        }
        if ($e && is_string($e)){
            $timee = $this->genTime($e);
            $this->options['RequestedToDate'] = $timee;
        }
        if (isset($this->options['RequestedFromDate']) && 
                isset($this->options['RequestedToDate']) && 
                $this->options['RequestedFromDate'] > $this->options['RequestedToDate']){
            $this->setTimeLimits($this->options['RequestedToDate'].' - 1 second');
        }
    }
    
    /**
     * Removes time limit options.
     * 
     * Use this in case you change your mind and want to remove the time limit
     * parameters you previously set.
     */
    public function resetTimeLimits(){
        unset($this->options['RequestedFromDate']);
        unset($this->options['RequestedToDate']);
    }
    
    /**
     * Fetches a list of Report Requests from Amazon.
     * 
     * Submits a <i>GetReportRequestList</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getList</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean $r <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchRequestList($r = true){
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
                $this->log("Recursively fetching more Report Requests");
                $this->fetchRequestList(false);
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
            $this->options['Action'] = 'GetReportRequestListByNextToken';
            if(isset($THROTTLE_LIMIT_REPORTTOKEN)) {
                $this->throttleLimit = $THROTTLE_LIMIT_REPORTTOKEN;
            }
            if(isset($THROTTLE_TIME_REPORTTOKEN)) {
                $this->throttleTime = $THROTTLE_TIME_REPORTTOKEN;
            }
            $this->throttleGroup = 'GetReportRequestListByNextToken';
            $this->resetRequestIds();
            $this->resetReportTypes();
            $this->resetReportStatuses();
            unset($this->options['MaxCount']);
            unset($this->options['RequestedFromDate']);
            unset($this->options['RequestedToDate']);
        } else {
            $this->options['Action'] = 'GetReportRequestList';
            if(isset($THROTTLE_LIMIT_REPORTREQUESTLIST)) {
                $this->throttleLimit = $THROTTLE_LIMIT_REPORTREQUESTLIST;
            }
            if(isset($THROTTLE_TIME_REPORTREQUESTLIST)) {
                $this->throttleTime = $THROTTLE_TIME_REPORTREQUESTLIST;
            }
            $this->throttleGroup = 'GetReportRequestList';
            unset($this->options['NextToken']);
            $this->reportList = array();
            $this->index = 0;
        }
    }
    
    /**
     * Sets up options for using <i>CancelReportRequests</i>.
     * 
     * This changes key options for using <i>CancelReportRequests</i>. Please note: because the
     * operation for cancelling feeds does not use all of the parameters, some of the
     * parameters will be removed. The following parameters are removed:
     * max count and token.
     */
    protected function prepareCancel(){
        include($this->env);
        $this->options['Action'] = 'CancelReportRequests';
        if(isset($THROTTLE_LIMIT_REPORTREQUESTLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTREQUESTLIST;
        }
        if(isset($THROTTLE_TIME_REPORTREQUESTLIST)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTREQUESTLIST;
        }
        $this->throttleGroup = 'CancelReportRequests';
        unset($this->options['MaxCount']);
        unset($this->options['NextToken']);
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
            $this->reportList[$i]['GeneratedReportId'] = (string)$x->GeneratedReportId;
            $this->reportList[$i]['StartedProcessingDate'] = (string)$x->StartedProcessingDate;
            $this->reportList[$i]['CompletedDate'] = (string)$x->CompletedDate;
            
            $this->index++;
        }
    }
    
    /**
     * Cancels the report requests that match the given parameters. Careful!
     * 
     * Submits a <i>CancelReportRequests</i> request to Amazon. Amazon will send
     * as a response the list of feeds that were cancelled, along with the count
     * of the number of affected feeds. This data can be retrieved using the same
     * methods as with <i>fetchRequestList</i> and <i>fetchCount</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function cancelRequests(){
        $this->prepareCancel();
        
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
     * Fetches a count of Report Requests from Amazon.
     * 
     * Submits a <i>GetReportRequestCount</i> request to Amazon. Amazon will send
     * the number back as a response, which can be retrieved using <i>getCount</i>.
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
     * Sets up options for using <i>countFeeds</i>.
     * 
     * This changes key options for using <i>countFeeds</i>. Please note: because the
     * operation for counting feeds does not use all of the parameters, some of the
     * parameters will be removed. The following parameters are removed:
     * request IDs, max count, and token.
     */
    protected function prepareCount(){
        include($this->env);
        $this->options['Action'] = 'GetReportRequestCount';
        if(isset($THROTTLE_LIMIT_REPORTREQUESTLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTREQUESTLIST;
        }
        if(isset($THROTTLE_TIME_REPORTREQUESTLIST)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTREQUESTLIST;
        }
        $this->throttleGroup = 'GetReportRequestCount';
        unset($this->options['NextToken']);
        unset($this->options['MaxCount']);
        $this->resetRequestIds();
    }
    
    /**
     * Returns the report request ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getRequestId($i = 0){
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
     * Returns the start date for the specified report request.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getStartDate($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['StartDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the end date for the specified report request.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getEndDate($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['EndDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns whether or not the specified report request is scheduled.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getIsScheduled($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['Scheduled'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date the specified report request was submitted.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getSubmittedDate($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['SubmittedDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the processing status for the specified report request.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getStatus($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['ReportProcessingStatus'];
        } else {
            return false;
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
            return $this->reportList[$i]['GeneratedReportId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date processing for the specified report request started.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getDateProcessingStarted($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['StartedProcessingDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date processing for the specified report request was finished.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getDateCompleted($i = 0){
        if (!isset($this->reportList)){
            return false;
        }
        if (is_int($i)){
            return $this->reportList[$i]['CompletedDate'];
        } else {
            return false;
        }
    }

    /**
     * Alias of getDateCompleted.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     * @see getDateCompleted
     * @deprecated since 1.3.0
     */
    public function getDateProcessingCompleted($i = 0){
        return $this->getDateCompleted($i);
    }
    
    /**
     * Returns the full list.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single report will have the following fields:
     * <ul>
     * <li><b>ReportRequestId</b></li>
     * <li><b>ReportType</b></li>
     * <li><b>StartDate</b></li>
     * <li><b>EndDate</b></li>
     * <li><b>Scheduled</b></li>
     * <li><b>ReportProcessingStatus</b></li>
     * <li><b>GeneratedReportId</b></li>
     * <li><b>StartedProcessingDate</b></li>
     * <li><b>CompletedDate</b></li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to NULL.</p>
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
     * Returns the report request count.
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
