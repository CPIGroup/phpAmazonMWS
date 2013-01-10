<?php
/**
 * Gets the details for a single order from Amazon.
 * 
 * This Amazon Order Core object retrieves (or simply contains) the data
 * for a single order on Amazon. In order to fetch this data, an Amazon
 * Order ID is required.
 */
class AmazonOrder extends AmazonOrderCore{
    private $data;

    /**
     * AmazonOrder object gets the details for a single object from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that two extra parameters come before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param string $id [optional] <p>The Order ID to set for the object.</p>
     * @param SimpleXMLElement $data [optional] <p>XML data from Amazon to be parsed.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s, $id = null, $data = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
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
        
        $this->throttleLimit = THROTTLE_LIMIT_ORDER;
        $this->throttleTime = THROTTLE_TIME_ORDER;
        $this->throttleGroup = 'GetOrder';
    }
    
    /**
     * Sets the Amazon Order ID. (Required)
     * 
     * This method sets the Amazon Order ID to be sent in the next request.
     * This parameter is required for fetching the order from Amazon.
     * @param string $s <p>either string or number</p>
     * @return boolean <p><b>FALSE</b> if improper input</p>
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
     * Fetches the specified order from Amazon.
     * 
     * Submits a <i>GetOrder</i> request to Amazon. In order to do this,
     * an Amazon order ID is required. Amazon will send
     * the data back as a response, which can be retrieved using <i>getData</i>.
     * Other methods are available for fetching specific values from the order.
     * @return boolean <p><b>FALSE</b> if something goes wrong</p>
     */
    public function fetchOrder(){
        if (!array_key_exists('AmazonOrderId.Id.1',$this->options)){
            $this->log("Order ID must be set in order to fetch it!",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        if ($this->mockMode){
            $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        $this->parseXML($xml->GetOrderResult->Orders->Order);
    
    }

    /**
     * Fetches items for the order from Amazon.
     * 
     * See the <i>AmazonOrderItemList</i> class for more information on the returned object.
     * @param boolean $token [optional] <p>whether or not to automatically use item tokens in the request</p>
     * @return AmazonOrderItemList <p>container for order's items</p>
     */
    public function fetchItems($token = false){
        if (!isset($this->data['AmazonOrderId'])){
            return false;
        }
        if (!is_bool($token)){
            $token = false;
        }
        $items = new AmazonOrderItemList($this->storeName,$this->data['AmazonOrderId']);
        $items->setConfig($this->config);
        $items->setLogPath($this->logpath);
        $items->setMock($this->mockMode,$this->mockFiles);
        $items->mockIndex = $this->mockIndex;
        $items->setUseToken($token);
        $items->fetchItems();
        return $items;
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLObject $xml <p>The XML response from Amazon.</p>
     * @return boolean <p><b>FALSE</b> if no XML data is found</p>
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
     * Returns the full set of data for the order.
     * 
     * This method will return <b>FALSE</b> if the order data has not yet been filled.
     * The array returned will have the following fields:
     * <ul>
     * <li><b>AmazonOrderId</b> - unique ID for the order, which you sent in the first place</li>
     * <li><b>SellerOrderId</b> (optional) - your unique ID for the order</li>
     * <li><b>PurchaseDate</b> - time in ISO8601 date format</li>
     * <li><b>LastUpdateDate</b> - time in ISO8601 date format</li>
     * <li><b>OrderStatus</b> - the current status of the order, see <i>getOrderStatus</i> for more details</li>
     * <li><b>MarketplaceId</b> - the marketplace in which the order was placed</li>
     * <li><b>FulfillmentChannel</b> (optional) - "AFN" or "MFN"</li>
     * <li><b>SalesChannel</b> (optional) - sales channel for the first item in the order</li>
     * <li><b>OrderChannel</b> (optional) - order channel for the first item in the order</li>
     * <li><b>ShipServiceLevel</b> (optional) - shipment service level of the order</li>
     * <li><b>ShippingAddress</b> (optional) - array, see <i>getShippingAddress</i> for more details</li>
     * <li><b>OrderTotal</b> (optional) - array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>NumberOfItemsShipped</b> (optional) - number of items shipped</li>
     * <li><b>NumberOfItemsUnshipped</b> (optional) - number of items not shipped</li>
     * <li><b>PaymentExecutionDetail</b> (optional) - multi-dimensional array, see <i>getPaymentExecutionDetail</i> for more details</li>
     * <li><b>PaymentMethod</b> (optional) - "COD", "CVS", or "Other"</li>
     * <li><b>BuyerName</b> (optional) - name of the buyer</li>
     * <li><b>BuyerEmail</b> (optional) - Amazon-generated email for the buyer</li>
     * <li><b>ShipServiceLevelCategory</b> (optional) - "Expedited", "NextDay", "SecondDay", or "Standard"</li>
     * </ul>
     * @return array|boolean <p>array of data, or <b>FALSE</b> if data not filled yet</p>
     */
    public function getData(){
        if (isset($this->data) && $this->data){
            return $this->data;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Amazon Order ID for the Order.
     * 
     * This method will return <b>FALSE</b> if the order ID has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if order ID not set yet</p>
     */
    public function getAmazonOrderId(){
        if (isset($this->data['AmazonOrderId'])){
            return $this->data['AmazonOrderId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the seller-defined ID for the Order.
     * 
     * This method will return <b>FALSE</b> if the order ID has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if order ID not set yet</p>
     */
    public function getSellerOrderId(){
        if (isset($this->data['SellerOrderId'])){
            return $this->data['SellerOrderId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the purchase date of the Order.
     * 
     * This method will return <b>FALSE</b> if the timestamp has not been set yet.
     * @return string|boolean <p>timestamp, or <b>FALSE</b> if timestamp not set yet</p>
     */
    public function getPurchaseDate(){
        if (isset($this->data['PurchaseDate'])){
            return $this->data['PurchaseDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the timestamp of the last modification date.
     * 
     * This method will return <b>FALSE</b> if the timestamp has not been set yet.
     * @return string|boolean <p>timestamp, or <b>FALSE</b> if timestamp not set yet</p>
     */
    public function getLastUpdateDate(){
        if (isset($this->data['LastUpdateDate'])){
            return $this->data['LastUpdateDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the status of the Order.
     * 
     * This method will return <b>FALSE</b> if the order status has not been set yet.
     * Possible Order Statuses are:
     * <ul>
     * <li>Pending</li>
     * <li>Unshipped</li>
     * <li>Partially Shipped</li>
     * <li>Shipped</li>
     * <li>Cancelled</li>
     * <li>Unfulfillable</li>
     * </ul>
     * @return string|boolean <p>single value, or <b>FALSE</b> if status not set yet</p>
     */
    public function getOrderStatus(){
        if (isset($this->data['OrderStatus'])){
            return $this->data['OrderStatus'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Fulfillment Channel.
     * 
     * This method will return <b>FALSE</b> if the fulfillment channel has not been set yet.
     * @return string|boolean <p>"AFN" or "MFN", or <b>FALSE</b> if channel not set yet</p>
     */
    public function getFulfillmentChannel(){
        if (isset($this->data['FulfillmentChannel'])){
            return $this->data['FulfillmentChannel'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Sales Channel of the Order.
     * 
     * This method will return <b>FALSE</b> if the sales channel has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if channel not set yet</p>
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
     * 
     * This method will return <b>FALSE</b> if the order channel has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if channel not set yet</p>
     */
    public function getOrderChannel(){
        if (isset($this->data['OrderChannel'])){
            return $this->data['OrderChannel'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipment service level of the Order.
     * 
     * This method will return <b>FALSE</b> if the shipment service level has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if level not set yet</p>
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
     * This method will return <b>FALSE</b> if the address has not been set yet.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>Name</b></li>
     * <li><b>AddressLine1</b></li>
     * <li><b>AddressLine2</b></li>
     * <li><b>AddressLine3</b></li>
     * <li><b>City</b></li>
     * <li><b>County</b></li>
     * <li><b>District</b></li>
     * <li><b>StateOrRegion</b></li>
     * <li><b>PostalCode</b></li>
     * <li><b>CountryCode</b></li>
     * <li><b>Phone</b></li>
     * </ul>
     * @return array|boolean <p>associative array, or <b>FALSE</b> if address not set yet</p>
     */
    public function getShippingAddress(){
        if (isset($this->data['ShippingAddress'])){
            return $this->data['ShippingAddress'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns an array containing the total cost of the Order along with the currency used.
     * 
     * This method will return <b>FALSE</b> if the order total has not been set yet.
     * The returned array has the following fields:
     * <ul>
     * <li><b>Amount</b></li>
     * <li><b>CurrencyCode</b></li>
     * </ul>
     * @return array|boolean <p>associative array, or <b>FALSE</b> if total not set yet</p>
     */
    public function getOrderTotal(){
        if (isset($this->data['OrderTotal'])){
            return $this->data['OrderTotal'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns just the total cost of the Order.
     * 
     * This method will return <b>FALSE</b> if the order total has not been set yet.
     * @return string|boolean <p>number, or <b>FALSE</b> if total not set yet</p>
     */
    public function getOrderTotalAmount(){
        if (isset($this->data['OrderTotal']) && isset($this->data['OrderTotal']['Amount'])){
            return $this->data['OrderTotal']['Amount'];
        } else {
            return false;
        }
    }

    /**
     * Returns the number of items in the Order that have been shipped.
     * 
     * This method will return <b>FALSE</b> if the number has not been set yet.
     * @return integer|boolean <p>non-negative number, or <b>FALSE</b> if number not set yet</p>
     */
    public function getNumberofItemsShipped(){
        if (isset($this->data['NumberOfItemsShipped'])){
            return $this->data['NumberOfItemsShipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the number of items in the Order that have yet to be shipped.
     * 
     * This method will return <b>FALSE</b> if the number has not been set yet.
     * @return integer|boolean <p>non-negative number, or <b>FALSE</b> if number not set yet</p>
     */
    public function getNumberOfItemsUnshipped(){
        if (isset($this->data['NumberOfItemsUnshipped'])){
            return $this->data['NumberOfItemsUnshipped'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns an array of the complete payment details.
     * 
     * This method will return <b>FALSE</b> if the payment details has not been set yet.
     * The array returned contains one or more arrays with the following fields:
     * <ul>
     * <li><b>Amount</b></li>
     * <li><b>CurrencyCode</b></li>
     * <li><b>SubPaymentMethod</b></li>
     * </ul>
     * @return array|boolean <p>multi-dimensional array, or <b>FALSE</b> if details not set yet</p>
     */
    public function getPaymentExecutionDetail(){
        if (isset($this->data['PaymentExecutionDetail'])){
            return $this->data['PaymentExecutionDetail'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the payment method of the Order.
     * 
     * This method will return <b>FALSE</b> if the payment method has not been set yet.
     * @return string|boolean <p>"COD", "CVS", "Other", or <b>FALSE</b> if method not set yet</p>
     */    
    public function getPaymentMethod(){
        if (isset($this->data['PaymentMethod'])){
            return $this->data['PaymentMethod'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the ID of the Marketplace in which the Order was placed.
     * 
     * This method will return <b>FALSE</b> if the marketplace ID has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if ID not set yet</p>
     */
    public function getMarketplaceId(){
        if (isset($this->data['MarketplaceId'])){
            return $this->data['MarketplaceId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the name of the buyer.
     * 
     * This method will return <b>FALSE</b> if the buyer name has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if name not set yet</p>
     */
    public function getBuyerName(){
        if (isset($this->data['BuyerName'])){
            return $this->data['BuyerName'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Amazon-generated email address of the buyer.
     * 
     * This method will return <b>FALSE</b> if the buyer email has not been set yet.
     * @return string|boolean <p>single value, or <b>FALSE</b> if email not set yet</p>
     */
    public function getBuyerEmail(){
        if (isset($this->data['BuyerEmail'])){
            return $this->data['BuyerEmail'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the shipment service level category of the Order.
     * 
     * This method will return <b>FALSE</b> if the service level category has not been set yet.
     * Valid values for the service level category are...
     * <ul>
     * <li>Expedited</li>
     * <li>NextDay</li>
     * <li>SecondDay</li>
     * <li>Standard</li>
     * </ul>
     * @return string|boolean <p>single value, or <b>FALSE</b> if category not set yet</p>
     */
    public function getShipServiceLevelCategory(){
        if (isset($this->data['ShipServiceLevelCategory'])){
            return $this->data['ShipServiceLevelCategory'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the ratio of shipped items to unshipped items.
     * 
     * This method will return <b>FALSE</b> if the shipment numbers have not been set yet.
     * @return float|boolean <p>Decimal number from 0 to 1, or <b>FALSE</b> if numbers not set yet</p>
     */
    public function getPercentShipped(){
        if (isset($this->data['NumberOfItemsShipped']) && isset($this->data['NumberOfItemsUnshipped'])){
            $total = $this->data['NumberOfItemsShipped'] + $this->data['NumberOfItemsUnshipped'];
            
            if ($total == 0){
                return 0;
            }
            
            $ratio = $this->data['NumberOfItemsShipped'] / $total;
            return $ratio;
        } else {
            return false;
        }
    }
}

?>
