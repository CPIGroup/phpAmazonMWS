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
 * Fetches a list of shipments from Amazon.
 * 
 * This Amazon Inbound Core Object retrieves a list of shipments from Amazon.
 * In order to this, either a list of IDs or a list of statuses are required.
 * This object can use tokens when fetching the list.
 */
class AmazonShipmentList extends AmazonInboundCore implements Iterator{
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $shipmentList;
    protected $index = 0;
    protected $i = 0;
    
    /**
     * AmazonShipmentList fetches a list of shipments from Amazon.
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
    }
    
    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the object should automatically use tokens if it receives one.
     * 
     * If this option is set to <b>TRUE</b>, the object will automatically perform
     * the necessary operations to retrieve the rest of the list using tokens. If
     * this option is off, the object will only ever retrieve the first section of
     * the list.
     * @param boolean $b [optional] <p>Defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * sets the status filter to be used in the next request. (Required*)
     * 
     * This method sets the list of seller SKUs to be sent in the next request.
     * Setting this parameter tells Amazon to only return shipments with statuses
     * that match those in the list. This parameter is required if the Shipment ID filter
     * is not used. Below is a list of valid statuses:
     * <ul>
     * <li>WORKING</li>
     * <li>SHIPPED</li>
     * <li>IN_TRANSIT</li>
     * <li>DELIVERED</li>
     * <li>CHECKED_IN</li>
     * <li>RECEIVING</li>
     * <li>CLOSED</li>
     * <li>CANCELLED</li>
     * <li>DELETED</li>
     * <li>ERROR</li>
     * </ul>
     * @param array|string $s <p>A list of statuses, or a single status string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setStatusFilter($s){
        if (is_string($s)){
            $this->resetStatusFilter();
            $this->options['ShipmentStatusList.member.1'] = $s;
        } else if (is_array($s)){
            $this->resetStatusFilter();
            $i = 1;
            foreach($s as $x){
                $this->options['ShipmentStatusList.member.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Resets the status options.
     * 
     * Since status is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetStatusFilter(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShipmentStatusList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the shipment ID(s). (Required*)
     * 
     * This method sets the list of Shipment IDs to be sent in the next request.
     * Setting this parameter tells Amazon to only return Shipments that match
     * the IDs in the list. This parameter is required if the Shipment Status filter
     * is not used.
     * @param array|string $s <p>A list of Feed Submission IDs, or a single ID string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setIdFilter($s){
        if (is_string($s)){
            $this->resetIdFilter();
            $this->options['ShipmentIdList.member.1'] = $s;
        } else if (is_array($s)){
            $this->resetIdFilter();
            $i = 1;
            foreach($s as $x){
                $this->options['ShipmentIdList.member.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Resets the shipment ID options.
     * 
     * Since shipment ID is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetIdFilter(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShipmentIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the time frame filter for the shipments fetched. (Optional)
     * 
     * If no times are specified, times default to the current time.
     * @param dateTime $lower <p>Date the order was created after, is passed through strtotime</p>
     * @param dateTime $upper <p>Date the order was created before, is passed through strtotime</p>
     * @throws InvalidArgumentException
     */
    public function setTimeLimits($lower = null, $upper = null){
        try{
            if ($lower){
                $after = $this->genTime($lower);
            } else {
                $after = $this->genTime('- 2 min');
            }
            if ($upper){
                $before = $this->genTime($upper);
            } else {
                $before = $this->genTime('- 2 min');
            }
            $this->options['LastUpdatedAfter'] = $after;
            $this->options['LastUpdatedBefore'] = $before;
            if (isset($this->options['LastUpdatedAfter']) && 
                isset($this->options['LastUpdatedBefore']) && 
                $this->options['LastUpdatedAfter'] > $this->options['LastUpdatedBefore']){
                $this->setTimeLimits($this->options['LastUpdatedBefore'].' - 1 second',$this->options['LastUpdatedBefore']);
            }
            
        } catch (Exception $e){
            throw new InvalidArgumentException('Parameters should be timestamps.');
        }
        
    }
    
    /**
     * Removes time limit options.
     * 
     * Use this in case you change your mind and want to remove the time limit
     * parameters you previously set.
     */
    public function resetTimeLimits(){
        unset($this->options['LastUpdatedAfter']);
        unset($this->options['LastUpdatedBefore']);
    }
    
    /**
     * Fetches a list of shipments from Amazon.
     * 
     * Submits a <i>ListInboundShipments</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getShipment</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchShipments($r = true){
        if (!array_key_exists('ShipmentStatusList.member.1', $this->options) && !array_key_exists('ShipmentIdList.member.1', $this->options)){
            $this->log("Either status filter or ID filter must be set before requesting a list!",'Warning');
            return false;
        }
        
        $this->prepareToken();
        
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
        
        $this->checkToken($xml);
        
        if ($this->tokenFlag && $this->tokenUseFlag && $r === true){
            while ($this->tokenFlag){
                $this->log("Recursively fetching more shipments");
                $this->fetchShipments(false);
            }
            
        }
        
        
    }
    
    /**
     * Sets up options for using tokens.
     * 
     * This changes key options for switching between simply fetching a list and
     * fetching the rest of a list using a token. Please note: because the
     * operation for using tokens does not use any other parameters, all other
     * parameters will be removed.
     */
    protected function prepareToken(){
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListInboundShipmentsByNextToken';
        } else {
            unset($this->options['NextToken']);
            $this->options['Action'] = 'ListInboundShipments';
            $this->index = 0;
            $this->shipmentList = array();
        }
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        foreach($xml->ShipmentData->children() as $x){
            $a = array();

            if (isset($x->ShipmentId)){
                $a['ShipmentId'] = (string)$x->ShipmentId;
            }
            if (isset($x->ShipmentName)){
                $a['ShipmentName'] = (string)$x->ShipmentName;
            }

            //Address
            $a['ShipFromAddress']['Name'] = (string)$x->ShipFromAddress->Name;
            $a['ShipFromAddress']['AddressLine1'] = (string)$x->ShipFromAddress->AddressLine1;
            if (isset($x->ShipFromAddress->AddressLine2)){
                $a['ShipFromAddress']['AddressLine2'] = (string)$x->ShipFromAddress->AddressLine2;
            } else {
                $a['ShipFromAddress']['AddressLine2'] = null;
            }
            $a['ShipFromAddress']['City'] = (string)$x->ShipFromAddress->City;
            if (isset($x->ShipFromAddress->DistrictOrCounty)){
                $a['ShipFromAddress']['DistrictOrCounty'] = (string)$x->ShipFromAddress->DistrictOrCounty;
            } else {
                $a['ShipFromAddress']['DistrictOrCounty'] = null;
            }
            $a['ShipFromAddress']['StateOrProvinceCode'] = (string)$x->ShipFromAddress->StateOrProvinceCode;
            $a['ShipFromAddress']['CountryCode'] = (string)$x->ShipFromAddress->CountryCode;
            $a['ShipFromAddress']['PostalCode'] = (string)$x->ShipFromAddress->PostalCode;

            if (isset($x->DestinationFulfillmentCenterId)){
                $a['DestinationFulfillmentCenterId'] = (string)$x->DestinationFulfillmentCenterId;
            }
            if (isset($x->LabelPrepType)){
                $a['LabelPrepType'] = (string)$x->LabelPrepType;
            }
            if (isset($x->ShipmentStatus)){
                $a['ShipmentStatus'] = (string)$x->ShipmentStatus;
            }

            $a['AreCasesRequired'] = (string)$x->AreCasesRequired;

            if (isset($x->ConfirmedNeedByDate)){
                $a['ConfirmedNeedByDate'] = (string)$x->ConfirmedNeedByDate;
            }
            
            $this->shipmentList[$this->index] = $a;
            $this->index++;
        }
    }
    
    /**
     * Returns array of item lists or a single item list.
     * 
     * If <i>$i</i> is not specified, the method will fetch the items for every
     * shipment in the list. Please note that for lists with a high number of shipments,
     * this operation could take a while due to throttling. (Two seconds per order when throttled.)
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to null.</p>
     * @param boolean $token [optional] <p>whether or not to automatically use tokens when fetching items.</p>
     * @return array|AmazonShipmentItemList <i>AmazonShipmentItemList</i> object or array of objects, or <b>FALSE</b> if non-numeric index
     */
    public function fetchItems($i = null, $token = false){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_null($i)){
            $a = array();
            $n = 0;
            foreach($this->shipmentList as $x){
                $a[$n] = new AmazonShipmentItemList($this->storeName,$x['ShipmentId'],$this->mockMode,$this->mockFiles,$this->config);
                $a[$n]->setUseToken($token);
                $a[$n]->mockIndex = $this->mockIndex;
                $a[$n]->fetchItems();
                $n++;
            }
            return $a;
        } else if (is_int($i)) {
            $temp = new AmazonShipmentItemList($this->storeName,$this->shipmentList[$i]['ShipmentId'],$this->mockMode,$this->mockFiles,$this->config);
            $temp->setUseToken($token);
            $temp->mockIndex = $this->mockIndex;
            $temp->fetchItems();
            return $temp;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipment ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getShipmentId($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['ShipmentId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the name for the specified shipment.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getShipmentName($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['ShipmentName'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipping address for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>Name</b></li>
     * <li><b>AddressLine1</b></li>
     * <li><b>AddressLine2</b> (optional)</li>
     * <li><b>City</b></li>
     * <li><b>DistrictOrCounty</b> (optional)</li>
     * <li><b>StateOrProvinceCode</b> (optional)</li>
     * <li><b>CountryCode</b></li>
     * <li><b>PostalCode</b></li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return array|boolean array, or <b>FALSE</b> if Non-numeric index
     */
    public function getAddress($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['ShipFromAddress'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Destination Fulfillment Center ID for the specified shipment.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getDestinationFulfillmentCenterId($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['DestinationFulfillmentCenterId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the label prep type for the specified shipment.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getLabelPrepType($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['LabelPrepType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipment status for the specified shipment.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getShipmentStatus($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['ShipmentStatus'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns whether or not cases are required for the specified shipment.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean "true" or "false", or <b>FALSE</b> if Non-numeric index
     */
    public function getIfCasesRequired($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['AreCasesRequired'];
        } else {
            return false;
        }
    }

    /**
     * Returns the maximum arrival date for the specified shipment.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean Date in YYYY-MM-DD format, or <b>FALSE</b> if Non-numeric index
     */
    public function getConfirmedNeedByDate($i = 0){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i]['ConfirmedNeedByDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the full list.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single shipment will have the following fields:
     * <ul>
     * <li><b>ShipmentId</b> (optional)</li>
     * <li><b>ShipmentName</b> (optional)</li>
     * <li><b>ShipFromAddress</b> (see <i>getAddress</i> for details)</li>
     * <li><b>DestinationFulfillmentCenterId</b> (optional)</li>
     * <li><b>LabelPrepType</b> (optional)</li>
     * <li><b>ShipmentStatus</b> (optional)</li>
     * <li><b>AreCasesRequired</b></li>
     * </ul>
     * @param int $i [optional] <p>List index of the report to return. Defaults to NULL.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getShipment($i = null){
        if (!isset($this->shipmentList)){
            return false;
        }
        if (is_int($i)){
            return $this->shipmentList[$i];
        } else {
            return $this->shipmentList;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->shipmentList[$this->i]; 
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
        return isset($this->shipmentList[$this->i]);
    }
}
?>
