<?php
/**
 * Core class for Amazon Sellers API.
 * 
 * This is the core class for the only object in the Amazon Sellers section.
 * It contains no functions in itself other than the constructor.
 */
abstract class AmazonSellersCore extends AmazonCore{
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
        
        $this->urlbranch = 'Sellers/'.$versionSellers;
        $this->options['Version'] = $versionSellers;
    }
}
?>
