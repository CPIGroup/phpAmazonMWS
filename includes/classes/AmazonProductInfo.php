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
     * AmazonProductInfo fetches a list of info from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
    }
    
    /**
     * Sets the feed seller SKU(s). (Required*)
     * 
     * This method sets the list of seller SKUs to be sent in the next request.
     * Setting this parameter tells Amazon to only return inventory supplies that match
     * the IDs in the list. If this parameter is set, ASINs cannot be set.
     * @param array|string $s <p>A list of Seller SKUs, or a single SKU string. (max: 20)</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Resets the seller SKU options.
     * 
     * Since seller SKU is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetSKUs(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#SellerSKUList#",$op)){
                unset($this->options[$op]);
            }
        }
        //remove Category-specific name
        unset($this->options['SellerSKU']);
    }
    
    /**
     * Sets the ASIN(s). (Required*)
     * 
     * This method sets the list of ASINs to be sent in the next request.
     * Setting this parameter tells Amazon to only return inventory supplies that match
     * the IDs in the list. If this parameter is set, Seller SKUs cannot be set.
     * @param array|string $s <p>A list of ASINs, or a single ASIN string. (max: 20)</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Resets the ASIN options.
     * 
     * Since ASIN is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetASINs(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ASINList#",$op)){
                unset($this->options[$op]);
            }
        }
        //remove Category-specific name
        unset($this->options['ASIN']);
    }
    
    /**
     * Sets the item condition filter. (Optional)
     * 
     * This method sets the item condition filter to be sent in the next request.
     * Setting this parameter tells Amazon to only return products with conditions that match
     * the one given. If this parameter is not set, Amazon will return products with any condition.
     * @param string $s <p>Single condition string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setConditionFilter($s){
        if (is_string($s)){
            $this->options['ItemCondition'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the "ExcludeSelf" flag. (Optional)
     * 
     * Sets whether or not the next Lowest Offer Listings request should exclude your own listings.
     * @param string|boolean $s <p>"true" or "false", or boolean</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Fetches a list of competitive pricing on products from Amazon.
     * 
     * Submits a <i>GetCompetitivePricingForSKU</i>
     * or <i>GetCompetitivePricingForASIN</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getProduct</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchCompetitivePricing(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        
        $this->prepareCompetitive();
        
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
    
    /**
     * Sets up options for using <i>fetchCompetitivePricing</i>.
     * 
     * This changes key options for using <i>fetchCompetitivePricing</i>.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * ItemCondition and ExcludeMe.
     */
    protected function prepareCompetitive(){
        include($this->env);
        if(isset($THROTTLE_TIME_PRODUCTPRICE)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTPRICE;
        }
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
     * Fetches a list of lowest offers on products from Amazon.
     * 
     * Submits a <i>GetLowestOfferListingsForSKU</i>
     * or <i>GetLowestOfferListingsForASIN</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getProduct</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchLowestOffer(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        
        $this->prepareLowest();
        
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
    
    /**
     * Sets up options for using <i>fetchLowestOffer</i>.
     * 
     * This changes key options for using <i>fetchLowestOffer</i>.
     */
    protected function prepareLowest(){
        include($this->env);
        if(isset($THROTTLE_TIME_PRODUCTPRICE)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTPRICE;
        }
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
     * Fetches a list of lowest offers on products from Amazon.
     *
     * Submits a <i>GetLowestPricedOffersForSKU</i>
     * or <i>GetLowestPricedOffersForASIN</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getProduct</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchLowestPricedOffers(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }

        $this->prepareLowestPriced();

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

    /**
     * Sets up options for using <i>fetchLowestPricedOffers</i>.
     *
     * This changes key options for using <i>fetchLowestPricedOffers</i>.
     */
    protected function prepareLowestPriced(){
        include($this->env);
        if(isset($THROTTLE_TIME_PRODUCTPRICE)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTPRICE;
        }
        $this->throttleGroup = 'GetLowestPricedOfferListings';
        if (array_key_exists('SellerSKUList.SellerSKU.1',$this->options)){
            $this->options['Action'] = 'GetLowestPricedOffersForSKU';
            $this->resetASINs();
            $this->options['SellerSKU'] = $this->options['SellerSKUList.SellerSKU.1'];
        } else if (array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->options['Action'] = 'GetLowestPricedOffersForASIN';
            $this->resetSKUs();
            $this->options['ASIN'] = $this->options['ASINList.ASIN.1'];
        }
    }
    
    /**
     * Fetches a list of your prices on products from Amazon.
     * 
     * Submits a <i>GetMyPriceForSKU</i>
     * or <i>GetMyPriceForASIN</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getProduct</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchMyPrice(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        
        $this->prepareMyPrice();
        
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
    
    /**
     * Sets up options for using <i>fetchMyPrice</i>.
     * 
     * This changes key options for using <i>fetchMyPrice</i>.
     * Please note: because the operation does not use all of the parameters,
     * the ExcludeMe parameter will be removed.
     */
    protected function prepareMyPrice(){
        include($this->env);
        if(isset($THROTTLE_TIME_PRODUCTPRICE)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTPRICE;
        }
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
     * Fetches a list of categories for products from Amazon.
     * 
     * Submits a <i>GetProductCategoriesForSKU</i>
     * or <i>GetProductCategoriesForASIN</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getProduct</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchCategories(){
        if (!array_key_exists('SellerSKUList.SellerSKU.1',$this->options) && !array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->log("Product IDs must be set in order to look them up!",'Warning');
            return false;
        }
        
        $this->prepareCategories();
        
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
    
    /**
     * Sets up options for using <i>fetchCategories</i>.
     * 
     * This changes key options for using <i>fetchCategories</i>.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * ItemCondition and ExcludeMe.
     */
    protected function prepareCategories(){
        include($this->env);
        if(isset($THROTTLE_TIME_PRODUCTLIST)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTLIST;
        }
        $this->throttleGroup = 'GetProductCategories';
        unset($this->options['ExcludeMe']);
        unset($this->options['ItemCondition']);
        if (array_key_exists('SellerSKUList.SellerSKU.1',$this->options)){
            $this->options['Action'] = 'GetProductCategoriesForSKU';
            $this->resetASINs();
            $this->options['SellerSKU'] = $this->options['SellerSKUList.SellerSKU.1'];
        } else if (array_key_exists('ASINList.ASIN.1',$this->options)){
            $this->options['Action'] = 'GetProductCategoriesForASIN';
            $this->resetSKUs();
            $this->options['ASIN'] = $this->options['ASINList.ASIN.1'];
        }
    }
    
}
?>