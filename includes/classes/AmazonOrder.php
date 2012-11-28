<?php
/**
 * AmazonOrder object gets the details for a single object from Amazon
 */
class AmazonOrder extends AmazonOrderCore{
    private $data;
    private $xmldata;

    /**
     * AmazonOrder object gets the details for a single object from Amazon
     * @param string $s store name as seen in config
     * @param string $o Order number to automatically set
     * @param SimpleXMLElement $d XML data from Amazon to be parsed
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s,$o = null,$d = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        if($o){
            $this->options['AmazonOrderId.Id.1'] = $o;
        }
        if ($d) {
            $this->xmldata = $d;
        }
        
        $this->options['Action'] = 'GetOrder';
        
        $this->throttleLimit = $throttleLimitOrder;
        $this->throttleTime = $throttleTimeOrder;
        $this->throttleGroup = 'GetOrder';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
            $this->throttleCount = $this->throttleLimit;
        }
        
    }
    
    /**
     * Populates the object's data using the stored XML data. Clears existing data
     * @return boolean if no XML data
     */
    protected function parseXML(){
        if (!$this->xmldata){
            return false;
        }
        $this->data = array();
        $this->data['AmazonOrderId'] = (string)$this->xmldata->AmazonOrderId;
        $this->data['SellerOrderId'] = (string)$this->xmldata->SellerOrderId;
        $this->data['PurchaseDate'] = (string)$this->xmldata->PurchaseDate;
        $this->data['LastUpdateDate'] = (string)$this->xmldata->LastUpdateDate;
        $this->data['OrderStatus'] = (string)$this->xmldata->OrderStatus;
        $this->data['FulfillmentChannel'] = (string)$this->xmldata->FulfillmentChannel;
        $this->data['SalesChannel'] = (string)$this->xmldata->SalesChannel;
        $this->data['OrderChannel'] = (string)$this->xmldata->OrderChannel;
        $this->data['ShipServiceLevel'] = (string)$this->xmldata->ShipServiceLevel;
        
        if (isset($this->xmldata->ShippingAddress)){
            $this->data['ShippingAddress'] = array();
            $this->data['ShippingAddress']['Phone'] = (string)$this->xmldata->ShippingAddress->Phone;
            $this->data['ShippingAddress']['PostalCode'] = (string)$this->xmldata->ShippingAddress->PostalCode;
            $this->data['ShippingAddress']['Name'] = (string)$this->xmldata->ShippingAddress->Name;
            $this->data['ShippingAddress']['CountryCode'] = (string)$this->xmldata->ShippingAddress->CountryCode;
            $this->data['ShippingAddress']['StateOrRegion'] = (string)$this->xmldata->ShippingAddress->StateOrRegion;
            $this->data['ShippingAddress']['AddressLine1'] = (string)$this->xmldata->ShippingAddress->AddressLine1;
            $this->data['ShippingAddress']['AddressLine2'] = (string)$this->xmldata->ShippingAddress->AddressLine2;
            $this->data['ShippingAddress']['AddressLine3'] = (string)$this->xmldata->ShippingAddress->AddressLine3;
            $this->data['ShippingAddress']['City'] = (string)$this->xmldata->ShippingAddress->City;
        }
        
        
        
        if (isset($this->xmldata->OrderTotal)){
            $this->data['OrderTotal'] = array();
            $this->data['OrderTotal']['Amount'] = (string)$this->xmldata->OrderTotal->Amount;
            $this->data['OrderTotal']['CurrencyCode'] = (string)$this->xmldata->OrderTotal->CurrencyCode;
        }
        
        $this->data['NumberOfItemsShipped'] = (string)$this->xmldata->NumberOfItemsShipped;
        $this->data['NumberOfItemsUnshipped'] = (string)$this->xmldata->NumberOfItemsUnshipped;
        
        if (isset($this->xmldata->PaymentExecutionDetail)){
            $this->data['PaymentExecutionDetail'] = array();
            
            $i = 0;
            foreach($this->xmldata->PaymentExecutionDetail->children() as $x){
                $this->data['PaymentExecutionDetail']['Payment'.$i]['Amount'] = (string)$x->Payment->Amount;
                $this->data['PaymentExecutionDetail']['Payment'.$i]['CurrencyCode'] = (string)$x->Payment->CurrencyCode;
                $this->data['PaymentExecutionDetail']['Payment'.$i]['SubPaymentMethod'] = (string)$x->SubPaymentMethod;
                $i++;
            }
        }
        
        $this->data['MarketplaceId'] = (string)$this->xmldata->MarketplaceId;
        $this->data['BuyerName'] = (string)$this->xmldata->BuyerName;
        $this->data['BuyerEmail'] = (string)$this->xmldata->BuyerEmail;
        $this->data['ShipServiceLevelCategory'] = (string)$this->xmldata->ShipServiceLevelCategory;
    }
    
    /**
     * Returns the Amazon Order ID for the Order
     * @return string Amazon's Order ID
     */
    public function getAmazonOrderId(){
        return $this->data['AmazonOrderId'];
    }
    
    /**
     * Returns the Seller ID for the Order
     * @return string Seller-defined Order ID
     */
    public function getSellerOrderId(){
        return $this->data['SellerOrderId'];
    }
    
    /**
     * Returns the purchase date of the Order
     * @return dateTime timestamp
     */
    public function getPurchaseDate(){
        return $this->data['PurchaseDate'];
    }
    
    /**
     * Returns the timestamp of the last modification date
     * @return dateTime timestamp
     */
    public function getLastUpdateDate(){
        return $this->data['LastUpdateDate'];
    }
    
    /**
     * Returns the status of the Order
     * 
     * Returns the status of the Order. Possible Order Statuses are:
     * -Pending
     * -Unshipped
     * -Partially Shipped
     * -Shipped
     * -InvoiceUnconfirmed (China only)
     * -Canceled
     * -Unfulfillable
     * @return string order status
     */
    public function getOrderStatus(){
        return $this->data['OrderStatus'];
    }
    
    /**
     * Returns the Fulfillment Channel (AFN or MFN)
     * @return string either AFN or MFN
     */
    public function getFulfillmentChannel(){
        return $this->data['FulfillmentChannel'];
    }
    
    /**
     * Returns the Sales Channel Channel of the Order
     * @return string
     */
    public function getSalesChannel(){
        return $this->data['SalesChannel'];
    }
    
    /**
     * Returns the Order Channel of the first item in the Order.
     * @return string
     */
    public function getOrderChannel(){
        return $this->data['OrderChannel'];
    }
    
    /**
     * Returns the shipment service level of the Order
     * @return string
     */
    public function getShipServiceLevel(){
        return $this->data['ShipServiceLevel'];
    }
    
    /**
     * Returns an array containing all of the address information.
     * 
     * Returns an associative array of the address information, with the following fields:
     * -Name
     * -AddressLine1
     * -AddressLine2
     * -AddressLine3
     * -City
     * -County
     * -District
     * -StateOrRegion
     * -PostalCode
     * -CountryCode
     * -Phone
     * @return array Address array
     */
    public function getShippingAddress(){
        return $this->data['ShippingAddress'];
    }
    
    /**
     * Returns an array containing the total cost of the Order along with the currency used
     * 
     * Returns an associative array with the following fields:
     * -Amount
     * -CurrencyCode
     * @return array
     */
    public function getOrderTotal(){
        return $this->data['OrderTotal'];
    }
    
    /**
     * Returns just the total cost of the Order
     * @return string String of order total
     */
    public function getOrderTotalAmount(){
        return $this->data['OrderTotal']['Amount'];
    }

    /**
     * Returns the number of items in the Order that have been shipped
     * @return integer
     */
    public function getNumberofItemsShipped(){
        return $this->data['NumberOfItemsShipped'];
    }
    
    /**
     * Returns the number of items in the Order that have yet to be shipped
     * @return integer
     */
    public function getNumberOfItemsUnshipped(){
        return $this->data['NumberOfItemsUnshipped'];
    }
    
    /**
     * Returns an array of the complete payment details
     * 
     * Returns an associative array...
     * ...
     * ...
     * @return array
     */
    public function getPaymentExecutionDetail(){
        return $this->data['PaymentExecutionDetail'];
    }
    
    /**
     * Returns the payment method (either COD, CVS, or Other) of the Order
     * @return string COD, CVS, or Other
     */    
    public function getPaymentMethod(){
        return $this->data['PaymentMethod'];
    }
    
    /**
     * Returns the ID of the Marketplace in which the Order was placed
     * @return string
     */
    public function getMarketplaceId(){
        return $this->data['MarketplaceId'];
    }
    
    /**
     * Returns the name of the buyer
     * @return string
     */
    public function getBuyerName(){
        return $this->data['BuyerName'];
    }
    
    /**
     * Returns the email address of the buyer
     * @return string
     */
    public function getBuyerEmail(){
        return $this->data['BuyerEmail'];
    }
    
    /**
     * Returns the shipment service level category of the Order
     * 
     * Returns the shipment serice level category of the Order. Valid values are...
     * -Expedited
     * -NextDay
     * -SecondDay
     * -Standard
     * @return type
     */
    public function getShipServiceLevelCategory(){
        return $this->data['ShipServiceLevelCategory'];
    }
    
    /**
     * Returns the ratio of shipped items to unshipped items
     * @return float Decimal number from 0 to 1
     */
    public function getPercentShipped(){
        if (array_key_exists('NumberOfItemsShipped',$this->data) && array_key_exists('NumberOfItemsUnshipped',$this->data)){
            $ratio = $this->data['NumberOfItemsShipped'] / $this->data['NumberOfItemsUnshipped'];
        }
        
        return $ratio;
    }
    
    /**
     * Fetches the specified order from Amazon after setting the necessary parameters
     */
    public function fetchOrder(){
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
//        myPrint($this->options);
//        myPrint($query);
        
//        myPrint($this->options);
//        $query = $this->genRequest();
//        myPrint($query);
        
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
        
        
        $this->xmldata = $xml->GetOrderResult->Orders->Order;
        $this->parseXML();
    
    }
    
    /**
     * Sets the Amazon Order ID for the next request, in case it was not set in the constructor
     * @param string $id the Amazon Order ID
     */
    public function setOrderId($id){
        if ($id){
            $this->options['AmazonOrderId.Id.1'] = $id;
        } else {
            $this->log("Attempted to set AmazonOrderId to nothing",'Warning');
            return false;
        }
    }

    /**
     * Fetches items for the order
     * @param boolean $token whether or not to automatically use item tokens
     * @return AmazonOrderItemList container for order's items
     */
    public function fetchItems($token = false){
        if (!is_bool($token)){
            return false;
        }
        $items = new AmazonOrderItemList($this->storeName,$this->data['AmazonOrderId'],$this->mockMode,$this->mockFiles);
        $items->setUseToken($token);
        $items->fetchItems();
        return $items;
    }
}

?>
