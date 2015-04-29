<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Gets all of the items for a given order.
 * 
 * This Amazon Orders Core object can retrieve the list of items associated
 * with a specific order. Before any items can be retrieved, an Order ID is
 * required. This object can use tokens when retrieving the list.
 */
class AmazonOrderItemList extends AmazonOrderCore implements Iterator{
    private $itemList;
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    private $i = 0;
    private $index = 0;

    /**
     * AmazonItemLists contain all of the items for a given order.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param string $id [optional] <p>The order ID to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s, $id=null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        
        
        if (!is_null($id)){
            $this->setOrderId($id);
        }
        
        if(isset($THROTTLE_LIMIT_ITEM)) {
            $this->throttleLimit = $THROTTLE_LIMIT_ITEM;
        }
        if(isset($THROTTLE_TIME_ITEM)) {
            $this->throttleTime = $THROTTLE_TIME_ITEM;
        }
        $this->throttleGroup = 'ListOrderItems';
    }
    
    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }

    /**
     * Sets whether or not the object should automatically use tokens if it receives one.
     * 
     * If this option is set to <b>TRUE</b>, the object will automatically perform
     * the necessary operations to retrieve the rest of the list using tokens. If
     * this option is off, the object will only ever retrieve the first section of
     * the list.
     * @param boolean $b [optional] <p>Defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the Amazon Order ID. (Required)
     * 
     * This method sets the Amazon Order ID to be sent in the next request.
     * This parameter is required for fetching the order's items from Amazon.
     * @param string $s <p>either string or number</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setOrderId($id){
        if (is_string($id) || is_numeric($id)){
            $this->options['AmazonOrderId'] = $id;
        } else {
            return false;
        }
    }

    /**
     * Retrieves the items from Amazon.
     * 
     * Submits a <i>ListOrderItems</i> request to Amazon. In order to do this,
     * an Amazon order ID is required. Amazon will send
     * the data back as a response, which can be retrieved using <i>getItems</i>.
     * Other methods are available for fetching specific values from the order.
     * This operation can potentially involve tokens.
     * @param boolean <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchItems($r = true){
        $this->prepareToken();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        if (is_null($xml->AmazonOrderId)){
            $this->log("You just got throttled.",'Warning');
            return false;
        } else if (isset($this->options['AmazonOrderId']) && $this->options['AmazonOrderId'] && $this->options['AmazonOrderId'] != $xml->AmazonOrderId){
            $this->log('You grabbed the wrong Order\'s items! - '.$this->options['AmazonOrderId'].' =/= '.$xml->AmazonOrderId,'Urgent');
        }
        
        $this->parseXML($xml->OrderItems);
        
        $this->checkToken($xml);
        
        if ($this->tokenFlag && $this->tokenUseFlag && $r === true){
            while ($this->tokenFlag){
                $this->log("Recursively fetching more items");
                $this->fetchItems(false);
            }
        }
    }

    /**
     * Sets up options for using tokens.
     * 
     * This changes key options for switching between simply fetching a list and
     * fetching the rest of a list using a token. Please note: because the
     * operation for using tokens does not use any other parameters, all other
     * parameters will be removed.
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
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLObject $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
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
     * Returns the specified order item, or all of them.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single order item will have the following fields:
     * <ul>
     * <li><b>ASIN</b> - the ASIN for the item</li>
     * <li><b>SellerSKU</b> - the SKU for the item</li>
     * <li><b>OrderItemId</b> - the unique ID for the order item</li>
     * <li><b>Title</b> - the name of the item</li>
     * <li><b>QuantityOrdered</b> - the quantity of the item ordered</li>
     * <li><b>QuantityShipped</b> (optional) - the quantity of the item shipped</li>
     * <li><b>GiftMessageText</b> (optional) - gift message for the item</li>
     * <li><b>GiftWrapLevel</b> (optional) - the type of gift wrapping for the item</li>
     * <li><b>ItemPrice</b> (optional) - price for the item, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>ShippingPrice</b> (optional) - price for shipping, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>GiftWrapPrice</b> (optional) - price for gift wrapping, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>ItemTax</b> (optional) - tax on the item, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>ShippingTax</b> (optional) - tax on shipping, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>GiftWrapTax</b> (optional) - tax on gift wrapping, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>ShippingDiscount</b> (optional) - discount on shipping, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>PromotionDiscount</b> (optional) -promotional discount, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>CODFee</b> (optional) -fee charged for COD service, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>CODFeeDiscount</b> (optional) -discount on COD fee, array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>PromotionIds</b> (optional) -array of promotion IDs</li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.</p>
     * @return array|boolean array, multi-dimensional array, or <b>FALSE</b> if list not filled yet
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
     * Returns the ASIN for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getASIN($i = 0){
        if (isset($this->itemList[$i]['ASIN'])){
            return $this->itemList[$i]['ASIN'];
        } else {
            return false;
        }
        
    }
    
    /**
     * Returns the seller SKU for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getSellerSKU($i = 0){
        if (isset($this->itemList[$i]['SellerSKU'])){
            return $this->itemList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the order item ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getOrderItemId($i = 0){
        if (isset($this->itemList[$i]['OrderItemId'])){
            return $this->itemList[$i]['OrderItemId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the name for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getTitle($i = 0){
        if (isset($this->itemList[$i]['Title'])){
            return $this->itemList[$i]['Title'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity ordered for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getQuantityOrdered($i = 0){
        if (isset($this->itemList[$i]['QuantityOrdered'])){
            return $this->itemList[$i]['QuantityOrdered'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the quantity shipped for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getQuantityShipped($i = 0){
        if (isset($this->itemList[$i]['QuantityShipped'])){
            return $this->itemList[$i]['QuantityShipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the seller SKU for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return float|boolean decimal number from 0 to 1, or <b>FALSE</b> if Non-numeric index
     */
    public function getPercentShipped($i = 0){
        if (!$this->getQuantityOrdered($i) || !$this->getQuantityShipped($i)){
            return false;
        }
        if (isset($this->itemList[$i]['QuantityOrdered']) && isset($this->itemList[$i]['QuantityShipped'])){
            return $this->itemList[$i]['QuantityShipped']/$this->itemList[$i]['QuantityOrdered'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the gift message text for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getGiftMessageText($i = 0){
        if (isset($this->itemList[$i]['GiftMessageText'])){
            return $this->itemList[$i]['GiftMessageText'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the gift wrap level for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getGiftWrapLevel($i = 0){
        if (isset($this->itemList[$i]['GiftWrapLevel'])){
            return $this->itemList[$i]['GiftWrapLevel'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the item price for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the shipping price for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the gift wrap price for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the item tax for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the shipping tax for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the gift wrap tax for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the shipping discount for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns the promotional discount for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the amount</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if Non-numeric index
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
     * Returns specified promotion ID for specified item.
     * 
     * This method will return the entire list of Promotion IDs if <i>$j</i> is not set.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Second list index to retrieve the value from. Defaults to NULL.</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if incorrect index
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
