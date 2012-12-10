<?php

abstract class AmazonProductsCore extends AmazonCore{
    protected $productList;
    
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        $this->urlbranch = 'Products/2011-10-01';
        $this->options['Version'] = '2011-10-01';
        
        if(array_key_exists('marketplaceId', $store[$s])){
            $this->options['MarketplaceId'] = $store[$s]['marketplaceId'];
        } else {
            $this->log("Marketplace ID is missing",'Urgent');
        }
    }
    
    /**
     * reads XML and creates product list
     * @param SimpleXMLObject $xml
     */
    protected function parseXML($xml){
        if ($xml->Products){
            $i = 0;
            foreach($xml->Products->children() as $x){
                $this->productList[$i] = new AmazonProduct($this->storeName, $this->mockMode, $this->mockFiles);
                $this->productList[$i]->loadXML($x);
                $i++;
            }
        } else if ($xml->Product) {
            $this->productList[0] = new AmazonProduct($this->storeName, $this->mockMode, $this->mockFiles);
            $this->productList[0]->loadXML($xml->Product);
        }
    }
    
    /**
     * Returns product specified or array of products
     * @param integer $num non-negative integer
     * @return AmazonProduct|array Product (or list of Products)
     */
    public function getProduct($num = null){
        if ($num && is_numeric($num)){
            return $this->productList[$num];
        } else {
            return $this->productList;
        }
    }
}
?>
