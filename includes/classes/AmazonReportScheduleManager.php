<?php

class AmazonReportScheduleManager extends AmazonReportsCore{
    private $scheduleList;
    private $count;
    
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
        
        $this->options['Action'] = 'ManageReportSchedule';
        
        $this->throttleLimit = $throttleLimitSchedule;
        $this->throttleTime = $throttleTimeSchedule;
    }
    
    /**
     * Sets the report type for the next request
     * @param string $s see comment inside for valid values
     * @return boolean false if improper input
     */
    public function setReportType($s){
        if (is_numeric($s) && $s >= 1 && $s <= 100){
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
     * Sets the schedule for the next request
     * @param string $s see comment inside for valid values
     * @return boolean false if improper input
     */
    public function setSchedule($s){
        if (is_numeric($s) && $s >= 1 && $s <= 100){
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
     * Sets the time the scheduled report will begin
     * @param string $t time string that will be passed through strtotime
     */
    public function setScheduledDate($t = null){
        try{
            if ($t){
                $after = $this->genTime($t);
            } else {
                $after = $this->genTime('- 2 min');
            }
            $this->options['ScheduledDate'] = $after;
            $this->resetSkus();
            
        } catch (Exception $e){
            $this->log("Parameter should be a timestamp, instead $t",'Warning');
        }
        
    }
    
    /**
     * Sends the report schedule information to Amazon
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
        foreach($xml->children() as $key=>$x){
            $i = $this->index;
            if ($key == 'Count'){
                $this->count = (string)$x;
            }
            if ($key != 'ReportSchedule'){
                continue;
            }
            
            /*
             * after I know what this is,
             * I might have to optimize this part
             */
            
            
            
            $this->scheduleList[$i]['ReportType'] = (string)$x->ReportType;
            $this->scheduleList[$i]['Schedule'] = (string)$x->Schedule;
            $this->scheduleList[$i]['ScheduledDate'] = (string)$x->ScheduledDate;
            
            $this->index++;
        }
    }
    
    /**
     * Returns the report type for the specified entry
     * @param int $i index, defaults to 0
     * @return string|boolean report type, or False if Non-numeric index
     */
    public function getReportType($i = 0){
        if (is_numeric($i)){
            return $this->scheduleList[$i]['ReportType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the schedule for the specified entry
     * @param int $i index, defaults to 0
     * @return string|boolean schedule, or False if Non-numeric index
     */
    public function getSchedule($i = 0){
        if (is_numeric($i)){
            return $this->scheduleList[$i]['Schedule'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the scheduled starting date for the specified entry
     * @param int $i index, defaults to 0
     * @return string|boolean date scheduled, or False if Non-numeric index
     */
    public function getScheduledDate($i = 0){
        if (is_numeric($i)){
            return $this->scheduleList[$i]['ScheduledDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the list of report arrays
     * @return array Array of arrays
     */
    public function getList(){
        return $this->scheduleList;
    }
    
    /**
     * gets the count, if it exists
     * @return string|boolean number, or false on failure
     */
    public function getCount(){
        if (!isset($this->count)){
            return false;
        } else {
            return $this->count;
        }
    }
    
}
?>