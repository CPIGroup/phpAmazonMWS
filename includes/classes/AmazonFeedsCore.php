<?php
/**
 * Core class for Amazon Feeds.
 * 
 * This is the core class for all objects in the Amazon Feeds section.
 * It contains no functions in itself other than the constructor.
 */
abstract class AmazonFeedsCore extends AmazonCore{
    /**
     * For organization's sake
     * @param string $s
     * @param boolean $mock
     * @param string|array $m
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->urlbranch = '';
        $this->options['Version'] = $versionFeeds;
    }
}
?>
