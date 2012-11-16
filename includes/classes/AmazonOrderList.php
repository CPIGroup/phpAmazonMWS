<?php
/**
 * Amazon Order Lists pull a set of Orders and turn them into an array of AmazonOrder objects.
 */
class AmazonOrderList extends AmazonCore implements Iterator{
    private $orderList;
    private $i;
    private $tokenFlag;
    private $itemFlag;
    private $tokenUseFlag;
    private $tokenItemFlag;
    private $index;
    private $token;

    /**
     * Amazon Order Lists pull a set of Orders and turn them into an array of AmazonOrder objects.
     * @param string $s name of store, as seen in the config file
     * @throws Exception if Marketplace ID is missing from config
     */
    public function __construct($s){
        parent::__construct($s);
        $this->i = 0;
        include($this->config);
        
        if(array_key_exists('marketplaceId', $store[$s])){
            $this->options['MarketplaceId.Id.1'] = $store[$s]['marketplaceId'];
        } else {
            throw new Exception('Marketplace ID missing.');
        }
        
        $this->urlbranch = 'Orders/2011-01-01';
        
        $this->throttleLimit = $throttleLimitOrderList;
        $this->throttleTime = $throttleTimeOrderList;
        $this->throttleCount = $this->throttleLimit;
        
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
     * Sets whether or not the OrderList should automatically grab items for the Orders it receives
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setFetchItems($b = true){
        if (is_bool($b)){
            $this->itemFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets whether or not the OrderList should automatically use tokens if it receives one. This includes item tokens
     * @param boolean $b
     * @return boolean false if invalid paramter
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
     * Sets whether or not the OrderList should automatically use tokens when fetching items
     * @param type $b
     * @return boolean false if invalid paramter
     */
    public function setUseItemToken($b = true){
        if (is_bool($b)){
            $this->tokenItemFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Fetches orders from Amazon using the pre-set parameters and putting them in an array of AmazonOrder objects
     */
    public function fetchOrders(){
        //Pseudocode am go
        //
        //get order ID
        //query database for said ID
        //if found
        //fetch XML from cache table
        //else do what I've normally been doing
        //log copy of results in database

        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'ListOrders';
        
        if (!array_key_exists('CreatedAfter', $this->options) && !array_key_exists('LastUpdatedAfter', $this->options)){
            $this->setLimits('Created');
        }
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->prepareToken();
        } else {
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->orderList = array();
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        //old way
//        myPrint($this->options);
//        myPrint($query);
        
//        myPrint($this->options);
//        $query = $this->genRequest();
//        myPrint($query);
        
        $this->throttle();
        $response = fetchURL($url,array('Post'=>$query));
        $this->logRequest();
        
        $path = $this->options['Action'].'Result';
        
        var_dump(simplexml_load_string($response['body']));
        var_dump($path);
        
        $xml = simplexml_load_string($response['body'])->$path;
        
        echo 'the lime must be drawn here';
        var_dump($xml);
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->token = true;
        }
        
        foreach($xml->Orders->children() as $key => $order){
            if ($key != 'Order'){
                break;
            }
            $this->orderList[$this->index] = new AmazonOrder($this->storeName,null,$order);
            $this->orderList[$this->index]->parseXML();
            $this->orderList[$this->index]->setUseItemToken($this->tokenItemFlag);
            if($this->itemFlag){
                $this->orderList[$this->index]->fetchItems();
            }
            $this->index++;
        }
        
        myPrint($this->orderList);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            echo '<br>IT BEGINS AGAIN<br>';
            $this->fetchOrders();
        }
        
        
    }

    /**
     * Makes the preparations necessary for using tokens
     * @return boolean returns false if no token to use
     */
    protected function prepareToken(){
        if (!$this->tokenFlag){
            return false;
        } else {
            $this->options['NextToken'] = $this->token;
            $this->options['Action'] = 'ListOrdersByNextToken';
            
            //When using tokens, only the NextToken option should be used
            foreach($this->options as $o => $v){
                if ($o == 'AWSAccessKeyId'){
                    continue;
                } else
                if ($o == 'Action'){
                    continue;
                } else
                if ($o == 'SellerId'){
                    continue;
                } else
                if ($o == 'SignatureVersion'){
                    continue;
                } else
                if ($o == 'SignatureMethod'){
                    continue;
                } else
                if ($o == 'NextToken'){
                    continue;
                } else
                if ($o == 'Timestamp'){
                    continue;
                } else
                if ($o == 'Version'){
                    continue;
                } else {
                    unset($this->options[$o]);
                }
            }
        }
    }
    
    /**
     * Sets the time frame for the orders fetched.
     * 
     * Sets the time frame for the orders fetched. If no times are specified, times default to the current time
     * @param string $mode "Created" or "Modified"
     * @param dateTime $lower Date the order was created after
     * @param dateTime $upper Date the order was created before
     * @throws InvalidArgumentException
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
                throw new InvalidArgumentException('First parameter should be either "Created" or "Modified".');
            }
            
        } catch (Exception $e){
            throw new InvalidArgumentException('Second/Third parameters should be timestamps.');
        }
        
    }
    
    /**
     * Sets option for status filter of next request
     * @param array $list array of strings, or a single string
     * @throws InvalidArgumentException
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
                $this->options['OrderStatus.Status.'.$i++] = $x;
            }
        } else {
            throw new InvalidArgumentException('setOrderStatusFilter() needs a string or array of strings');
        }
    }

    /**
     * Resets the Order Status Filter to default
     */
    public function resetOrderStatusFilter(){
        for ($i = 1; $i <= 7; $i++){
            if (array_key_exists('OrderStatus.Status.'.$i,$this->options)){
                unset($this->options['OrderStatus.Status.'.$i]);
            }
        }
    }
    
    /**
     * Sets (or resets) the Fulfillment Channel Filter
     * @param string $filter 'AFN' or 'MFN' or null
     */
    public function setFulfillmentChannelFilter($filter = null){
        if ($filter == 'AFN' || $filter == 'MFN'){
            $this->options['FulfillmentChannel.Channel.1'] = $filter;
        } else {
            unset($this->options['FulfillmentChannel.Channel.1']);
        }
    }
    
    /**
     * Sets option for payment method filter of next request
     * @param array $list array of strings, or a single string
     * @throws InvalidArgumentException
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
            throw new InvalidArgumentException('setOrderStatusFilter() needs a string or array of strings');
        }
    }
    
    /**
     * Resets the Payment Method Filter to default
     */
    public function resetPaymentMethodFilter(){
        for ($i = 1; $i <= 7; $i++){
            if (array_key_exists('PaymentMethod.'.$i,$this->options)){
                unset($this->options['PaymentMethod.'.$i]);
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
     * @param string $filter string or null
     */
    public function setEmailFilter($filter = null){
        if (is_string($filter)){
            $this->options['BuyerEmail'] = $filter;
            //these fields must be disabled
            unset($this->options['SellerOrderId']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else {
            unset($this->options['BuyerEmail']);
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
     * @param string $filter string or null
     */
    public function setSellerOrderIdFilter($filter = null){
        if (is_string($filter)){
            $this->options['SellerOrderId'] = $filter;
            //these fields must be disabled
            unset($this->options['BuyerEmail']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else {
            unset($this->options['SellerOrderId']);
        }
    }
    
    /**
     * Sets the max number of results per page for the next request
     * @param type $num
     * @throws InvalidArgumentException
     */
    public function setMaxResultsPerPage($num){
        if (is_int($num)){
            if ($num <= 100 && $num >= 1){
                $this->options['MaxResultsPerPage'] = $num;
            }
        } else {
            throw new InvalidArgumentException();
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
