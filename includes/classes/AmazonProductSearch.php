<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param string $q [optional] <p>The query string to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $q = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        
        if($q){
            $this->setQuery($q);
        }
        
        $this->options['Action'] = 'ListMatchingProducts';
        
        if(isset($THROTTLE_TIME_PRODUCTMATCH)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTMATCH;
        }
        $this->throttleGroup = 'ListMatchingProducts';
    }
    
    /**
     * Sets the query to search for. (Required)
     * @param string $q <p>search query</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Setting this parameter tells Amazon to only return products from the given
     * context. If this parameter is not set, Amazon will return products from
     * any context.
     * @param string $q <p>See comment inside for list of valid values.</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * @return boolean <b>FALSE</b> if something goes wrong
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
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        $this->parseXML($xml);
    }
    
}
?>