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
     * AmazonFeedResult gets the result of a Feed from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param string $id [optional] <p>The Feed Submission ID to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     */
    public function __construct($s, $id = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
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
     * Sets the feed submission ID for the next request. (Required)
     * 
     * This method sets the feed submission ID to be sent in the next request. This
     * parameter is required in order to retrieve a feed from Amazon.
     * @param string|integer $n <p>Must be numeric</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setFeedId($n){
        if (is_numeric($n)){
            $this->options['FeedSubmissionId'] = $n;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon for a feed.
     * 
     * Submits a <i>GetFeedSubmissionResult</i> request to Amazon. In order to
     * do this, a feed submission ID is required. Amazon will send back the raw results
     * of the feed as a response, which can be saved to a file using <i>saveFeed</i>.
     * @return boolean <p><b>FALSE</b> if something goes wrong</p>
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
     * Saves the raw report data to a path you specify.
     * 
     * This method will record in the log whether or not the save was successful.
     * @param string $path <p>path for the file to save the feed data in</p>
     * @return boolean <p><b>FALSE</b> if something goes wrong</p>
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