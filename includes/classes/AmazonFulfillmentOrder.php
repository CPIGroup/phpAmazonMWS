<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Fetches a fulfillment order from Amazon.
 * 
 * This Amazon Outbound Core object can retrieve a fulfillment order
 * from Amazon, or cancel it. In order to fetch or cancel an order,
 * a Shipment ID is needed. Shipment IDs are given by Amazon by
 * using the <i>AmazonFulfillmentPreview</i> object.
 */
class AmazonFulfillmentOrder extends AmazonOutboundCore{
    protected $order;
    
    /**
     * AmazonFulfillmentOrder fetches a fulfillment order from Amazon. You need a Fulfillment Order ID.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param string $id [optional] <p>The Fulfillment Order ID to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $id = null, $mock = false, $m = null, $config = null) {
        parent::__construct($s, $mock, $m, $config);
        
        if($id){
            $this->setOrderId($id);
        }
    }
    
    /**
     * Sets the fulfillment order ID. (Required)
     * 
     * This method sets the Fulfillment Order ID to be sent in the next request.
     * This parameter is required for fetching the fulfillment order from Amazon.
     * @param string $s <p>Maximum 40 characters.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setOrderId($s){
        if (is_string($s)){
            $this->options['SellerFulfillmentOrderId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Fetches data on a fulfillment order from Amazon.
     * 
     * Submits a <i>GetFulfillmentOrder</i> request to Amazon. In order to do this,
     * a fulfillment order ID is required. Amazon will send
     * the data back as a response, which can be retrieved using <i>getOrder</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchOrder(){
        if (!array_key_exists('SellerFulfillmentOrderId',$this->options)){
            $this->log("Fulfillment Order ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $this->options['Action'] = 'GetFulfillmentOrder';
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $this->parseXML($xml);
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
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
        $this->order['Details']['MarketplaceId'] = (string)$d->MarketplaceId;
        $this->order['Details']['DisplayableOrderId'] = (string)$d->DisplayableOrderId;
        $this->order['Details']['DisplayableOrderDateTime'] = (string)$d->DisplayableOrderDateTime;
        $this->order['Details']['DisplayableOrderComment'] = (string)$d->DisplayableOrderComment;
        $this->order['Details']['ShippingSpeedCategory'] = (string)$d->ShippingSpeedCategory;
        if (isset($d->DeliveryWindow)) {
            $this->order['Details']['DeliveryWindow']['StartDateTime'] = (string)$d->DeliveryWindow->StartDateTime;
            $this->order['Details']['DeliveryWindow']['EndDateTime'] = (string)$d->DeliveryWindow->EndDateTime;
        }
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
        if (isset($d->FulfillmentAction)){
            $this->order['Details']['FulfillmentAction'] = (string)$d->FulfillmentAction;
        }
        if (isset($d->FulfillmentPolicy)){
            $this->order['Details']['FulfillmentPolicy'] = (string)$d->FulfillmentPolicy;
        }
        if (isset($d->FulfillmentMethod)){
            //deprecated
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
        if (isset($d->CODSettings->IsCODRequired)){
            $this->order['Details']['CODSettings']['IsCODRequired'] = (string)$d->CODSettings->IsCODRequired;
        }
        if (isset($d->CODSettings->CODCharge)){
            $this->order['Details']['CODSettings']['CODCharge']['CurrencyCode'] = (string)$d->CODSettings->CODCharge->CurrencyCode;
            $this->order['Details']['CODSettings']['CODCharge']['Value'] = (string)$d->CODSettings->CODCharge->Value;
        }
        if (isset($d->CODSettings->CODChargeTax)){
            $this->order['Details']['CODSettings']['CODChargeTax']['CurrencyCode'] = (string)$d->CODSettings->CODChargeTax->CurrencyCode;
            $this->order['Details']['CODSettings']['CODChargeTax']['Value'] = (string)$d->CODSettings->CODChargeTax->Value;
        }
        if (isset($d->CODSettings->ShippingCharge)){
            $this->order['Details']['CODSettings']['ShippingCharge']['CurrencyCode'] = (string)$d->CODSettings->ShippingCharge->CurrencyCode;
            $this->order['Details']['CODSettings']['ShippingCharge']['Value'] = (string)$d->CODSettings->ShippingCharge->Value;
        }
        if (isset($d->CODSettings->ShippingChargeTax)){
            $this->order['Details']['CODSettings']['ShippingChargeTax']['CurrencyCode'] = (string)$d->CODSettings->ShippingChargeTax->CurrencyCode;
            $this->order['Details']['CODSettings']['ShippingChargeTax']['Value'] = (string)$d->CODSettings->ShippingChargeTax->Value;
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
            if (isset($x->PerUnitPrice)){
                $this->order['Items'][$i]['PerUnitPrice']['CurrencyCode'] = (string)$x->PerUnitPrice->CurrencyCode;
                $this->order['Items'][$i]['PerUnitPrice']['Value'] = (string)$x->PerUnitPrice->Value;
            }
            if (isset($x->PerUnitTax)){
                $this->order['Items'][$i]['PerUnitTax']['CurrencyCode'] = (string)$x->PerUnitTax->CurrencyCode;
                $this->order['Items'][$i]['PerUnitTax']['Value'] = (string)$x->PerUnitTax->Value;
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
     * Cancels a fulfillment order on Amazon.
     * 
     * Submits a <i>CancelFulfillmentOrder</i> request to Amazon. In order to do this,
     * a fulfillment order ID is required. Amazon will send back an HTTP response,
     * so there is no data to retrieve afterwards.
     * @return boolean <b>TRUE</b> if the cancellation was successful, <b>FALSE</b> if something goes wrong
     */
    public function cancelOrder(){
        if (!array_key_exists('SellerFulfillmentOrderId',$this->options)){
            $this->log("Fulfillment Order ID must be set in order to cancel it!",'Warning');
            return false;
        }
        
        $this->options['Action'] = 'CancelFulfillmentOrder';
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        if ($this->mockMode){
            $response = $this->fetchMockResponse();
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
        }
        if (!$this->checkResponse($response)){
            return false;
        } else {
            $this->log("Successfully deleted Fulfillment Order ".$this->options['SellerFulfillmentOrderId']);
            return true;
        }
    }
    
    /**
     * Returns the full order information.
     * 
     * This method will return <b>FALSE</b> if the data has not yet been filled.
     * The array returned will have the following fields:
     * <ul>
     * <li><b>Details</b> - array of general information, such as destination address</li>
     * <li><b>Items</b> - multi-dimensional array of item data</li>
     * <li><b>Shipments</b> - multi-dimensional array of shipment data</li>
     * </ul>
     * @return array|boolean data array, or <b>FALSE</b> if data not filled yet
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
