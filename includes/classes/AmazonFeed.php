<?php

class AmazonFeed extends AmazonFeedsCore{
    
    
    /**
     * AmazonFeed object submits a Feed to Amazon
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
        
        $this->options['Action'] = 'SubmitFeed';
        
        $this->throttleLimit = $throttleLimitFeedSubmit;
        $this->throttleTime = $throttleTimeFeedSubmit;
        $this->throttleGroup = 'SubmitFeed';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
        
    }
}
?>