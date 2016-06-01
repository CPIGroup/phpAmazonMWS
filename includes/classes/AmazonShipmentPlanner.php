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
 *  Fetches an inbound shipment plan from Amazon.
 * 
 * This Amazon Inbound Core object retrieves a newly-generated inbound shipment
 * plan from Amazon using the provided information. In order to generate a
 * shipment plan, an address and a list of items are required.
 */
class AmazonShipmentPlanner extends AmazonInboundCore implements Iterator{
    protected $planList;
    protected $i = 0;
    
    /**
     * AmazonShipmentPlanner fetches a shipment plan from Amazon. This is how you get a Shipment ID.
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
        
        $this->options['Action'] = 'CreateInboundShipmentPlan';
    }
    
    /**
     * Sets the address. (Required)
     * 
     * This method sets the shipper's address to be sent in the next request.
     * This parameter is required for planning a fulfillment order with Amazon.
     * The array provided should have the following fields:
     * <ul>
     * <li><b>Name</b> - max: 50 char</li>
     * <li><b>AddressLine1</b> - max: 180 char</li>
     * <li><b>AddressLine2</b> (optional) - max: 60 char</li>
     * <li><b>City</b> - max: 30 char</li>
     * <li><b>DistrictOrCounty</b> (optional) - max: 25 char</li>
     * <li><b>StateOrProvinceCode</b> (recommended) - 2 digits</li>
     * <li><b>CountryCode</b> - 2 digits</li>
     * <li><b>PostalCode</b> (recommended) - max: 30 char</li>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setAddress($a){
        if (!$a || is_null($a) || is_string($a)){
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
        if (array_key_exists('StateOrProvinceCode', $a)){
            $this->options['ShipFromAddress.StateOrProvinceCode'] = $a['StateOrProvinceCode'];
        }
        $this->options['ShipFromAddress.CountryCode'] = $a['CountryCode'];
        if (array_key_exists('PostalCode', $a)){
            $this->options['ShipFromAddress.PostalCode'] = $a['PostalCode'];
        }
        
        
    }
    
    /**
     * Resets the address options.
     * 
     * Since address is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetAddress(){
        unset($this->options['ShipFromAddress.Name']);
        unset($this->options['ShipFromAddress.AddressLine1']);
        unset($this->options['ShipFromAddress.AddressLine2']);
        unset($this->options['ShipFromAddress.City']);
        unset($this->options['ShipFromAddress.DistrictOrCounty']);
        unset($this->options['ShipFromAddress.StateOrProvinceCode']);
        unset($this->options['ShipFromAddress.CountryCode']);
        unset($this->options['ShipFromAddress.PostalCode']);
    }

    /**
     * Sets the destination country code. (Optional)
     * @param string $c <p>Country code in ISO 3166-1 alpha-2 format</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setCountry($c) {
        if (is_string($c)){
            $this->options['ShipToCountryCode'] = $c;
        } else {
            return false;
        }
    }

    /**
     * Sets the destination country subdivision code. (Optional)
     * @param string $c <p>Country subdivision code in ISO 3166-2 format</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setCountrySubdivision($c) {
        if (is_string($c)){
            $this->options['ShipToCountrySubdivisionCode'] = $c;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the labeling preference. (Optional)
     * 
     * If this parameter is not set, Amazon will assume SELLER_LABEL.
     * @param string $s <p>"SELLER_LABEL", "AMAZON_LABEL_ONLY", "AMAZON_LABEL_PREFERRED"</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setLabelPreference($s){
        if (is_string($s) && $s){
            if ($s == 'SELLER_LABEL' || $s == 'AMAZON_LABEL_ONLY' || $s == 'AMAZON_LABEL_PREFERRED'){
                $this->options['LabelPrepPreference'] = $s;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Sets the items. (Required)
     * 
     * This method sets the Fulfillment Order ID to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * The array provided should contain a list of arrays, each with the following fields:
     * <ul>
     * <li><b>SellerSKU</b> - max: 200 char</li>
     * <li><b>Quantity</b> - numeric</li>
     * <li><b>ASIN</b> (optional) - must be valid</li>
     * <li><b>QuantityInCase</b> (optional) - numeric</li>
     * <li><b>Condition</b> (optional) - Valid Values:</li>
     * <ul>
     * <li>NewItem</li>
     * <li>NewWithWarranty</li>
     * <li>NewOEM</li>
     * <li>NewOpenBox</li>
     * <li>UsedLikeNew</li>
     * <li>UsedVeryGood</li>
     * <li>UsedGood</li>
     * <li>UsedAcceptable</li>
     * <li>UsedPoor</li>
     * <li>UsedRefurbished</li>
     * <li>CollectibleLikeNew</li>
     * <li>CollectibleVeryGood</li>
     * <li>CollectibleGood</li>
     * <li>CollectibleAcceptable</li>
     * <li>CollectiblePoor</li>
     * <li>RefurbishedWithWarranty</li>
     * <li>Refurbished</li>
     * <li>Club</li>
     * </ul>
     * <li><b>PrepDetailsList</b> (optional) - Array with keys "PrepInstruction" and "PrepOwner".
     * Valid values for "PrepInstruction":</li>
     * <ul>
     * <li>Polybagging</li>
     * <li>BubbleWrapping</li>
     * <li>Taping</li>
     * <li>BlackShrinkWrapping</li>
     * <li>Labeling</li>
     * <li>HangGarment</li>
     * </ul>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setItems($a){
        if (!$a || is_null($a) || is_string($a)){
            $this->log("Tried to set Items to invalid values",'Warning');
            return false;
        }
        $this->resetItems();
        $i = 1;
        foreach ($a as $x){
            if (array_key_exists('SellerSKU', $x) && array_key_exists('Quantity', $x)){
                $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.SellerSKU'] = $x['SellerSKU'];
                $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.Quantity'] = $x['Quantity'];
                if (array_key_exists('ASIN', $x)){
                    $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.ASIN'] = $x['ASIN'];
                }
                if (array_key_exists('QuantityInCase', $x)){
                    $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.QuantityInCase'] = $x['QuantityInCase'];
                }
                if (array_key_exists('Condition', $x)){
                    $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.Condition'] = $x['Condition'];
                }
                if (array_key_exists('PrepDetailsList', $x) && is_array($x['PrepDetailsList'])){
                    $j = 1;
                    foreach ($x['PrepDetailsList'] as $z) {
                        if (!isset($z['PrepInstruction']) || !isset($z['PrepOwner'])) {
                            $this->log("Tried to set invalid prep details for item",'Warning');
                            continue;
                        }
                        $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.PrepDetailsList.PrepDetails.'.$j.'.PrepInstruction'] = $z['PrepInstruction'];
                        $this->options['InboundShipmentPlanRequestItems.member.'.$i.'.PrepDetailsList.PrepDetails.'.$j.'.PrepOwner'] = $z['PrepOwner'];
                        $j++;
                    }
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
     * Resets the item options.
     * 
     * Since the list of items is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    public function resetItems(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#InboundShipmentPlanRequestItems#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sends a request to Amazon to create an Inbound Shipment Plan.
     * 
     * Submits a <i>CreateInboundShipmentPlan</i> request to Amazon. In order to do this,
     * all required parameters must be set. Amazon will send back a list of Shipment Plans
     * as a response, which can be retrieved using <i>getPlan</i>.
     * Other methods are available for fetching specific values from the list.
     * @return boolean <b>TRUE</b> if success, <b>FALSE</b> if something goes wrong
     */
    public function fetchPlan(){
        if (!array_key_exists('ShipFromAddress.Name',$this->options)){
            $this->log("Address must be set in order to make a plan",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentPlanRequestItems.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to make a plan",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path->InboundShipmentPlans;
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path->InboundShipmentPlans;
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
                $this->planList[$i]['Items'][$j]['FulfillmentNetworkSKU'] = (string)$z->FulfillmentNetworkSKU;
                if (isset($z->PrepDetailsList)) {
                    foreach ($z->PrepDetailsList as $zz) {
                        $temp = array();
                        $temp['PrepInstruction'] = (string)$zz->PrepInstruction;
                        $temp['PrepOwner'] = (string)$zz->PrepOwner;
                        $this->planList[$i]['Items'][$j]['PrepDetailsList'][] = $temp;
                    }
                }
                $j++;
                
            }
            $i++;
        }
    }
    
    /**
     * Returns the supply type for the specified entry.
     * 
     * If <i>$i</i> is not specified, the entire list of plans will be returned.
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The returned array of a single plan will contain the following fields:
     * <ul>
     * <li><b>ShipToAddress</b> - See <i>getAddress</i> for details.</li>
     * <li><b>ShipmentId</b> - Unique ID for the shipment to use.</li>
     * <li><b>DestinationFulfillmentCenterId</b> - ID for the Fulfillment Center the shipment would ship to.</li>
     * <li><b>LabelPrepType</b> - Label preparation required.</li>
     * <li><b>Items</b> - See <i>getItems</i> for details.</li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to NULL.</p>
     * @return array|boolean plan array, multi-dimensional array, or <b>FALSE</b> if invalid index
     */
    public function getPlan($i = null){
        if (!isset($this->planList)){
            return false;
        } else {
            if (is_int($i)){
                return $this->planList[$i];
            } else {
                return $this->planList;
            }
        }
    }
    
    /**
     * Returns an array of only the shipping IDs for convenient use.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean list of shipping IDs, or <b>FALSE</b> if list not fetched yet
     */
    public function getShipmentIdList(){
        if (!isset($this->planList)){
            return false;
        }
        $a = array();
        foreach($this->planList as $x){
            $a[] = $x['ShipmentId'];
        }
        return $a;
    }
    
    /**
     * Returns the shipment ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getShipmentId($i = 0){
        if (!isset($this->planList)){
            return false;
        }
        if (is_int($i)){
            return $this->planList[$i]['ShipmentId'];
        } else {
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
