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
 * Fetches a fulfillment shipment template from Amazon.
 * 
 * This Amazon Outbound Core object retrieves fulfillment shipment previews,
 * which Amazon generates from the parameters sent. This is how you get
 * Shipment IDs, which are needed for dealing with fulfillment orders.
 */
class AmazonFulfillmentPreview extends AmazonOutboundCore{
    protected $previewList;
    
    /**
     * AmazonFulfillmentPreview sends a request to Amazon to generate a Fulfillment Shipment Preview.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $mock = false, $m = null, $config = null) {
        parent::__construct($s, $mock, $m, $config);
        
        $this->options['Action'] = 'GetFulfillmentPreview';
    }
    
    /**
     * Sets the address. (Required)
     * 
     * This method sets the destination address to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * The array provided should have the following fields:
     * <ul>
     * <li><b>Name</b> - max: 50 char</li>
     * <li><b>Line1</b> - max: 180 char</li>
     * <li><b>Line2</b> (optional) - max: 60 char</li>
     * <li><b>Line3</b> (optional) - max: 60 char</li>
     * <li><b>DistrictOrCounty</b> (optional) - max: 150 char</li>
     * <li><b>City</b> - max: 50 char</li>
     * <li><b>StateOrProvinceCode</b> - max: 150 char</li>
     * <li><b>CountryCode</b> - 2 digits</li>
     * <li><b>PostalCode</b> - max: 20 char</li>
     * <li><b>PhoneNumber</b> - max: 20 char</li>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
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
        $this->options['Address.StateOrProvinceCode'] = $a['StateOrProvinceCode'];
        $this->options['Address.CountryCode'] = $a['CountryCode'];
        $this->options['Address.PostalCode'] = $a['PostalCode'];
        if (array_key_exists('PhoneNumber', $a)){
            $this->options['Address.PhoneNumber'] = $a['PhoneNumber'];
        } else {
            $this->options['Address.PhoneNumber'] = null;
        }
    }
    
    /**
     * Resets the address options.
     * 
     * Since address is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetAddress(){
        unset($this->options['Address.Name']);
        unset($this->options['Address.Line1']);
        unset($this->options['Address.Line2']);
        unset($this->options['Address.Line3']);
        unset($this->options['Address.DistrictOrCounty']);
        unset($this->options['Address.City']);
        unset($this->options['Address.StateOrProvinceCode']);
        unset($this->options['Address.CountryCode']);
        unset($this->options['Address.PostalCode']);
        unset($this->options['Address.PhoneNumber']);
    }
    
    /**
     * Sets the items. (Required)
     * 
     * This method sets the Fulfillment Order ID to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * The array provided should contain a list of arrays, each with the following fields:
     * <ul>
     * <li><b>SellerSKU</b> - max: 50 char</li>
     * <li><b>SellerFulfillmentOrderItemId</b> - useful for differentiating different items with the same SKU, max: 50 char</li>
     * <li><b>Quantity</b> - numeric</li>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Resets the item options.
     * 
     * Since the list of items is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetItems(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#Items#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the preferred shipping speeds. (Optional)
     * 
     * This method sets the shipping speed to be sent in the next request.
     * @param string|array $s <p>"Standard", "Expedited", or "Priority", or an array of these values</p>
     * @return boolean <b>FALSE</b> if improper input
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
     * Removes shipping speed options.
     * 
     * Use this in case you change your mind and want to remove the shipping speed
     * parameters you previously set.
     */
    public function resetShippingSpeeds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShippingSpeedCategories#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the option for getting previews that are for COD (Cash On Delivery). (Optional)
     *
     * If this option is set, Amazon will give previews for COD in addition to the normal previews.
     * If this option is not set or is set to FALSE, Amazon will not give previews that are for COD.
     * @param boolean $s [optional] <p>Defaults to TRUE</p>
     */
    public function setIncludeCod($s = 'true') {
        if (filter_var($s, FILTER_VALIDATE_BOOLEAN)) {
            $s = 'true';
        } else {
            $s = 'false';
        }
        $this->options['IncludeCODFulfillmentPreview'] = $s;
    }

    /**
     * Sets the option for getting delivery window data in the fetched previews. (Optional)
     *
     * If this option is set, Amazon will give delivery window data for applicable order previews.
     * If this option is not set or is set to FALSE, Amazon will not give delivery window data.
     * @param boolean $s [optional] <p>Defaults to TRUE</p>
     */
    public function setIncludeDeliveryWindows($s = 'true') {
        if (filter_var($s, FILTER_VALIDATE_BOOLEAN)) {
            $s = 'true';
        } else {
            $s = 'false';
        }
        $this->options['IncludeDeliveryWindows'] = $s;
    }
    
    /**
     * Generates a Fulfillment Preview with Amazon.
     * 
     * Submits a <i>GetFulfillmentPreview</i> request to Amazon. In order to do this,
     * an address and list of items are required. Amazon will send back a list of
     * previews as a response, which can be retrieved using <i>getPreview</i>.
     * This is how you acquire Order IDs to use. Please note that this does not
     * actually create the fulfillment order, but simply makes a plan for what
     * the order would be like.
     * @return boolean <b>FALSE</b> if something goes wrong
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
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path->FulfillmentPreviews;
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path->FulfillmentPreviews;
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
        $i = 0;
        foreach($xml->children() as $x){
            $this->previewList[$i]['ShippingSpeedCategory'] = (string)$x->ShippingSpeedCategory;
            $this->previewList[$i]['IsFulfillable'] = (string)$x->IsFulfillable;
            $this->previewList[$i]['IsCODCapable'] = (string)$x->IsCODCapable;
            $this->previewList[$i]['MarketplaceId'] = (string)$x->MarketplaceId;
            if (isset($x->EstimatedShippingWeight)){
                $this->previewList[$i]['EstimatedShippingWeight']['Unit'] = (string)$x->EstimatedShippingWeight->Unit;
                $this->previewList[$i]['EstimatedShippingWeight']['Value'] = (string)$x->EstimatedShippingWeight->Value;
            }
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
            if (isset($x->ScheduledDeliveryInfo)){
                $this->previewList[$i]['ScheduledDeliveryInfo']['DeliveryTimeZone'] = (string)$x->ScheduledDeliveryInfo->DeliveryTimeZone;
                foreach ($x->ScheduledDeliveryInfo->DeliveryWindows->children() as $y){
                    $temp = array();
                    $temp['StartDateTime'] = (string)$y->DeliveryWindow->StartDateTime;
                    $temp['EndDateTime'] = (string)$y->DeliveryWindow->EndDateTime;
                    $this->previewList[$i]['ScheduledDeliveryInfo']['DeliveryWindows'][] = $temp;
                }
            }
            
            $i++;
        }
    }
    
    /**
     * Returns the specified fulfillment preview, or all of them.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single fulfillment order will have the following fields:
     * <ul>
     * <li><b>ShippingSpeedCategory</b> - "Standard", "Expedited", or "Priority"</li>
     * <li><b>IsFulfillable</b> - "true" or "false"</li>
     * <li><b>IsCODCapable</b> - "true" or "false"</li>
     * <li><b>MarketplaceId</b> - marketplace ID</li>
     * <li><b>EstimatedShippingWeight</b> (optional) - an array with the fields <b>Unit</b> and <b>Value</b></li>
     * <li><b>FulfillmentPreviewShipments</b> (optional)- array of shipments:</li>
     * <ul>
     * <li><b>EarliestShipDate</b> - ISO 8601 date format</li>
     * <li><b>LatestShipDate</b> - ISO 8601 date format</li>
     * <li><b>EarliestArrivalDate</b> - ISO 8601 date format</li>
     * <li><b>LatestArrivalDate</b> - ISO 8601 date format</li>
     * <li><b>FulfillmentPreviewItems</b> - array of items</li>
     * <ul>
     * <li><b>SellerSKU</b> - SKU</li>
     * <li><b>SellerFulfillmentOrderItemId</b> - unique ID for the item</li>
     * <li><b>Quantity</b> - quantity in the shipment</li>
     * <li><b>EstimatedShippingWeight</b> - an array with the fields <b>Unit</b> and <b>Value</b></li>
     * <li><b>ShippingWeightCalculationMethod</b> - "Package" or "Dimensional"</li>
     * </ul>
     * </ul>
     * <li><b>EstimatedFees</b> (optional)- array of fees</li>
     * <ul>
     * <li><b>Name</b> - name of the fee</li>
     * <li><b>CurrencyCode</b> - currency for the fee</li>
     * <li><b>Value</b> - value for the fee</li>
     * </ul>
     * <li><b>UnfulfillablePreviewItems</b> (optional)- array of items</li>
     * <ul>
     * <li><b>SellerSKU</b> - SKU</li>
     * <li><b>SellerFulfillmentOrderItemId</b> - unique ID for the item</li>
     * <li><b>Quantity</b> - quantity of the item</li>
     * <li><b>ItemUnfulfillableReasons</b> - message as to why the item is unfulfillable</li>
     * </ul>
     * <li><b>OrderUnfulfillableReasons</b> (optional)- array of message strings</li>
     * <li><b>ScheduledDeliveryInfo</b> (optional)- time zone and array of delivery windows</li>
     * <ul>
     * <li><b>DeliveryTimeZone</b> - IANA time zone name</li>
     * <li><b>DeliveryWindows</b> - array of delivery windows</li>
     * <ul>
     * <li><b>StartDateTime</b> - ISO 8601 date format</li>
     * <li><b>EndDateTime</b> - ISO 8601 date format</li>
     * </ul>
     * </ul>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.</p>
     * @return array|boolean array, multi-dimensional array, or <b>FALSE</b> if list not filled yet
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
     * Returns the estimated shipping weight for the specified entry.
     * 
     * The mode can be set to change what is returned: 0 = value, 1 = unit, 2 = value & unit
     * @param int $i [optional]<p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $mode [optional]<p>The type of value to return. Defaults to only value.</p>
     * @return string|boolean weight value, or <b>FALSE</b> if improper input
     */
    public function getEstimatedWeight($i = 0,$mode = 0){
        if (!isset($this->previewList)){
            return false;
        }
        if (is_int($i) && $i >= 0){
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
