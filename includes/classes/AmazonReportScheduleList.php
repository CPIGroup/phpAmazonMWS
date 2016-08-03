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
 * Fetches list of report schedules from Amazon.
 * 
 * This Amazon Reports Core object retrieves a list of previously submitted
 * report schedules on Amazon. An optional filter is available for narrowing
 * the types of reports that are returned. This object can also retrieve a
 * count of the scheudles in the same manner. This object can use tokens when
 * retrieving the list.
 */
class AmazonReportScheduleList extends AmazonReportsCore implements Iterator{
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $index = 0;
    protected $i = 0;
    protected $scheduleList;
    protected $count;
    
    /**
     * AmazonReportScheduleList sets a list of report schedules from Amazon.
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
        
        if(isset($THROTTLE_LIMIT_REPORTSCHEDULE)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTSCHEDULE;
        }
        if(isset($THROTTLE_TIME_REPORTSCHEDULE)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTSCHEDULE;
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
     * Fetches a list of Report Schedules from Amazon.
     * 
     * Submits a <i>GetReportScheduleList</i> request to Amazon. Amazon will send
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
                $this->log("Recursively fetching more Report Schedules");
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
            $this->options['Action'] = 'GetReportScheduleListByNextToken';
            if(isset($THROTTLE_LIMIT_REPORTTOKEN)) {
                $this->throttleLimit = $THROTTLE_LIMIT_REPORTTOKEN;
            }
            if(isset($THROTTLE_TIME_REPORTTOKEN)) {
                $this->throttleTime = $THROTTLE_TIME_REPORTTOKEN;
            }
            $this->throttleGroup = 'GetReportScheduleListByNextToken';
            $this->resetReportTypes();
        } else {
            $this->options['Action'] = 'GetReportScheduleList';
            if(isset($THROTTLE_LIMIT_REPORTSCHEDULE)) {
                $this->throttleLimit = $THROTTLE_LIMIT_REPORTSCHEDULE;
            }
            if(isset($THROTTLE_TIME_REPORTSCHEDULE)) {
                $this->throttleTime = $THROTTLE_TIME_REPORTSCHEDULE;
            }
            $this->throttleGroup = 'GetReportScheduleList';
            unset($this->options['NextToken']);
            $this->scheduleList = array();
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
     * Fetches a count of Report Schedules from Amazon.
     * 
     * Submits a <i>GetReportScheduleCount</i> request to Amazon. Amazon will send
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
        $this->options['Action'] = 'GetReportScheduleCount';
        if(isset($THROTTLE_LIMIT_REPORTSCHEDULE)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTSCHEDULE;
        }
        if(isset($THROTTLE_TIME_REPORTSCHEDULE)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTSCHEDULE;
        }
        $this->throttleGroup = 'GetReportScheduleCount';
        unset($this->options['NextToken']);
    }
    
    /**
     * Returns the report type for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getReportType($i = 0){
        if (!isset($this->scheduleList)){
            return false;
        }
        if (is_int($i)){
            return $this->scheduleList[$i]['ReportType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the schedule for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getSchedule($i = 0){
        if (!isset($this->scheduleList)){
            return false;
        }
        if (is_int($i)){
            return $this->scheduleList[$i]['Schedule'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date the specified report is scheduled for.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getScheduledDate($i = 0){
        if (!isset($this->scheduleList)){
            return false;
        }
        if (is_int($i)){
            return $this->scheduleList[$i]['ScheduledDate'];
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
     * <li><b>ReportType</b></li>
     * <li><b>Schedule</b></li>
     * <li><b>ScheduledDate</b></li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to NULL.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getList($i = null){
        if (!isset($this->scheduleList)){
            return false;
        }
        if (is_int($i)){
            return $this->scheduleList[$i];
        } else {
            return $this->scheduleList;
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
