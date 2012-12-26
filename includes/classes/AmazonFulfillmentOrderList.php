<?php
/**
 * Fetches a list of fulfillment orders from Amazon.
 * 
 * This Amazon Outbound Core object can retrieve a list of
 * previously created fulfillment orders. While no parameters
 * are required, filters for start time and method are available.
 * This object can use tokens when retrieving the list.
 */
class AmazonFulfillmentOrderList extends AmazonOutboundCore implements Iterator{
    private $orderList;
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $i = 0;
    private $index = 0;
    
    /**
     * AmazonFulfillmentOrderList retrieves a list of fulfillment orders from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->options['Action'] = 'ListAllFulfillmentOrders';
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Sets the start time. (Optional)
     * 
     * This method sets the earliest time frame to be sent in the next request.
     * If this parameter is set, Amazon will only return fulfillment orders that
     * were last updated after the time set. If this parameter is not set, Amazon
     * will only return orders that were updated in the past 36 hours.
     * The parameter is passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * @param string $s <p>Time string.</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setStartTime($s){
        if (is_string($s)){
            $time = $this->genTime($s);
            $this->options['QueryStartDateTime'] = $time;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the fulfillment method filter. (Optional)
     * 
     * This method sets the Fulfillment Method to be sent in the next request.
     * If this parameter is set, Amazon will return fulfillment orders using the given method.
     * If this parameter is not set, Amazon will only return fulfillment orders
     * with a <i>Consumer</i> method.
     * Here is a quick description of the methods:
     * <ul>
     * <li><b>Consumer</b> - customer order</li>
     * <li><b>Removal</b> - inventory will be returned to the given address</li>
     * </ul>
     * @param string $s <p>"Consumer" or "Removal"</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setMethodFilter($s){
        if ($s == 'Consumer' || $s == 'Removal'){
            $this->options['FulfillmentMethod'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the object should automatically use tokens if it receives one.
     * 
     * If this option is set to <b>TRUE</b>, the object will automatically perform
     * the necessary operations to retrieve the rest of the list using tokens. If
     * this option is off, the object will only ever retrieve the first section of
     * the list.
     * @param boolean $b [optional] <p>Defaults to <b>TRUE</b></p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Fetches the fulfillment order list from Amazon.
     * 
     * Submits a <i>ListAllFulfillmentOrders</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getOrder</i>.
     * This operation can potentially involve tokens.
     * @return boolean <p><b>FALSE</b> if something goes wrong</p>
     */
    public function fetchOrderList(){
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareToken();
        
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = $this->options['Action'].'Result';
        
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
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        
        $this->parseXML($xml->FulfillmentOrders);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Orders");
            $this->fetchOrderList(false);
        }
        
    }
    
    /**
     * Sets up options for using tokens.
     * 
     * This changes key options for switching between simply fetching a list and
     * fetching the rest of a list using a token. Please note: because the
     * operation for using tokens does not use any other parameters, all other
     * parameters will be removed.
     */
    protected function prepareToken(){
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListAllFulfillmentOrdersByNextToken';
            unset($this->options['QueryStartDateTime']);
            unset($this->options['FulfillmentMethod']);
        } else {
            $this->options['Action'] = 'ListAllFulfillmentOrders';
            unset($this->options['NextToken']);
            $this->orderList = array();
            $this->index = 0;
        }
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
            $i = $this->index;
            $this->orderList[$i]['SellerFulfillmentOrderId'] = (string)$x->SellerFulfillmentOrderId;
            $this->orderList[$i]['DisplayableOrderId'] = (string)$x->DisplayableOrderId;
            $this->orderList[$i]['DisplayableOrderDateTime'] = (string)$x->DisplayableOrderDateTime;
            $this->orderList[$i]['DisplayableOrderComment'] = (string)$x->DisplayableOrderComment;
            $this->orderList[$i]['ShippingSpeedCategory'] = (string)$x->ShippingSpeedCategory;
            if (isset($x->DestinationAddress)){
                $this->orderList[$i]['DestinationAddress']['Name'] = (string)$x->DestinationAddress->Name;
                $this->orderList[$i]['DestinationAddress']['Line1'] = (string)$x->DestinationAddress->Line1;
                if (isset($x->DestinationAddress->Line2)){
                    $this->orderList[$i]['DestinationAddress']['Line2'] = (string)$x->DestinationAddress->Line2;
                }
                if (isset($x->DestinationAddress->Line3)){
                    $this->orderList[$i]['DestinationAddress']['Line3'] = (string)$x->DestinationAddress->Line3;
                }
                if (isset($x->DestinationAddress->DistrictOrCounty)){
                    $this->orderList[$i]['DestinationAddress']['DistrictOrCounty'] = (string)$x->DestinationAddress->DistrictOrCounty;
                }
                $this->orderList[$i]['DestinationAddress']['City'] = (string)$x->DestinationAddress->City;
                $this->orderList[$i]['DestinationAddress']['StateOrProvinceCode'] = (string)$x->DestinationAddress->StateOrProvinceCode;
                $this->orderList[$i]['DestinationAddress']['CountryCode'] = (string)$x->DestinationAddress->CountryCode;
                if (isset($x->DestinationAddress->PostalCode)){
                    $this->orderList[$i]['DestinationAddress']['PostalCode'] = (string)$x->DestinationAddress->PostalCode;
                }
                if (isset($x->DestinationAddress->PhoneNumber)){
                    $this->orderList[$i]['DestinationAddress']['PhoneNumber'] = (string)$x->DestinationAddress->PhoneNumber;
                }
            }
            if (isset($x->FulfillmentPolicy)){
                $this->orderList[$i]['FulfillmentPolicy'] = (string)$x->FulfillmentPolicy;
            }
            if (isset($x->FulfillmentMethod)){
                $this->orderList[$i]['FulfillmentPolicy'] = (string)$x->FulfillmentMethod;
            }
            $this->orderList[$i]['ReceivedDateTime'] = (string)$x->ReceivedDateTime;
            $this->orderList[$i]['FulfillmentOrderStatus'] = (string)$x->FulfillmentOrderStatus;
            $this->orderList[$i]['StatusUpdatedDateTime'] = (string)$x->StatusUpdatedDateTime;
            if (isset($x->NotificationEmailList)){
                $j = 0;
                foreach($x->NotificationEmailList->children() as $y){
                    $this->orderList[$i]['NotificationEmailList'][$j++] = (string)$y;
                }
            }
            $this->index++;
        }
    }
    
    /**
     * Creates a list of full order objects from the list. (Warning: could take a while.)
     * 
     * This method automatically creates an array of <i>AmazonFulfillmentOrder</i> objects
     * and fetches all of their full information from Amazon. Because of throttling, this
     * could take a while if the list has more than a few orders.
     * @return array|boolean <p>array of <i>AmazonFulfillmentOrder</i> objects, or <b>FALSE</b> if list not filled yet</p>
     */
    public function getFullList(){
        if (!isset($this->orderList)){
            return false;
        }
        $list = array();
        $i = 0;
        foreach($this->orderList as $x){
            $list[$i] = new AmazonFulfillmentOrder($this->storeName,$x['SellerFulfillmentOrderId'],$this->mockMode,$this->mockFiles);
            $list[$i]->mockIndex = $this->mockIndex;
            $list[$i]->fetchOrder();
            $i++;
        }
        return $list;
    }
    
    /**
     * Returns the specified fulfillment order, or all of them.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single fulfillment order will have the following fields:
     * <ul>
     * <li><b>SellerFulfillmentOrderId</b> - the ID for the order</li>
     * <li><b>DisplayableOrderId</b> - your ID for the order</li>
     * <li><b>DisplayableOrderDateTime</b> - the time the order was created, in ISO 8601 date format</li>
     * <li><b>ShippingSpeedCategory</b> - shipping speed for the order</li>
     * <li><b>DestinationAddress</b> - address array, see <i>AmazonFulfillmentOrderCreator</i> for more details</li>
     * <li><b>FulfillmentPolicy</b> (optional) - "FillOrKill", "FillAll", or "FillAllAvailable"</li>
     * <li><b>FulfillmentMethod</b> (optional) - "Consumer" or "Removal"</li>
     * <li><b>ReceivedDateTime</b> - the time the order was received by the Amazon fulfillment center, in ISO 8601 date format</li>
     * <li><b>FulfillmentOrderStatus</b> - the status of the order</li>
     * <li><b>StatusUpdatedDateTime</b> - the time the status was last updated, in ISO 8601 date format</li>
     * <li><b>NotificationEmailList</b> (optional) - list of email addresses</li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.</p>
     * @return array|boolean <p>array, multi-dimensional array, or <b>FALSE</b> if list not filled yet</p>
     */
    public function getOrder($i = null){
        if (!isset($this->orderList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->orderList[$i];
        } else {
            return $this->orderList;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->orderList[$this->i]; 
    }

    /**
     * Iterator function
     */
    public function rewind(){
        $this->i = 0;
    }

    /**
     * Iterator function
     * @return type
     */
    public function key() {
        return $this->i;
    }

    /**
     * Iterator function
     */
    public function next() {
        $this->i++;
    }

    /**
     * Iterator function
     * @return type
     */
    public function valid() {
        return isset($this->orderList[$this->i]);
    }
    
}
?>
