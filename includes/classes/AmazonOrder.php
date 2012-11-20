<?php
/**
 * AmazonOrder object gets the details for a single object from Amazon
 */
class AmazonOrder extends AmazonOrderCore{
    private $itemFlag;
    private $tokenItemFlag;
    private $data;
    private $xmldata;

    /**
     * AmazonOrder object gets the details for a single object from Amazon
     * @param string $s store name as seen in config
     * @param string $o Order number to automatically set
     * @param SimpleXMLElement $d XML data from Amazon to be parsed
     * @param boolean $mock set true to enable mock mode
     */
    public function __construct($s,$o = null,$d = null, $mock = false){
        parent::__construct($s, $mock);
        include($this->config);
        
        if($o){
            $this->options['AmazonOrderId.Id.1'] = $o;
        }
        if ($d) {
            $this->xmldata = $d;
        }
        
        $this->throttleLimit = $throttleLimitOrder;
        $this->throttleTime = $throttleTimeOrder;
        $this->throttleCount = $this->throttleLimit;
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
            $this->throttleCount = $this->throttleLimit;
        }
        
        //$this->options['Action'] = 'GetOrder';
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
     * Sets the flag for whether or not to fetch items
     * @param boolean $b True to get items, False to not
     * @throws InvalidArgumentException
     */
    public function setFetchItems($b = true){
        if (is_bool($b)){
            $this->itemFlag = $b;
        } else {
            throw new InvalidArgumentException('The paramater for setFetchItems() should be either true or false.');
        }
    }

    /**
     * Sets whether or not the Order should automatically use tokens when fetching items
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
     * @throws Exception if request fails
     */
    public function fetchOrder(){
        //STILL TO DO: GET SET OF MULTIPLE ORDER IDS
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'GetOrder';
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
//        myPrint($this->options);
//        myPrint($query);
        
//        myPrint($this->options);
//        $query = $this->genRequest();
//        myPrint($query);
        
        if ($this->mockMode){
            $response = $this->fetchMockFile();
        } else {
            $this->throttle();
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
        }
        
        if ($response['code'] != 200){
            throw new Exception('Still to do: handle this better');
        }
        
        $xml = simplexml_load_string($response['body']);
        
        $this->xmldata = $xml->GetOrderResult->Orders->Order;
        $this->parseXML();
        
        if ($this->itemFlag){
            $this->fetchItems();
        }
    
    }
    
//    protected function fetchOrderFromCache(){
//        include ('db-config.php');
//        
//        $sql = 'SELECT * FROM `amazonOrderLog` WHERE orderid = ?';
//        $value = array($this->options['AmazonOrderId.Id.1']);
//        
//        $result = db::executeQuery($sql, $value, DB_PLUGINS)->fetchAll();
//        
//        myPrint($result);
//        
//        return array();
//    }
    
    
    /**
     * Sets the Amazon Order ID for the next request, in case it was not set in the constructor
     * @param string $id the Amazon Order ID
     * @throws InvalidArgumentException if the parameter is left empty
     */
    public function setOrderId($id){
        if ($id){
            $this->options['AmazonOrderId.Id.1'] = $id;
        } else {
            throw new InvalidArgumentException('No Order ID given!');
        }
    }

    /**
     * Fetches items for the orders stored in the Order List
     */
    public function fetchItems(){
        $this->data['Items'] = new AmazonItemList($this->storeName,$this->data['AmazonOrderId']);
        $this->data['Items']->setUseToken($this->tokenItemFlag);
        $this->data['Items']->fetchItems();
    }
    
    /**
     * returns entire Item List object, for convenience
     * @return AmazonItemList item list
     */
    public function getItems(){
        return $this->data['Items'];
    }
}

?>
