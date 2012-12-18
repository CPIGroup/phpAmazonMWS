<?php
/**
 * Fetches list of products from Amazon
 * 
 * This Amazon Products Core object retrieves a list of products from Amazon
 * that match the given product IDs. In order to do this, both the ID type
 * and product ID(s) must be given.
 */
class AmazonProductList extends AmazonProductsCore implements Iterator{
    private $i = 0;
    
    /**
     * AmazonProductList fetches a list of products from Amazon
     * @param string $s store name as seen in config
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
        
        $this->options['Action'] = 'GetMatchingProductForId';
        
        $this->throttleLimit = $throttleLimitProduct;
        $this->throttleTime = $throttleTimeProductList;
        $this->throttleGroup = 'GetMatchingProductForId';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
        
    }
    
    /**
     * Sets the ID type for the next request
     * @param string $s "ASIN", "SellerSKU", "UPC", "EAN", "ISBN", or "JAN"
     * @return boolean false if improper input
     */
    public function setIdType($s){
        if (is_string($s)){
            $this->options['IdType'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * sets the request ID(s) to be used in the next request
     * @param array|string $s array of Report Request IDs or single ID (max: 5)
     * @return boolean false if failure
     */
    public function setProductIds($s){
        if (is_string($s)){
            $this->resetProductIds();
            $this->options['IdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetProductIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['IdList.Id.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes ID options
     */
    public function resetProductIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#IdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Fetches the report list from Amazon, using a token if available
     */
    public function fetchProductList(){
        if (!array_key_exists('IdList.Id.1',$this->options)){
            $this->log("Product IDs must be set in order to fetch them!",'Warning');
            return false;
        }
        if (!array_key_exists('IdType',$this->options)){
            $this->log("ID Type must be set in order to use the given IDs!",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile();
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        $this->parseXML($xml);
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->productList[$this->i]; 
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
        return isset($this->productList[$this->i]);
    }
    
}
?>