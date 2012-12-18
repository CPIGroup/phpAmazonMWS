<?php
/**
 * Gets all of the items for a given order.
 * 
 * This Amazon Orders Core object can retrieve the list of items associated
 * with a specific order. Before any items can be retrieved, an Order ID is
 * required. This object can use tokens when retrieving the list.
 */
class AmazonOrderItemList extends AmazonOrderCore implements Iterator{
    private $itemList;
    private $tokenFlag = false;
    private $tokenUseFlag = false;
    private $i = 0;
    private $index = 0;

    /**
     * AmazonItemLists contain all of the items for a given order
     * @param string $s store name as seen in Config
     * @param string $id order ID to be automatically set
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $id=null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        
        if (!is_null($id)){
            $this->setOrderId($id);
        }
        
        $this->throttleLimit = $throttleLimitItem;
        $this->throttleTime = $throttleTimeItem;
        $this->throttleGroup = 'ListOrderItems';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
    }

    /**
     * Sets whether or not the ItemList should automatically use tokens if it receives one.
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
     * Sets the Order ID to be used, in case it was not already set when the object was initiated
     * @param string $id Amazon Order ID
     * @return boolean false if invalid paramter
     */
    public function setOrderId($id){
        if (is_string($id) || is_numeric($id)){
            $this->options['AmazonOrderId'] = $id;
        } else {
            return false;
        }
    }

    /**
     * Retrieves the items from amazon using the pre-defined parameters
     */
    public function fetchItems(){
        $this->options['Timestamp'] = $this->genTime();
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
        
        
        if (is_null($xml->AmazonOrderId)){
            $this->log("You just got throttled.",'Warning');
            return false;
        } else if ($this->options['AmazonOrderId'] && $this->options['AmazonOrderId'] != $xml->AmazonOrderId){
            $this->log('You grabbed the wrong Order\'s items! - '.$this->options['AmazonOrderId'].' =/= '.$xml->AmazonOrderId,'Urgent');
            return false;
        }
        
        $this->parseXML($xml->OrderItems);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more items");
            $this->fetchItems();
        }
    }

    /**
     * Makes the preparations necessary for using tokens
     * @return boolean returns false if no token to use
     */
    protected function prepareToken(){
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListOrderItemsByNextToken';
            //When using tokens, only the NextToken option should be used
            unset($this->options['AmazonOrderId']);
        } else {
            $this->options['Action'] = 'ListOrderItems';
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->itemList = array();
        }
    }
    
    /**
     * Populates the object's data using the stored XML data. Clears existing data
     * @return boolean false if no XML data
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        
        foreach($xml->children() as $item){
            $n = $this->index;
            
            $this->itemList[$n]['ASIN'] = (string)$item->ASIN;
            $this->itemList[$n]['SellerSKU'] = (string)$item->SellerSKU;
            $this->itemList[$n]['OrderItemId'] = (string)$item->OrderItemId;
            $this->itemList[$n]['Title'] = (string)$item->Title;
            $this->itemList[$n]['QuantityOrdered'] = (string)$item->QuantityOrdered;
            if (isset($item->QuantityShipped)){
                $this->itemList[$n]['QuantityShipped'] = (string)$item->QuantityShipped;
            }
            if (isset($item->GiftMessageText)){
                $this->itemList[$n]['GiftMessageText'] = (string)$item->GiftMessageText;
            }
            if (isset($item->GiftWrapLevel)){
                $this->itemList[$n]['GiftWrapLevel'] = (string)$item->GiftWrapLevel;
            }
            if (isset($item->ItemPrice)){
                $this->itemList[$n]['ItemPrice']['Amount'] = (string)$item->ItemPrice->Amount;
                $this->itemList[$n]['ItemPrice']['CurrencyCode'] = (string)$item->ItemPrice->CurrencyCode;
            }
            if (isset($item->ShippingPrice)){
                $this->itemList[$n]['ShippingPrice']['Amount'] = (string)$item->ShippingPrice->Amount;
                $this->itemList[$n]['ShippingPrice']['CurrencyCode'] = (string)$item->ShippingPrice->CurrencyCode;
            }
            if (isset($item->GiftWrapPrice)){
                $this->itemList[$n]['GiftWrapPrice']['Amount'] = (string)$item->GiftWrapPrice->Amount;
                $this->itemList[$n]['GiftWrapPrice']['CurrencyCode'] = (string)$item->GiftWrapPrice->CurrencyCode;
            }
            if (isset($item->ItemTax)){
                $this->itemList[$n]['ItemTax']['Amount'] = (string)$item->ItemTax->Amount;
                $this->itemList[$n]['ItemTax']['CurrencyCode'] = (string)$item->ItemTax->CurrencyCode;
            }
            if (isset($item->ShippingTax)){
                $this->itemList[$n]['ShippingTax']['Amount'] = (string)$item->ShippingTax->Amount;
                $this->itemList[$n]['ShippingTax']['CurrencyCode'] = (string)$item->ShippingTax->CurrencyCode;
            }
            if (isset($item->GiftWrapTax)){
                $this->itemList[$n]['GiftWrapTax']['Amount'] = (string)$item->GiftWrapTax->Amount;
                $this->itemList[$n]['GiftWrapTax']['CurrencyCode'] = (string)$item->GiftWrapTax->CurrencyCode;
            }
            if (isset($item->ShippingDiscount)){
                $this->itemList[$n]['ShippingDiscount']['Amount'] = (string)$item->ShippingDiscount->Amount;
                $this->itemList[$n]['ShippingDiscount']['CurrencyCode'] = (string)$item->ShippingDiscount->CurrencyCode;
            }
            if (isset($item->PromotionDiscount)){
                $this->itemList[$n]['PromotionDiscount']['Amount'] = (string)$item->PromotionDiscount->Amount;
                $this->itemList[$n]['PromotionDiscount']['CurrencyCode'] = (string)$item->PromotionDiscount->CurrencyCode;
            }
            if (isset($item->CODFee)){
                $this->itemList[$n]['CODFee']['Amount'] = (string)$item->CODFee->Amount;
                $this->itemList[$n]['CODFee']['CurrencyCode'] = (string)$item->CODFee->CurrencyCode;
            }
            if (isset($item->CODFeeDiscount)){
                $this->itemList[$n]['CODFeeDiscount']['Amount'] = (string)$item->CODFeeDiscount->Amount;
                $this->itemList[$n]['CODFeeDiscount']['CurrencyCode'] = (string)$item->CODFeeDiscount->CurrencyCode;
            }
            if (isset($item->PromotionIds)){
                $i = 0;
                foreach($item->PromotionIds->children() as $x){
                    $this->itemList[$n]['PromotionIds'][$i] = (string)$x;
                    $i++;
                }
            }
            $this->index++;
        }
            
    }
    
    /**
     * Returns whether or not the Item List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Returns entire list of items or single item
     * @param string $i id of item to get
     * @return array list of item arrays or single item
     */
    public function getItems($i = null){
        if (isset($this->itemList)){
            if (is_numeric($i)){
                return $this->itemList[$i];
            } else {
                return $this->itemList;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns ASIN of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getASIN($i = 0){
        if (isset($this->itemList[$i]['ASIN'])){
            return $this->itemList[$i]['ASIN'];
        } else {
            return false;
        }
        
    }
    
    /**
     * Returns Seller SKU of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getSellerSKU($i = 0){
        if (isset($this->itemList[$i]['SellerSKU'])){
            return $this->itemList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns Order Item ID of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getOrderItemId($i = 0){
        if (isset($this->itemList[$i]['OrderItemId'])){
            return $this->itemList[$i]['OrderItemId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns Title of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getTitle($i = 0){
        if (isset($this->itemList[$i]['Title'])){
            return $this->itemList[$i]['Title'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns quantity ordered of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getQuantityOrdered($i = 0){
        if (isset($this->itemList[$i]['QuantityOrdered'])){
            return $this->itemList[$i]['QuantityOrdered'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns quantity shipped of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getQuantityShipped($i = 0){
        if (isset($this->itemList[$i]['QuantityShipped'])){
            return $this->itemList[$i]['QuantityShipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Calculates percent of items shipped
     * @param string $i id of item to get
     * @return float|boolean decimal number from 0 to 1, false if not yet set
     */
    public function getPercentShipped($i = 0){
        if ($this->itemList[$i]['QuantityOrdered'] == 0){
            return false;
        }
        if (isset($this->itemList[$i]['QuantityOrdered']) && isset($this->itemList[$i]['QuantityShipped'])){
            return $this->itemList[$i]['QuantityShipped']/$this->itemList[$i]['QuantityOrdered'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns text for gift message of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getGiftMessageText($i = 0){
        if (isset($this->itemList[$i]['GiftMessageText'])){
            return $this->itemList[$i]['GiftMessageText'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns quantity shipped of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string|boolean false if not yet set
     */
    public function getGiftWrapLevel($i = 0){
        if (isset($this->itemList[$i]['GiftWrapLevel'])){
            return $this->itemList[$i]['GiftWrapLevel'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns item price of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getItemPrice($i = 0, $only = false){
        if (isset($this->itemList[$i]['ItemPrice'])){
            if ($only){
                return $this->itemList[$i]['ItemPrice']['Amount'];
            } else {
                return $this->itemList[$i]['ItemPrice'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns shipping price of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getShippingPrice($i = 0, $only = false){
        if (isset($this->itemList[$i]['ShippingPrice'])){
            if ($only){
                return $this->itemList[$i]['ShippingPrice']['Amount'];
            } else {
                return $this->itemList[$i]['ShippingPrice'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns wrapping price of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getGiftWrapPrice($i = 0, $only = false){
        if (isset($this->itemList[$i]['GiftWrapPrice'])){
            if ($only){
                return $this->itemList[$i]['GiftWrapPrice']['Amount'];
            } else {
                return $this->itemList[$i]['GiftWrapPrice'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns item tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getItemTax($i = 0, $only = false){
        if (isset($this->itemList[$i]['ItemTax'])){
            if ($only){
                return $this->itemList[$i]['ItemTax']['Amount'];
            } else {
                return $this->itemList[$i]['ItemTax'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns shipping tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getShippingTax($i = 0, $only = false){
        if (isset($this->itemList[$i]['ShippingTax'])){
            if ($only){
                return $this->itemList[$i]['ShippingTax']['Amount'];
            } else {
                return $this->itemList[$i]['ShippingTax'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns wrapping tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getGiftWrapTax($i = 0, $only = false){
        if (isset($this->itemList[$i]['GiftWrapTax'])){
            if ($only){
                return $this->itemList[$i]['GiftWrapTax']['Amount'];
            } else {
                return $this->itemList[$i]['GiftWrapTax'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns item tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getShippingDiscount($i = 0, $only = false){
        if (isset($this->itemList[$i]['ShippingDiscount'])){
            if ($only){
                return $this->itemList[$i]['ShippingDiscount']['Amount'];
            } else {
                return $this->itemList[$i]['ShippingDiscount'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns item tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @param boolean $only set to true to get only the amount
     * @return array|boolean contains Amount and Currency Code, false if not yet set
     */
    public function getPromotionDiscount($i = 0, $only = false){
        if (isset($this->itemList[$i]['PromotionDiscount'])){
            if ($only){
                return $this->itemList[$i]['PromotionDiscount']['Amount'];
            } else {
                return $this->itemList[$i]['PromotionDiscount'];
            }
        } else {
            return false;
        }
    }
    
    /**
     * Returns specified promotion ID for specified item, both default to first if none given
     * @param string $i id of item to get
     * @param integer $j index of promotion to get 
     * @return string|boolean false if not yet set
     */
    public function getPromotionIds($i = 0, $j = null){
        if (isset($this->itemList[$i]['PromotionIds'])){
            if (isset($this->itemList[$i]['PromotionIds'][$j])){
                return $this->itemList[$i]['PromotionIds'][$j];
            } else {
                return $this->itemList[$i]['PromotionIds'];
            }
        } else {
            return false;
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
