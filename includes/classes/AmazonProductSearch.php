<?php
/**
 * Fetches a list of products from Amazon using a search query.
 * 
 * This Amazon Products Core object retrieves a list of products from Amazon
 * that match the given search query. In order to search, a query is required.
 * The search context (ex: Kitchen, MP3 Downloads) can be specified as an
 * optional parameter.
 */
class AmazonProductSearch extends AmazonProductsCore{
    
    
    /**
     * AmazonProductList fetches a list of products from Amazon that match a search query.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param string $q [optional] <p>The query string to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     */
    public function __construct($s, $q = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if($q){
            $this->setQuery($q);
        }
        
        $this->options['Action'] = 'ListMatchingProducts';
        
        $this->throttleTime = THROTTLE_TIME_PRODUCTMATCH;
        $this->throttleGroup = 'ListMatchingProducts';
    }
    
    /**
     * Sets the query to search for. (Required)
     * @param string $q <p>search query</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setQuery($q){
        if (is_string($q)){
            $this->options['Query'] = $q;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the query context ID. (Optional)
     * 
     * Setting this paramter tells Amazon to only return products from the given
     * context. If this parameter is not set, Amazon will return products from
     * any context.
     * @param string $q <p>See comment inside for list of valid values.</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
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
     * Fetches a list of products from Amazon that match the given query.
     * 
     * Submits a <i>ListMatchingProducts</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getProduct</i>.
     * In order to perform this action, a search query is required.
     * @return boolean <p><b>FALSE</b> if something goes wrong</p>
     */
    public function searchProducts(){
        if (!array_key_exists('Query',$this->options)){
            $this->log("Search Query must be set in order to search for a query!",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
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
    
}
?>