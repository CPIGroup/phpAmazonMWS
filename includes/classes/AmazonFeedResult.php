<?php

class AmazonFeedResult extends AmazonFeedsCore{
    
    
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
            $this->options['PackageNumber'] = $id;
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
}
?>