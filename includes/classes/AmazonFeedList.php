<?php

class AmazonFeedList extends AmazonFeedsCore implements Iterator{
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $feedList;
    private $index = 0;
    private $i = 0;
    private $count;
    
    /**
     * AmazonFeedList fetches a list of Feeds from Amazon
     * @param string $s store name as seen in config
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        $this->throttleLimit = $throttleLimitFeedList;
        $this->throttleTime = $throttleTimeFeedList;
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
        
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
    public function setFeedIds($s){
        if (is_string($s)){
            $this->resetFeedIds();
            $this->options['FeedSubmissionIdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetFeedIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['FeedSubmissionIdList.Id.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes ID options
     */
    public function resetFeedIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#FeedSubmissionIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sets the feed type(s) to be used in the next request
     * @param array|string $s array of Feed  Types or single type
     * @return boolean false if failure
     */
    public function setFeedTypes($s){
        if (is_string($s)){
            $this->resetFeedTypes();
            $this->options['FeedTypeList.Type.1'] = $s;
        } else if (is_array($s)){
            $this->resetFeedTypes();
            $i = 1;
            foreach ($s as $x){
                $this->options['FeedTypeList.Type.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes type options
     */
    public function resetFeedTypes(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#FeedTypeList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sets the feed type(s) to be used in the next request
     * @param array|string $s array of Feed  Types or single type
     * @return boolean false if failure
     */
    public function setFeedStatuses($s){
        if (is_string($s)){
            $this->resetFeedStatuses();
            $this->options['FeedProcessingStatusList.Status.1'] = $s;
        } else if (is_array($s)){
            $this->resetFeedStatuses();
            $i = 1;
            foreach ($s as $x){
                $this->options['FeedProcessingStatusList.Status.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes type options
     */
    public function resetFeedStatuses(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#FeedProcessingStatusList#",$op)){
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
        if (is_numeric($s) && $s >= 1 && $s <= 100){
            $this->options['MaxCount'] = $s;
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
            $this->options['SubmittedFromDate'] = $times;
        }
        if ($e && is_string($e)){
            $timee = $this->genTime($e);
            $this->options['SubmittedToDate'] = $timee;
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
     * Fetches the participation list from Amazon, using a token if available
     * @param boolean $refresh set false to preserve current list (for internal use)
     */
    public function fetchFeedSubmissions(){
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareToken();
        
        if (!isset($this->options['QueryStartDateTime']) && !isset($this->options['SellerSkus.member.1'])){
            $this->setStartTime();
        }
        
        
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
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        
        
        
//        var_dump($this->supplyList);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Feeds");
            $this->fetchInventoryList(false);
        }
        
    }
    
    /**
     * Sets up token stuff
     */
    protected function prepareToken(){
        include($this->config);
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'GetFeedSubmissionListByNextToken';
            $this->throttleLimit = $throttleLimitReportToken;
            $this->throttleTime = $throttleTimeReportToken;
            $this->throttleGroup = 'GetFeedSubmissionListByNextToken';
            $this->resetFeedTypes();
            $this->resetFeedStatuses();
            $this->resetFeedIds();
            $this->resetTimeLimits();
            unset($this->options['MaxCount']);
        } else {
            $this->options['Action'] = 'GetFeedSubmissionList';
            $this->throttleLimit = $throttleLimitFeedList;
            $this->throttleTime = $throttleTimeFeedList;
            $this->throttleGroup = 'GetFeedSubmissionList';
            unset($this->options['NextToken']);
            $this->feedList = array();
            $this->index = 0;
        }
    }
    
    /**
     * Fetches the count from Amazon
     */
    public function countFeeds(){
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareCount();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = 'GetFeedSubmissionCountResult';
        
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
        
        $this->count = (string)$xml->Count;
        
    }
    
    protected function prepareCount(){
        $this->options['Action'] = 'GetFeedSubmissionCount';
        $this->throttleLimit = $throttleLimitFeedList;
        $this->throttleTime = $throttleTimeFeedList;
        $this->throttleGroup = 'GetFeedSubmissionCount';
        $this->resetFeedIds();
        unset($this->options['MaxCount']);
        unset($this->options['NextToken']);
    }
    
    /**
     *Cancels the report requests that match the given parameters. Careful!
     */
    public function cancelFeeds(){
        $this->prepareCancel();
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
     * Modifies the options for cancelling
     */
    protected function prepareCancel(){
        include($this->config);
        $this->options['Action'] = 'CancelFeedSubmissions';
        $this->throttleLimit = $throttleLimitFeedList;
        $this->throttleTime = $throttleTimeFeedList;
        $this->throttleGroup = 'CancelFeedSubmissions';
        unset($this->options['MaxCount']);
        unset($this->options['NextToken']);
        $this->resetFeedStatuses();
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->feedList[$this->i]; 
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
        return isset($this->feedList[$this->i]);
    }
    
}
?>