<?php

class AmazonInboundShipmentItemList extends AmazonInboundCore implements Iterator{
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $itemList;
    private $index = 0;
    private $i = 0;
    
    /**
     * Fetches a list of shipments from Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array $m list of mock files to use
     */
    public function __construct($s, $id = null, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        if (!is_null($id)){
            $this->options['ShipmentId'] = $id;
        }
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Returns whether or not the Participation List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the Shipment List should automatically use tokens if it receives one.
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
     * Sets the Shipment List for the next request.
     * @param string $s Shipment ID
     * @return boolean false if invalid paramter
     */
    public function setShipmentId($s){
        if (is_string($s)){
            $this->options['ShipmentId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the time frame filter for the shipments fetched.
     * 
     * Sets the time frame for the orders fetched. If no times are specified, times default to the current time
     * @param dateTime $lower Date the order was created after, is passed through strtotime
     * @param dateTime $upper Date the order was created before, is passed through strtotime
     * @throws InvalidArgumentException
     */
    public function setTimeLimits($lower = null, $upper = null){
        try{
            if ($lower){
                $after = $this->genTime($lower);
            } else {
                $after = $this->genTime('- 2 min');
            }
            if ($upper){
                $before = $this->genTime($upper);
            } else {
                $before = $this->genTime();
            }
            $this->options['LastUpdatedAfter'] = $after;
            $this->options['LastUpdatedBefore'] = $before;
            
        } catch (Exception $e){
            throw new InvalidArgumentException('Parameters should be timestamps.');
        }
        
    }
    
    /**
     * removes the time frame filter
     */
    public function resetTimeLimits(){
        unset($this->options['LastUpdatedAfter']);
        unset($this->options['LastUpdatedBefore']);
    }
    
    /**
     * Fetches shipment items from Amazon using the pre-set parameters
     */
    public function fetchItems(){
        $this->options['Timestamp'] = $this->genTime();
        
        
        if (!array_key_exists('ShipmentId', $this->options)){
            $this->log("Shipment ID must be set before requesting items!",'Warning');
            return false;
        }
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListInboundShipmentItemsByNextToken';
        } else {
            unset($this->options['NextToken']);
            $this->options['Action'] = 'ListInboundShipmentItems';
            $this->index = 0;
            $this->itemList = array();
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

            $xml = simplexml_load_string($response['body'])->$path;
        }
            
        
        echo 'the lime must be drawn here';
        var_dump($xml);
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        foreach($xml->ItemData->children() as $x){
            $this->itemList[$this->index] = $this->parseXML($x);
            $this->index++;
        }
        
        myPrint($this->itemList);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more shipments for the list");
            $this->fetchItems();
        }
        
        
    }
    
    /**
     * Reads piece of XML to fill out a single shipment's info
     * @param SimpleXMLObject $xml
     * @return array
     */
    protected function parseXML($xml){
        $a = array();
        
        if (isset($xml->ShipmentId)){
            $a['ShipmentId'] = (string)$xml->ShipmentId;
        }
        $a['SellerSKU'] = (string)$xml->SellerSKU;
        if (isset($xml->FulfillmentNetworkSKU)){
            $a['FulfillmentNetworkSKU'] = (string)$xml->FulfillmentNetworkSKU;
        }
        $a['QuantityShipped'] = (string)$xml->QuantityShipped;
        if (isset($xml->QuantityReceived)){
            $a['QuantityReceived'] = (string)$xml->QuantityReceived;
        }
        if (isset($xml->QuantityInCase)){
            $a['QuantityInCase'] = (string)$xml->QuantityInCase;
        }
        
        return $a;
    }
    
    /**
     * Returns the Shipment ID for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getShipmentId($i = 0){
        if (is_numeric($i)){
            return $this->itemList[$i]['ShipmentId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Seller SKU for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getSellerSKU($i = 0){
        if (is_numeric($i)){
            return $this->itemList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Fulfillment Network SKU for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getFulfillmentNetworkSKU($i = 0){
        if (is_numeric($i)){
            return $this->itemList[$i]['FulfillmentNetworkSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity shipped for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getQuantityShipped($i = 0){
        if (is_numeric($i)){
            return $this->itemList[$i]['QuantityShipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity received for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getQuantityReceived($i = 0){
        if (is_numeric($i)){
            return $this->itemList[$i]['QuantityReceived'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity in cases for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getQuantityInCase($i = 0){
        if (is_numeric($i)){
            return $this->itemList[$i]['QuantityInCase'];
        } else {
            return false;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->itemList[$this->i]; 
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
        return isset($this->itemList[$this->i]);
    }
}
?>
