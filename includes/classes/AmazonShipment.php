<?php

class AmazonShipment extends AmazonInboundCore{
    private $shipmentId;
    
    /**
     * Submits a shipment to Amazon or updates it.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->options['InboundShipmentHeader.ShipmentStatus'] = 'WORKING';
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Automatically fills in the necessary fields using a planner array to reduce redundant declarations
     * @param array $x plan array from InboundShipmentPlanner
     * @return boolean false on failure
     */
    public function usePlan($x){
        if (is_array($x)){
            $this->options['ShipmentId'] = $x['ShipmentId'];
            
            //inheriting address
            $this->setAddress($x['ShipToAddress']);
            
            $this->options['InboundShipmentHeader.ShipmentId'] = $x['ShipmentId'];
            $this->options['InboundShipmentHeader.DestinationFulfillmentCenterId'] = $x['DestinationFulfillmentCenterId'];
            $this->options['InboundShipmentHeader.LabelPrepType'] = $x['LabelPrepType'];
            
            $this->setItems($x['Items']);
            
        } else {
           $this->log("usePlan requires an array",'Warning');
           return false; 
        }
    }
    
    /**
     * sets the address to use in the next request
     * 
     * Set the address to use in the next request with an array with these keys:
     * 
     * 'Name' max: 50 char
     * 'AddressLine1' max: 180 char
     * 'AddressLine2' (optional) max: 60 char
     * 'City' max 30: char
     * 'DistrictOrCounty' (optional) max: 25 char
     * 'StateOrProvidenceCode' (recommended) 2 digits
     * 'CountryCode' 2 digits
     * 'PostalCode' (recommended) max: 30 char
     * @param array $a
     * @return boolean false on failure
     */
    public function setAddress($a){
        if (!$a || is_null($a) || is_string($a)){
            $this->log("Tried to set address to invalid values",'Warning');
            return false;
        }
        if (!array_key_exists('AddressLine1', $a)){
            $this->resetAddress();
            $this->log("Tried to set address with invalid array",'Warning');
            return false;
        }
        $this->resetAddress();
        $this->options['InboundShipmentHeader.ShipFromAddress.Name'] = $a['Name'];
        $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine1'] = $a['AddressLine1'];
        if (array_key_exists('AddressLine2', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2'] = $a['AddressLine2'];
        } else {
            $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2'] = null;
        }
        $this->options['InboundShipmentHeader.ShipFromAddress.City'] = $a['City'];
        if (array_key_exists('DistrictOrCounty', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty'] = $a['DistrictOrCounty'];
        } else {
            $this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty'] = null;
        }
        $this->options['InboundShipmentHeader.ShipFromAddress.StateOrProvinceCode'] = $a['StateOrProvinceCode'];
        $this->options['InboundShipmentHeader.ShipFromAddress.CountryCode'] = $a['CountryCode'];
        $this->options['InboundShipmentHeader.ShipFromAddress.PostalCode'] = $a['PostalCode'];
    }
    
    /**
     * resets the address options
     */
    protected function resetAddress(){
        unset($this->options['InboundShipmentHeader.ShipFromAddress.Name']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.AddressLine1']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.City']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.StateOrProvinceCode']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.CountryCode']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.PostalCode']);
    }
    
    /**
     * Sets the items to be included in the next request
     * 
     * Sets the items to be included in the next request, using this format:
     * Array of arrays, each with the following fields:
     * 'SellerSKU'
     * 'Quantity'
     * 'QuantityInCase' (optional)
     * @param array $a array of item arrays
     * @return boolean false if failure
     */
    public function setItems($a){
        if (!$a || is_null($a) || is_string($a)){
            $this->log("Tried to set Items to invalid values",'Warning');
            return false;
        }
        $this->resetItems();
        $caseflag = false;
        $i = 1;
        foreach ($a as $x){
            
            if (is_array($x) && array_key_exists('SellerSKU', $x) && array_key_exists('Quantity', $x)){
                $this->options['InboundShipmentItems.member.'.$i.'.SellerSKU'] = $x['SellerSKU'];
                $this->options['InboundShipmentItems.member.'.$i.'.QuantityShipped'] = $x['Quantity'];
                if (array_key_exists('QuantityInCase', $x)){
                    $this->options['InboundShipmentItems.member.'.$i.'.QuantityInCase'] = $x['QuantityInCase'];
                    $caseflag = true;
                }
                $i++;
            } else {
                $this->resetItems();
                $this->log("Tried to set Items with invalid array",'Warning');
                return false;
            }
        }
        $this->setCases($caseflag);
    }
    
    /**
     * removes item options
     */
    public function resetItems(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#InboundShipmentItems#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * set the shipment status to be used in the next request
     * @param string $s "WORKING", "SHIPPED", or "CANCELLED" (updating only)
     */
    public function setStatus($s){
        if (is_string($s) && $s){
            if ($s == 'WORKING' || $s == 'SHIPPED' || $s == 'CANCELLED'){
                $this->options['InboundShipmentHeader.ShipmentStatus'] = $s;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * set the shipment id to be used in the next request
     * @param string $s id
     */
    public function setShipmentId($s){
        if (is_string($s) && $s){
            $this->options['ShipmentId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Set whether or not cases are required.
     * @param boolean $b
     */
    protected function setCases($b = true){
        if ($b){
            $this->options['InboundShipmentHeader.AreCasesRequired'] = 'true';
        } else {
            $this->options['InboundShipmentHeader.AreCasesRequired'] = 'false';
        }
    }
    
    /**
     * Sends a request to Amazon to create an Inbound Shipment
     * @return boolean true on success, false on failure
     */
    public function createShipment(){
        if (!isset($this->options['ShipmentId'])){
            $this->log("Shipment ID must be set in order to create it",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentHeader.ShipFromAddress.Name',$this->options)){
            $this->log("Header must be set in order to make a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentItems.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to make a shipment",'Warning');
            return false;
        }
        $this->options['Action'] = 'CreateInboundShipment';
        
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
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        $this->shipmentId = (string)$xml->ShipmentId;
        
        if ($this->shipmentId){
            $this->log("Successfully created Shipment #".$this->shipmentId);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon to create an Inbound Shipment
     * @return boolean true on success, false on failure
     */
    public function updateShipment(){
        if (!isset($this->options['ShipmentId'])){
            $this->log("Shipment ID must be set in order to update it",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentHeader.ShipFromAddress.Name',$this->options)){
            $this->log("Header must be set in order to update a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentItems.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to update a shipment",'Warning');
            return false;
        }
        $this->options['Action'] = 'UpdateInboundShipment';
        
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
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        $this->shipmentId = (string)$xml->ShipmentId;
        
        if ($this->shipmentId){
            $this->log("Successfully updated Shipment #".$this->shipmentId);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * returns the shipment ID of the newly created/modified order
     * @return string|boolean false if shipment ID not yet retrieved
     */
    public function getShipmentId(){
        if (isset($this->shipmentId)){
            return $this->shipmentId;
        } else {
            return false;
        }
    }
    
}
?>
