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
     * AmazonProductsCore constructor sets up key information used in all Amazon Products Core requests
     * 
     * This constructor is called when initializing all objects in the Amazon Products Core.
     * The parameters are passed by the child objects' constructors, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->urlbranch = 'Products/'.AMAZON_VERSION_PRODUCTS;
        $this->options['Version'] = AMAZON_VERSION_PRODUCTS;
        
        if(array_key_exists('marketplaceId', $store[$s])){
            $this->options['MarketplaceId'] = $store[$s]['marketplaceId'];
        } else {
            $this->log("Marketplace ID is missing",'Urgent');
        }
        
        $this->throttleLimit = THROTTLE_LIMIT_PRODUCT;
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLObject $xml <p>The XML response from Amazon.</p>
     * @return boolean <p><b>FALSE</b> if no XML data is found</p>
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
                    $this->productList[$this->index] = new AmazonProduct($this->storeName, $z, $this->mockMode, $this->mockFiles,$this->config);
                    $this->index++;
                }
            } else if ($x->getName() == 'GetProductCategoriesForSKUResult' || $x->getName() == 'GetProductCategoriesForASINResult'){
                $this->productList[$this->index] = new AmazonProduct($this->storeName, $x, $this->mockMode, $this->mockFiles,$this->config);
                $this->index++;
            } else {
                foreach($x->children() as $z){
                    if($z->getName() != 'Product'){
                        $this->productList[$z->getName()] = (string)$z;
                        $this->log("Special case: ".$z->getName());
                    } else {
                        $this->productList[$this->index] = new AmazonProduct($this->storeName, $z, $this->mockMode, $this->mockFiles,$this->config);
                        $this->index++;
                    }
                }
            }
        }
    }
    
    /**
     * Returns product specified or array of products.
     * 
     * See the <i>AmazonProduct</i> class for more information on the returned objects.
     * @param int $num [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return AmazonProduct|array <p>Product (or list of Products)</p>
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
