<?php

abstract class AmazonProductsCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        $this->urlbranch = 'Products/2011-10-01';
        $this->options['Version'] = '2011-10-01';
        
        if(array_key_exists('marketplaceId', $store[$s])){
            $this->options['MarketplaceId'] = $store[$s]['marketplaceId'];
        } else {
            $this->log("Marketplace ID is missing",'Urgent');
        }
    }
}
?>
