<?php
/**
 * Retrieves feeds from Amazon.
 * 
 * This Amazon Feeds Core object can retrieve the results of a
 * processed feed from Amazon, which can then be saved to a file
 * specified by the user. In order to fetch feed results, the
 * feed's ID must be given.
 */
class AmazonFeedResult extends AmazonFeedsCore{
    private $rawFeed;
    
    /**
     * AmazonFeed object gets the result of a Feed from Amazon
     * @param string $s store name as seen in config
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $id = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        if($id){
            $this->options['FeedSubmissionId'] = $id;
        }
        
        $this->options['Action'] = 'GetFeedSubmissionResult';
        
        $this->throttleLimit = $throttleLimitFeedResult;
        $this->throttleTime = $throttleTimeFeedResult;
        $this->throttleGroup = 'GetFeedSubmissionResult';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
        
    }
    
    /**
     * Sets the feed submission ID for the next request
     * @param integer $n
     * @return boolean false if improper input
     */
    public function setFeedId($n){
        if (is_numeric($n)){
            $this->options['FeedSubmissionId'] = $n;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon for a feed
     * @return boolean false on failure
     */
    public function fetchFeedResult(){
        if (!array_key_exists('FeedSubmissionId',$this->options)){
            $this->log("Feed Submission ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $this->rawFeed = $this->fetchMockFile(false);
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $this->rawFeed = $response['body'];
        }
        
    }
    
    /**
     * Saves the raw report data to a path you specify
     * @param string $path filename to save the file in
     * @return boolean false on failure
     */
    public function saveFeed($path){
        if (!isset($this->rawFeed)){
            return false;
        }
        try{
            file_put_contents($path,$this->rawFeed);
            $this->log("Successfully saved feed #".$this->options['FeedSubmissionId']." at $path");
        } catch (Exception $e){
            $this->log("Unable to save feed #".$this->options['FeedSubmissionId']." at $path: ".$e->getMessage(),'Urgent');
            return false;
        }
    }
    
}
?>