<?php
/**
 * Gets the details for a single object from Amazon.
 * 
 * This Amazon Order Core object retrieves (or simply contains) the data
 * for a single order on Amazon. In order to fetch this data, an Amazon
 * Order ID is required.
 */
class AmazonOrder extends AmazonOrderCore{
    private $data;

    /**
     * AmazonOrder object gets the details for a single object from Amazon
     * @param string $s store name as seen in config
     * @param string $id Order number to automatically set
     * @param SimpleXMLElement $data XML data from Amazon to be parsed
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $id = null, $data = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if($id){
            $this->setOrderId($id);
        }
        if ($data) {
            $this->parseXML($data);
        }
        
        $this->options['Action'] = 'GetOrder';
        
        $this->throttleLimit = $throttleLimitOrder;
        $this->throttleTime = $throttleTimeOrder;
        $this->throttleGroup = 'GetOrder';
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
        }
        
    }
    
    /**
     * Sets the Amazon Order ID for the next request, in case it was not set in the constructor
     * @param string $id the Amazon Order ID
     */
    public function setOrderId($id){
        if (is_string($id) || is_numeric($id)){
            $this->options['AmazonOrderId.Id.1'] = $id;
        } else {
            $this->log("Attempted to set AmazonOrderId to invalid value",'Warning');
            return false;
        }
    }
    
    /**
     * Fetches the specified order from Amazon after setting the necessary parameters
     */
    public function fetchOrder(){
        if (!array_key_exists('AmazonOrderId.Id.1',$this->options)){
            $this->log("Order ID must be set in order to fetch it!",'Warning');
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
        
        $this->parseXML($xml->GetOrderResult->Orders->Order);
    
    }

    /**
     * Fetches items for the order
     * @param boolean $token whether or not to automatically use item tokens
     * @return AmazonOrderItemList container for order's items
     */
    public function fetchItems($token = false){
        if (!isset($this->data['AmazonOrderId'])){
            return false;
        }
        if (!is_bool($token)){
            $token = false;
        }
        $items = new AmazonOrderItemList($this->storeName,$this->data['AmazonOrderId'],$this->mockMode,$this->mockFiles);
        $items->mockIndex = $this->mockIndex;
        $items->setUseToken($token);
        $items->fetchItems();
        return $items;
    }
    
    /**
     * Populates the object's data using the stored XML data. Clears existing data
     * @return boolean if no XML data
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        $d = array();
        $d['AmazonOrderId'] = (string)$xml->AmazonOrderId;
        if (isset($xml->SellerOrderId)){
            $d['SellerOrderId'] = (string)$xml->SellerOrderId;
        }
        $d['PurchaseDate'] = (string)$xml->PurchaseDate;
        $d['LastUpdateDate'] = (string)$xml->LastUpdateDate;
        $d['OrderStatus'] = (string)$xml->OrderStatus;
        if (isset($xml->FulfillmentChannel)){
            $d['FulfillmentChannel'] = (string)$xml->FulfillmentChannel;
        }
        if (isset($xml->SalesChannel)){
            $d['SalesChannel'] = (string)$xml->SalesChannel;
        }
        if (isset($xml->OrderChannel)){
            $d['OrderChannel'] = (string)$xml->OrderChannel;
        }
        if (isset($xml->ShipServiceLevel)){
            $d['ShipServiceLevel'] = (string)$xml->ShipServiceLevel;
        }
        if (isset($xml->ShippingAddress)){
            $d['ShippingAddress'] = array();
            $d['ShippingAddress']['Name'] = (string)$xml->ShippingAddress->Name;
            $d['ShippingAddress']['AddressLine1'] = (string)$xml->ShippingAddress->AddressLine1;
            $d['ShippingAddress']['AddressLine2'] = (string)$xml->ShippingAddress->AddressLine2;
            $d['ShippingAddress']['AddressLine3'] = (string)$xml->ShippingAddress->AddressLine3;
            $d['ShippingAddress']['City'] = (string)$xml->ShippingAddress->City;
            $d['ShippingAddress']['County'] = (string)$xml->ShippingAddress->County;
            $d['ShippingAddress']['District'] = (string)$xml->ShippingAddress->District;
            $d['ShippingAddress']['StateOrRegion'] = (string)$xml->ShippingAddress->StateOrRegion;
            $d['ShippingAddress']['PostalCode'] = (string)$xml->ShippingAddress->PostalCode;
            $d['ShippingAddress']['CountryCode'] = (string)$xml->ShippingAddress->CountryCode;
            $d['ShippingAddress']['Phone'] = (string)$xml->ShippingAddress->Phone;
        }
        if (isset($xml->OrderTotal)){
            $d['OrderTotal'] = array();
            $d['OrderTotal']['Amount'] = (string)$xml->OrderTotal->Amount;
            $d['OrderTotal']['CurrencyCode'] = (string)$xml->OrderTotal->CurrencyCode;
        }
        if (isset($xml->NumberOfItemsShipped)){
            $d['NumberOfItemsShipped'] = (string)$xml->NumberOfItemsShipped;
        }
        if (isset($xml->NumberOfItemsUnshipped)){
            $d['NumberOfItemsUnshipped'] = (string)$xml->NumberOfItemsUnshipped;
        }
        if (isset($xml->PaymentExecutionDetail)){
            $d['PaymentExecutionDetail'] = array();
            
            $i = 0;
            foreach($xml->PaymentExecutionDetail->children() as $x){
                $d['PaymentExecutionDetail'][$i]['Amount'] = (string)$x->Payment->Amount;
                $d['PaymentExecutionDetail'][$i]['CurrencyCode'] = (string)$x->Payment->CurrencyCode;
                $d['PaymentExecutionDetail'][$i]['SubPaymentMethod'] = (string)$x->SubPaymentMethod;
                $i++;
            }
        }
        if (isset($xml->PaymentMethod)){
            $d['PaymentMethod'] = (string)$xml->PaymentMethod;
        }
        $d['MarketplaceId'] = (string)$xml->MarketplaceId;
        if (isset($xml->BuyerName)){
            $d['BuyerName'] = (string)$xml->BuyerName;
        }
        if (isset($xml->BuyerEmail)){
            $d['BuyerEmail'] = (string)$xml->BuyerEmail;
        }
        if (isset($xml->ShipServiceLevelCategory)){
            $d['ShipServiceLevelCategory'] = (string)$xml->ShipServiceLevelCategory;
        }
        
        $this->data = $d;
    }
    
    /**
     * returns all data
     * @return array|boolean entire set of data, or false on failure
     */
    public function getData(){
        if (isset($this->data) && $this->data){
            return $this->data;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Amazon Order ID for the Order
     * @return string|boolean Amazon's Order ID, or false if not set yet
     */
    public function getAmazonOrderId(){
        if (isset($this->data['AmazonOrderId'])){
            return $this->data['AmazonOrderId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Seller ID for the Order
     * @return string|boolean Seller-defined Order ID, or false if not set yet
     */
    public function getSellerOrderId(){
        if (isset($this->data['SellerOrderId'])){
            return $this->data['SellerOrderId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the purchase date of the Order
     * @return dateTime timestamp, or false if not set yet
     */
    public function getPurchaseDate(){
        if (isset($this->data['PurchaseDate'])){
            return $this->data['PurchaseDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the timestamp of the last modification date
     * @return dateTime timestamp, or false if not set yet
     */
    public function getLastUpdateDate(){
        if (isset($this->data['LastUpdateDate'])){
            return $this->data['LastUpdateDate'];
        } else {
            return false;
        }
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
     * @return string order status, or false if not set yet
     */
    public function getOrderStatus(){
        if (isset($this->data['OrderStatus'])){
            return $this->data['OrderStatus'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Fulfillment Channel (AFN or MFN)
     * @return string either AFN or MFN, or false if not set yet
     */
    public function getFulfillmentChannel(){
        if (isset($this->data['FulfillmentChannel'])){
            return $this->data['FulfillmentChannel'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Sales Channel Channel of the Order
     * @return string channel, or false if not set yet
     */
    public function getSalesChannel(){
        if (isset($this->data['SalesChannel'])){
            return $this->data['SalesChannel'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Order Channel of the first item in the Order.
     * @return string channel, or false if not set yet
     */
    public function getOrderChannel(){
        if (isset($this->data['OrderChannel'])){
            return $this->data['OrderChannel'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipment service level of the Order
     * @return string service level, or false if not set yet
     */
    public function getShipServiceLevel(){
        if (isset($this->data['ShipServiceLevel'])){
            return $this->data['ShipServiceLevel'];
        } else {
            return false;
        }
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
     * @return array Address array, or false if not set yet
     */
    public function getShippingAddress(){
        if (isset($this->data['ShippingAddress'])){
            return $this->data['ShippingAddress'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns an array containing the total cost of the Order along with the currency used
     * 
     * Returns an associative array with the following fields:
     * -Amount
     * -CurrencyCode
     * @return array order total data, or false if not set yet
     */
    public function getOrderTotal(){
        if (isset($this->data['OrderTotal'])){
            return $this->data['OrderTotal'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns just the total cost of the Order
     * @return string String of order total, or false if not set yet
     */
    public function getOrderTotalAmount(){
        if (isset($this->data['OrderTotal']) && isset($this->data['OrderTotal']['Amount'])){
            return $this->data['OrderTotal']['Amount'];
        } else {
            return false;
        }
    }

    /**
     * Returns the number of items in the Order that have been shipped
     * @return integer non-negative integer, or false if not set yet
     */
    public function getNumberofItemsShipped(){
        if (isset($this->data['NumberOfItemsShipped'])){
            return $this->data['NumberOfItemsShipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the number of items in the Order that have yet to be shipped
     * @return integer non-negative integer, or false if not set yet
     */
    public function getNumberOfItemsUnshipped(){
        if (isset($this->data['NumberOfItemsUnshipped'])){
            return $this->data['NumberOfItemsUnshipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns an array of the complete payment details
     * 
     * Returns an associative array...
     * ...
     * ...
     * @return array payment data, or false if not set yet
     */
    public function getPaymentExecutionDetail(){
        if (isset($this->data['PaymentExecutionDetail'])){
            return $this->data['PaymentExecutionDetail'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the payment method (either COD, CVS, or Other) of the Order
     * @return string COD, CVS, or Other, or false if not set yet
     */    
    public function getPaymentMethod(){
        if (isset($this->data['PaymentMethod'])){
            return $this->data['PaymentMethod'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the ID of the Marketplace in which the Order was placed
     * @return string Marketplace ID, or false if not set yet
     */
    public function getMarketplaceId(){
        if (isset($this->data['MarketplaceId'])){
            return $this->data['MarketplaceId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the name of the buyer
     * @return string name, or false if not set yet
     */
    public function getBuyerName(){
        if (isset($this->data['BuyerName'])){
            return $this->data['BuyerName'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the email address of the buyer
     * @return string email, or false if not set yet
     */
    public function getBuyerEmail(){
        if (isset($this->data['BuyerEmail'])){
            return $this->data['BuyerEmail'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipment service level category of the Order
     * 
     * Returns the shipment serice level category of the Order. Valid values are...
     * -Expedited
     * -NextDay
     * -SecondDay
     * -Standard
     * @return string value, or false if not set yet
     */
    public function getShipServiceLevelCategory(){
        if (isset($this->data['ShipServiceLevelCategory'])){
            return $this->data['ShipServiceLevelCategory'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the ratio of shipped items to unshipped items
     * @return float Decimal number from 0 to 1, or false if not set yet
     */
    public function getPercentShipped(){
        if (isset($this->data['NumberOfItemsShipped']) && isset($this->data['NumberOfItemsUnshipped'])){
            $total = $this->data['NumberOfItemsShipped'] + $this->data['NumberOfItemsUnshipped'];
            $ratio = $this->data['NumberOfItemsShipped'] / $total;
            return $ratio;
        } else {
            return false;
        }
    }
}

?>
