<?php
/**
 * Core class for Amazon Products API.
 * 
 * This is the core class for all objects in the Amazon Products section.
 * It contains a few methods that all Amazon Products Core objects use.
 */
abstract class AmazonProductsCore extends AmazonCore{
    protected $productList;
    protected $index = 0;
    
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
        
        $this->urlbranch = 'Products/'.$versionProducts;
        $this->options['Version'] = $versionProducts;
        
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
        if (!$xml){
            return false;
        }
        
        foreach($xml->children() as $x){
            if($x->getName() == 'ResponseMetadata'){
                continue;
            }
            $temp = (array)$x->attributes();
            if (isset($temp['@attributes']['status']) && $temp['@attributes']['status'] != 'Success'){
                $this->log("Warning: product return was not successful",'Warning');
            }
            if (isset($x->Products)){
                foreach($x->Products->children() as $z){
                    $this->productList[$this->index] = new AmazonProduct($this->storeName, $z, $this->mockMode, $this->mockFiles);
                    $this->index++;
                }
            } else if ($x->getName() == 'GetProductCategoriesForSKUResult' || $x->getName() == 'GetProductCategoriesForASINResult'){
                $this->productList[$this->index] = new AmazonProduct($this->storeName, $x, $this->mockMode, $this->mockFiles);
                $this->index++;
            } else {
                foreach($x->children() as $z){
                    if($z->getName() != 'Product'){
                        $this->productList[$z->getName()] = (string)$z;
                        $this->log("Special case: ".$z->getName());
                    } else {
                        $this->productList[$this->index] = new AmazonProduct($this->storeName, $z, $this->mockMode, $this->mockFiles);
                        $this->index++;
                    }
                }
            }
        }
    }
    
    /**
     * Returns product specified or array of products
     * @param integer $num non-negative integer
     * @return AmazonProduct|array Product (or list of Products)
     */
    public function getProduct($num = null){
        if (!isset($this->productList)){
            return false;
        }
        if (is_numeric($num)){
            return $this->productList[$num];
        } else {
            return $this->productList;
        }
    }
}
?>
