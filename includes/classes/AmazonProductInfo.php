<?php

class AmazonProductInfo extends AmazonProductsCore{
    
    
    /**
     * AmazonProductInfo fetches a list of info from Amazon
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
        
        if ($throttleSafe){
            $this->throttleLimit++; 
        }
        
    }
    
    /**
     * sets the seller SKU(s) to be used in the next request
     * @param array|string $s array of seller SKUs or single SKU (max: 20)
     * @return boolean false if failure
     */
    public function setSKUs($s){
        if (is_string($s)){
            $this->resetASINs();
            $this->resetSKUs();
            $this->options['SellerSKUList.SellerSKU.1'] = $s;
        } else if (is_array($s)){
            $this->resetASINs();
            $this->resetSKUs();
            $i = 1;
            foreach ($s as $x){
                $this->options['SellerSKUList.SellerSKU.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes ID options
     */
    public function resetSKUs(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#SellerSKUList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sets the ASIN(s) to be used in the next request
     * @param array|string $s array of ASINs or single ASIN (max: 20)
     * @return boolean false if failure
     */
    public function setASINs($s){
        if (is_string($s)){
            $this->resetSKUs();
            $this->resetASINs();
            $this->options['ASINList.ASIN.1'] = $s;
        } else if (is_array($s)){
            $this->resetSKUs();
            $this->resetASINs();
            $i = 1;
            foreach ($s as $x){
                $this->options['ASINList.ASIN.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes ID options
     */
    public function resetASINs(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ASINList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the item condition filter for the next request
     * @param string $s 
     * @return boolean false if improper input
     */
    public function setConditionFilter($s){
        if (is_string($s)){
            $this->options['ItemCondition'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets whether or not the next Lowest Offer Listings request should exclude your own listings
     * @param string $s "true" or "false"
     * @return boolean false if improper input
     */
    public function setExcludeSelf($s = 'true'){
        if ($s == 'true' || $s == 'false'){
            $this->options['ExcludeMe'] = $s;
        } else {
            return false;
        }
    }
    
}
?>