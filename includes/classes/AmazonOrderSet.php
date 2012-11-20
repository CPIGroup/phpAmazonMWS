<?php

class AmazonOrderSet extends AmazonOrderCore implements Iterator{
    private $i;
    private $orderList;
    private $itemFlag;
    private $tokenItemFlag;
    
    /**
     * Amazon Order Set is a variation of Amazon Order that pulls multiple specified orders.
     * @param string $s name of store, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     * @throws Exception if Marketplace ID is missing from config
     */
    public function __construct($s, $o = null, $mock = false){
        parent::__construct($s, $mock);
        $this->i = 0;
        include($this->config);
        
        if(array_key_exists('marketplaceId', $store[$s])){
            $this->options['MarketplaceId.Id.1'] = $store[$s]['marketplaceId'];
        } else {
            throw new Exception('Marketplace ID missing.');
        }
        
        if($o && is_array($o) && !is_string($o)){
            $k = 1;
            foreach ($o as $id){
                $this->options['AmazonOrderId.Id.'.$k++] = $id;
            }
        }
        
        $this->throttleLimit = $throttleLimitOrder;
        $this->throttleTime = $throttleTimeOrder;
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
    }
    
    /**
     * Sets the Amazon Order IDs for the next request, in case they were not set in the constructor
     * @param array $id the Amazon Order ID
     */
    public function setOrderIds($o){
        if($o && is_array($o) && !is_string($o)){
            foreach($this->options as $op=>$junk){
                if(preg_match("#AmazonOrderId.Id.#",$op)){
                    unset($this->options[$op]);
                }
            }
            
            $k = 1;
            foreach ($o as $id){
                $this->options['AmazonOrderId.Id.'.$k++] = $id;
            }
        }
    }
    
    /**
     * Sets whether or not the OrderSet should automatically grab items for the Orders it receives
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
     * Sets whether or not the OrderSet should automatically use tokens when fetching items
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
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'GetOrder';
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        
        $this->throttle();
        $response = fetchURL($url,array('Post'=>$query));
        $this->logRequest();
        
        var_dump(simplexml_load_string($response['body']));
        
        $xml = simplexml_load_string($response['body'])->GetOrderResult;
        
        echo 'the lime must be drawn here';
        var_dump($xml);
        
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
