<?php

class AmazonInboundShipmentPlanner extends AmazonInboundCore implements Iterator{
    private $xmldata;
    private $planList;
    private $i;
    
    /**
     * Fetches a plan from Amazon. This is how you get a Shipment ID.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        $this->options['Action'] = 'CreateInboundShipmentPlan';
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
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
        $this->options['ShipFromAddress.Name'] = $a['Name'];
        $this->options['ShipFromAddress.AddressLine1'] = $a['AddressLine1'];
        if (array_key_exists('AddressLine2', $a)){
            $this->options['ShipFromAddress.AddressLine2'] = $a['AddressLine2'];
        }
        $this->options['ShipFromAddress.City'] = $a['City'];
        if (array_key_exists('DistrictOrCounty', $a)){
            $this->options['ShipFromAddress.DistrictOrCounty'] = $a['DistrictOrCounty'];
        }
        if (array_key_exists('StateOrProvidenceCode', $a)){
            $this->options['ShipFromAddress.StateOrProvidenceCode'] = $a['StateOrProvidenceCode'];
        }
        $this->options['ShipFromAddress.CountryCode'] = $a['CountryCode'];
        if (array_key_exists('PostalCode', $a)){
            $this->options['ShipFromAddress.PostalCode'] = $a['PostalCode'];
        }
        
        
    }
    
    /**
     * resets the address options
     */
    protected function resetAddress(){
        unset($this->options['ShipFromAddress.Name']);
        unset($this->options['ShipFromAddress.AddressLine1']);
        unset($this->options['ShipFromAddress.AddressLine2']);
        unset($this->options['ShipFromAddress.City']);
        unset($this->options['ShipFromAddress.DistrictOrCounty']);
        unset($this->options['ShipFromAddress.StateOrProvidenceCode']);
        unset($this->options['ShipFromAddress.CountryCode']);
        unset($this->options['ShipFromAddress.PostalCode']);
    }
    
    /**
     * Sets the labeling preference, not required, default setting is SELLER_LABEL
     * @param string $s "SELLER_LABEL", "AMAZON_LABEL_ONLY", "AMAZON_LABEL_PREFERRED"
     */
    public function setLabelPreference($s){
        if (is_string($s)){
            $this->options['LabelPrepPreference'] = $s;
        }
    }
    
    /**
     * Sets the items to be included in the next request
     * 
     * Sets the items to be included in the next request, using this format:
     * Array of arrays, each with two fields:
     * 'SellerSKU'
     * 'Quantity'
     * 'ASIN' (optional)
     * 'QuantityInCase' (optional)
     * 'Condition' (optjonal)(Possible values:
     *      "NewItem"
     *      "NewWithWarranty"
     *      "NewOEM"
     *      "NewOpenBox"
     *      "UsedLikeNew"
     *      "UsedVeryGood"
     *      "UsedGood"
     *      "UsedAcceptable"
     *      "UsedPoor"
     *      "UsedRefurbished"
     *      "CollectibleLikeNew"
     *      "CollectibleVeryGood"
     *      "CollectibleGood"
     *      "CollectibleAcceptable"
     *      "CollectiblePoor"
     *      "RefurbishedWithWarranty"
     *      "Refurbished"
     *      "Club")
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
                if (array_key_exists('QuantityInCase', $x)){
                    $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.QuantityInCase'] = $x['QuantityInCase'];
                }
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
    
    public function fetchPlan(){
        if (!array_key_exists('ShipFromAddress.Name',$this->options)){
            $this->log("Address must be set in order to make a plan",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentPlanRequestItems.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to make a plan",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->CreateInboundShipmentPlanResult->InboundShipmentPlans;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();

            $xml = simplexml_load_string($response['body'])->CreateInboundShipmentPlanResult->InboundShipmentPlans;
        }
//        myPrint($xml);
        $this->xmldata = $xml;
        
        $this->parseXML();
        
        
        
    }
    
    protected function parseXML() {
        $i = 0;
        foreach($this->xmldata->children() as $x){
            foreach($x->ShipToAddress->children() as $y => $z){
                $this->planList[$i]['ShipToAddress'][$y] = (string)$z;
                
            }
            $this->planList[$i]['ShipmentId'] = (string)$x->ShipmentId;
            $this->planList[$i]['DestinationFulfillmentCenterId'] = (string)$x->DestinationFulfillmentCenterId;
            $this->planList[$i]['LabelPrepType'] = (string)$x->LabelPrepType;
            $j = 0;
            foreach($x->Items->children() as $y => $z){
                $this->planList[$i]['Items'][$j]['SellerSKU'] = (string)$z->SellerSKU;
                $this->planList[$i]['Items'][$j]['Quantity'] = (string)$z->Quantity;
                $j++;
                
            }
            $i++;
        }
    }
    
    /**
     * Returns the plan because why not
     * @return SimpleXMLObject
     */
    public function getPlan($i = 0){
        return $this->planList[$i];
    }
    
    /**
     * returns an array of the shipping IDs for convenient use
     * @return array list of shipping IDs, or false on failure
     */
    public function getShipmentIdList(){
        if (!is_array($this->planList)){
            $this->log("No plan list defined yet",'Warning');
            return false;
        }
        $a = array();
        foreach($this->planList as $x){
            $a[] = $x['ShipmentId'];
        }
    }
    
    /**
     * returns the shipment ID if it exists
     * @param integer $i index to return, defaults to 0
     * @return string single Shipment ID or false on failure
     */
    public function getShipmentId($i = 0){
        if (is_numeric($i)){
            if (isset($this->planList[$i]['ShipmentId'])){
                return $this->planList[$i]['ShipmentId'];
            } else {
                $this->log("Shipment ID not found!",'Warning');
                return false;
            }
        } else {
            $this->log("Invalid index for shipping ID: $i",'Warning');
            return false;
        }
    }

    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->planList[$this->i]; 
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
        return isset($this->planList[$this->i]);
    }
}
?>
