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
 * Fetches list of inventory supplies from Amazon.
 * 
 * This Amazon Inventory Core object retrieves a list of inventory supplies
 * from Amazon. This is the only object in the Amazon Inventory Core. This
 * object can use tokens when retrieving the list.
 */
class AmazonInventoryList extends AmazonInventoryCore implements Iterator{
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $supplyList;
    protected $index = 0;
    protected $i = 0;
    
    /**
     * AmazonInventoryList fetches a list of inventory supplies Amazon.
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
     * Sets the start time. (Required*)
     * 
     * This method sets the earliest time frame to be sent in the next request.
     * Setting this parameter tells Amazon to only return inventory supplies that
     * were updated after the given time.
     * If this parameters is set, seller SKUs cannot be set.
     * The parameter is passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * @param string $t <p>Time string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setStartTime($t = null){
        if (is_string($t) && $t){
            $after = $this->genTime($t);
        } else {
            $after = $this->genTime('- 2 min');
        }
        $this->options['QueryStartDateTime'] = $after;
        $this->resetSkus();
        
    }
    
    /**
     * Sets the feed seller SKU(s). (Required*)
     * 
     * This method sets the list of seller SKUs to be sent in the next request.
     * Setting this parameter tells Amazon to only return inventory supplies that match
     * the IDs in the list. If this parameter is set, Start Time cannot be set.
     * @param array|string $a <p>A list of Seller SKUs, or a single ID string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setSellerSkus($a){
        if (is_string($a)){
            $this->resetSkus();
            $this->options['SellerSkus.member.1'] = $a;
        } else if (is_array($a)){
            $this->resetSkus();
            $i = 1;
            foreach($a as $x){
                $this->options['SellerSkus.member.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
        unset($this->options['QueryStartDateTime']);
    }
    
    /**
     * Resets the seller SKU options.
     * 
     * Since seller SKU is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetSkus(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#SellerSkus.member.#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets whether or not to get detailed results back. (Optional)
     * 
     * If this parameter is set to "Detailed", the list returned will contain
     * extra information regarding availability. If this parameter is not set,
     * Amazon will return a Basic response.
     * @param string $s <p>"Basic" or "Detailed"</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setResponseGroup($s){
        if ($s == 'Basic' || $s == 'Detailed'){
            $this->options['ResponseGroup'] = $s;
        } else {
            return false;
        }
    }
    
     /**
     * Fetches the inventory supply list from Amazon.
     * 
     * Submits a <i>ListInventorySupply</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getSupply</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchInventoryList($r = true){
        if (!isset($this->options['QueryStartDateTime']) && !isset($this->options['SellerSkus.member.1'])){
            $this->setStartTime();
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
        
        $this->parseXML($xml->InventorySupplyList);
        
        $this->checkToken($xml);
        
        if ($this->tokenFlag && $this->tokenUseFlag && $r === true){
            while ($this->tokenFlag){
                $this->log("Recursively fetching more Inventory Supplies");
                $this->fetchInventoryList(false);
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
            $this->options['Action'] = 'ListInventorySupplyByNextToken';
            unset($this->options['QueryStartDateTime']);
            unset($this->options['ResponseGroup']);
            $this->resetSkus();
        } else {
            $this->options['Action'] = 'ListInventorySupply';
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->supplyList = array();
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
            $this->supplyList[$this->index]['SellerSKU'] = (string)$x->SellerSKU;
            $this->supplyList[$this->index]['ASIN'] = (string)$x->ASIN;
            $this->supplyList[$this->index]['TotalSupplyQuantity'] = (string)$x->TotalSupplyQuantity;
            $this->supplyList[$this->index]['FNSKU'] = (string)$x->FNSKU;
            $this->supplyList[$this->index]['Condition'] = (string)$x->Condition;
            $this->supplyList[$this->index]['InStockSupplyQuantity'] = (string)$x->InStockSupplyQuantity;
            if ((int)$x->TotalSupplyQuantity > 0){
                if ($x->EarliestAvailability->TimepointType == 'DateTime'){
                    $this->supplyList[$this->index]['EarliestAvailability'] = (string)$x->EarliestAvailability->DateTime;
                } else {
                    $this->supplyList[$this->index]['EarliestAvailability'] = (string)$x->EarliestAvailability->TimepointType;
                }
            }
            if (isset($this->options['ResponseGroup']) && $this->options['ResponseGroup'] == 'Detailed' && isset($x->SupplyDetail)){
                $j = 0;
                foreach($x->SupplyDetail->children() as $z){
                    if ((string)$z->EarliestAvailableToPick->TimepointType == 'DateTime'){
                        $this->supplyList[$this->index]['SupplyDetail'][$j]['EarliestAvailableToPick'] = (string)$z->EarliestAvailableToPick->DateTime;
                    } else {
                        $this->supplyList[$this->index]['SupplyDetail'][$j]['EarliestAvailableToPick'] = (string)$z->EarliestAvailableToPick->TimepointType;
                    }
                    if ((string)$z->LatestAvailableToPick->TimepointType == 'DateTime'){
                        $this->supplyList[$this->index]['SupplyDetail'][$j]['LatestAvailableToPick'] = (string)$z->LatestAvailableToPick->DateTime;
                    } else {
                        $this->supplyList[$this->index]['SupplyDetail'][$j]['LatestAvailableToPick'] = (string)$z->LatestAvailableToPick->TimepointType;
                    }
                    $this->supplyList[$this->index]['SupplyDetail'][$j]['Quantity'] = (string)$z->Quantity;
                    $this->supplyList[$this->index]['SupplyDetail'][$j]['SupplyType'] = (string)$z->SupplyType;
                    $j++;
                }
            }
            $this->index++;
        }
    }
    
    /**
     * Returns the specified fulfillment order, or all of them.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single fulfillment order will have the following fields:
     * <ul>
     * <li><b>SellerSKU</b> - the seller SKU for the item</li>
     * <li><b>ASIN</b> - the ASIN for the item</li>
     * <li><b>TotalSupplyQuantity</b> - total number available, including in transit</li>
     * <li><b>FNSKU</b> - the Fulfillment Network SKU for the item</li>
     * <li><b>Condition</b> - the condition the item</li>
     * <li><b>InStockSupplyQuantity</b> - total number in a fulfillment center, not counting items in transit</li>
     * <li><b>EarliestAvailability</b> (optional) - time when the item is expected to be available if TotalSupplyQuantity is greater than 0</li>
     * <li><b>SupplyDetail</b> (optional) - multi-dimensional array of extra information returned when the Response Group is set to "Detailed"</li>
     * <ul>
     * <li><b>Quantity</b> - quantity fo a specific item</li>
     * <li><b>SupplyType</b> - "InStock", "Inbound", or "Transfer"</li>
     * <li><b>EarliestAvailableToPick</b> - time point, possibly in ISO 8601 date format</li>
     * <li><b>LatestAvailableToPick</b> - time point, possibly in ISO 8601 date format</li>
     * </ul>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.</p>
     * @return array|boolean array, multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getSupply($i = null){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->supplyList[$i];
        } else {
            return $this->supplyList;
        }
    }
    
    /**
     * Returns the seller SKU for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getSellerSku($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i)){
            return $this->supplyList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the ASIN for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getASIN($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i)){
            return $this->supplyList[$i]['ASIN'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the total supply quantity for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getTotalSupplyQuantity($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i)){
            return $this->supplyList[$i]['TotalSupplyQuantity'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the fulfillment network SKU for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getFNSKU($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i)){
            return $this->supplyList[$i]['FNSKU'];
        } else {
            return false;
        }
    }

    /**
     * Returns the item condition for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getCondition($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i)){
            return $this->supplyList[$i]['Condition'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the in-stock supply quantity for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getInStockSupplyQuantity($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i)){
            return $this->supplyList[$i]['InStockSupplyQuantity'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the earliest availability for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getEarliestAvailability($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i) && array_key_exists('EarliestAvailability', $this->supplyList[$i])){
            return $this->supplyList[$i]['EarliestAvailability'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the ASIN for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If <i>$j</i> is specified, it will return a single supply detail. Otherwise
     * it will return a list of all details for a given supply.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Detail index to retrieve the value from. Defaults to NULL.</p>
     * @return array|boolean array of arrays, single detail array, or <b>FALSE</b> if Non-numeric index
     */
    public function getSupplyDetails($i = 0, $j = null){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            if (is_numeric($j)) {
                return $this->supplyList[$i]['SupplyDetail'][$j];
            } else {
                return $this->supplyList[$i]['SupplyDetail'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns the earliest pick timeframe for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Detail index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getEarliestAvailableToPick($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['EarliestAvailableToPick'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the latest pick timeframe for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Detail index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getLatestAvailableToPick($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['LatestAvailableToPick'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the detail quantity for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Detail index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getQuantity($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['Quantity'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the supply type for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Detail index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getSupplyType($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_int($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['SupplyType'];
        } else {
            return false;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->supplyList[$this->i]; 
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
        return isset($this->supplyList[$this->i]);
    }
}
?>
