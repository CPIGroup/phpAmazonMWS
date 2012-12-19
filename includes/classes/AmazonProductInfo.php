<?php
/**
 * Fetches various information about products from Amazon.
 * 
 * This Amazon Products Core object retrieves a list of various product info
 * using the given IDs. The information this object can retrieve includes
 * competitive pricing, lowest prices, your own price, and product categories.
 * At least one ID (SKU or ASIN) is required in order to fetch info. A couple of
 * optional parameters are also available for some of the functions.
 */
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
            throw new Exception('Config file does not exist!');
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
                $i++;
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
                $i++;
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
        if ($s == 'true' || (is_bool($s) && $s == true)){
            $this->options['ExcludeMe'] = 'true';
        } else if ($s == 'false' || (is_bool($s) && $s == false)){
            $this->options['ExcludeMe'] = 'false';
        } else {
            return false;
        }
    }
    
    /**
     * Fetches the competitive pricing list from Amazon
     */
    public function fetchCompetitivePricing(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareCompetitive();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        $this->parseXML($xml);
        
    }
    
    /**
     * Sets up stuff
     */
    protected function prepareCompetitive(){
        include($this->config);
        $this->throttleTime = $throttleTimeProductPrice;
        $this->throttleGroup = 'GetCompetitivePricing';
        unset($this->options['ExcludeMe']);
        unset($this->options['ItemCondition']);
        if (array_key_exists('SellerSKUList.SellerSKU.1',$this->options)){
            $this->options['Action'] = 'GetCompetitivePricingForSKU';
            $this->resetASINs();
        } else if (array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->options['Action'] = 'GetCompetitivePricingForASIN';
            $this->resetSKUs();
        }
    }
    
    /**
     * Fetches the competitive pricing list from Amazon
     */
    public function fetchLowestOffer(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareLowest();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        
        $this->parseXML($xml);
        
    }
    
    /**
     * Sets up stuff
     */
    protected function prepareLowest(){
        include($this->config);
        $this->throttleTime = $throttleTimeProductPrice;
        $this->throttleGroup = 'GetLowestOfferListings';
        if (array_key_exists('SellerSKUList.SellerSKU.1',$this->options)){
            $this->options['Action'] = 'GetLowestOfferListingsForSKU';
            $this->resetASINs();
        } else if (array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->options['Action'] = 'GetLowestOfferListingsForASIN';
            $this->resetSKUs();
        }
    }
    
    /**
     * Fetches the competitive pricing list from Amazon
     */
    public function fetchMyPrice(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareMyPrice();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        
        $this->parseXML($xml);
        
    }
    
    /**
     * Sets up stuff
     */
    protected function prepareMyPrice(){
        include($this->config);
        $this->throttleTime = $throttleTimeProductPrice;
        $this->throttleGroup = 'GetMyPrice';
        unset($this->options['ExcludeMe']);
        if (array_key_exists('SellerSKUList.SellerSKU.1',$this->options)){
            $this->options['Action'] = 'GetMyPriceForSKU';
            $this->resetASINs();
        } else if (array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->options['Action'] = 'GetMyPriceForASIN';
            $this->resetSKUs();
        }
    }
    
    /**
     * Fetches the competitive pricing list from Amazon
     */
    public function fetchCategories(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareCategories();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        $this->parseXML($xml);
        
    }
    
    /**
     * Sets up stuff
     */
    protected function prepareCategories(){
        include($this->config);
        $this->throttleTime = $throttleTimeProductList;
        $this->throttleGroup = 'GetProductCategories';
        unset($this->options['ExcludeMe']);
        unset($this->options['ItemCondition']);
        if (array_key_exists('SellerSKUList.SellerSKU.1',$this->options)){
            $this->options['Action'] = 'GetProductCategoriesForSKU';
            $this->resetASINs();
        } else if (array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->options['Action'] = 'GetProductCategoriesForASIN';
            $this->resetSKUs();
        }
    }
    
}
?>