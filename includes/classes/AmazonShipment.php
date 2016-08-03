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
 * Submits a shipment to Amazon or updates it.
 * 
 * This Amazon Inbound Core object submits a request to create an inbound
 * shipment with Amazon. It can also update existing shipments. In order to
 * create or update a shipment, information from a Shipment Plan is required.
 * Use the AmazonShipmentPlanner object to retrieve this information.
 */
class AmazonShipment extends AmazonInboundCore{
    protected $shipmentId;
    
    /**
     * AmazonShipment ubmits a shipment to Amazon or updates it.
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
        
        $this->options['InboundShipmentHeader.ShipmentStatus'] = 'WORKING';
    }
    
    /**
     * Automatically fills in the necessary fields using a planner array.
     * 
     * This is a quick way to set the shipment ID, destination, label prep type, and items.
     * Note that the label preperation preference will be set to "AMAZON_LABEL_PREFERRED" if the
     * fulfillment preview selects the label type as "AMAZON_LABEL" and "SELLER_LABEL" otherwise.
     * This information is required to submit a shipment, but this method is not required.
     * @param array $x <p>plan array from <i>AmazonShipmentPlanner</i></p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function usePlan($x){
        if (is_array($x)){
            $this->options['ShipmentId'] = $x['ShipmentId'];
            
            $this->setShipmentId($x['ShipmentId']);
            $this->setDestination($x['DestinationFulfillmentCenterId']);

            //label preference is not a direct match to preview results
            if ($x['LabelPrepType'] == 'AMAZON_LABEL') {
                $this->setLabelPrepPreference('AMAZON_LABEL_PREFERRED');
            } else {
                $this->setLabelPrepPreference('SELLER_LABEL');
            }
            
            $this->setItems($x['Items']);
            
        } else {
           $this->log("usePlan requires an array",'Warning');
           return false; 
        }
    }

    /**
     * Sets the name for the shipment. (Required)
     *
     * This information is required to create a fulfillment shipment.
     * @param string $n <p>name</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setShipmentName($n) {
        if (is_string($n)) {
            $this->options['InboundShipmentHeader.ShipmentName'] = $n;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the address. (Required)
     * 
     * This method sets the shipper's address to be sent in the next request.
     * This parameter is required for creating a fulfillment order with Amazon.
     * The array provided should have the following fields:
     * <ul>
     * <li><b>Name</b> - max: 50 char</li>
     * <li><b>AddressLine1</b> - max: 180 char</li>
     * <li><b>AddressLine2</b> (optional) - max: 60 char</li>
     * <li><b>City</b> - max: 30 char</li>
     * <li><b>DistrictOrCounty</b> (optional) - max: 25 char</li>
     * <li><b>StateOrProvinceCode</b> (recommended) - 2 digits</li>
     * <li><b>CountryCode</b> - 2 digits</li>
     * <li><b>PostalCode</b> - max: 30 char</li>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setAddress($a){
        if (!$a || is_null($a) || is_string($a)){
            $this->log("Tried to set address to invalid values",'Warning');
            return false;
        }
        if (!array_key_exists('AddressLine1', $a)){
            $this->resetAddress();
            $this->log("Tried to set address with invalid array",'Warning');
            return false;
        }
        $this->resetAddress();
        $this->options['InboundShipmentHeader.ShipFromAddress.Name'] = $a['Name'];
        $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine1'] = $a['AddressLine1'];
        if (array_key_exists('AddressLine2', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2'] = $a['AddressLine2'];
        } else {
            $this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2'] = null;
        }
        $this->options['InboundShipmentHeader.ShipFromAddress.City'] = $a['City'];
        if (array_key_exists('DistrictOrCounty', $a)){
            $this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty'] = $a['DistrictOrCounty'];
        } else {
            $this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty'] = null;
        }
        $this->options['InboundShipmentHeader.ShipFromAddress.StateOrProvinceCode'] = $a['StateOrProvinceCode'];
        $this->options['InboundShipmentHeader.ShipFromAddress.CountryCode'] = $a['CountryCode'];
        $this->options['InboundShipmentHeader.ShipFromAddress.PostalCode'] = $a['PostalCode'];
    }
    
    /**
     * Resets the address options.
     * 
     * Since address is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetAddress(){
        unset($this->options['InboundShipmentHeader.ShipFromAddress.Name']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.AddressLine1']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.AddressLine2']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.City']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.DistrictOrCounty']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.StateOrProvinceCode']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.CountryCode']);
        unset($this->options['InboundShipmentHeader.ShipFromAddress.PostalCode']);
    }

    /**
     * Sets the name for the shipment. (Required)
     *
     * This information is required to create a fulfillment shipment.
     * @param string $d <p>destination fulfillment center ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setDestination($d) {
        if (is_string($d)) {
            $this->options['InboundShipmentHeader.DestinationFulfillmentCenterId'] = $d;
        } else {
            return false;
        }
    }

    /**
     * Sets the label prep type preference for the shipment. (Required)
     *
     * This information is required to create a fulfillment shipment.
     * @param string $p <p>"SELLER_LABEL", "AMAZON_LABEL_ONLY", or "AMAZON_LABEL_PREFERRED"</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setLabelPrepPreference($p) {
        if (in_array($p, array('SELLER_LABEL', 'AMAZON_LABEL_ONLY', 'AMAZON_LABEL_PREFERRED'))) {
            $this->options['InboundShipmentHeader.LabelPrepPreference'] = $p;
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
     * <li><b>SellerSKU</b> - max: 50 char</li>
     * <li><b>Quantity</b> - numeric</li>
     * <li><b>QuantityInCase</b> (optional) - numeric</li>
     * <li><b>PrepDetailsList</b> (optional) - array</li>
     * <ul>
     * <li><b>PrepInstruction</b> - "Polybagging", "BubbleWrapping", "Taping",
     * "BlackShrinkWrapping", "Labeling", or "HangGarment"</li>
     * <li><b>PrepOwner</b> - "AMAZON" or "SELLER"</li>
     * </ul>
     * <li><b>ReleaseDate</b> (optional) - date string</li>
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
        $caseflag = false;
        $i = 1;
        foreach ($a as $x){
            
            if (is_array($x) && array_key_exists('SellerSKU', $x) && array_key_exists('Quantity', $x)){
                $this->options['InboundShipmentItems.member.'.$i.'.SellerSKU'] = $x['SellerSKU'];
                $this->options['InboundShipmentItems.member.'.$i.'.QuantityShipped'] = $x['Quantity'];
                if (array_key_exists('QuantityInCase', $x)){
                    $this->options['InboundShipmentItems.member.'.$i.'.QuantityInCase'] = $x['QuantityInCase'];
                    $caseflag = true;
                }
                if (array_key_exists('PrepDetailsList', $x) && is_array($x['PrepDetailsList'])){
                    $j = 1;
                    foreach ($x['PrepDetailsList'] as $z) {
                        if (!isset($z['PrepInstruction']) || !isset($z['PrepOwner'])) {
                            $this->log("Tried to set invalid prep details for item",'Warning');
                            continue;
                        }
                        $this->options['InboundShipmentItems.member.'.$i.'.PrepDetailsList.PrepDetails.'.$j.'.PrepInstruction'] = $z['PrepInstruction'];
                        $this->options['InboundShipmentItems.member.'.$i.'.PrepDetailsList.PrepDetails.'.$j.'.PrepOwner'] = $z['PrepOwner'];
                        $j++;
                    }
                }
                if (array_key_exists('ReleaseDate', $x)){
                    $this->options['InboundShipmentItems.member.'.$i.'.ReleaseDate'] = $this->genTime($x['ReleaseDate']);
                }
                $i++;
            } else {
                $this->resetItems();
                $this->log("Tried to set Items with invalid array",'Warning');
                return false;
            }
        }
        $this->setCases($caseflag);
    }
    
    /**
     * Resets the item options.
     * 
     * Since the list of items is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetItems(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#InboundShipmentItems#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the shipment status. (Required)
     * @param string $s <p>"WORKING", "SHIPPED", or "CANCELLED" (updating only)</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setStatus($s){
        if (is_string($s) && $s){
            if ($s == 'WORKING' || $s == 'SHIPPED' || $s == 'CANCELLED'){
                $this->options['InboundShipmentHeader.ShipmentStatus'] = $s;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Sets the shipment ID. (Required)
     * @param string $s <p>Shipment ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setShipmentId($s){
        if (is_string($s) && $s){
            $this->options['ShipmentId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Set whether or not cases are required. (Required if cases used)
     * @param boolean $b <p>Defaults to <b>TRUE</b>.</p>
     */
    protected function setCases($b = true){
        if ($b){
            $this->options['InboundShipmentHeader.AreCasesRequired'] = 'true';
        } else {
            $this->options['InboundShipmentHeader.AreCasesRequired'] = 'false';
        }
    }
    
    /**
     * Sends a request to Amazon to create an Inbound Shipment.
     * 
     * Submits a <i>CreateInboundShipment</i> request to Amazon. In order to do this,
     * all parameters must be set. Data for these headers can be generated using an
     * <i>AmazonShipmentPlanner</i> object. Amazon will send back the Shipment ID
     * as a response, which can be retrieved using <i>getShipmentId</i>.
     * @return boolean <b>TRUE</b> if success, <b>FALSE</b> if something goes wrong
     */
    public function createShipment(){
        if (!isset($this->options['ShipmentId'])){
            $this->log("Shipment ID must be set in order to create it",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentHeader.ShipmentName',$this->options)){
            $this->log("Header must be set in order to make a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentHeader.ShipFromAddress.Name',$this->options)){
            $this->log("Address must be set in order to make a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentItems.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to make a shipment",'Warning');
            return false;
        }
        $this->options['Action'] = 'CreateInboundShipment';
        
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
        $this->shipmentId = (string)$xml->ShipmentId;
        
        if ($this->shipmentId){
            $this->log("Successfully created Shipment #".$this->shipmentId);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Sends a request to Amazon to create an Inbound Shipment.
     * 
     * Submits a <i>UpdateInboundShipment</i> request to Amazon. In order to do this,
     * all parameters must be set. Data for these headers can be generated using an
     * <i>AmazonShipmentPlanner</i> object. Amazon will send back the Shipment ID
     * as a response, which can be retrieved using <i>getShipmentId</i>.
     * @return boolean <b>TRUE</b> if success, <b>FALSE</b> if something goes wrong
     */
    public function updateShipment(){
        if (!isset($this->options['ShipmentId'])){
            $this->log("Shipment ID must be set in order to update it",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentHeader.ShipmentName',$this->options)){
            $this->log("Header must be set in order to update a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentHeader.ShipFromAddress.Name',$this->options)){
            $this->log("Address must be set in order to update a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('InboundShipmentItems.member.1.SellerSKU',$this->options)){
            $this->log("Items must be set in order to update a shipment",'Warning');
            return false;
        }
        $this->options['Action'] = 'UpdateInboundShipment';
        
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
        $this->shipmentId = (string)$xml->ShipmentId;
        
        if ($this->shipmentId){
            $this->log("Successfully updated Shipment #".$this->shipmentId);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipment ID of the newly created/modified order.
     * @return string|boolean single value, or <b>FALSE</b> if Shipment ID not fetched yet
     */
    public function getShipmentId(){
        if (isset($this->shipmentId)){
            return $this->shipmentId;
        } else {
            return false;
        }
    }
    
}
?>
