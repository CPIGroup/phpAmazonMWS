<?php

class AmazonFulfillmentOrderCreator extends AmazonOutboundCore{
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
        if (is_null($a) || is_string($a)){
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
                $this->options['ShippingSpeedCategory'] = $s;
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
     * @param array $s array of strings or single string (max 64 chars each)
     * @return boolean false if failure
     */
    public function setEmails($s){
        if (is_string($s)){
            $this->resetEmails();
            $this->options['NotificationEmailList.member.1'] = $s;
        } else if (is_array($s)){
            $this->resetEmails();
            $i = 1;
            foreach ($s as $x){
                $this->options['NotificationEmailList.member.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes speed options
     */
    public function resetEmails(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#NotificationEmailList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
}
?>
