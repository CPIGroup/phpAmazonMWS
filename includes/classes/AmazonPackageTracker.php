<?php
/**
 * Fetches package tracking info from Amazon.
 * 
 * This Amazon Outbound Core object retrieves package tracking data
 * from Amazon. A package number is required for this.
 */
class AmazonPackageTracker extends AmazonOutboundCore{
    private $details;
    
    /**
     * Fetches a plan from Amazon. This is how you get a Shipment ID.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $id = null, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if($id){
            $this->setPackageNumber($id);
        }
        
        $this->options['Action'] = 'GetPackageTrackingDetails';
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Sets the package number for the next request
     * @param integer $n
     * @return boolean false if improper input
     */
    public function setPackageNumber($n){
        if (is_numeric($n)){
            $this->options['PackageNumber'] = $n;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon for package tracking details
     * @return boolean false on failure
     */
    public function fetchTrackingDetails(){
        if (!array_key_exists('PackageNumber',$this->options)){
            $this->log("Package Number must be set in order to fetch it!",'Warning');
            return false;
        }
        
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
        
        $this->parseXML($xml);
    }
    
    /**
     * converts XML into arrays
     */
    protected function parseXML($d) {
        $this->details['PackageNumber'] = (string)$d->PackageNumber;
        $this->details['TrackingNumber'] = (string)$d->TrackingNumber;
        $this->details['CarrierCode'] = (string)$d->CarrierCode;
        $this->details['CarrierPhoneNumber'] = (string)$d->CarrierPhoneNumber;
        $this->details['CarrierURL'] = (string)$d->CarrierURL;
        $this->details['ShipDate'] = (string)$d->ShipDate;
        //Address
            $this->details['ShipToAddress']['City'] = (string)$d->ShipToAddress->City;
            $this->details['ShipToAddress']['State'] = (string)$d->ShipToAddress->State;
            $this->details['ShipToAddress']['Country'] = (string)$d->ShipToAddress->Country;
        //End of Address
        $this->details['CurrentStatus'] = (string)$d->CurrentStatus;
        $this->details['SignedForBy'] = (string)$d->SignedForBy;
        $this->details['EstimatedArrivalDate'] = (string)$d->EstimatedArrivalDate;
        
        $i = 0;
        foreach($d->TrackingEvents->children() as $y){
            $this->details['TrackingEvents'][$i]['EventDate'] = (string)$y->EventDate;
            //Address
                $this->details['TrackingEvents'][$i]['EventAddress']['City'] = (string)$y->EventAddress->City;
                $this->details['TrackingEvents'][$i]['EventAddress']['State'] = (string)$y->EventAddress->State;
                $this->details['TrackingEvents'][$i]['EventAddress']['Country'] = (string)$y->EventAddress->Country;
            //End of Address
            $this->details['TrackingEvents'][$i]['EventCode'] = (string)$y->EventCode;
            $i++;
        }
        
        $this->details['AdditionalLocationInfo'] = (string)$d->AdditionalLocationInfo;
        
    }
    
    /**
     * returns all of the details
     * @return array|boolean all of the details, or false if not set yet
     */
    public function getDetails(){
        if (isset($this->details)){
            return $this->details;
        } else {
            return false;
        }
        
    }
    
}
?>
