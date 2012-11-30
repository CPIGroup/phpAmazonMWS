<?php

class AmazonInventoryList extends AmazonInventoryCore implements Iterator{
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $supplyList;
    private $index = 0;
    private $i = 0;
    
    /**
     * Fetches a list of inventory supplies Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
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
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
//        myPrint($xml);
        
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
                if ($x->EarliestAvailability->TimepointType == 'DateTime'){
                    $this->supplyList[$this->index]['EarliestAvailability'] = (string)$x->EarliestAvailability->DateTime;
                } else {
                    $this->supplyList[$this->index]['EarliestAvailability'] = (string)$x->EarliestAvailability->TimepointType;
                }
            }
            if ($this->options['ResponseGroup'] == 'Detailed'){
                $j = 0;
                foreach($x->SupplyDetail->children() as $z){
                    if ($z->EarliestAvailableToPick->TimepointType == 'DateTime'){
                        $this->supplyList[$this->index]['SupplyDetail'][$j]['EarliestAvailableToPick'] = (string)$z->EarliestAvailableToPick->DateTime;
                    } else {
                        $this->supplyList[$this->index]['SupplyDetail'][$j]['EarliestAvailableToPick'] = (string)$z->EarliestAvailableToPick->TimepointType;
                    }
                    if ($z->LatestAvailableToPick->TimepointType == 'DateTime'){
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
        
//        var_dump($this->supplyList);
        
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
                $after = $this->genTime($t);
            } else {
                $after = $this->genTime('- 2 min');
            }
            $this->options['QueryStartDateTime'] = $after;
            $this->resetSkus();
            
        } catch (Exception $e){
            $this->log("Parameter should be a timestamp, instead $t",'Warning');
        }
        
    }
    
    /**
     * set the SKUs to fetch in the next request
     * @param array|string $a array or single string
     */
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
    
    /**
     * resets the Seller SKU options
     */
    private function resetSkus(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#SellerSkus.member.#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets whether or not to get detailed results back
     * @param string $s "Basic" or "Detailed"
     */
    public function setResponseGroup($s){
        if ($s == 'Basic' || $s == 'Detailed'){
            $this->options['ResponseGroup'] = $s;
        }
    }
    
    /**
     * Returns all info of the given index, defaulting to 0
     * @param integer $i
     * @return array
     */
    public function getSupply($i = 0){
        if (is_numeric($i)){
            return $this->supplyList[$i];
        }
    }
    
    /**
     * Returns the Seller SKU of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getSellerSku($i = 0){
        if (is_numeric($i)){
            return $this->supplyList[$i]['SellerSKU'];
        }
    }
    
    /**
     * Returns the ASIN of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getASIN($i = 0){
        if (is_numeric($i)){
            return $this->supplyList[$i]['ASIN'];
        }
    }
    
    /**
     * Returns the Total Supply Quantity of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getTotalSupplyQuantity($i = 0){
        if (is_numeric($i)){
            return $this->supplyList[$i]['TotalSupplyQuantity'];
        }
    }
    
    /**
     * Returns the FNSKU of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getFNSKU($i = 0){
        if (is_numeric($i)){
            return $this->supplyList[$i]['FNSKU'];
        }
    }

    /**
     * Returns the Seller SKU of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getCondition($i = 0){
        if (is_numeric($i)){
            return $this->supplyList[$i]['Condition'];
        }
    }
    
    /**
     * Returns the in-stock supply quantity of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getInStockSupplyQuantity($i = 0){
        if (is_numeric($i)){
            return $this->supplyList[$i]['InStockSupplyQuantity'];
        }
    }
    
    /**
     * Returns the earliest availability timeframe of the given index, defaulting to 0
     * @param integer $i
     * @return string timeframe or timestamp
     */
    public function getEarliestAvailability($i = 0){
        if (is_numeric($i) && array_key_exists('EarliestAvailability', $this->supplyList[$i])){
            return $this->supplyList[$i]['EarliestAvailability'];
        }
    }
    
    /**
     * Returns all supply details of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j optional
     * @return array
     */
    public function getSupplyDetails($i = 0, $j = null){
        if (is_numeric($i) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            if (is_null($j)){
                return $this->supplyList[$i]['SupplyDetail'];
            } else if (is_numeric($j)) {
                return $this->supplyList[$i]['SupplyDetail'][$j];
            }
        }
    }
    
    /**
     * Returns the earliest pick timeframe of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string timeframe or timestamp
     */
    public function getEarliestAvailableToPick($i = 0, $j = 0){
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['EarliestAvailableToPick'];
        }
    }
    
    /**
     * Returns the latest pick timeframe of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string timeframe or timestamp
     */
    public function getLatestAvailableToPick($i = 0, $j = 0){
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['LatestAvailableToPick'];
        }
    }
    
    /**
     * Returns the quantity detail of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string number
     */
    public function getQuantity($i = 0, $j = 0){
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['Quantity'];
        }
    }
    
    /**
     * Returns the supply type detail of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string
     */
    public function getSupplyType($i = 0, $j = 0){
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['SupplyType'];
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
