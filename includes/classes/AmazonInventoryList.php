<?php
/**
 * Fetches list of inventory supplies from Amazon.
 * 
 * This Amazon Inventory Core object retrieves a list of inventory supplies
 * from Amazon. This is the only object in the Amazon Inventory Core. This
 * object can use tokens when retrieving the list.
 */
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
            throw new Exception('Config file does not exist!');
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
     * Sets the time to start looking for
     * @param string $t time string that will be passed through strtotime
     * @return boolean false on failure
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
     * set the SKUs to fetch in the next request
     * @param array|string $a array or single string
     * @return boolean false on failure
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
     * @return boolean false on failure
     */
    public function setResponseGroup($s){
        if ($s == 'Basic' || $s == 'Detailed'){
            $this->options['ResponseGroup'] = $s;
        } else {
            return false;
        }
    }
    
     /**
     * Fetches the participation list from Amazon, using a token if available
     */
    public function fetchInventoryList(){
        if (!isset($this->options['QueryStartDateTime']) && !isset($this->options['SellerSkus.member.1'])){
            $this->setStartTime();
        }
        $this->prepareToken();
        $this->options['Timestamp'] = $this->genTime();
        
        
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
        
        $this->parseXML($xml->InventorySupplyList);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Inventory Supplies");
            $this->fetchInventoryList();
        }
        
    }
    
    private function prepareToken(){
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
            if ($this->options['ResponseGroup'] == 'Detailed'){
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
     * Returns all info of the given index, or whole list
     * @param integer $i
     * @return array|boolean false if not set yet
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
     * Returns the Seller SKU of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getSellerSku($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->supplyList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the ASIN of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getASIN($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->supplyList[$i]['ASIN'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Total Supply Quantity of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getTotalSupplyQuantity($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->supplyList[$i]['TotalSupplyQuantity'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the FNSKU of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getFNSKU($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->supplyList[$i]['FNSKU'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Seller SKU of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getCondition($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->supplyList[$i]['Condition'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the in-stock supply quantity of the given index, defaulting to 0
     * @param integer $i
     * @return string
     */
    public function getInStockSupplyQuantity($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i)){
            return $this->supplyList[$i]['InStockSupplyQuantity'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the earliest availability timeframe of the given index, defaulting to 0
     * @param integer $i
     * @return string timeframe or timestamp
     */
    public function getEarliestAvailability($i = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists('EarliestAvailability', $this->supplyList[$i])){
            return $this->supplyList[$i]['EarliestAvailability'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns all supply details of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j optional
     * @return array
     */
    public function getSupplyDetails($i = 0, $j = null){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
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
     * Returns the earliest pick timeframe of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string timeframe or timestamp
     */
    public function getEarliestAvailableToPick($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['EarliestAvailableToPick'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the latest pick timeframe of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string timeframe or timestamp
     */
    public function getLatestAvailableToPick($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['LatestAvailableToPick'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity detail of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string number
     */
    public function getQuantity($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
            return $this->supplyList[$i]['SupplyDetail'][$j]['Quantity'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the supply type detail of the given index, defaulting to 0
     * @param integer $i
     * @param integer $j
     * @return string
     */
    public function getSupplyType($i = 0, $j = 0){
        if (!isset($this->supplyList)){
            return false;
        }
        if (is_numeric($i) && is_numeric($j) && array_key_exists('SupplyDetail', $this->supplyList[$i])){
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
