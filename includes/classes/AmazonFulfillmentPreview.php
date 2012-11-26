<?php

class AmazonFulfillmentPreview extends AmazonOutboundCore{
    private $xmldata;
    
    /**
     * Fetches a plan from Amazon. This is how you get a Shipment ID.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        $this->options['Action'] = 'GetFulfillmentPreview';
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * sets the address to use in the next request
     * 
     * Set the address to use in the next request with an array with these keys:
     * 
     * 'Name' max: 50 char
     * 'Line1' max: 180 char
     * 'Line2' (optional) max: 60 char
     * 'Line3' (optional) max: 60 char
     * 'DistrictOrCounty' (optional) max: 150 char
     * 'City' max: 50 char
     * 'StateOrProvidenceCode' max: 150 char
     * 'CountryCode' 2 digits
     * 'PostalCode' max: 20 char
     * 'PhoneNumber' max: 20 char
     * @param array $a
     * @return boolean false on failure
     */
    public function setAddress($a){
        if (is_null($a) || is_string($a)){
            $this->log("Tried to set address to invalid values",'Warning');
            return false;
        }
        $this->resetAddress();
        $this->options['Address.Name'] = $a['Name'];
        $this->options['Address.Line1'] = $a['Line1'];
        if (array_key_exists('Line2', $a)){
            $this->options['Address.Line2'] = $a['Line2'];
        } else {
            $this->options['Address.Line2'] = null;
        }
        if (array_key_exists('Line3', $a)){
            $this->options['Address.Line3'] = $a['Line3'];
        } else {
            $this->options['Address.Line3'] = null;
        }
        if (array_key_exists('DistrictOrCounty', $a)){
            $this->options['Address.DistrictOrCounty'] = $a['DistrictOrCounty'];
        } else {
            $this->options['Address.DistrictOrCounty'] = null;
        }
        $this->options['Address.City'] = $a['City'];
        $this->options['Address.StateOrProvidenceCode'] = $a['StateOrProvidenceCode'];
        $this->options['Address.CountryCode'] = $a['CountryCode'];
        $this->options['Address.PostalCode'] = $a['PostalCode'];
        if (array_key_exists('PhoneNumber', $a)){
            $this->options['Address.PhoneNumber'] = $a['PhoneNumber'];
        } else {
            $this->options['Address.PhoneNumber'] = null;
        }
    }
    
    /**
     * resets the address options
     */
    protected function resetAddress(){
        unset($this->options['Address.Name']);
        unset($this->options['Address.Line1']);
        unset($this->options['Address.Line2']);
        unset($this->options['Address.Line3']);
        unset($this->options['Address.DistrictOrCounty']);
        unset($this->options['Address.City']);
        unset($this->options['Address.StateOrProvidenceCode']);
        unset($this->options['Address.CountryCode']);
        unset($this->options['Address.PostalCode']);
        unset($this->options['Address.PhoneNumber']);
    }
    
    /**
     * Sets the items to be included in the next request
     * 
     * Sets the items to be included in the next request, using this format:
     * Array of arrays, each with the following fields:
     * 'SellerSKU'
     * 'SellerFulfillmentOrderItemId'
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
                $this->options['Items.member.'.$i.'.SellerSKU'] = $x['SellerSKU'];
                $this->options['Items.member.'.$i.'.SellerFulfillmentOrderItemId'] = $x['SellerFulfillmentOrderItemId'];
                $this->options['Items.member.'.$i.'.Quantity'] = $x['Quantity'];
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
            if(preg_match("#Items#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sets the preferred shipping speeds to be used in the next request
     * @param array $s array of strings or single string: "Standard", "Expedited", or "Priority"
     * @return boolean false if failure
     */
    public function setShippingSpeeds($s){
        if (is_string($s)){
            $this->resetShippingSpeeds();
            $this->options['ShippingSpeedCategories.1'] = $s;
        } else if (is_array($s)){
            $this->resetShippingSpeeds();
            $i = 1;
            foreach ($s as $x){
                $this->options['ShippingSpeedCategories.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes speed options
     */
    public function resetShippingSpeeds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShippingSpeedCategories#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sends a request to Amazon to create a Fulfillment Preview
     * @return boolean true on success, false on failure
     */
    public function fetchPreview(){
        if (!array_key_exists('Address.Name',$this->options)){
            $this->log("Address must be set in order to create a preview",'Warning');
            return false;
        }
        if (!array_key_exists('Items.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to create a preview",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->GetFulfillmentPreviewResult;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();

            $xml = simplexml_load_string($response['body'])->GetFulfillmentPreviewResult;
        }
        myPrint($xml);
        
    }
}
?>
