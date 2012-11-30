<?php

class AmazonProductSearch extends AmazonProductsCore{
    
    
    /**
     * AmazonProductList fetches a list of products from Amazon that match a search query
     * @param string $s store name as seen in config
     * @param string $q query string to search for
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $q = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        if($q){
            $this->options['Query'] = $q;
        }
        
        $this->options['Action'] = 'ListMatchingProducts';
        
        $this->throttleLimit = $throttleLimitProduct;
        $this->throttleTime = $throttleTimeProductMatch;
        $this->throttleGroup = 'ListMatchingProducts';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
        
    }
    
    /**
     * Sets the package number for the next request
     * @param string $q search query
     * @return boolean false if improper input
     */
    public function setQuery($q){
        if (is_string($q)){
            $this->options['Query'] = $q;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the query context ID for the next request
     * @param string $q see comment inside for list of valid values
     * @return boolean false if improper input
     */
    public function setContextId($q){
        if (is_string($q)){
            $this->options['QueryContextId'] = $q;
        } else {
            return false;
        }
        /**
         * Valid Query Context IDs (US):
         * All
         * Apparel
         * Appliances
         * ArtsAndCrafts
         * Automotive
         * Baby
         * Beauty
         * Books
         * Classical
         * DigitalMusic
         * DVD
         * Electronics
         * Grocery
         * HealthPersonalCare
         * HomeGarden
         * Industrial
         * Jewelry
         * KindleStore
         * Kitchen
         * Magazines
         * Miscellaneous
         * MobileApps
         * MP3Downloads
         * Music
         * MusicalInstruments
         * OfficeProducts
         * PCHardware
         * PetSupplies
         * Photo
         * Shoes
         * Software
         * SportingGoods
         * Tools
         * Toys
         * UnboxVideo
         * VHS
         * Video
         * VideoGames
         * Watches
         * Wireless
         * WirelessAccessories
         */
    }
    
    /**
     * Sends a request to Amazon for package tracking details
     * @return boolean false on failure
     */
    public function searchProducts(){
        if (!array_key_exists('Query',$this->options)){
            $this->log("Search Query must be set in order to search for a query!",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = 'ListMatchingProductsResult';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $this->parseXML($xml);
    }
    
}
?>