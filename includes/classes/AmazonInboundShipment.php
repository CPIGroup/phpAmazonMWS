<?php

class AmazonInboundShipment extends AmazonInboundCore{
    
    
    /**
     * Fetches a plan from Amazon. This is how you get a Shipment ID.
     * @param string $s name of store as seen in config file
     * @param string $id optional ID to set
     * @param boolean $mock true to enable mock mode
     * @param array $m list of mock files to use
     */
    public function __construct($s, $id = null, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        
        if (isset($id) && is_numeric($id)){
            $this->options['ShipmentId'] = $id;
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
            $this->resetAddress();
            $this->resetItems();
            
            //inheriting address
            $this->options['InboundShipmentHeader.ShipFromAddress.Name'] = $x['ShipToAddress']['Name'];
            $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine1'] = $x['ShipToAddress']['AddressLine1'];
            if (array_key_exists('AddressLine2', $x['ShipToAddress'])){
            $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2'] = $x['ShipToAddress']['AddressLine2'];
            }
            $this->options['InboundShipmentHeader.ShipFromAddress.City'] = $x['ShipToAddress']['City'];
            if (array_key_exists('DistrictOrCounty', $x['ShipToAddress'])){
                $this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty'] = $x['ShipToAddress']['DistrictOrCounty'];
            }
            if (array_key_exists('StateOrProvidenceCode', $x['ShipToAddress'])){
                $this->options['InboundShipmentHeader.ShipFromAddress.StateOrProvidenceCode'] = $x['ShipToAddress']['StateOrProvidenceCode'];
            }
            $this->options['InboundShipmentHeader.ShipFromAddress.CountryCode'] = $x['ShipToAddress']['CountryCode'];
            if (array_key_exists('PostalCode', $x['ShipToAddress'])){
                $this->options['InboundShipmentHeader.ShipFromAddress.PostalCode'] = $x['ShipToAddress']['PostalCode'];
            }
            
            $this->options['InboundShipmentHeader.ShipmentId'] = $x['ShipmentId'];
            $this->options['InboundShipmentHeader.DestinationFulfillmentCenterId'] = $x['DestinationFulfillmentCenterId'];
            $this->options['InboundShipmentHeader.LabelPrepType'] = $x['LabelPrepType'];
            
            $i = 1;
            foreach($x['Items'] as $z){
                $this->options['InboundShipmentItems.member.'.$i.'.SellerSKU'] = $z['SellerSKU'];
                $this->options['InboundShipmentItems.member.'.$i.'.QuantityShipped'] = $z['Quantity'];
                $i++;
            }
            
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
        if (is_null($a) || is_string($a)){
            $this->log("Tried to set address to invalid values",'Warning');
            return false;
        }
        $this->resetAddress();
        $this->options['InboundShipmentHeader.ShipFromAddress.Name'] = $a['Name'];
        $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine1'] = $a['AddressLine1'];
        if (array_key_exists('AddressLine2', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2'] = $a['AddressLine2'];
        }
        $this->options['InboundShipmentHeader.ShipFromAddress.City'] = $a['City'];
        if (array_key_exists('DistrictOrCounty', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty'] = $a['DistrictOrCounty'];
        }
        if (array_key_exists('StateOrProvidenceCode', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.StateOrProvidenceCode'] = $a['StateOrProvidenceCode'];
        }
        $this->options['InboundShipmentHeader.ShipFromAddress.CountryCode'] = $a['CountryCode'];
        if (array_key_exists('PostalCode', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.PostalCode'] = $a['PostalCode'];
        }
        
        
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
        unset($this->options['InboundShipmentHeader.ShipFromAddress.StateOrProvidenceCode']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.CountryCode']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.PostalCode']);
    }
    
    /**
     * Sets the items to be included in the next request
     * 
     * Sets the items to be included in the next request, using this format:
     * Array of arrays, each with two fields:
     * 'SellerSKU'
     * 'Quantity'
     * @param array $a array of item arrays
     * @return boolean false if failure
     */
    public function setItems($a){
        if (is_null($a) || is_string($a)){
            $this->log("Tried to set Items to invalid values",'Warning');
            return false;
        }
        $this->resetItems();
        $i = 1;
        foreach ($a as $x){
            if (array_key_exists('SellerSKU', $x) && array_key_exists('Quantity', $x)){
                $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.SellerSKU'] = $x['SellerSKU'];
                $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.Quantity'] = $x['Quantity'];
                $i++;
            } else {
                $this->resetItems();
                $this->log("Tried to set Items with invalid array",'Warning');
                return false;
            }
        }
    }
    
    /**
     * removes item options
     */
    public function resetItems(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#InboundShipmentPlanRequestItems#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * 
     * @param string $s
     */
    public function setStatus($s){
        if (is_string($s) && $s){
            $this->options['InboundShipmentHeader.ShipmentStatus'] = $s;
        }
    }
    
    /**
     * Sends a request to Amazon to create an Inbound Shipment
     * 
     * TEST THIS BEFORE I MOVE ONTO UPDATING... WHAT HAPPENS IF IT FAILS?
     * @return boolean true on success, false on failure
     */
    public function createShipment(){
        if (!array_key_exists('InboundShipmentHeader.ShipmentName',$this->options)){
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
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->CreateInboundShipmentResult;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();

            $xml = simplexml_load_string($response['body'])->CreateInboundShipmentPlanResult->InboundShipmentPlans;
        }
        myPrint($xml);
        $verify = (string)$xml->ShipmentId;
        
        if ($verify != $this->options['InboundShipmentHeader.ShipmentId']){
            $this->log("Order ID mismatch! ".$this->options['InboundShipmentHeader.ShipmentId']." =/= $verify",'Warning');
            return false;
        } else {
            return true;
        }
    }
    
}
?>
