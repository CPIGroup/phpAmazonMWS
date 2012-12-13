<?php

class AmazonFeed extends AmazonFeedsCore{
    private $response;
    private $feedContent;
    private $feedMD5;
    
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
     * @param string $s contents to put in file
     * @param string $override path to temporary file, from main plugin directory
     */
    public function setFeedContent($s, $override = null){
        if (is_string($s) && $s){
            if ($override && file_exists($override) && is_writable($override)){
                if (strpos($override, '/') == 0){
                    file_put_contents($override, $s);
                } else {
                    file_put_contents('../../'.$override, $s);
                }
                $this->loadFeedFile($override);
            } else {
                file_put_contents('../../temp.xml', $s);
                $this->loadFeedFile('temp.xml');
            }
        } else {
            return false;
        }
    }
    
    /**
     * sets the feed content
     * @param string $url file path
     */
    public function loadFeedFile($path){
        if (file_exists($path)){
            if (strpos($path, '/') == 0){
                $this->feedContent = $path;
            } else {
                $url = '/var/www/athena/plugins/newAmazon/'.$path; //todo: change to current install dir
                $this->feedContent = $url;
            }
            $this->feedMD5 = base64_encode(md5(file_get_contents($this->feedContent),true));
        }
    }
    
    /**
     * set the feed type to be used in the next request
     * @param string $s value from specific list, see comment inside
     */
    public function setFeedType($s){
        if (is_string($s) && $s){
            $this->options['FeedType'] = $s;
        } else {
            return false;
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
         *      FBA Shipment Injection ~ _POST_FULFILLMENT_ORDER_CANCELLATION_
         *      Cancellation Feed ~ _REQUEST_DATA_
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
     * @param boolean|string $s boolean, or "true" or "false"
     * @return boolean false if improper input
     */
    public function setPurge($s = 'true'){
        if ($s == 'true' || ($s && is_bool($s))){
            $this->log("Caution! Purge mode set!",'Warning');
            $this->options['PurgeAndReplace'] = 'true';
            $this->throttleTime = 86400;
        } else if ($s == 'false' || (!$s && is_bool($s))){
            $this->log("Purge mode deactivated.");
            $this->options['PurgeAndReplace'] = 'false';
            if (file_exists($this->config)){
                include($this->config);
                $this->throttleTime = $throttleTimeFeedSubmit;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Submits a feed to Amazon
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
        
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = $this->options['Action'].'Result';
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $headers = $this->genHeader();
            $post = $this->genPost();
            $response = fetchURL("$url?$query",array('Header'=>$headers,'Post'=>$post));
            $this->logRequest();
            
            myPrint($response);
            $this->checkResponse($response);
            
            //getting Response 100?
            if ($response['head'] == 'HTTP/1.1 100 Continue'){
                $body = strstr($response['body'],'<');
                var_dump($body);
                $xml = simplexml_load_string($body)->$path;
            } else {
                $xml = simplexml_load_string($response['body'])->$path;
            }
            
            
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
        
        $this->log("Successfully submitted feed #".$this->response['FeedSubmissionId'].' ('.$this->response['FeedType'].')');
    }
    
    /**
     * Generates array for Header
     * @return array
     */
    protected function genHeader(){
        $return[0] = "Content-MD5:".$this->feedMD5;
        return $return;
    }
    
    /**
     * Generates array for Post
     * @return array
     */
    protected function genPost(){
        $return['file'] = '@'.$this->feedContent;
        return $return;
    }
    
    /**
     * checks whether or not the response is OK, due to '100' response
     * @param array $r response array
     */
    protected function checkResponse($r){
        if (!is_array($r)){
            $this->log("No Response found",'Warning');
            return;
        }
        //for dealing with 100 response
        if (array_key_exists('error', $r) && $r['ok'] == 0){
            $this->log("Response Not OK! Error: ".$r['error'],'Urgent');
            return;
        } else {
            $this->log("Response OK!");
            return;
        }
    }
    
    /**
     * Returns the response data.
     * @return array
     */
    public function getResponse(){
        return $this->response;
    }
    
    
    
}
?>