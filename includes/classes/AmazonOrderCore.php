<?php
/**
 * Core class for Amazon Orders API.
 * 
 * This is the core class for all objects in the Amazon Orders section.
 * It contains no functions in itself other than the constructor.
 */
abstract class AmazonOrderCore extends AmazonCore{
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
        
        $this->urlbranch = 'Orders/'.$versionOrders;
        $this->options['Version'] = $versionOrders;
    }
}
?>
