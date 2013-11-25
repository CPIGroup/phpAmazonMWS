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
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s, $id = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if($id){
            $this->options['FeedSubmissionId'] = $id;
        }
        
        $this->options['Action'] = 'GetFeedSubmissionResult';
        
        if(isset($THROTTLE_LIMIT_FEEDRESULT))
        $this->throttleLimit = $THROTTLE_LIMIT_FEEDRESULT;
        if(isset($THROTTLE_TIME_FEEDRESULT))
        $this->throttleTime = $THROTTLE_TIME_FEEDRESULT;
        $this->throttleGroup = 'GetFeedSubmissionResult';
    }
    
    /**
     * Sets the feed submission ID for the next request. (Required)
     * 
     * This method sets the feed submission ID to be sent in the next request. This
     * parameter is required in order to retrieve a feed from Amazon.
     * @param string|integer $n <p>Must be numeric</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchFeedResult(){
        if (!array_key_exists('FeedSubmissionId',$this->options)){
            $this->log("Feed Submission ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        if ($this->mockMode){
           $this->rawFeed = $this->fetchMockFile(false);
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
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
     * @return boolean <b>FALSE</b> if something goes wrong
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