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
 * Manages report schedules with Amazon.
 * 
 * This Amazon Reports Core object sends a request to Amazon to modify the
 * existing report schedules and create new ones. To do this, a report type
 * and schedule are required. Only one report schedule can be modified at a time.
 * Amazon will return a count of the number of report schedules affected,
 * which will usually be 1.
 */
class AmazonReportScheduleManager extends AmazonReportsCore implements Iterator{
    protected $scheduleList;
    protected $count;
    protected $i = 0;
    protected $index = 0;
    
    /**
     * AmazonReportsScheduleManager manages report schedules.
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
        
        $this->options['Action'] = 'ManageReportSchedule';
        
        if(isset($THROTTLE_LIMIT_REPORTSCHEDULE)) {
            $this->throttleLimit = $THROTTLE_LIMIT_REPORTSCHEDULE;
        }
        if(isset($THROTTLE_TIME_REPORTSCHEDULE)) {
            $this->throttleTime = $THROTTLE_TIME_REPORTSCHEDULE;
        }
    }
    
    /**
     * Sets the report type. (Optional)
     * 
     * This method sets the report type to be sent in the next request.
     * @param string $s <p>See the comment inside for a list of valid values.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setReportType($s){
        if (is_string($s)){
            $this->options['ReportType'] = $s;
        } else {
            return false;
        }
        /*
         * Valid valuies for Report Type
         * Scheduled XML Order Report ~ _GET_ORDERS_DATA_
         * Scheduled Flat File Order Report ~ _GET_FLAT_FILE_ORDERS_DATA_
         * Flat File Order Report ~ _GET_CONVERGED_FLAT_FILE_ORDER_REPORT_DATA_
         * Product Ads Daily Performance by SKU Report, flat File ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_DAILY_DATA_TSV_
         * Product Ads Daily Performance by SKU Report, XML ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_DAILY_DATA_XML_
         * Product Ads Weekly Performance by SKU Report, flat File ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_WEEKLY_DATA_TSV_
         * Product Ads Weekly Performance by SKU Report, XML ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_WEEKLY_DATA_XML_
         * Product Ads Monthly Performance by SKU Report, flat File ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_MONTHLY_DATA_TSV_
         * Product Ads Monthly Performance by SKU Report, XML ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_MONTHLY_DATA_XML_
         */
    }
    
    /**
     * Sets the schedule. (Optional)
     * 
     * This method sets the schedule to be sent in the next request.
     * @param string $s <p>See the comment inside for a list of valid values.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setSchedule($s){
        if (is_string($s)){
            $this->options['Schedule'] = $s;
        } else {
            return false;
        }
        /*
         * Valid Schedule values:
         * Every 15 minutes ~   _15_MINUTES_
         * Every 30 minutes ~   _30_MINUTES_
         * Every hour ~         _1_HOUR_
         * Every 2 hours ~      _2_HOURS_
         * Every 4 hours ~      _4_HOURS_
         * Every 8 hours ~      _8_HOURS_
         * Every 12 hours ~     _12_HOURS_
         * Every day ~          _1_DAY_
         * Every 2 days ~       _2_DAYS_
         * Every 3 days ~       _72_HOURS_
         * Every 7 days ~       _7_DAYS_
         * Every 14 days ~      _14_DAYS_
         * Every 15 days ~      _15_DAYS_
         * Every 30 days ~      _30_DAYS_
         * Delete ~             _NEVER_
         */
    }
    
    /**
     * Sets the scheduled date. (Optional)
     * 
     * This method sets the scheduled date for the next request.
     * If this parameters is set, the scheduled report will take effect
     * at the given time. The value can be no more than 366 days in the future.
     * If this parameter is not set, the scheduled report will take effect immediately.
     * The parameter is passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * @param string $t <p>Time string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setScheduledDate($t = null){
        try{
            if ($t){
                $after = $this->genTime($t);
            } else {
                $after = $this->genTime('- 2 min');
            }
            $this->options['ScheduledDate'] = $after;
            
        } catch (Exception $e){
            $this->log("Error: ".$e->getMessage(),'Warning');
        }
        
    }
    
    /**
     * Sends the report schedule information to Amazon.
     * 
     * Submits a <i>ManageReportSchedule</i> request to Amazon. In order to do this,
     * a report type and a schedule are required. Amazon will send
     * data back as a response, which can be retrieved using <i>getList</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function manageReportSchedule(){
        if (!array_key_exists('ReportType',$this->options)){
            $this->log("Report Type must be set in order to manage a report schedule!",'Warning');
            return false;
        }
        if (!array_key_exists('Schedule',$this->options)){
            $this->log("Schedule must be set in order to manage a report schedule!",'Warning');
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
            if ($key == 'Count'){
                $this->count = (string)$x;
            }
            if ($key != 'ReportSchedule'){
                continue;
            }
            $i = $this->index;
            
            $this->scheduleList[$i]['ReportType'] = (string)$x->ReportType;
            $this->scheduleList[$i]['Schedule'] = (string)$x->Schedule;
            $this->scheduleList[$i]['ScheduledDate'] = (string)$x->ScheduledDate;
            
            $this->index++;
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
     * Returns the date the specified report request is scheduled to start.
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