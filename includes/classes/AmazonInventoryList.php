<?php

class AmazonInventoryList extends AmazonInventoryCore{
    private $tokenFlag;
    private $tokenUseFlag;
    private $supplyList;
    private $index = 0;
    
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        
        $this->throttleLimit = $throttleLimitInventory;
        $this->throttleTime = $throttleTimeInventory;
    }
    
    /**
     * Returns whether or not the Participation List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the Participation List should automatically use tokens if it receives one.
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
     * Fetches the participation list from Amazon, using a token if available
     * @param boolean $refresh set false to preserve current list (for internal use)
     */
    public function fetchInventoryList(){
        $this->options['Timestamp'] = $this->genTime();
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListInventorySupplyByNextToken';
        } else {
            $this->options['Action'] = 'ListInventorySupply';
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->supplyList = array();
        }
        
        if (!isset($this->options['QueryStartDateTime']) && !isset($this->options['SellerSkus.member.1'])){
            $this->setStartTime();
        }
        
        
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

            $xml = simplexml_load_string($response['body'])->$path;
        }
        myPrint($xml);
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        
        foreach($xml->InventorySupplyList->children() as $x){
            $this->supplyList[$this->index]['SellerSKU'] = (string)$x->SellerSKU;
            $this->supplyList[$this->index]['ASIN'] = (string)$x->ASIN;
            $this->supplyList[$this->index]['TotalSupplyQuantity'] = (string)$x->TotalSupplyQuantity;
            $this->supplyList[$this->index]['FNSKU'] = (string)$x->FNSKU;
            $this->supplyList[$this->index]['Condition'] = (string)$x->Condition;
            $this->supplyList[$this->index]['InStockSupplyQuantity'] = (string)$x->InStockSupplyQuantity;
            if ((int)$x->TotalSupplyQuantity > 0){
                $this->supplyList[$this->index]['EarliestAvailability'] = (string)$x->EarliestAvailability->TimepointType;
            }
            if ($this->options['ResponseGroup'] == 'Detailed'){
                
            }
            $this->index++;
        }
        
        var_dump($this->supplyList);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Inventory Supplies");
            $this->fetchInventoryList(false);
        }
        
    }
    
    /**
     * Sets the time to start looking for
     * @param string $t time string that will be passed through strtotime
     */
    public function setStartTime($t = null){
        try{
            if ($t){
                $after = $this->genTime($lower);
            } else {
                $after = $this->genTime('- 2 min');
            }
            $this->options['QueryStartDateTime'] = $after;
            $this->resetSkus();
            
        } catch (Exception $e){
            $this->log("Parameter should be a timestamp, instead $t",'Warning');
        }
        
    }
    
    public function setSellerSkus($a){
        $this->resetSkus();
        if (is_string($a)){
            $this->options['SellerSkus.member.1'] = $a;
        } else if (is_array($a)){
            $i = 1;
            foreach($a as $x){
                $this->options['SellerSkus.member.'.$i++] = $x;
            }
        }
        unset($this->options['QueryStartDateTime']);
    }
    
    private function resetSkus(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#SellerSkus.member.#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    public function setResponseGroup($s){
        if ($s == 'Basic' || $s == 'Detailed'){
            $this->options['ResponseGroup'] = $s;
        }
    }
}
?>
