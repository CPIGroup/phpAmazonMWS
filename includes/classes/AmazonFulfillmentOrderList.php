<?php

class AmazonFulfillmentOrderList extends AmazonOutboundCore implements Iterator{
    private $xmldata;
    private $orderList;
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $i = 0;
    private $index = 0;
    
    /**
     * Fetches a plan from Amazon. This is how you get a Shipment ID.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        $this->options['Action'] = 'ListAllFulfillmentOrders';
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Sets the start time for the next request
     * @param string $s
     * @return boolean false if improper input
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
     * Sets the fulfillment method filter for the next request
     * @param string $s "Consumer" or "Removal"
     * @return boolean false if improper input
     */
    public function setMethodFilter($s){
        if ($s == 'Consumer' || $s == 'Removal'){
            $this->options['FulfillmentMethod'] = s;
        } else {
            return false;
        }
    }
    
    /**
     * Returns whether or not the Order List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the OrderList should automatically use tokens if it receives one. This includes item tokens
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
            $this->tokenItemFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Fetches the fulfillment order list from Amazon, using a token if available
     */
    public function fetchOrderList(){
        $this->options['Timestamp'] = $this->genTime();
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
        
        
        $this->xmldata = $xml->FulfillmentOrders;
        $this->parseXML();
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Orders");
            $this->fetchOrderList(false);
        }
        
    }
    
    /**
     * converts the XML to arrays
     */
    protected function parseXML(){
        if (!$this->xmldata){
            return false;
        }
        foreach($this->xmldata->children() as $x){
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
     * @return AmazonFulfillmentOrder
     */
    public function getFullList(){
        $list = array();
        $i = 0;
        foreach($this->orderList as $x){
            $list[$i] = new AmazonFulfillmentOrder($this->storeName,$x['SellerFulfillmentOrderId'],$this->mockMode,$this->mockFiles);
            $list[$i]->fetchOrder();
            $i++;
        }
        return $list;
    }
    
    /**
     * Returns specified Order
     * @param int $i index, defaults to 0
     * @return array array of basic order information, or array of arrays
     */
    public function getOrder($i = 0){
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
