<?php

class AmazonProduct extends AmazonProductsCore{
    
    
    /**
     * AmazonProduct acts as a container for various results from other classes
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
        
        $this->throttleLimit = $throttleLimitProduct;
        
    }
}
?>