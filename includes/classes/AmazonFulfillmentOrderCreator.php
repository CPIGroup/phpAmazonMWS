<?php
/**
 * Submits a request to create a fulfillment order to Amazon.
 * 
 * This Amazon Outbound Core object can submit a request to Amazon to
 * create a new Fulfillment Order. In order to create an order,
 * a Shipment ID is needed. Shipment IDs are given by Amazon by
 * using the AmazonFulfillmentPreview object.
 */
class AmazonFulfillmentOrderCreator extends AmazonOutboundCore{
    
    /**
     * Creates a fulfillment order. You need a Shipment ID.
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
        
        $this->options['Action'] = 'CreateFulfillmentOrder';
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Sets the fulfillment order ID for the next request
     * @param string $s (max: 40 chars)
     * @return boolean false if improper input
     */
    public function setFulfillmentOrderId($s){
        if (is_string($s)){
            $this->options['SellerFulfillmentOrderId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the displayed order ID for the next request
     * @param string $s must be alpha-numeric or ISO-8559-1 compliant (max: 40 chars)
     * @return boolean false if improper input
     */
    public function setDisplayableOrderId($s){
        if (is_string($s)){
            $this->options['DisplayableOrderId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the displayed timestamp for the next request
     * @param string $s is passed through strtotime
     * @return boolean false if improper input
     */
    public function setDate($s){
        if (is_string($s)){
            $time = $this->genTime($s);
            $this->options['DisplayableOrderDateTime'] = $time;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the displayed comment for the next request
     * @param string $s (max: 1000 chars)
     * @return boolean false if improper input
     */
    public function setComment($s){
        if (is_string($s)){
            $this->options['DisplayableOrderComment'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the shipping speed for the next request
     * @param string $s "Standard", "Expedited", or "Priority"
     * @return boolean false if improper input
     */
    public function setShippingSpeed($s){
        if (is_string($s)){
            if ($s == 'Standard' || $s == 'Expedited' || $s == 'Priority'){
                $this->options['ShippingSpeedCategory'] = $s;
            } else {
                $this->log("Tried to set shipping status to invalid value",'Warning');
                return false;
            }
        } else {
            return false;
        }
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
        if (is_null($a) || is_string($a) || !$a){
            $this->log("Tried to set address to invalid values",'Warning');
            return false;
        }
        $this->resetAddress();
        $this->options['DestinationAddress.Name'] = $a['Name'];
        $this->options['DestinationAddress.Line1'] = $a['Line1'];
        if (array_key_exists('Line2', $a)){
            $this->options['DestinationAddress.Line2'] = $a['Line2'];
        } else {
            $this->options['DestinationAddress.Line2'] = null;
        }
        if (array_key_exists('Line3', $a)){
            $this->options['DestinationAddress.Line3'] = $a['Line3'];
        } else {
            $this->options['DestinationAddress.Line3'] = null;
        }
        if (array_key_exists('DistrictOrCounty', $a)){
            $this->options['DestinationAddress.DistrictOrCounty'] = $a['DistrictOrCounty'];
        } else {
            $this->options['DestinationAddress.DistrictOrCounty'] = null;
        }
        $this->options['DestinationAddress.City'] = $a['City'];
        $this->options['DestinationAddress.StateOrProvidenceCode'] = $a['StateOrProvidenceCode'];
        $this->options['DestinationAddress.CountryCode'] = $a['CountryCode'];
        $this->options['DestinationAddress.PostalCode'] = $a['PostalCode'];
        if (array_key_exists('PhoneNumber', $a)){
            $this->options['DestinationAddress.PhoneNumber'] = $a['PhoneNumber'];
        } else {
            $this->options['DestinationAddress.PhoneNumber'] = null;
        }
    }
    
    /**
     * resets the address options
     */
    protected function resetAddress(){
        unset($this->options['DestinationAddress.Name']);
        unset($this->options['DestinationAddress.Line1']);
        unset($this->options['DestinationAddress.Line2']);
        unset($this->options['DestinationAddress.Line3']);
        unset($this->options['DestinationAddress.DistrictOrCounty']);
        unset($this->options['DestinationAddress.City']);
        unset($this->options['DestinationAddress.StateOrProvidenceCode']);
        unset($this->options['DestinationAddress.CountryCode']);
        unset($this->options['DestinationAddress.PostalCode']);
        unset($this->options['DestinationAddress.PhoneNumber']);
    }
    
    /**
     * Sets the fulfillment policy for the next request
     * @param string $s "FillOrKill", "FillAll", or "FillAllAvailable"
     * @return boolean false if improper input
     */
    public function setFulfillmentPolicy($s){
        if (is_string($s)){
            if ($s == 'FillOrKill' || $s == 'FillAll' || $s == 'FillAllAvailable'){
                $this->options['FulfillmentPolicy'] = $s;
            } else {
                $this->log("Tried to set fulfillment policy to invalid value",'Warning');
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Sets the fulfillment method for the next request
     * @param string $s "Consumer" or "Removal"
     * @return boolean false if improper input
     */
    public function setFulfillmentMethod($s){
        if (is_string($s)){
            if ($s == 'Consumer' || $s == 'Removal'){
                $this->options['FulfillmentMethod'] = $s;
            } else {
                $this->log("Tried to set fulfillment method to invalid value",'Warning');
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * sets the email(s) to be used in the next request
     * @param array|string $s array of emails or single email (max 64 chars each)
     * @return boolean false if failure
     */
    public function setEmails($s){
        if (is_string($s)){
            $this->resetEmails();
            $this->options['NotificationEmailList.member.1'] = $s;
        } else if (is_array($s) && $s){
            $this->resetEmails();
            $i = 1;
            foreach ($s as $x){
                $this->options['NotificationEmailList.member.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes email options
     */
    public function resetEmails(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#NotificationEmailList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the items to be included in the next request
     * 
     * Sets the items to be included in the next request, using this format:
     * Array of arrays, each with the following fields:
     * 'SellerSKU'
     * 'SellerFulfillmentOrderItemId'
     * 'Quantity'
     * 'GiftMessage' (optional, 512 chars)
     * 'DisplayableComment' (optional, 250 chars)
     * 'FulfillmentNetworkSKU' (optional)
     * 'OrderItemDisposition' (optional) "Sellable" or "Unsellable"
     * 'PerUnitDeclaredValue' (optional array)
     *      'CurrencyCode' three digits
     *      'Value'
     * @param array $a array of item arrays
     * @return boolean false if failure
     */
    public function setItems($a){
        if (is_null($a) || is_string($a) || !$a){
            $this->log("Tried to set Items to invalid values",'Warning');
            return false;
        }
        $this->resetItems();
        $i = 1;
        foreach ($a as $x){
            if (is_array($x) && array_key_exists('SellerSKU', $x) && array_key_exists('SellerFulfillmentOrderItemId', $x) && array_key_exists('Quantity', $x)){
                $this->options['Items.member.'.$i.'.SellerSKU'] = $x['SellerSKU'];
                $this->options['Items.member.'.$i.'.SellerFulfillmentOrderItemId'] = $x['SellerFulfillmentOrderItemId'];
                $this->options['Items.member.'.$i.'.Quantity'] = $x['Quantity'];
                if (array_key_exists('GiftMessage', $x)){
                    $this->options['Items.member.'.$i.'.GiftMessage'] = $x['GiftMessage'];
                }
                if (array_key_exists('Comment', $x)){
                    $this->options['Items.member.'.$i.'.DisplayableComment'] = $x['Comment'];
                }
                if (array_key_exists('FulfillmentNetworkSKU', $x)){
                    $this->options['Items.member.'.$i.'.FulfillmentNetworkSKU'] = $x['FulfillmentNetworkSKU'];
                }
                if (array_key_exists('OrderItemDisposition', $x)){
                    $this->options['Items.member.'.$i.'.OrderItemDisposition'] = $x['OrderItemDisposition'];
                }
                if (array_key_exists('PerUnitDeclaredValue', $x)){
                    $this->options['Items.member.'.$i.'.PerUnitDeclaredValue.CurrencyCode'] = $x['PerUnitDeclaredValue']['CurrencyCode'];
                    $this->options['Items.member.'.$i.'.PerUnitDeclaredValue.Value'] = $x['PerUnitDeclaredValue']['Value'];
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
            if(preg_match("#Items#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sends a request to Amazon to create a Fulfillment Order
     * @return boolean true on success, false on failure
     */
    public function createOrder(){
        if (!array_key_exists('SellerFulfillmentOrderId',$this->options)){
            $this->log("Seller Fulfillment OrderID must be set in order to create an order",'Warning');
            return false;
        }
        if (!array_key_exists('DisplayableOrderId',$this->options)){
            $this->log("Displayable Order ID must be set in order to create an order",'Warning');
            return false;
        }
        if (!array_key_exists('DisplayableOrderDateTime',$this->options)){
            $this->log("Date must be set in order to create an order",'Warning');
            return false;
        }
        if (!array_key_exists('DisplayableOrderComment',$this->options)){
            $this->log("Comment must be set in order to create an order",'Warning');
            return false;
        }
        if (!array_key_exists('ShippingSpeedCategory',$this->options)){
            $this->log("Shipping Speed must be set in order to create an order",'Warning');
            return false;
        }
        if (!array_key_exists('DestinationAddress.Name',$this->options)){
            $this->log("Address must be set in order to create an order",'Warning');
            return false;
        }
        if (!array_key_exists('Items.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to create an order",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
            $response = $this->fetchMockResponse();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
        }
        if (!$this->checkResponse($response)){
            return false;
        } else {
            $this->log("Successfully created Fulfillment Order ".$this->options['SellerFulfillmentOrderId']." / ".$this->options['DisplayableOrderId']);
            return true;
        }
    }
    
}
?>
