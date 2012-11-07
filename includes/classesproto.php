<?php
abstract class AmazonCore{
    //this is the abstract master class thing
    //track and do throttling
    //handle API and credentials
    protected $urlbase;
    protected $urlbranch;
    protected $throttleLimit;
    protected $throttleTime;
    protected $throttleCount;
    protected $storeName;
    protected $marketplaceId;
    protected $secretKey;
    protected $options;
    
    protected function __construct($s){
        include('/var/www/athena/plugins/newAmazon/amazon-config.php');
        
        if(array_key_exists($s, $store)){
            if(array_key_exists('name', $store[$s])){
                $this->storeName = $store[$s]['name'];
            } else {
                $this->storeName = $s;
            }
            if(array_key_exists('merchantId', $store[$s])){
                $this->options['SellerId'] = $store[$s]['merchantId'];
            } else {
                throw new Exception('Merchant ID missing.');
            }
            if(array_key_exists('marketplaceId', $store[$s])){
//                $this->marketplaceId = $store[$s]['marketplaceId'];
            } else {
                throw new Exception('Marketplace ID missing.');
            }
            if(array_key_exists('keyId', $store[$s])){
                $this->options['AWSAccessKeyId'] = $store[$s]['keyId'];
            } else {
                throw new Exception('Access Key ID missing.');
            }
            if(array_key_exists('secretKey', $store[$s])){
                $this->secretKey = $store[$s]['secretKey'];
            } else {
                throw new Exception('Access Key missing.');
            }
            
        } else {
            throw new Exception('Store does not exist.');
        }
        
        $this->urlbase = $serviceURL;
        
        $this->options['SignatureVersion'] = 2;
        $this->options['SignatureMethod'] = 'HmacSHA256';
    }
    
    protected function throttle(){
        $this->throttleCount--;
        if ($this->throttleCount < 1){
            sleep($this->throttleTime);
            $this->throttleCount++;
        }
        
    }
    
    protected function throttleReset(){
        $this->throttleCount = $this->throttleLimit;
    }
    
    /**
     * Returns all information for sake of convenience
     * @return array All information in an associative array
     */
    public function getAllDetails(){
        return $this->data;
    }
    
    public function genRequest(){
        $url = $this->urlbase;
        $url .= $this->urlbranch;
        
        $query = '';
        
        foreach ($this->options as $i => $x){
                if (!$firstdone){
                    $query .= '?';
                    $firstdone = true;
                } else {
                    $query .= '&';
                }
                
                $query .= $i.'='.$x;
            }
        
        $sig = $this->genSig();
        
        $query .= $sig;
        
        $this->debug();
    }
    
    /**
     * Generates the signature hash for signing the request
     * @return string has string
     * @throws InvalidArgumentException if no options are detected
     */
    protected function genSig(){
        //start with method
        $query = 'POST';
        //add Amazon endpoint
        $query .= $this->urlbase;
        
        if (is_array($this->options)){
            ksort($this->options);
            
            //add query bits
            foreach ($this->options as $i => $x){
                if (!$firstdone){
                    $query .= '?';
                    $firstdone = true;
                } else {
                    $query .= '&';
                }
                
                $query .= $i.'='.$x;
            }
            
        } else {
            throw new Exception('No query options set!');
        }
        
        //DEBUG MODE IS GO
        echo $query;
        
        return hash_hmac('sha256', $query, $this->secretKey);
    }

    protected function makesomekindofrequest(){
        include('/var/www/athena/includes/includes.php');

        

        fetchURL($this->urlbase);

    }
    
    protected function debug(){
        myPrint($this->options);
    }
}

//handles order retrieval
class AmazonOrder extends AmazonCore{
    private $orderId;
    private $itemFlag;
    private $tokenItemFlag;
    private $data;

    public function __construct($s,$o = null,$d = null){
        include('/var/www/athena/plugins/newAmazon/amazon-config.php');
        parent::__construct($s);
        
        if($o){
            $this->options['AmazonOrderId.Id.1'] = $o;
        }
        if ($d && is_array($d)) {
            //fill out info this way
        }
        
        $this->urlbranch = 'Orders/2011-01-01/';
        
        $this->throttleLimit = $throttleLimitOrder;
        $this->throttleTime = $throttleTimeOrder;
        $this->throttleCount = $this->throttleLimit;
        
        $this->options['Action'] = 'GetOrder';
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
    public function PurchaseDate(){
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
        return $this->data['AmazonOrderId'];
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
    
    public function fetchOrder(){
        $this->options['Timestamp'] = date('Y-m-d\TH%3\Ai%3\AsO');
    }

    /**
     * Sets the Amazon Order ID for the next request, in case it was not set in the constructor
     * @param string $id the Amazon Order ID
     * @throws InvalidArgumentException if the parameter is left empty
     */
    public function setOrderId($id){
        if ($id){
            $this->options['AmazonOrderId'] = $id;
        } else {
            throw new InvalidArgumentException('No Order ID given!');
        }
    }

    public function fetchItems(){
        
    }
}

//makes a list of order objects from source
class AmazonOrderList extends AmazonCore implements Iterator{
    private $orderList;
    private $i;
    private $tokenFlag;
    private $itemFlag;
    private $tokenUseFlag;
    private $tokenItemFlag;
    private $createdBefore;
    private $createdAfter;
    private $modifiedBefore;
    private $modifiedAfter;

    public function __construct(){
        $this->i = 0;
    }
    
    /**
     * Returns whether or not the Order List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }

    /**
     * Sets whether or not the OrderList should automatically grab items for the Orders it receives
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setFetchItems($b = true){
        if (is_bool($b)){
            $this->itemFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets whether or not the OrderList should automatically use tokens if it receives one. This includes item tokens
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
            $this->tokenItemFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets whether or not the OrderList should automatically use tokens when fetching items
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
    
    public function fetchOrders(){
        $this->orderList = array();
    }

    /**
     * Sets the time frame for the orders fetched.
     * 
     * Sets the time frame for the orders fetched. If no times are specified, times default to the current time
     * @param string $mode "Created" or "Modified"
     * @param dateTime $lower Date the order was created after
     * @param dateTime $upper Date the order was created before
     * @throws InvalidArgumentException
     */
    public function setLimits($mode,$lower = null,$upper = null){
        try{
            if ($lower){
                $after = strtotime($lower);
            } else {
                $after = strtotime(time().'- 2 minutes');
            }
            if ($upper){
                $before = strtotime($upper);
            }
        } catch (Exception $e){
            throw new InvalidArgumentException('Second/Third parameters should be timestamps.');
        }
        
        if ($mode == 'Created'){
            $this->options['CreatedAfter'] = $after;
            $this->options['CreatedBefore'] = $before;
        } else if ($mode == 'Modified'){
            $this->options['LastUpdatedAfter'] = $after;
            $this->options['LastUpdatedBefore'] = $before;
        } else {
            throw new InvalidArgumentException('First parameter should be either "Created" or "Modified".');
        }
    }

    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->orderList[$this->i]; 
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
        return isset($this->orderList[$this->i]);
    }
}

//contains info for a single item
class AmazonItemList extends AmazonCore implements Iterator{
    private $orderId;
    private $itemList;
    private $tokenFlag;
    private $tokenUseFlag;
    private $i;

    public function __construct(){
        include('/var/www/athena/plugins/newAmazon/amazon-config.php');
        parent::__construct();
        
        
        
        $this->throttleLimit = $throttleLimitItem;
        $this->throttleTime = $throttleTimeItem;
        $this->throttleCount = $this->throttleLimit;
    }
    
    public function setUseToken($b){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    public function setOrderId($id){
        if (!is_null($id)){
            $this->orderId = $id;
        } else {
            throw new InvalidArgumentException('Order ID was Null');
        }
    }

    public function fetchItems(){
        
        
    }

    public function hasToken(){
        return $this->tokenFlag;
    }

    public function current(){
       return $this->itemList[$this->i]; 
    }

    public function rewind(){
        $this->i = 0;
    }

    public function key() {
        return $this->i;
    }

    public function next() {
        $this->i++;
    }

    public function valid() {
        return isset($this->itemList[$this->i]);
    }
}
?>
