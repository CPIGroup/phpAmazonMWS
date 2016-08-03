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
 * Fetches a list of fulfillment orders from Amazon.
 * 
 * This Amazon Outbound Core object can retrieve a list of
 * previously created fulfillment orders. While no parameters
 * are required, filters for start time and method are available.
 * This object can use tokens when retrieving the list.
 */
class AmazonFulfillmentOrderList extends AmazonOutboundCore implements Iterator{
    protected $orderList;
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $i = 0;
    protected $index = 0;
    
    /**
     * AmazonFulfillmentOrderList retrieves a list of fulfillment orders from Amazon.
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
        
        $this->options['Action'] = 'ListAllFulfillmentOrders';
    }
    
    /**
     * Sets the start time. (Optional)
     * 
     * This method sets the earliest time frame to be sent in the next request.
     * If this parameter is set, Amazon will only return fulfillment orders that
     * were last updated after the time set. If this parameter is not set, Amazon
     * will only return orders that were updated in the past 36 hours.
     * The parameter is passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * @param string $s <p>Time string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setStartTime($s){
        if (is_string($s)){
            $time = $this->genTime($s);
            $this->options['QueryStartDateTime'] = $time;
        } else {
            return false;
        }
    }
    
    /**
     * The "FulfillmentMethod" option is no longer used.
     * @return boolean <b>FALSE</b>
     * @deprecated since 1.3.0
     */
    public function setMethodFilter($s){
        $this->log("The FulfillmentMethod option is no longer used for getting fulfillment orders.", 'Warning');
        return FALSE;
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
     * Fetches the fulfillment order list from Amazon.
     * 
     * Submits a <i>ListAllFulfillmentOrders</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getOrder</i>.
     * This operation can potentially involve tokens.
     * @param boolean <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchOrderList($r = true){
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
        
        $this->parseXML($xml->FulfillmentOrders);
        
        $this->checkToken($xml);
        
        if ($this->tokenFlag && $this->tokenUseFlag && $r === true){
            while ($this->tokenFlag){
                $this->log("Recursively fetching more Orders");
                $this->fetchOrderList(false);
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
            $this->options['Action'] = 'ListAllFulfillmentOrdersByNextToken';
            unset($this->options['QueryStartDateTime']);
        } else {
            $this->options['Action'] = 'ListAllFulfillmentOrders';
            unset($this->options['NextToken']);
            $this->orderList = array();
            $this->index = 0;
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
        foreach($xml->children() as $x){
            $i = $this->index;
            $this->orderList[$i]['SellerFulfillmentOrderId'] = (string)$x->SellerFulfillmentOrderId;
            $this->orderList[$i]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->orderList[$i]['DisplayableOrderId'] = (string)$x->DisplayableOrderId;
            $this->orderList[$i]['DisplayableOrderDateTime'] = (string)$x->DisplayableOrderDateTime;
            $this->orderList[$i]['DisplayableOrderComment'] = (string)$x->DisplayableOrderComment;
            $this->orderList[$i]['ShippingSpeedCategory'] = (string)$x->ShippingSpeedCategory;
            if (isset($x->DeliveryWindow)) {
                $this->orderList[$i]['DeliveryWindow']['StartDateTime'] = (string)$x->DeliveryWindow->StartDateTime;
                $this->orderList[$i]['DeliveryWindow']['EndDateTime'] = (string)$x->DeliveryWindow->EndDateTime;
            }
            if (isset($x->DestinationAddress)){
                $this->orderList[$i]['DestinationAddress']['Name'] = (string)$x->DestinationAddress->Name;
                $this->orderList[$i]['DestinationAddress']['Line1'] = (string)$x->DestinationAddress->Line1;
                if (isset($x->DestinationAddress->Line2)){
                    $this->orderList[$i]['DestinationAddress']['Line2'] = (string)$x->DestinationAddress->Line2;
                }
                if (isset($x->DestinationAddress->Line3)){
                    $this->orderList[$i]['DestinationAddress']['Line3'] = (string)$x->DestinationAddress->Line3;
                }
                if (isset($x->DestinationAddress->DistrictOrCounty)){
                    $this->orderList[$i]['DestinationAddress']['DistrictOrCounty'] = (string)$x->DestinationAddress->DistrictOrCounty;
                }
                $this->orderList[$i]['DestinationAddress']['City'] = (string)$x->DestinationAddress->City;
                $this->orderList[$i]['DestinationAddress']['StateOrProvinceCode'] = (string)$x->DestinationAddress->StateOrProvinceCode;
                $this->orderList[$i]['DestinationAddress']['CountryCode'] = (string)$x->DestinationAddress->CountryCode;
                if (isset($x->DestinationAddress->PostalCode)){
                    $this->orderList[$i]['DestinationAddress']['PostalCode'] = (string)$x->DestinationAddress->PostalCode;
                }
                if (isset($x->DestinationAddress->PhoneNumber)){
                    $this->orderList[$i]['DestinationAddress']['PhoneNumber'] = (string)$x->DestinationAddress->PhoneNumber;
                }
            }
            if (isset($x->FulfillmentAction)){
                $this->orderList[$i]['FulfillmentAction'] = (string)$x->FulfillmentAction;
            }
            if (isset($x->FulfillmentPolicy)){
                $this->orderList[$i]['FulfillmentPolicy'] = (string)$x->FulfillmentPolicy;
            }
            if (isset($x->FulfillmentMethod)){
                //deprecated
                $this->orderList[$i]['FulfillmentMethod'] = (string)$x->FulfillmentMethod;
            }
            $this->orderList[$i]['ReceivedDateTime'] = (string)$x->ReceivedDateTime;
            $this->orderList[$i]['FulfillmentOrderStatus'] = (string)$x->FulfillmentOrderStatus;
            $this->orderList[$i]['StatusUpdatedDateTime'] = (string)$x->StatusUpdatedDateTime;
            if (isset($x->NotificationEmailList)){
                $j = 0;
                foreach($x->NotificationEmailList->children() as $y){
                    $this->orderList[$i]['NotificationEmailList'][$j++] = (string)$y;
                }
            }
            if (isset($x->CODSettings->IsCODRequired)){
                $this->orderList[$i]['CODSettings']['IsCODRequired'] = (string)$x->CODSettings->IsCODRequired;
            }
            if (isset($x->CODSettings->CODCharge)){
                $this->orderList[$i]['CODSettings']['CODCharge']['CurrencyCode'] = (string)$x->CODSettings->CODCharge->CurrencyCode;
                $this->orderList[$i]['CODSettings']['CODCharge']['Value'] = (string)$x->CODSettings->CODCharge->Value;
            }
            if (isset($x->CODSettings->CODChargeTax)){
                $this->orderList[$i]['CODSettings']['CODChargeTax']['CurrencyCode'] = (string)$x->CODSettings->CODChargeTax->CurrencyCode;
                $this->orderList[$i]['CODSettings']['CODChargeTax']['Value'] = (string)$x->CODSettings->CODChargeTax->Value;
            }
            if (isset($x->CODSettings->ShippingCharge)){
                $this->orderList[$i]['CODSettings']['ShippingCharge']['CurrencyCode'] = (string)$x->CODSettings->ShippingCharge->CurrencyCode;
                $this->orderList[$i]['CODSettings']['ShippingCharge']['Value'] = (string)$x->CODSettings->ShippingCharge->Value;
            }
            if (isset($x->CODSettings->ShippingChargeTax)){
                $this->orderList[$i]['CODSettings']['ShippingChargeTax']['CurrencyCode'] = (string)$x->CODSettings->ShippingChargeTax->CurrencyCode;
                $this->orderList[$i]['CODSettings']['ShippingChargeTax']['Value'] = (string)$x->CODSettings->ShippingChargeTax->Value;
            }
            $this->index++;
        }
    }
    
    /**
     * Creates a list of full order objects from the list. (Warning: could take a while.)
     * 
     * This method automatically creates an array of <i>AmazonFulfillmentOrder</i> objects
     * and fetches all of their full information from Amazon. Because of throttling, this
     * could take a while if the list has more than a few orders.
     * @return array|boolean array of <i>AmazonFulfillmentOrder</i> objects, or <b>FALSE</b> if list not filled yet
     */
    public function getFullList(){
        if (!isset($this->orderList)){
            return false;
        }
        $list = array();
        $i = 0;
        foreach($this->orderList as $x){
            $list[$i] = new AmazonFulfillmentOrder($this->storeName,$x['SellerFulfillmentOrderId'],$this->mockMode,$this->mockFiles,$this->config);
            $list[$i]->mockIndex = $this->mockIndex;
            $list[$i]->fetchOrder();
            $i++;
        }
        return $list;
    }
    
    /**
     * Returns the specified fulfillment order, or all of them.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single fulfillment order will have the following fields:
     * <ul>
     * <li><b>SellerFulfillmentOrderId</b> - the ID for the order</li>
     * <li><b>DisplayableOrderId</b> - your ID for the order</li>
     * <li><b>DisplayableOrderDateTime</b> - the time the order was created, in ISO 8601 date format</li>
     * <li><b>ShippingSpeedCategory</b> - shipping speed for the order</li>
     * <li><b>DeliveryWindow</b> (optional) - array of ISO 8601 dates with the keys "StartDateTime" and "EndDateTime"</li>
     * <li><b>DestinationAddress</b> - address array, see <i>AmazonFulfillmentOrderCreator</i> for more details</li>
     * <li><b>FulfillmentAction</b> (optional) - "Ship" or "Hold"</li>
     * <li><b>FulfillmentPolicy</b> (optional) - "FillOrKill", "FillAll", or "FillAllAvailable"</li>
     * <li><b>ReceivedDateTime</b> - the time the order was received by the Amazon fulfillment center, in ISO 8601 date format</li>
     * <li><b>FulfillmentOrderStatus</b> - the status of the order</li>
     * <li><b>StatusUpdatedDateTime</b> - the time the status was last updated, in ISO 8601 date format</li>
     * <li><b>NotificationEmailList</b> (optional) - list of email addresses</li>
     * <li><b>CODSettings</b> (optional) - array, see <i>AmazonFulfillmentOrderCreator</i> for more details</li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.</p>
     * @return array|boolean array, multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getOrder($i = null){
        if (!isset($this->orderList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->orderList[$i];
        } else {
            return $this->orderList;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->orderList[$this->i]; 
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
        return isset($this->orderList[$this->i]);
    }
    
}
?>
