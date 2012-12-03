<?php

class AmazonFeed extends AmazonFeedsCore{
    private $response;
    
    /**
     * AmazonFeed object submits a Feed to Amazon
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
        
        $this->options['Action'] = 'SubmitFeed';
        
        $this->throttleLimit = $throttleLimitFeedSubmit;
        $this->throttleTime = $throttleTimeFeedSubmit;
        $this->throttleGroup = 'SubmitFeed';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
        
    }
    
    /**
     * sets the feed content
     * @param string $s ?????????????????
     */
    public function setFeedContent($s){
        if (is_string($s) && $s){
            
        }
    }
    
    /**
     * set the feed type to be used in the next request
     * @param string $s value from specific list, see comment inside
     */
    public function setFeedType($s){
        if (is_string($s) && $s){
            $this->options['ReportType'] = $s;
        }
        /*
         * List of valid Feed Types:
         * XML Feeds:
         *      Product Feed ~ _POST_PRODUCT_DATA_
         *      Relationships Feed ~ _POST_PRODUCT_RELATIONSHIP_DATA_
         *      Single Format Item Feed ~ _POST_ITEM_DATA_
         *      Shipping Override Feed ~ _POST_PRODUCT_OVERRIDES_DATA_
         *      Product Images Feed ~ _POST_PRODUCT_IMAGE_DATA_
         *      Pricing Feed ~ _POST_PRODUCT_PRICING_DATA_
         *      Inventory Feed ~ _POST_INVENTORY_AVAILABILITY_DATA_
         *      Order Acknowledgement Feed ~ _POST_ORDER_ACKNOWLEDGEMENT_DATA_
         *      Order Fulfillment Feed ~ _POST_ORDER_FULFILLMENT_DATA_
         *      FBA Shipment Injection Fulfillment Feed~  _POST_FULFILLMENT_ORDER_REQUEST_DATA_
         *      FBA Shipment Injection ~ _POST_FULFILLMENT_ORDER_CANCELLATION
         *      Cancellation Feed ~ _REQUEST_DATA 
         *      Order Adjustment Feed ~ _POST_PAYMENT_ADJUSTMENT_DATA_
         *      Invoice Confirmation Feed ~ _POST_INVOICE_CONFIRMATION_DATA_
         * Tab Delimited Feeds:
         *      Flat File Listings Feed ~ _POST_FLAT_FILE_LISTINGS_DATA_
         *      Flat File Order Acknowledgement Feed ~ _POST_FLAT_FILE_ORDER_ACKNOWLEDGEMENT_DATA_
         *      Flat File Order Fulfillment Feed ~ _POST_FLAT_FILE_FULFILLMENT_DATA_
         *      Flat File FBA Shipment Injection Fulfillment Feed ~ _POST_FLAT_FILE_FULFILLMENT_ORDER_REQUEST_DATA_
         *      Flat File FBA Shipment Injection Cancellation Feed ~ _POST_FLAT_FILE_FULFILLMENT_ORDER_CANCELLATION_REQUEST_DATA_
         *      FBA Flat File Create Inbound Shipment Feed ~ _POST_FLAT_FILE_FBA_CREATE_INBOUND_SHIPMENT_
         *      FBA Flat File Update Inbound Shipment Feed ~ _POST_FLAT_FILE_FBA_UPDATE_INBOUND_SHIPMENT_
         *      FBA Flat File Shipment Notification Feed ~ _POST_FLAT_FILE_FBA_SHIPMENT_NOTIFICATION_FEED_
         *      Flat File Order Adjustment Feed ~ _POST_FLAT_FILE_PAYMENT_ADJUSTMENT_DATA_
         *      Flat File Invoice Confirmation Feed ~ _POST_FLAT_FILE_INVOICE_CONFIRMATION_DATA_
         *      Flat File Inventory Loader Feed ~ _POST_FLAT_FILE_INVLOADER_DATA_
         *      Flat File Music Loader File ~ _POST_FLAT_FILE_CONVERGENCE_LISTINGS_DATA_
         *      Flat File Book Loader File ~ _POST_FLAT_FILE_BOOKLOADER_DATA_
         *      Flat File Video Loader File ~ _POST_FLAT_FILE_LISTINGS_DATA_
         *      Flat File Price and Quantity Update File ~ _POST_FLAT_FILE_PRICEANDQUANTITYONLY_UPDATE_DATA_
         *      Product Ads Flat File Feed ~ _POST_FLAT_FILE_SHOPZILLA_DATA_
         * Universal Information Exchange Environment (UIEE) Feeds:
         *      UIEE Inventory File ~ _POST_UIEE_BOOKLOADER_DATA_
         */
    }
    
    /**
     * sets the request ID(s) to be used in the next request
     * @param array|string $s array of Report Request IDs or single ID (max: 5)
     * @return boolean false if failure
     */
    public function setMarketplaceIds($s){
        if (is_string($s)){
            $this->resetMarketplaceIds();
            $this->options['MarketplaceIdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetMarketplaceIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['MarketplaceIdList.Id.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes ID options
     */
    public function resetMarketplaceIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#MarketplaceIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets whether or not the tab delimited feed should completely replace old data
     * 
     * Warning! This has a 24 hour throttling time! Use this parameter only in exceptional cases.
     * @param string $s "true" or "false"
     * @return boolean false if improper input
     */
    public function setPurge($s = 'true'){
        if ($s == 'true' || $s == 'false'){
            $this->options['PurgeAndReplace'] = $s;
            $this->throttleTime = 86400;
        } else {
            return false;
        }
    }
    
    /**
     * Submits a feed to Amazon??????????????????
     */
    public function submitFeed(){
        if (!array_key_exists('FeedType',$this->options)){
            $this->log("Report Type must be set in order to request a report!",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = 'SubmitFeedResult';
        
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
        
        $this->parseXML($xml->FeedSubmissionInfo);
        
    }
    
    /**
     * loads XML response into array
     * @param SimpleXMLObject $xml XML from response
     * @return boolean false on failure
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
        
    }
    
}
?>