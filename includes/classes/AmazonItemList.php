<?php
/**
 * AmazonItemLists contain all of the items for a given order
 */
class AmazonItemList extends AmazonCore implements Iterator{
    private $itemList;
    private $tokenFlag;
    private $tokenUseFlag;
    private $i;
    private $xmldata;
    private $orderId;
    private $index;
    private $token;

    /**
     * AmazonItemLists contain all of the items for a given order
     * @param string $s store name as seen in Config
     * @param string $id order ID to be automatically set
     */
    public function __construct($s, $id=null){
        parent::__construct($s);
        include($this->config);
        
        $this->urlbranch = 'Orders/2011-01-01';
        
        if (!is_null($id)){
            $this->options['AmazonOrderId'] = $id;
            $this->orderId = $id;
        }
        
        $this->throttleLimit = $throttleLimitItem;
        $this->throttleTime = $throttleTimeItem;
        $this->throttleCount = $this->throttleLimit;
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
            $this->throttleCount = $this->throttleLimit;
        }
    }
    
    /**
     * Populates the object's data using the stored XML data. Clears existing data
     * @param boolean $reset put TRUE to remove existing data
     * @return boolean if no XML data
     */
    protected function parseXML($reset = false){
        if (!$this->xmldata){
            return false;
        }
        if ($reset){
            $this->itemList = array();
            $this->index = 0;
        }
        
        
        foreach($this->xmldata->children() as $item){
            $n = $this->index++;
            
            $this->itemList[$n]['ASIN'] = (string)$item->ASIN;
            $this->itemList[$n]['SellerSKU'] = (string)$item->SellerSKU;
            $this->itemList[$n]['OrderItemId'] = (string)$item->OrderItemId;
            $this->itemList[$n]['Title'] = (string)$item->Title;
            $this->itemList[$n]['QuantityOrdered'] = (string)$item->QuantityOrdered;
            $this->itemList[$n]['QuantityShipped'] = (string)$item->QuantityShipped;
            $this->itemList[$n]['GiftMessageText'] = (string)$item->GiftMessageText;
            $this->itemList[$n]['GiftWrapLevel'] = (string)$item->GiftWrapLevel;

            if (isset($item->ItemPrice)){
                $this->itemList[$n]['ItemPrice'] = array();
                $this->itemList[$n]['ItemPrice']['Amount'] = (string)$item->ItemPrice->Amount;
                $this->itemList[$n]['ItemPrice']['CurrencyCode'] = (string)$item->ItemPrice->CurrencyCode;
            }

            if (isset($item->ShippingPrice)){
                $this->itemList[$n]['ShippingPrice'] = array();
                $this->itemList[$n]['ShippingPrice']['Amount'] = (string)$item->ShippingPrice->Amount;
                $this->itemList[$n]['ShippingPrice']['CurrencyCode'] = (string)$item->ShippingPrice->CurrencyCode;
            }
            
            if (isset($item->GiftWrapPrice)){
                $this->itemList[$n]['GiftWrapPrice'] = array();
                $this->itemList[$n]['GiftWrapPrice']['Amount'] = (string)$item->GiftWrapPrice->Amount;
                $this->itemList[$n]['GiftWrapPrice']['CurrencyCode'] = (string)$item->GiftWrapPrice->CurrencyCode;
            }
            
            if (isset($item->ItemTax)){
                $this->itemList[$n]['ItemTax'] = array();
                $this->itemList[$n]['ItemTax']['Amount'] = (string)$item->ItemTax->Amount;
                $this->itemList[$n]['ItemTax']['CurrencyCode'] = (string)$item->ItemTax->CurrencyCode;
            }
            
            if (isset($item->ShippingTax)){
                $this->itemList[$n]['ShippingTax'] = array();
                $this->itemList[$n]['ShippingTax']['Amount'] = (string)$item->ShippingTax->Amount;
                $this->itemList[$n]['ShippingTax']['CurrencyCode'] = (string)$item->ShippingTax->CurrencyCode;
            }
            
            if (isset($item->GiftWrapTax)){
                $this->itemList[$n]['GiftWrapTax'] = array();
                $this->itemList[$n]['GiftWrapTax']['Amount'] = (string)$item->GiftWrapTax->Amount;
                $this->itemList[$n]['GiftWrapTax']['CurrencyCode'] = (string)$item->GiftWrapTax->CurrencyCode;
            }
            
            if (isset($item->ShippingDiscount)){
                $this->itemList[$n]['ShippingDiscount'] = array();
                $this->itemList[$n]['ShippingDiscount']['Amount'] = (string)$item->ShippingDiscount->Amount;
                $this->itemList[$n]['ShippingDiscount']['CurrencyCode'] = (string)$item->ShippingDiscount->CurrencyCode;
            }
            
            if (isset($item->PromotionDiscount)){
                $this->itemList[$n]['PromotionDiscount'] = array();
                $this->itemList[$n]['PromotionDiscount']['Amount'] = (string)$item->PromotionDiscount->Amount;
                $this->itemList[$n]['PromotionDiscount']['CurrencyCode'] = (string)$item->PromotionDiscount->CurrencyCode;
            }

            if (isset($item->PromotionIds)){
                $this->itemList[$n]['PromotionIds'] = array();

                $i = 0;
                foreach($item->PromotionIds->children() as $x){
                    $this->itemList[$n]['PromotionIds'][$i] = (string)$x;
                    $i++;
                }
            }
        }
            
    }
    
    /**
     * Sets the Order ID to be used, in case it was not already set when the object was initiated
     * @param string $id Amazon Order ID
     * @throws InvalidArgumentException if none given
     */
    public function setOrderId($id){
        if (!is_null($id)){
            $this->options['AmazonOrderId'] = $id;
        } else {
            throw new InvalidArgumentException('Order ID was Null');
        }
    }

    /**
     * Retrieves the items from amazon using the pre-defined parameters
     * @throws Exception if the request to Amazon fails
     */
    public function fetchItems(){
        //Pseudocode am go
        //
        //get order ID
        //query database for ID to see if items marked as fetched
        //if found
        //query database for items belonging to said ID
        //if found
        //fetch XML from cache table
        //else do what I've normally been doing
        //log copy of results in database
        //mark entry for order as now having items
        
        //STILL TO DO: EAT THE TOKENS
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'ListOrderItems';
        
        if($this->tokenFlag && $this->tokenUseFlag){
            $this->prepareToken();
        } else {
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->itemList = array();
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
//        myPrint($this->options);
//        myPrint($query);
        
//        myPrint($this->options);
//        $query = $this->genRequest();
//        myPrint($query);
        
        $this->throttle();
        $response = fetchURL($url,array('Post'=>$query));
        $this->logRequest();
        
        $path = $this->options['Action'].'Result';
        $xml = simplexml_load_string($response['body'])->$path;
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->token = true;
        }
        
        
        if (is_null($xml->AmazonOrderId)){
            throw new Exception('You dun got throttled.');
        }
        
        if ($this->orderId != $xml->AmazonOrderId){
            throw new Exception('You grabbed the wrong Order\'s items! - '.$this->orderId.' =/='.$xml->AmazonOrderId);
        }
        
        
        
        $this->xmldata = $xml->OrderItems;
        
        $this->parseXML();
        
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            echo '<br>IT BEGINS AGAIN<br>';
            $this->fetchItems();
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
            $this->options['Action'] = 'ListOrderItemsByNextToken';
            
            //When using tokens, only the NextToken option should be used
            unset($this->options['AmazonOrderId']);
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
     * Sets whether or not the ItemList should automatically use tokens if it receives one. This includes item tokens
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setUseToken($b){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Returns entire list of items
     * @return array list of item arrays
     */
    public function getItemList(){
        return $this->itemList;
    }
    
    /**
     * Returns the Order ID, which is the same for all items in the list
     * @return string
     */
    public function getAmazonOrderId(){
        return $this->itemList[0]['AmazonOrderId'];
    }
    
    /**
     * Returns ASIN of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getASIN($i = 0){
        return $this->itemList[$i]['ASIN'];
    }
    
    /**
     * Returns Seller SKU of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getSellerSKU($i = 0){
        return $this->itemList[$i]['SellerSKU'];
    }
    
    /**
     * Returns Order Item ID of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getOrderItemId($i = 0){
        return $this->itemList[$i]['OrderItemId'];
    }
    
    /**
     * Returns Title of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getTitle($i = 0){
        return $this->itemList[$i]['Title'];
    }
    
    /**
     * Returns quantity ordered of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function QuantityOrdered($i = 0){
        return $this->itemList[$i]['QuantityOrdered'];
    }
    
    /**
     * Returns quantity shipped of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getQuantityShipped($i = 0){
        return $this->itemList[$i]['QuantityShipped'];
    }
    
    /**
     * Calculates percent of items shipped
     * @param string $i id of item to get
     * @return float decimal number from 0 to 1
     */
    public function getPercentShipped($i = 0){
        if ($this->itemList[$i]['QuantityOrdered'] == 0){
            return false;
        }
        return $this->itemList[$i]['QuantityShipped']/$this->itemList[$i]['QuantityOrdered'];
    }
    
    /**
     * Returns text for gift message of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getGiftMessageText($i = 0){
        return $this->itemList[$i]['GiftMessageText'];
    }
    
    /**
     * Returns quantity shipped of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getGiftWrapLevel($i = 0){
        return $this->itemList[$i]['GiftWrapLevel'];
    }
    
    /**
     * Returns item price of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getItemPrice($i = 0){
        return $this->itemList[$i]['ItemPrice'];
    }
    
    /**
     * Returns price amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getItemPriceAmount($i = 0){
        return $this->itemList[$i]['QuantityShipped']['Amount'];
    }
    
    /**
     * Returns shipping price of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getShippingPrice($i = 0){
        return $this->itemList[$i]['ShippingPrice'];
    }
    
    /**
     * Returns shipping price amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getShippingPriceAmount($i = 0){
        return $this->itemList[$i]['ShippingPrice']['Amount'];
    }
    
    /**
     * Returns wrapping price of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getGiftWrapPrice($i = 0){
        return $this->itemList[$i]['GiftWrapPrice'];
    }
    
    /**
     * Returns wrapping price amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getGiftWrapPriceAmount($i = 0){
        return $this->itemList[$i]['GiftWrapPrice']['Amount'];
    }
    
    /**
     * Returns item tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getItemTax($i = 0){
        return $this->itemList[$i]['ItemTax'];
    }
    
    /**
     * Returns item tax amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getItemTaxAmount($i = 0){
        return $this->itemList[$i]['ItemTax']['Amount'];
    }
    
    /**
     * Returns shipping tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getShippingTax($i = 0){
        return $this->itemList[$i]['ShippingTax'];
    }
    
    /**
     * Returns shipping tax amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getShippingTaxAmount($i = 0){
        return $this->itemList[$i]['ShippingTax']['Amount'];
    }
    
    /**
     * Returns wrapping tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getGiftWrapTax($i = 0){
        return $this->itemList[$i]['GiftWrapTax'];
    }
    
    /**
     * Returns wrapping tax amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getGiftWrapTaxAmount($i = 0){
        return $this->itemList[$i]['GiftWrapTax']['Amount'];
    }
    
    /**
     * Returns item tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getShippingDiscount($i = 0){
        return $this->itemList[$i]['ShippingDiscount'];
    }
    
    /**
     * Returns item tax amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getShippingDiscountAmount($i = 0){
        return $this->itemList[$i]['ShippingDiscount']['Amount'];
    }
    
    /**
     * Returns item tax of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array contains Amount and Currency Code
     */
    public function getPromotionDiscount($i = 0){
        return $this->itemList[$i]['PromotionDiscount'];
    }
    
    /**
     * Returns item tax amount of specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return string
     */
    public function getPromotionDiscountAmount($i = 0){
        return $this->itemList[$i]['PromotionDiscount']['Amount'];
    }
    
    /**
     * Returns list of promotions for specified item, defaults to first if none given
     * @param string $i id of item to get
     * @return array
     */
    public function getPromotionIds($i = 0){
        return $this->itemList[$i]['PromotionIds'];
    }
    
    /**
     * Returns specified promotion ID for specified item, both default to first if none given
     * @param string $i id of item to get
     * @param integer $j index of promotion to get 
     * @return type
     */
    public function getPromotionId($i = 0, $j = 0){
        return $this->itemList[$i]['PromotionIds'][$j];
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
