<?php
/**
 * Pulls a list of Orders and turn them into an array of AmazonOrder objects.
 * 
 * This Amazon Orders Core object can retrieve a list of orders from Amazon
 * and store them in an array of AmazonOrder objects. A number of filters
 * are available to narrow the number of orders returned, but none of them
 * are required. This object can use tokens when retrieving the list.
 */
class AmazonOrderList extends AmazonOrderCore implements Iterator{
    private $orderList;
    private $i = 0;
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $index = 0;

    /**
     * Amazon Order Lists pull a set of Orders and turn them into an array of AmazonOrder objects.
     * @param string $s name of store, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if(array_key_exists('marketplaceId', $store[$s])){
            $this->options['MarketplaceId.Id.1'] = $store[$s]['marketplaceId'];
        } else {
            $this->log("Marketplace ID is missing",'Urgent');
        }
        
        $this->throttleLimit = $throttleLimitOrderList;
        $this->throttleTime = $throttleTimeOrderList;
        $this->throttleGroup = 'ListOrders';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
            $this->throttleCount = $this->throttleLimit;
        }
    }
    
    /**
     * Returns whether or not the Order List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the OrderList should automatically use tokens if it receives one.
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the time frame for the orders fetched.
     * 
     * Sets the time frame for the orders fetched. If no times are specified, times default to the current time
     * @param string $mode "Created" or "Modified"
     * @param dateTime $lower Date the order was created after
     * @param dateTime $upper Date the order was created before
     * @return boolean false on failure
     */
    public function setLimits($mode,$lower = null,$upper = null){
        try{
            if ($lower){
                $after = $this->genTime($lower);
            } else {
                $after = $this->genTime('- 2 min');
            }
            if ($upper){
                $before = $this->genTime($upper);
            }
            if ($mode == 'Created'){
                $this->options['CreatedAfter'] = $after;
                if ($before) {
                    $this->options['CreatedBefore'] = $before;
                }
                unset($this->options['LastUpdatedAfter']);
                unset($this->options['LastUpdatedBefore']);
            } else if ($mode == 'Modified'){
                $this->options['LastUpdatedAfter'] = $after;
                if ($before){
                    $this->options['LastUpdatedBefore'] = $before;
                }
                unset($this->options['CreatedAfter']);
                unset($this->options['CreatedBefore']);
            } else {
                $this->log('First parameter should be either "Created" or "Modified".','Warning');
                return false;
            }
            
        } catch (Exception $e){
            $this->log('The parameters given broke strtotime().','Warning');
            return false;
        }
        
    }
    
    /**
     * Sets option for status filter of next request
     * @param array|string $list array of strings, or a single string
     * @return boolean false on failure
     */
    public function setOrderStatusFilter($list){
        if (is_string($list)){
            //if single string, set as filter
            $this->resetOrderStatusFilter();
            $this->options['OrderStatus.Status.1'] = $list;
        } else if (is_array($list)){
            //if array of strings, set all filters
            $this->resetOrderStatusFilter();
            $i = 1;
            foreach($list as $x){
                $this->options['OrderStatus.Status.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Resets the Order Status Filter to default
     */
    public function resetOrderStatusFilter(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#OrderStatus#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets (or resets) the Fulfillment Channel Filter
     * @param string $filter 'AFN' or 'MFN' or null
     * @return boolean false on failure
     */
    public function setFulfillmentChannelFilter($filter){
        if ($filter == 'AFN' || $filter == 'MFN'){
            $this->options['FulfillmentChannel.Channel.1'] = $filter;
        } else if (is_null($filter)){
            unset($this->options['FulfillmentChannel.Channel.1']);
        } else {
            return false;
        }
    }
    
    /**
     * Sets option for payment method filter of next request
     * @param array|string $list array of strings, or a single string
     * @return boolean false on failure
     */
    public function setPaymentMethodFilter($list){
        if (is_string($list)){
            //if single string, set as filter
            $this->resetPaymentMethodFilter();
            $this->options['PaymentMethod.1'] = $list;
        } else if (is_array($list)){
            //if array of strings, set all filters
            $this->resetPaymentMethodFilter();
            $i = 1;
            foreach($list as $x){
                $this->options['PaymentMethod.'.$i++] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Resets the Payment Method Filter to default
     */
    public function resetPaymentMethodFilter(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#PaymentMethod#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets (or resets) the Email Filter. This resets certain fields.
     * 
     * Sets (or resets) the Seller Order ID Filter. The following filter options are disabled by this function:
     * -SellerOrderId
     * -OrderStatus
     * -PaymentMethod
     * -FulfillmentChannel
     * -LastUpdatedAfter
     * -LastUpdatedBefore
     * @param string|null $filter string or null
     * @return boolean false on failure
     */
    public function setEmailFilter($filter){
        if (is_string($filter)){
            $this->options['BuyerEmail'] = $filter;
            //these fields must be disabled
            unset($this->options['SellerOrderId']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else if (is_null($filter)){
            unset($this->options['BuyerEmail']);
        } else {
            return false;
        }
    }
    
    /**
     * Sets (or resets) the Seller Order ID Filter. This resets certain fields.
     * 
     * Sets (or resets) the Seller Order ID Filter. The following filter options are disabled by this function:
     * -BuyerEmail
     * -OrderStatus
     * -PaymentMethod
     * -FulfillmentChannel
     * -LastUpdatedAfter
     * -LastUpdatedBefore
     * @param string|null $filter string or null
     * @return boolean false on failure
     */
    public function setSellerOrderIdFilter($filter){
        if (is_string($filter)){
            $this->options['SellerOrderId'] = $filter;
            //these fields must be disabled
            unset($this->options['BuyerEmail']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else if (is_null($filter)){
            unset($this->options['SellerOrderId']);
        } else {
            return false;
        }
    }
    
    /**
     * Sets the max number of results per page for the next request
     * @param int $num integer from 1 to 100
     * @return boolean false on failure
     */
    public function setMaxResultsPerPage($num){
        if (is_int($num) && $num <= 100 && $num >= 1){
            $this->options['MaxResultsPerPage'] = $num;
        } else {
            return false;
        }
    }
    
    /**
     * Fetches orders from Amazon using the pre-set parameters and putting them in an array of AmazonOrder objects
     */
    public function fetchOrders(){
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'ListOrders';
        
        if (!array_key_exists('CreatedAfter', $this->options) && !array_key_exists('LastUpdatedAfter', $this->options)){
            $this->setLimits('Created');
        }
        
        $this->prepareToken();
        
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
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        $this->parseXML($xml);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more orders");
            $this->fetchOrders();
        }
    }

    /**
     * Makes the preparations necessary for using tokens
     * @return boolean returns false if no token to use
     */
    protected function prepareToken(){
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListOrdersByNextToken';
            
            //When using tokens, only the NextToken option should be used
            unset($this->options['SellerOrderId']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            $this->setSellerOrderIdFilter(null);
            $this->setEmailFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
            unset($this->options['CreatedAfter']);
            unset($this->options['CreatedBefore']);
            unset($this->options['MaxResultsPerPage']);
            
        } else {
            $this->options['Action'] = 'ListOrders';
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->orderList = array();
        }
    }
    
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        
        foreach($xml->Orders->children() as $key => $data){
            if ($key != 'Order'){
                break;
            }
            $this->orderList[$this->index] = new AmazonOrder($this->storeName,null,$data,$this->mockMode,$this->mockFiles);
            $this->orderList[$this->index]->mockIndex = $this->mockIndex;
            $this->index++;
        }
        
    }
    
    /**
     * returns array of item lists or a single item list
     * @param boolean $token whether or not to automatically use tokens when fetching items
     * @param integer $i index
     * @return array|AmazonOrderItemList AmazonOrderItemList or array of AmazonOrderItemLists
     */
    public function fetchItems($token = false, $i = null){
        if (!isset($this->orderList)){
            return false;
        }
        if (!is_bool($token)){
            $token = false;
        }
         if (is_int($i)) {
            return $this->orderList[$i]->fetchItems($token);
        } else {
            $a = array();
            foreach($this->orderList as $x){
                $a[] = $x->fetchItems($token);
            }
            return $a;
        }
    }
    
    /**
     * Returns the list of orders
     * @return array Array of AmazonOrder objects, or false if not set yet
     */
    public function getList(){
        if (isset($this->orderList)){
            return $this->orderList;
        } else {
            return false;
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
