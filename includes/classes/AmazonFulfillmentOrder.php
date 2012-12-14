<?php
/**
 * Fetches a fulfillment order from Amazon.
 * 
 * This Amazon Outbound Core object can retrieve a fulfillment order
 * from Amazon, or cancel it. In order to fetch or cancel an order,
 * a Shipment ID is needed. Shipment IDs are given by Amazon by
 * using the AmazonFulfillmentPreview object.
 */
class AmazonFulfillmentOrder extends AmazonOutboundCore{
    private $order;
    
    /**
     * Fetches an order from Amazon. You need a Shipment ID.
     * @param string $s name of store as seen in config file
     * @param string $id Order number to automatically set
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
            $this->setOrderId($id);
        }
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Sets the fulfillment order ID for the next request
     * @param string $s (max: 40 chars)
     * @return boolean false if improper input
     */
    public function setOrderId($s){
        if (is_string($s)){
            $this->options['SellerFulfillmentOrderId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon to create a Fulfillment Preview
     * @return boolean true on success, false on failure
     */
    public function fetchOrder(){
        if (!array_key_exists('SellerFulfillmentOrderId',$this->options)){
            $this->log("Fulfillment Order ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $this->options['Action'] = 'GetFulfillmentOrder';
        
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
            myPrint($response);
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
    protected function parseXML($xml) {
        if (!$xml){
            return false;
        }
        if (!$xml->FulfillmentOrder){
            return false;
        }
        if (!$xml->FulfillmentOrderItem){
            return false;
        }
        if (!$xml->FulfillmentShipment){
            return false;
        }
        //Section 1: ShipmentOrder
        $d = $xml->FulfillmentOrder;
        $this->order['Details']['SellerFulfillmentOrderId'] = (string)$d->SellerFulfillmentOrderId;
        $this->order['Details']['DisplayableOrderId'] = (string)$d->DisplayableOrderId;
        $this->order['Details']['DisplayableOrderDateTime'] = (string)$d->DisplayableOrderDateTime;
        $this->order['Details']['DisplayableOrderComment'] = (string)$d->DisplayableOrderComment;
        $this->order['Details']['ShippingSpeedCategory'] = (string)$d->ShippingSpeedCategory;
        //Address
            $this->order['Details']['DestinationAddress']['Name'] = (string)$d->DestinationAddress->Name;
            $this->order['Details']['DestinationAddress']['Line1'] = (string)$d->DestinationAddress->Line1;
            if (isset($d->DestinationAddress->Line2)){
                $this->order['Details']['DestinationAddress']['Line2'] = (string)$d->DestinationAddress->Line2;
            }
            if (isset($d->DestinationAddress->Line3)){
                $this->order['Details']['DestinationAddress']['Line3'] = (string)$d->DestinationAddress->Line3;
            }
            if (isset($d->DestinationAddress->DistrictOrCounty)){
                $this->order['Details']['DestinationAddress']['DistrictOrCounty'] = (string)$d->DestinationAddress->DistrictOrCounty;
            }
            $this->order['Details']['DestinationAddress']['City'] = (string)$d->DestinationAddress->City;
            $this->order['Details']['DestinationAddress']['StateOrProvinceCode'] = (string)$d->DestinationAddress->StateOrProvinceCode;
            $this->order['Details']['DestinationAddress']['CountryCode'] = (string)$d->DestinationAddress->CountryCode;
            if (isset($d->DestinationAddress->PostalCode)){
                $this->order['Details']['DestinationAddress']['PostalCode'] = (string)$d->DestinationAddress->PostalCode;
            }
            if (isset($d->DestinationAddress->PhoneNumber)){
                $this->order['Details']['DestinationAddress']['PhoneNumber'] = (string)$d->DestinationAddress->PhoneNumber;
            }
        //End of Address
        if (isset($d->FulfillmentPolicy)){
            $this->order['Details']['FulfillmentPolicy'] = (string)$d->FulfillmentPolicy;
        }
        if (isset($d->FulfillmentMethod)){
            $this->order['Details']['FulfillmentMethod'] = (string)$d->FulfillmentMethod;
        }
        $this->order['Details']['ReceivedDateTime'] = (string)$d->ReceivedDateTime;
        $this->order['Details']['FulfillmentOrderStatus'] = (string)$d->FulfillmentOrderStatus;
        $this->order['Details']['StatusUpdatedDateTime'] = (string)$d->StatusUpdatedDateTime;
        if (isset($d->NotificationEmailList)){
            $i = 0;
            foreach($d->NotificationEmailList->children() as $x){
                $this->order['Details']['NotificationEmailList'][$i++] = (string)$x;
            }
        }
        
        //Section 2: Order Items
        $i = 0;
        foreach($xml->FulfillmentOrderItem->children() as $x){
            $this->order['Items'][$i]['SellerSKU'] = (string)$x->SellerSKU;
            $this->order['Items'][$i]['SellerFulfillmentOrderItemId'] = (string)$x->SellerFulfillmentOrderItemId;
            $this->order['Items'][$i]['Quantity'] = (string)$x->Quantity;
            if (isset($x->GiftMessage)){
                $this->order['Items'][$i]['GiftMessage'] = (string)$x->GiftMessage;
            }
            if (isset($x->DisplayableComment)){
                $this->order['Items'][$i]['DisplayableComment'] = (string)$x->DisplayableComment;
            }
            if (isset($x->FulfillmentNetworkSKU)){
                $this->order['Items'][$i]['FulfillmentNetworkSKU'] = (string)$x->FulfillmentNetworkSKU;
            }
            if (isset($x->OrderItemDisposition)){
                $this->order['Items'][$i]['OrderItemDisposition'] = (string)$x->OrderItemDisposition;
            }
            $this->order['Items'][$i]['CancelledQuantity'] = (string)$x->CancelledQuantity;
            $this->order['Items'][$i]['UnfulfillableQuantity'] = (string)$x->UnfulfillableQuantity;
            if (isset($x->EstimatedShipDateTime)){
                $this->order['Items'][$i]['EstimatedShipDateTime'] = (string)$x->EstimatedShipDateTime;
            }
            if (isset($x->EstimatedArrivalDateTime)){
                $this->order['Items'][$i]['EstimatedArrivalDateTime'] = (string)$x->EstimatedArrivalDateTime;
            }
            if (isset($x->PerUnitDeclaredValue)){
                $this->order['Items'][$i]['PerUnitDeclaredValue']['CurrencyCode'] = (string)$x->PerUnitDeclaredValue->CurrencyCode;
                $this->order['Items'][$i]['PerUnitDeclaredValue']['Value'] = (string)$x->PerUnitDeclaredValue->Value;
            }
            $i++;
        }
        
        //Section 3: Order Shipments
        $i = 0;
        foreach($xml->FulfillmentShipment->children() as $x){
            $this->order['Shipments'][$i]['AmazonShipmentId'] = (string)$x->AmazonShipmentId;
            $this->order['Shipments'][$i]['FulfillmentCenterId'] = (string)$x->FulfillmentCenterId;
            $this->order['Shipments'][$i]['FulfillmentShipmentStatus'] = (string)$x->FulfillmentShipmentStatus;
            if (isset($x->ShippingDateTime)){
                $this->order['Shipments'][$i]['ShippingDateTime'] = (string)$x->ShippingDateTime;
            }
            if (isset($x->EstimatedArrivalDateTime)){
                $this->order['Shipments'][$i]['EstimatedArrivalDateTime'] = (string)$x->EstimatedArrivalDateTime;
            }
            //FulfillmentShipmentItem
            $j = 0;
            foreach ($x->FulfillmentShipmentItem->children() as $y){
                if (isset($y->SellerSKU)){
                    $this->order['Shipments'][$i]['FulfillmentShipmentItem'][$j]['SellerSKU'] = (string)$y->SellerSKU;
                }
                $this->order['Shipments'][$i]['FulfillmentShipmentItem'][$j]['SellerFulfillmentOrderItemId'] = (string)$y->SellerFulfillmentOrderItemId;
                $this->order['Shipments'][$i]['FulfillmentShipmentItem'][$j]['Quantity'] = (string)$y->Quantity;
                if (isset($y->PackageNumber)){
                    $this->order['Shipments'][$i]['FulfillmentShipmentItem'][$j]['PackageNumber'] = (string)$y->PackageNumber;
                }
                $j++;
            }
            if (isset($x->FulfillmentShipmentPackage)){
                $j = 0;
                foreach ($x->FulfillmentShipmentPackage->children() as $y){
                    $this->order['Shipments'][$i]['FulfillmentShipmentPackage'][$j]['PackageNumber'] = (string)$y->PackageNumber;
                    $this->order['Shipments'][$i]['FulfillmentShipmentPackage'][$j]['CarrierCode'] = (string)$y->CarrierCode;
                    if (isset($y->TrackingNumber)){
                        $this->order['Shipments'][$i]['FulfillmentShipmentPackage'][$j]['TrackingNumber'] = (string)$y->TrackingNumber;
                    }
                    if (isset($y->EstimatedArrivalDateTime)){
                        $this->order['Shipments'][$i]['FulfillmentShipmentPackage'][$j]['EstimatedArrivalDateTime'] = (string)$y->EstimatedArrivalDateTime;
                    }
                    $j++;
                }
            }
            
            $i++;
        }
    }
    
    /**
     * Sends a request to Amazon to create a Fulfillment Preview
     * @return boolean true on success, false on failure
     */
    public function cancelOrder(){
        if (!array_key_exists('SellerFulfillmentOrderId',$this->options)){
            $this->log("Fulfillment Order ID must be set in order to cancel it!",'Warning');
            return false;
        }
        
        $this->options['Action'] = 'CancelFulfillmentOrder';
        
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
            $this->log("Successfully deleted Fulfillment Order ".$this->options['SellerFulfillmentOrderId']);
            return true;
        }
    }
    
    /**
     * Returns the giant array of info about the shipment
     * @return array|boolean array, or false if not yet set
     */
    public function getOrder(){
        if (isset($this->order)){
            return $this->order;
        } else {
            return false;
        }
    }
}
?>
