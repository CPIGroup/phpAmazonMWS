<?php

class AmazonOrderSet extends AmazonOrderCore implements Iterator{
    private $i = 0;
    private $orderList;
    
    /**
     * Amazon Order Set is a variation of Amazon Order that pulls multiple specified orders.
     * @param string $s name of store, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $o = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        $this->i = 0;
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
        
        if($o && is_array($o) && !is_string($o)){
            $k = 1;
            foreach ($o as $id){
                $this->options['AmazonOrderId.Id.'.$k++] = $id;
            }
        }
        
        $this->throttleLimit = $throttleLimitOrder;
        $this->throttleTime = $throttleTimeOrder;
        $this->throttleGroup = 'GetOrder';
        
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
     * Fetches orders from Amazon using the pre-set parameters and putting them in an array of AmazonOrder objects
     */
    public function fetchOrders(){
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'GetOrder';
        
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
        
        echo 'the lime must be drawn here';
        var_dump($xml);
        
        foreach($xml->Orders->children() as $key => $order){
            if ($key != 'Order'){
                break;
            }
            $this->orderList[$this->index] = new AmazonOrder($this->storeName,null,$order);
            $this->orderList[$this->index]->parseXML();
            $this->index++;
        }
        
        myPrint($this->orderList);
        
    }
    
    /**
     * returns array of item lists or a single item list
     * @param boolean $token whether or not to automatically use tokens when fetching items
     * @param integer $i index
     * @return array AmazonOrderItemList or array of AmazonOrderItemLists
     */
    public function fetchItems($token = false, $i = null){
        if ($i == null){
            $a = array();
            foreach($this->orderList as $x){
                $a[] = $x->fetchItems($token);
            }
            return $a;
        } else if (is_numeric($i)) {
            return $this->orderList[$i]->fetchItems($token);
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
