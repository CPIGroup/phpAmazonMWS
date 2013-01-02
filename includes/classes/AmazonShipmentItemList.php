<?php
/**
 * Fetches a list of shipment items from Amazon.
 * 
 * This Amazon Inbound Core object retrieves a list of items for the given
 * shipment from Amazon. In order to get the list, a shipment ID is required.
 * An optional paramter is available to narrow the returned items.
 */
class AmazonShipmentItemList extends AmazonInboundCore implements Iterator{
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    private $itemList;
    private $index = 0;
    private $i = 0;
    
    /**
     * Fetches a list of items from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param string $id [optional] <p>The order ID to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s, $id = null, $mock = false, $m = null, $config = null) {
        parent::__construct($s, $mock, $m, $config);
        
        if ($id){
            $this->setShipmentId($id);
        }
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
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
            $this->tokenItemFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the shipment ID. (Required)
     * 
     * This method sets the shipment ID to be sent in the next request.
     * This parameter is required for fetching the shipment's items from Amazon.
     * @param string $n <p>Shipment ID</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
     */
    public function setShipmentId($s){
        if (is_string($s)){
            $this->options['ShipmentId'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the time frame filter for the shipment items fetched. (Optional)
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
            if ($after > $before){
                $after = $before - 1;
            }
            $this->options['LastUpdatedAfter'] = $after;
            $this->options['LastUpdatedBefore'] = $before;
            
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
     * Fetches a list of shipment items from Amazon.
     * 
     * Submits a <i>ListInboundShipmentItems</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getItems</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @return boolean <p><b>FALSE</b> if something goes wrong</p>
     */
    public function fetchItems(){
        if (!array_key_exists('ShipmentId', $this->options)){
            $this->log("Shipment ID must be set before requesting items!",'Warning');
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
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more shipment items");
            $this->fetchItems();
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
            $this->options['Action'] = 'ListInboundShipmentItemsByNextToken';
        } else {
            unset($this->options['NextToken']);
            $this->options['Action'] = 'ListInboundShipmentItems';
            $this->index = 0;
            $this->itemList = array();
        }
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLObject $xml <p>The XML response from Amazon.</p>
     * @return boolean <p><b>FALSE</b> if no XML data is found</p>
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        $a = array();
        foreach($xml->ItemData->children() as $x){

            if (isset($x->ShipmentId)){
                $a['ShipmentId'] = (string)$x->ShipmentId;
            }
            $a['SellerSKU'] = (string)$x->SellerSKU;
            if (isset($x->FulfillmentNetworkSKU)){
                $a['FulfillmentNetworkSKU'] = (string)$x->FulfillmentNetworkSKU;
            }
            $a['QuantityShipped'] = (string)$x->QuantityShipped;
            if (isset($x->QuantityReceived)){
                $a['QuantityReceived'] = (string)$x->QuantityReceived;
            }
            if (isset($x->QuantityInCase)){
                $a['QuantityInCase'] = (string)$x->QuantityInCase;
            }
            
            $this->itemList[$this->index] = $a;
            $this->index++;
        }
    }
    
    /**
     * Returns the shipment ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean <p>single value, or <b>FALSE</b> if Non-numeric index</p>
     */
    public function getShipmentId($i = 0){
        if (!isset($this->itemList)){
            return false;
        }
        if (is_int($i)){
            return $this->itemList[$i]['ShipmentId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the seller SKU for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean <p>single value, or <b>FALSE</b> if Non-numeric index</p>
     */
    public function getSellerSKU($i = 0){
        if (!isset($this->itemList)){
            return false;
        }
        if (is_int($i)){
            return $this->itemList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Fulfillment Network SKU for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean <p>single value, or <b>FALSE</b> if Non-numeric index</p>
     */
    public function getFulfillmentNetworkSKU($i = 0){
        if (!isset($this->itemList)){
            return false;
        }
        if (is_int($i)){
            return $this->itemList[$i]['FulfillmentNetworkSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity shipped for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean <p>single value, or <b>FALSE</b> if Non-numeric index</p>
     */
    public function getQuantityShipped($i = 0){
        if (!isset($this->itemList)){
            return false;
        }
        if (is_int($i)){
            return $this->itemList[$i]['QuantityShipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity received for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean <p>single value, or <b>FALSE</b> if Non-numeric index</p>
     */
    public function getQuantityReceived($i = 0){
        if (!isset($this->itemList)){
            return false;
        }
        if (is_int($i)){
            return $this->itemList[$i]['QuantityReceived'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity in cases for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean <p>single value, or <b>FALSE</b> if Non-numeric index</p>
     */
    public function getQuantityInCase($i = 0){
        if (!isset($this->itemList)){
            return false;
        }
        if (is_int($i)){
            return $this->itemList[$i]['QuantityInCase'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the full list.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single shipment item will have the following fields:
     * <ul>
     * <li><b>ShipmentId</b></li>
     * <li><b>SellerSKU</b></li>
     * <li><b>FulfillmentNetworkSKU</b></li>
     * <li><b>QuantityShipped</b></li>
     * <li><b>QuantityReceived</b></li>
     * <li><b>QuantityInCase</b></li>
     * </ul>
     * @param int $i [optional] <p>List index of the item to return. Defaults to NULL.</p>
     * @return array|boolean <p>multi-dimensional array, or <b>FALSE</b> if list not filled yet</p>
     */
    public function getItems($i = null){
        if (!isset($this->itemList)){
            return false;
        }
        if (is_int($i)){
            return $this->itemList[$i];
        } else {
            return $this->itemList;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->itemList[$this->i]; 
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
        return isset($this->itemList[$this->i]);
    }
}
?>
