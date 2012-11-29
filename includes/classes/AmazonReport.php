<?php

class AmazonPackageTracker extends AmazonReportsCore{
    private $rawreport;
    private $report;
    
    /**
     * Fetches a plan from Amazon. This is how you get a Shipment ID.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $id = null, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        if($id){
            $this->options['ReportId'] = $id;
        }
        
        $this->options['Action'] = 'GetReport';
        
        $this->throttleLimit = $throttleLimitReport;
        $this->throttleTime = $throttleTimeReport;
    }
    
    /**
     * Sets the report ID for the next request
     * @param integer $n
     * @return boolean false if improper input
     */
    public function setReportId($n){
        if (is_numeric($n)){
            $this->options['PackageNumber'] = $n;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon for a report
     * @return boolean false on failure
     */
    public function fetchReport(){
        if (!array_key_exists('ReportId',$this->options)){
            $this->log("Report ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $this->rawreport = $this->fetchMockFile();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $this->rawreport = simplexml_load_string($response['body']);
        }
        
    }
    
    
    
    /*
     * TODO: Figure out what to do with reports
     */
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     * converts XML into arrays
     */
    protected function parseXML() {
//        $d = $this->xmldata;
//        $this->report['PackageNumber'] = (string)$d->PackageNumber;
//        $this->report['TrackingNumber'] = (string)$d->TrackingNumber;
//        $this->report['CarrierCode'] = (string)$d->CarrierCode;
//        $this->report['CarrierPhoneNumber'] = (string)$d->CarrierPhoneNumber;
//        $this->report['CarrierURL'] = (string)$d->CarrierURL;
//        $this->report['ShipDate'] = (string)$d->ShipDate;
//        //Address
//            $this->report['ShipToAddress']['City'] = (string)$d->ShipToAddress->City;
//            $this->report['ShipToAddress']['State'] = (string)$d->ShipToAddress->State;
//            $this->report['ShipToAddress']['Country'] = (string)$d->ShipToAddress->Country;
//        //End of Address
//        $this->report['CurrentStatus'] = (string)$d->CurrentStatus;
//        $this->report['SignedForBy'] = (string)$d->SignedForBy;
//        $this->report['EstimatedArrivalDate'] = (string)$d->EstimatedArrivalDate;
//        
//        $i = 0;
//        foreach($d->TrackingEvents->children() as $y){
//            $this->report['TrackingEvents'][$i]['EventDate'] = (string)$y->EventDate;
//            //Address
//                $this->report['TrackingEvents'][$i]['EventAddress']['City'] = (string)$d->ShipToAddress->City;
//                $this->report['TrackingEvents'][$i]['EventAddress']['State'] = (string)$d->ShipToAddress->State;
//                $this->report['TrackingEvents'][$i]['EventAddress']['Country'] = (string)$d->ShipToAddress->Country;
//            //End of Address
//            $this->report['TrackingEvents'][$i]['EventCode'] = (string)$y->EventCode;
//            $j++;
//        }
//        
//        $this->report['AdditionalLocationInfo'] = (string)$d->AdditionalLocationInfo;
        
    }
    
    /**
     * returns all of the details
     * @return array all of the details
     */
    public function getReport(){
        return $this->report;
    }
    
}
?>
