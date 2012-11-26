<?php

class AmazonShipmentList extends AmazonInboundCore implements Iterator{
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $shipmentList;
    private $index = 0;
    private $i = 0;
    
    /**
     * Fetches a list of shipments from Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
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
        } else {
            return false;
        }
    }
    
    /**
     * sets the status filter to be used in the next request
     * 
     * Sets the status filter to be used in the next request. Valid statuses:
     * "WORKING"
     * "SHIPPED"
     * "IN_TRANSIT"
     * "DELIVERED"
     * "CHECKED_IN"
     * "RECEIVING"
     * "CLOSED"
     * "CANCELLED"
     * "DELETED"
     * "ERROR"
     * @param array $s list of statuses, or single status string
     */
    public function setStatusFilter($s){
        if (is_string($s)){
            $this->resetStatusFilter();
            $this->options['ShipmentStatusList.member.1'] = $s;
        } else if (is_array($s)){
            $this->resetStatusFilter();
            $i = 1;
            foreach($s as $x){
                $this->options['ShipmentStatusList.member.'.$i++] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes the status filter
     */
    public function resetStatusFilter(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShipmentStatusList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sets the ID filter to be used in the next request
     * @param array $s list of shipment IDs, or single ID string
     */
    public function setIdFilter($s){
        if (is_string($s)){
            $this->resetIdFilter();
            $this->options['ShipmentIdList.member.1'] = $s;
        } else if (is_array($s)){
            $this->resetIdFilter();
            $i = 1;
            foreach($s as $x){
                $this->options['ShipmentIdList.member.'.$i++] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes the ID filter
     */
    public function resetIdFilter(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShipmentIdList#",$op)){
                unset($this->options[$op]);
            }
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
     * Fetches shipments from Amazon using the pre-set parameters
     */
    public function fetchShipments(){
        $this->options['Timestamp'] = $this->genTime();
        
        if (!array_key_exists('ShipmentStatusList.member.1', $this->options) && !array_key_exists('ShipmentIdList.member.1', $this->options)){
            $this->log("Either status filter or ID filter must be set before requesting a list!",'Warning');
            return false;
        }
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListInboundShipmentsByNextToken';
        } else {
            unset($this->options['NextToken']);
            $this->options['Action'] = 'ListInboundShipments';
            $this->index = 0;
            $this->shipmentList = array();
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
        
        foreach($xml->ShipmentData->children() as $x){
            $this->shipmentList[$this->index] = $this->parseXML($x);
            $this->index++;
        }
        
        myPrint($this->shipmentList);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more shipments for the list");
            $this->fetchShipments();
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
        if (isset($xml->ShipmentName)){
            $a['ShipmentName'] = (string)$xml->ShipmentName;
        }
        
        //Address
        $a['ShipFromAddress']['Name'] = (string)$xml->ShipFromAddress->Name;
        $a['ShipFromAddress']['AddressLine1'] = (string)$xml->ShipFromAddress->AddressLine1;
        if (isset($xml->ShipFromAddress->AddressLine2)){
            $a['ShipFromAddress']['AddressLine2'] = (string)$xml->ShipFromAddress->AddressLine2;
        } else {
            $a['ShipFromAddress']['AddressLine2'] = null;
        }
        $a['ShipFromAddress']['City'] = (string)$xml->ShipFromAddress->City;
        if (isset($xml->ShipFromAddress->DistrictOrCounty)){
            $a['ShipFromAddress']['DistrictOrCounty'] = (string)$xml->ShipFromAddress->DistrictOrCounty;
        } else {
            $a['ShipFromAddress']['DistrictOrCounty'] = null;
        }
        $a['ShipFromAddress']['StateOrProvidenceCode'] = (string)$xml->ShipFromAddress->StateOrProvidenceCode;
        $a['ShipFromAddress']['CountryCode'] = (string)$xml->ShipFromAddress->CountryCode;
        $a['ShipFromAddress']['PostalCode'] = (string)$xml->ShipFromAddress->PostalCode;
        
        if (isset($xml->DestinationFulfillmentCenterId)){
            $a['DestinationFulfillmentCenterId'] = (string)$xml->DestinationFulfillmentCenterId;
        }
        if (isset($xml->LabelPrepType)){
            $a['LabelPrepType'] = (string)$xml->LabelPrepType;
        }
        if (isset($xml->ShipmentStatus)){
            $a['ShipmentStatus'] = (string)$xml->ShipmentStatus;
        }
        
        $a['AreCasesRequired'] = (string)$xml->AreCasesRequired;
        
        return $a;
    }
    
    /**
     * returns array of item lists or a single item list
     * @param boolean $token whether or not to automatically use tokens when fetching items
     * @param integer $i index
     * @return array AmazonOrderItemList or array of AmazonOrderItemLists
     */
    public function fetchItems($token = false, $i = null){
        if ($i == null){
            $a = array();
            $n = 0;
            foreach($this->shipmentList as $x){
                $a[$n] = new AmazonShipmentItemList($this->storeName,$x['ShipmentId'],$this->mockMode,$this->mockFiles);
                $a[$n]->setUseToken($token);
                $a[$n]->fetchItems();
            }
            return $a;
        } else if (is_numeric($i)) {
            $temp = new AmazonShipmentItemList($this->storeName,$this->shipmentList[$i]['ShipmentId'],$this->mockMode,$this->mockFiles);
            $temp->setUseToken($token);
            $temp->fetchItems();
            return $temp;
        }
    }
    
    /**
     * Returns the Shipment ID for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getShipmentId($i = 0){
        if (is_numeric($i)){
            return $this->shipmentList[$i]['ShipmentId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Shipment Name for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentId, or False if Non-numeric index
     */
    public function getShipmentName($i = 0){
        if (is_numeric($i)){
            return $this->shipmentList[$i]['ShipmentName'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the entire shipping address for the specified entry
     * @param int $i index, defaults to 0
     * @return array Address, or False if Non-numeric index
     */
    public function getAddress($i = 0){
        if (is_numeric($i)){
            return $this->shipmentList[$i]['ShipFromAddress'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Destination Fulfillment Center ID for the specified entry
     * @param int $i index, defaults to 0
     * @return string DestinationFulfillmentCenterId, or False if Non-numeric index
     */
    public function getDestinationFulfillmentCenterId($i = 0){
        if (is_numeric($i)){
            return $this->shipmentList[$i]['DestinationFulfillmentCenterId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Label Prep Type for the specified entry
     * @param int $i index, defaults to 0
     * @return string LabelPrepType, or False if Non-numeric index
     */
    public function getLabelPrepType($i = 0){
        if (is_numeric($i)){
            return $this->shipmentList[$i]['LabelPrepType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Shipment Status for the specified entry
     * @param int $i index, defaults to 0
     * @return string ShipmentStatus, or False if Non-numeric index
     */
    public function getShipmentStatus($i = 0){
        if (is_numeric($i)){
            return $this->shipmentList[$i]['ShipmentStatus'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns whether or not cases are required for the specified entry
     * @param int $i index, defaults to 0
     * @return string "true" or "false", or false if Non-numeric index
     */
    public function getCasesRequired($i = 0){
        if (is_numeric($i)){
            return $this->shipmentList[$i]['AreCasesRequired'];
        } else {
            return false;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->shipmentList[$this->i]; 
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
        return isset($this->shipmentList[$this->i]);
    }
}
?>
