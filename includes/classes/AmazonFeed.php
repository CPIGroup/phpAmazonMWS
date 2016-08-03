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
 * Submits feeds to Amazon.
 * 
 * This Amazon Feeds Core object can submit feeds to Amazon.
 * In order to submit a feed, the feed's contents (as direct input or from a file)
 * and feed type must be set. Once the feed has been submitted,
 * the response from Amazon can be viewed with <i>getResponse</i>.
 */
class AmazonFeed extends AmazonFeedsCore{
    protected $response;
    protected $feedContent;
    protected $feedMD5;
    
    /**
     * AmazonFeed submits a Feed to Amazon.
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
        include($this->env);
        
        $this->options['Action'] = 'SubmitFeed';
        
        if(isset($THROTTLE_LIMIT_FEEDSUBMIT)) {
            $this->throttleLimit = $THROTTLE_LIMIT_FEEDSUBMIT;
        }
        if(isset($THROTTLE_TIME_FEEDSUBMIT)) {
            $this->throttleTime = $THROTTLE_TIME_FEEDSUBMIT;
        }
        $this->throttleGroup = 'SubmitFeed';
    }
    
    /**
     * Sets the Feed Content. (Required)
     * 
     * Thie method sets the feed's contents from direct input.
     * This parameter is required in order to submit a feed to Amazon.
     * @param string $s <p>The contents to put in the file.</p>
     * It can be relative or absolute.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setFeedContent($s){
        if (is_string($s) && $s){
            $this->feedContent=$s;
            $this->feedMD5 = base64_encode(md5($this->feedContent,true));
        } else {
            return false;
        }
    }
    
    /**
     * Sets the Feed Content. (Required)
     * 
     * This method loads the contents of a file to send as the feed. This
     * parameter is required in order to submit a feed to Amazon.
     * @param string $path <p>The path to a file you want to use.
     * It can be relative or absolute.</p>
     */
    public function loadFeedFile($path){
        if (file_exists($path)){
            if (strpos($path, '/') == 0){
                $this->feedContent = file_get_contents($path);
            } else {
                $url = __DIR__.'/../../'.$path; //todo: change to current install dir
                $this->feedContent = file_get_contents($url);
            }
            $this->feedMD5 = base64_encode(md5($this->feedContent,true));
        }
    }
    
    /**
     * Sets the Feed Type. (Required)
     * 
     * This method sets the Feed Type to be sent in the next request. This tells
     * Amazon how the Feed should be processsed.
     * This parameter is required in order to submit a feed to Amazon.
     * @param string $s <p>A value from the list of valid Feed Types.
     * See the comment inside the function for the complete list.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setFeedType($s){
        if (is_string($s) && $s){
            $this->options['FeedType'] = $s;
        } else {
            return false;
        }
        /*
         * List of valid Feed Types:
         * Product & Inventory Feeds (XML):
         *      Product Feed ~ _POST_PRODUCT_DATA_
         *      Inventory Feed ~ _POST_INVENTORY_AVAILABILITY_DATA_
         *      Overrides Feed ~ _POST_PRODUCT_OVERRIDES_DATA_
         *      Pricing Feed ~ _POST_PRODUCT_PRICING_DATA_
         *      Product Images Feed ~ _POST_PRODUCT_IMAGE_DATA_
         *      Relationships Feed ~ _POST_PRODUCT_RELATIONSHIP_DATA_
         *      ACES 3.0 Data (Automotive Part Finder) Feed ~ _POST_STD_ACES_DATA_
         * Product & Inventory Feeds (Tab Delimited):
         *      Flat File Inventory Loader Feed ~ _POST_FLAT_FILE_INVLOADER_DATA_
         *      Flat File Listings Feed ~ _POST_FLAT_FILE_LISTINGS_DATA_
         *      Flat File Book Loader File ~ _POST_FLAT_FILE_BOOKLOADER_DATA_
         *      Flat File Music Loader File ~ _POST_FLAT_FILE_CONVERGENCE_LISTINGS_DATA_
         *      Flat File Video Loader File ~ _POST_FLAT_FILE_LISTINGS_DATA_
         *      Flat File Price and Quantity Update File ~ _POST_FLAT_FILE_PRICEANDQUANTITYONLY_UPDATE_DATA_
         * Product & Inventory Feeds (Other):
         *      UIEE Inventory File ~ _POST_UIEE_BOOKLOADER_DATA_
         * Order Feeds (XML):
         *      Order Acknowledgement Feed ~ _POST_ORDER_ACKNOWLEDGEMENT_DATA_
         *      Order Adjustment Feed ~ _POST_PAYMENT_ADJUSTMENT_DATA_
         *      Order Fulfillment Feed ~ _POST_ORDER_FULFILLMENT_DATA_
         *      Invoice Confirmation Feed ~ _POST_INVOICE_CONFIRMATION_DATA_
         * Order Feeds (Tab Delimited):
         *      Flat File Order Acknowledgement Feed ~ _POST_FLAT_FILE_ORDER_ACKNOWLEDGEMENT_DATA_
         *      Flat File Order Adjustment Feed ~ _POST_FLAT_FILE_PAYMENT_ADJUSTMENT_DATA_
         *      Flat File Order Fulfillment Feed ~ _POST_FLAT_FILE_FULFILLMENT_DATA_
         *      Flat File Invoice Confirmation Feed ~ _POST_FLAT_FILE_INVOICE_CONFIRMATION_DATA_
         * Fulfillment By Amazon Feeds (XML):
         *      FBA Fulfillment Order Feed ~ _POST_FULFILLMENT_ORDER_REQUEST_DATA_
         *      FBA Fulfillment Order Cancellation Request ~ _POST_FULFILLMENT_ORDER_CANCELLATION_REQUEST_DATA_
         *      FBA Inbound Shipment Carton Information Feed ~ _POST_FBA_INBOUND_CARTON_CONTENTS_
         * Fulfillment By Amazon Feeds (Tab Delimited):
         *      Flat File FBA Fulfillment Order Feed ~ _POST_FLAT_FILE_FULFILLMENT_ORDER_REQUEST_DATA_
         *      Flat File FBA Fulfillment Order Cancellation Feed ~ _POST_FLAT_FILE_FULFILLMENT_ORDER_CANCELLATION_REQUEST_DATA_
         *      Flat File FBA Create Inbound Shipment Plan Feed ~ _POST_FLAT_FILE_FBA_CREATE_INBOUND_PLAN_
         *      Flat File FBA Update Inbound Shipment Plan Feed ~ _POST_FLAT_FILE_FBA_UPDATE_INBOUND_PLAN_
         *      Flat File FBA Create Removal Feed ~ _POST_FLAT_FILE_FBA_CREATE_REMOVAL_
         */
    }
    
    /**
     * Sets the request ID(s). (Optional)
     * 
     * This method sets the list of Marketplace IDs to be sent in the next request.
     * Setting this parameter tells Amazon to apply the Feed to more than one
     * Marketplace. These should be IDs for Marketplaces that you are registered
     * to sell in. If this is not set, Amazon will only use the first Marketplace
     * you are registered for.
     * @param array|string $s <p>A list of Marketplace IDs, or a single ID string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setMarketplaceIds($s){
        if ($s && is_string($s)){
            $this->resetMarketplaceIds();
            $this->options['MarketplaceIdList.Id.1'] = $s;
        } else if ($s && is_array($s)){
            $this->resetMarketplaceIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['MarketplaceIdList.Id.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Removes ID options.
     * 
     * Use this in case you change your mind and want to remove the Marketplace ID
     * parameters you previously set.
     */
    public function resetMarketplaceIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#MarketplaceIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Turns on or off Purge mode. (Optional)
     * 
     * 
     * <b>Warning! This parameter can only be used once every 24 hours!</b>
     * 
     * This method sets whether or not the tab delimited feed you provide should
     * completely replace old data. Use this parameter only in exceptional cases.
     * If this is not set, Amazon assumes it to be false.
     * @param boolean|string $s [optional] <p>The value "true" or "false", either as
     * a boolean or a string. It defaults to "true".</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setPurge($s = 'true'){
        if ($s == 'true' || ($s && is_bool($s))){
            $this->log("Caution! Purge mode set!",'Warning');
            $this->options['PurgeAndReplace'] = 'true';
            $this->throttleTime = 86400;
        } else if ($s == 'false' || (!$s && is_bool($s))){
            $this->log("Purge mode deactivated.");
            $this->options['PurgeAndReplace'] = 'false';
            include($this->env);
            if(isset($THROTTLE_TIME_FEEDSUBMIT)) {
                $this->throttleTime = $THROTTLE_TIME_FEEDSUBMIT;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Submits a feed to Amazon.
     * 
     * Submits a <i>SubmitFeed</i> request to Amazon. In order to do this, both
     * the feed's contents and feed type are required. The request will not be
     * sent if either of these are not set. Amazon will send a response back,
     * which can be retrieved using <i>getResponse</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function submitFeed(){
        if (!$this->feedContent){
            $this->log("Feed's contents must be set in order to submit it!",'Warning');
            return false;
        }
        if (!array_key_exists('FeedType',$this->options)){
            $this->log("Feed Type must be set in order to submit a feed!",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $headers = $this->genHeader();
            $response = $this->sendRequest("$url?$query",array('Header'=>$headers,'Post'=>$this->feedContent));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            if (isset($response['code']) && $response['code'] == '200'){
                $body = strstr($response['body'],'<');
                $xml = simplexml_load_string($body)->$path;
            } else {
                $this->log("Unexpected response: ".print_r($response,true),'Warning');
                $xml = simplexml_load_string($response['body'])->$path;
            }
            
            
        }
        
        $this->parseXML($xml->FeedSubmissionInfo);
        
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        
        $this->response = array();
        $this->response['FeedSubmissionId'] = (string)$xml->FeedSubmissionId;
        $this->response['FeedType'] = (string)$xml->FeedType;
        $this->response['SubmittedDate'] = (string)$xml->SubmittedDate;
        $this->response['FeedProcessingStatus'] = (string)$xml->FeedProcessingStatus;
        
        $this->log("Successfully submitted feed #".$this->response['FeedSubmissionId'].' ('.$this->response['FeedType'].')');
    }
    
    /**
     * Generates array for Header.
     * 
     * This method creates the Header array to use with cURL. It contains the Content MD5.
     * @return array
     */
    protected function genHeader(){
        $return[0] = "Content-MD5:".$this->feedMD5;
        return $return;
    }
    
    /**
     * Returns the response data in array.
     * 
     * It will contain the following fields:
     * <ul>
     * <li><b>FeedSubmissionId</b> - Unique ID for the feed submission</li>
     * <li><b>FeedType</b> - Same as the feed type you gave</li>
     * <li><b>SubmittedDate</b> - The timestamp for when the Feed was received</li>
     * <li><b>FeedProcessingStatus</b> - The status of the feed, likely "_SUBMITTED_"</li>
     * </ul>
     * @return array|boolean associative array, or <b>FALSE</b> if no response is found
     */
    public function getResponse(){
        if (isset($this->response)){
            return $this->response;
        } else {
            return false;
        }
    }
    
    
    
}
?>