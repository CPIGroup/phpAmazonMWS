<?php
/**
 * Fetches a fulfillment shipment template from Amazon.
 * 
 * This Amazon Outbound Core object retrieves fulfillment shipment previews,
 * which Amazon generates from the parameters sent. This is how you get
 * Shipment IDs, which are needed for dealing with fulfillment orders.
 */
class AmazonFulfillmentPreview extends AmazonOutboundCore{
    private $previewList;
    
    /**
     * Sends a request to Amazon to generate a Fulfillment Shipment Preview.
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
        if (is_null($a) || is_string($a) || !$a){
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
     * @param array|string $s array of strings or single string: "Standard", "Expedited", or "Priority"
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
                $i++;
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
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path->FulfillmentPreviews;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path->FulfillmentPreviews;
        }
        
        $this->parseXML($xml);
    }
    
    /**
     * converts XML into arrays
     */
    protected function parseXML($xml) {
        if (!$xml){
            return false;
        }
        $i = 0;
        foreach($xml->children() as $x){
            if (isset($x->EstimatedShippingWeight)){
                $this->previewList[$i]['EstimatedShippingWeight']['Unit'] = (string)$x->EstimatedShippingWeight->Unit;
                $this->previewList[$i]['EstimatedShippingWeight']['Value'] = (string)$x->EstimatedShippingWeight->Value;
            }
            $this->previewList[$i]['ShippingSpeedCategory'] = (string)$x->ShippingSpeedCategory;
            if (isset($x->FulfillmentPreviewShipments)){
                $j = 0;
                foreach ($x->FulfillmentPreviewShipments->children() as $y){
                    $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['LatestShipDate'] = (string)$y->LatestShipDate;
                    $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['LatestArrivalDate'] = (string)$y->LatestArrivalDate;
                    $k = 0;
                    foreach ($y->FulfillmentPreviewItems->children() as $z){
                        $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['FulfillmentPreviewItems'][$k]['EstimatedShippingWeight']['Unit'] = (string)$z->EstimatedShippingWeight->Unit;
                        $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['FulfillmentPreviewItems'][$k]['EstimatedShippingWeight']['Value'] = (string)$z->EstimatedShippingWeight->Value;
                        $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['FulfillmentPreviewItems'][$k]['SellerSKU'] = (string)$z->SellerSKU;
                        $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['FulfillmentPreviewItems'][$k]['SellerFulfillmentOrderItemId'] = (string)$z->SellerFulfillmentOrderItemId;
                        $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['FulfillmentPreviewItems'][$k]['ShippingWeightCalculationMethod'] = (string)$z->ShippingWeightCalculationMethod;
                        $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['FulfillmentPreviewItems'][$k]['Quantity'] = (string)$z->Quantity;
                        $k++;
                    }
                    $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['EarliestShipDate'] = (string)$y->EarliestShipDate;
                    $this->previewList[$i]['FulfillmentPreviewShipments'][$j]['EarliestArrivalDate'] = (string)$y->EarliestArrivalDate;
                    $j++;
                }
            }
            if (isset($x->EstimatedFees)){
                $j = 0;
                foreach ($x->EstimatedFees->children() as $y){
                    $this->previewList[$i]['EstimatedFees'][$j]['CurrencyCode'] = (string)$y->Amount->CurrencyCode;
                    $this->previewList[$i]['EstimatedFees'][$j]['Value'] = (string)$y->Amount->Value;
                    $this->previewList[$i]['EstimatedFees'][$j]['Name'] = (string)$y->Name;
                    $j++;
                }
            }
            if (isset($x->UnfulfillablePreviewItems)){
                $j = 0;
                foreach ($x->UnfulfillablePreviewItems->children() as $y){
                    $this->previewList[$i]['UnfulfillablePreviewItems'][$j]['SellerSKU'] = (string)$y->SellerSKU;
                    $this->previewList[$i]['UnfulfillablePreviewItems'][$j]['SellerFulfillmentOrderItemId'] = (string)$y->SellerFulfillmentOrderItemId;
                    $this->previewList[$i]['UnfulfillablePreviewItems'][$j]['Quantity'] = (string)$y->Quantity;
                    $this->previewList[$i]['UnfulfillablePreviewItems'][$j]['ItemUnfulfillableReasons'] = (string)$y->ItemUnfulfillableReasons;
                    $j++;
                }
            }
            if (isset($x->OrderUnfulfillableReasons)){
                $j = 0;
                foreach ($x->OrderUnfulfillableReasons->children() as $y){
                    $this->previewList[$i]['OrderUnfulfillableReasons'][$j] = (string)$y;
                    $j++;
                }
            }
            $this->previewList[$i]['IsFulfillable'] = (string)$x->IsFulfillable;
            
            $i++;
        }
    }
    
    /**
     * Returns specified Preview
     * @param int $i index, defaults to 0
     * @return array gigantic array of information
     */
    public function getPreview($i = null){
        if (!isset($this->previewList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->previewList[$i];
        } else {
            return $this->previewList;
        }
    }
    
    /**
     * Returns the estimated shipping weight for the specified entry
     * @param int $i index, defaults to 0
     * @param int $mode 0 = value, 1 = unit, 2 = value & unit
     * @return string|boolean weight value, or False if Non-numeric index
     */
    public function getEstimatedWeight($i = 0,$mode = 0){
        if (!isset($this->previewList)){
            return false;
        }
        if (is_numeric($i) && $i >= 0){
            if ($mode == 1){
                return $this->previewList[$i]['EstimatedShippingWeight']['Unit'];
            } else if ($mode == 2){
                return $this->previewList[$i]['EstimatedShippingWeight'];
            } else 
            {
                return $this->previewList[$i]['EstimatedShippingWeight']['Value'];
            }
        } else {
            return false;
        }
    }
}
?>
