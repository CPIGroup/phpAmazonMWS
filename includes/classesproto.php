<?php

/*
 * Plan:
 * Database doubles as look-up table and record cache
 * -unique ID
 * -AmazonOrderID
 * -request type (either ListOrders/token or GetOrder)
 * -XML response, broken down into individual orders
 * -timestamp of request, used for throttling calculations
 * -status of order, used to determine which orders should be updated (eg Shipped is done with)
 * -flag for items for this order were ever retrieved
 * 
 * item table is similar
 * -unique ID
 * -id  corresponding to other table id
 * -timestamp
 * -even though it's dumb, store whether or not token was used via order status
 * -XML response broken into individual items
 * 
 * Need to find a way to connect to the database, check last timestamp of desired request type
 * for retrieving specific order information, check cache first to see if it was already received
 * functionality for updating orders
 * 
 * Get = fetch from cache or ?????
 * I'll probably have to make a new function for this, with a different name
 * 
 * caching is a great idea because it means information retrieval even if Amazon is down
 * 
 * need a function for Updating non-completed orders
 * 
 * oh and I still need Mock powers
 */

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
    protected $secretKey;
    protected $options;
    protected $config;
    protected $mockMode;
    
    /**
     * AmazonCore constructor sets up key information used in all Amazon requests
     * @param string $s Name for store as seen in config file
     * @param boolean $mock flag for enabling Mock Mode
     * @throws Exception if key config data is missing
     */
    protected function __construct($s, $mock=false){
        $this->config = '/var/www/athena/plugins/newAmazon/amazon-config.php';
        
        include($this->config);
        
        if(array_key_exists($s, $store)){
            $this->storeName = $s;
            if(array_key_exists('merchantId', $store[$s])){
                $this->options['SellerId'] = $store[$s]['merchantId'];
            } else {
                throw new Exception('Merchant ID missing.');
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
        
        if (is_bool($mock)){
            $this->mockMode = $mock;
        }
        
        
        $this->urlbase = $serviceURL;
        
        $this->options['SignatureVersion'] = 2;
        $this->options['SignatureMethod'] = 'HmacSHA256';
        $this->options['Version'] = '2011-01-01';
    }
    
    /**
     * Skeleton function
     */
    protected function parseXML(){
        
    }
    
    /**
     * Manages the object's throttling
     */
    protected function throttle(){
//        echo $this->throttleCount.'-->';
//        $this->throttleCount--;
//        if ($this->throttleCount < 1){
//            sleep($this->throttleTime);
//            $this->throttleCount++;
//        }
//        echo $this->throttleCount.'<br>';
        //database stuff goes here
        include('/var/www/athena/includes/config.php');
        //DB_PLUGINS;
        
        $sql = 'SELECT MAX(timestamp) as maxtime FROM `amazonRequestLog` WHERE `type` = ?';
        $value = array($this->options['Action']); //tokens...
        $result = db::executeQuery($sql, $value, DB_PLUGINS)->fetchAll();
        if(!$result){
            return;
        }
        
        $maxtime = $result[0]['maxtime'];
        flush();
        while(true){
            $mintime = time()-$this->throttleTime;
            $timediff = $maxtime-$mintime;
            if($maxtime <= $mintime){
                flush();
                return;
            }
            flush();
            sleep($timediff);
            $result = db::executeQuery($sql, $value, DB_PLUGINS)->fetchAll();
            $maxtime = $result[0]['maxtime'];
        }
        
        
        
//        $previous = time();
//        $refresh = 2;
//        $now = time();
//        
//        if($now-$previous < $refresh){
//            sleep($refresh);
//        }
        
        
    }
    
    /**
     * Resets throttle count
     * 
     * DEPRECATED?
     */
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
    
    /**
     * trying to generate a proper URL
     * 
     * DEPRECATED?
     * @return string
     */
    public function genRequest(){
        $query = '';
        uksort($this->options,'strcmp');
        foreach ($this->options as $i => $x){
                if (!$firstdone){
                    //$query .= '?';
                    $firstdone = true;
                } else {
                    $query .= '&';
                }
                
                $query .= $i.'='.$x;
            }
        
//            $queryParameters = array();
//        foreach ($parameters as $key => $value) {
//            $queryParameters[] = $key . '=' . $this->_urlencode($value);
//        }
//        return implode('&', $queryParameters);
            
        $sig = $this->genSig();
        
        var_dump($sig);
        
        $query .= '&Signature='.$sig;
        
        //$this->options['Signature'] = $sig;
        return $query;
        //return $sig;
    }
    
    /**
     * Generates the signature hash for signing the request
     * 
     * DEPRECATED?
     * @return string has string
     * @throws InvalidArgumentException if no options are detected
     */
    protected function genSig(){
        include($this->config);
        $query = 'POST';
        $query .= "\n";
        $endpoint = parse_url ($serviceURL);
        $query .= $endpoint['host'];
        $query .= "\n";
//        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;
//        if (!isset ($uri)) {
//        	$uri = "/";
//        }
//		$uriencoded = implode("/", explode("/", $uri));
//        $query .= $uriencoded;
        $query .= '/'.$this->urlbranch;
        $query .= "\n";
        
        
        
        if (is_array($this->options)){
            //combine query bits
            $queryParameters = array();
            foreach ($this->options as $key => $value) {
                $queryParameters[] = $key . '=' . $this->_urlencode($value);
            }
            $query = implode('&', $queryParameters);
//            //add query bits
//            foreach ($this->options as $i => $x){
//                if (!$firstdone){
//                    //$query .= '?';
//                    $firstdone = true;
//                } else {
//                    $query .= '&';
//                }
//                
//                $query .= $i.'='.$x;
//            }
        } else {
            throw new Exception('No query options set!');
        }
        
        
        return rawurlencode(base64_encode(hash_hmac('sha1', $query, $this->secretKey,true)));
    }
    
    /**
     * Generates timestamp in ISO8601 format, two minutes earlier than provided date
     * @param string $time time string that is fed through strtotime before being used
     * @return string time
     */
    protected function genTime($time=false){
        if (!$time){
            $time = time();
        } else {
            $time = strtotime($time);
            
        }
        return date('Y-m-d\TH:i:sO',$time-2*60);
            
    }
    
    protected function logRequest(){
        include('/var/www/athena/includes/config.php');
        DB_PLUGINS;
        
        $sql = "INSERT INTO  `amazonRequestLog` (`id` ,`type` ,`timestamp`)VALUES (NULL ,  ?,  ?)";
        $value = array($this->options['Action'],time());
        var_dump($value);
        
        $result = db::executeQuery($sql, $value, DB_PLUGINS);
        if (!$result){
            throw new Exception('write failed');
        }
    }
    
    // -- test --
    /**
     * Reformats the provided string using rawurlencode while also replacing ~, copied from Amazon
     * 
     * Almost the same as using rawurlencode
     * @param string $value
     * @return string
     */
    protected function _urlencode($value) {
        return rawurlencode($value);
		return str_replace('%7E', '~', rawurlencode($value));
    }
    
    /**
     * Fuses all of the parameters together into a string, copied from Amazon
     * @param array $parameters
     * @return string
     */
    protected function _getParametersAsString(array $parameters) {
        $queryParameters = array();
        foreach ($parameters as $key => $value) {
            $queryParameters[] = $key . '=' . $this->_urlencode($value);
        }
        return implode('&', $queryParameters);
    }
    
    /**
     * validates signature and sets up signing of them, copied from Amazon
     * @param array $parameters
     * @param string $key
     * @return string signed string
     * @throws Exception
     */
    protected function _signParameters(array $parameters, $key) {
        $algorithm = $this->options['SignatureMethod'];
        $stringToSign = null;
        if (2 === $this->options['SignatureVersion']) {
            $stringToSign = $this->_calculateStringToSignV2($parameters);
//            var_dump($stringToSign);
        } else {
            throw new Exception("Invalid Signature Version specified");
        }
        return $this->_sign($stringToSign, $key, $algorithm);
    }
    
    /**
     * generates the string to sign, copied from Amazon
     * @param array $parameters
     * @return type
     */
    protected function _calculateStringToSignV2(array $parameters) {
        $data = 'POST';
        $data .= "\n";
        $endpoint = parse_url ($this->urlbase.$this->urlbranch);
        $data .= $endpoint['host'];
        $data .= "\n";
        $uri = array_key_exists('path', $endpoint) ? $endpoint['path'] : null;
        if (!isset ($uri)) {
        	$uri = "/";
        }
		$uriencoded = implode("/", array_map(array($this, "_urlencode"), explode("/", $uri)));
        $data .= $uriencoded;
        $data .= "\n";
        uksort($parameters, 'strcmp');
        $data .= $this->_getParametersAsString($parameters);
        return $data;
    }
    /**
     * Runs the hash, copied from Amazon
     * @param string $data
     * @param string $key
     * @param string $algorithm 'HmacSHA1' or 'HmacSHA256'
     * @return string
     * @throws Exception
     */
     protected function _sign($data, $key, $algorithm)
    {
        if ($algorithm === 'HmacSHA1') {
            $hash = 'sha1';
        } else if ($algorithm === 'HmacSHA256') {
            $hash = 'sha256';
        } else {
            throw new Exception ("Non-supported signing method specified");
        }
        
        return base64_encode(
            hash_hmac($hash, $data, $key, true)
        );
    }
    
    // -- end test --
    
}

/**
 * AmazonOrder object gets the details for a single object from Amazon
 */
class AmazonOrder extends AmazonCore{
    private $itemFlag;
    private $tokenItemFlag;
    private $data;
    private $xmldata;

    /**
     * AmazonOrder object gets the details for a single object from Amazon
     * @param string $s store name as seen in config
     * @param string $o Order number to automatically set
     * @param SimpleXMLElement $d XML data from Amazon to be parsed
     */
    public function __construct($s,$o = null,$d = null){
        parent::__construct($s);
        include($this->config);
        
        if($o){
            $this->options['AmazonOrderId.Id.1'] = $o;
        }
        if ($d) {
            $this->xmldata = $d;
        }
        
        $this->urlbranch = 'Orders/2011-01-01';
        
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
        
        $this->throttle();
        $response = fetchURL($url,array('Post'=>$query));
        $this->logRequest();
        //myPrint($response);
        
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

/**
 * Amazon Order Lists pull a set of Orders and turn them into an array of AmazonOrder objects.
 */
class AmazonOrderList extends AmazonCore implements Iterator{
    private $orderList;
    private $i;
    private $tokenFlag;
    private $itemFlag;
    private $tokenUseFlag;
    private $tokenItemFlag;
    private $index;
    private $token;

    /**
     * Amazon Order Lists pull a set of Orders and turn them into an array of AmazonOrder objects.
     * @param string $s name of store, as seen in the config file
     * @throws Exception if Marketplace ID is missing from config
     */
    public function __construct($s){
        parent::__construct($s);
        $this->i = 0;
        include($this->config);
        
        if(array_key_exists('marketplaceId', $store[$s])){
            $this->options['MarketplaceId.Id.1'] = $store[$s]['marketplaceId'];
        } else {
            throw new Exception('Marketplace ID missing.');
        }
        
        $this->urlbranch = 'Orders/2011-01-01';
        
        $this->throttleLimit = $throttleLimitOrderList;
        $this->throttleTime = $throttleTimeOrderList;
        $this->throttleCount = $this->throttleLimit;
        
        if ($throttleSafe){
            $this->throttleLimit++;
            $this->throttleTime++;
            $this->throttleCount = $this->throttleLimit;
        }
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
    
    /**
     * Fetches orders from Amazon using the pre-set parameters and putting them in an array of AmazonOrder objects
     */
    public function fetchOrders(){
        //Pseudocode am go
        //
        //get order ID
        //query database for said ID
        //if found
        //fetch XML from cache table
        //else do what I've normally been doing
        //log copy of results in database

        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'ListOrders';
        
        if (!array_key_exists('CreatedAfter', $this->options) && !array_key_exists('LastUpdatedAfter', $this->options)){
            $this->setLimits('Created');
        }
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->prepareToken();
        } else {
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->orderList = array();
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        //old way
//        myPrint($this->options);
//        myPrint($query);
        
//        myPrint($this->options);
//        $query = $this->genRequest();
//        myPrint($query);
        
        $this->throttle();
        $response = fetchURL($url,array('Post'=>$query));
        $this->logRequest();
        
        $path = $this->options['Action'].'Result';
        
        var_dump(simplexml_load_string($response['body']));
        var_dump($path);
        
        $xml = simplexml_load_string($response['body'])->$path;
        
        echo 'the lime must be drawn here';
        var_dump($xml);
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->token = true;
        }
        
        foreach($xml->Orders->children() as $key => $order){
            if ($key != 'Order'){
                break;
            }
            $this->orderList[$this->index] = new AmazonOrder($this->storeName,null,$order);
            $this->orderList[$this->index]->parseXML();
            $this->orderList[$this->index]->setUseItemToken($this->tokenItemFlag);
            if($this->itemFlag){
                $this->orderList[$this->index]->fetchItems();
            }
            $this->index++;
        }
        
        myPrint($this->orderList);
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            echo '<br>IT BEGINS AGAIN<br>';
            $this->fetchOrders();
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
            $this->options['Action'] = 'ListOrdersByNextToken';
            
            //When using tokens, only the NextToken option should be used
            foreach($this->options as $o => $v){
                if ($o == 'AWSAccessKeyId'){
                    continue;
                } else
                if ($o == 'Action'){
                    continue;
                } else
                if ($o == 'SellerId'){
                    continue;
                } else
                if ($o == 'SignatureVersion'){
                    continue;
                } else
                if ($o == 'SignatureMethod'){
                    continue;
                } else
                if ($o == 'NextToken'){
                    continue;
                } else
                if ($o == 'Timestamp'){
                    continue;
                } else
                if ($o == 'Version'){
                    continue;
                } else {
                    unset($this->options[$o]);
                }
            }
        }
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
                $after = $this->genTime($lower);
            } else {
                $after = $this->genTime('- 2 min');
            }
            if ($upper){
                $before = $this->genTime($upper);
            }
            if ($mode == 'Created'){
                $this->options['CreatedAfter'] = $after;
                if ($before) {
                    $this->options['CreatedBefore'] = $before;
                }
                unset($this->options['LastUpdatedAfter']);
                unset($this->options['LastUpdatedBefore']);
            } else if ($mode == 'Modified'){
                $this->options['LastUpdatedAfter'] = $after;
                if ($before){
                    $this->options['LastUpdatedBefore'] = $before;
                }
                unset($this->options['CreatedAfter']);
                unset($this->options['CreatedBefore']);
            } else {
                throw new InvalidArgumentException('First parameter should be either "Created" or "Modified".');
            }
            
        } catch (Exception $e){
            throw new InvalidArgumentException('Second/Third parameters should be timestamps.');
        }
        
    }
    
    /**
     * Sets option for status filter of next request
     * @param array $list array of strings, or a single string
     * @throws InvalidArgumentException
     */
    public function setOrderStatusFilter($list){
        if (is_string($list)){
            //if single string, set as filter
            $this->resetOrderStatusFilter();
            $this->options['OrderStatus.Status.1'] = $list;
        } else if (is_array($list)){
            //if array of strings, set all filters
            $this->resetOrderStatusFilter();
            $i = 1;
            foreach($list as $x){
                $this->options['OrderStatus.Status.'.$i++] = $x;
            }
        } else {
            throw new InvalidArgumentException('setOrderStatusFilter() needs a string or array of strings');
        }
    }

    /**
     * Resets the Order Status Filter to default
     */
    public function resetOrderStatusFilter(){
        for ($i = 1; $i <= 7; $i++){
            if (array_key_exists('OrderStatus.Status.'.$i,$this->options)){
                unset($this->options['OrderStatus.Status.'.$i]);
            }
        }
    }
    
    /**
     * Sets (or resets) the Fulfillment Channel Filter
     * @param string $filter 'AFN' or 'MFN' or null
     */
    public function setFulfillmentChannelFilter($filter = null){
        if ($filter == 'AFN' || $filter == 'MFN'){
            $this->options['FulfillmentChannel.Channel.1'] = $filter;
        } else {
            unset($this->options['FulfillmentChannel.Channel.1']);
        }
    }
    
    /**
     * Sets option for payment method filter of next request
     * @param array $list array of strings, or a single string
     * @throws InvalidArgumentException
     */
    public function setPaymentMethodFilter($list){
        if (is_string($list)){
            //if single string, set as filter
            $this->resetPaymentMethodFilter();
            $this->options['PaymentMethod.1'] = $list;
        } else if (is_array($list)){
            //if array of strings, set all filters
            $this->resetPaymentMethodFilter();
            $i = 1;
            foreach($list as $x){
                $this->options['PaymentMethod.'.$i++] = $x;
            }
        } else {
            throw new InvalidArgumentException('setOrderStatusFilter() needs a string or array of strings');
        }
    }
    
    /**
     * Resets the Payment Method Filter to default
     */
    public function resetPaymentMethodFilter(){
        for ($i = 1; $i <= 7; $i++){
            if (array_key_exists('PaymentMethod.'.$i,$this->options)){
                unset($this->options['PaymentMethod.'.$i]);
            }
        }
    }
    
    /**
     * Sets (or resets) the Email Filter. This resets certain fields.
     * 
     * Sets (or resets) the Seller Order ID Filter. The following filter options are disabled by this function:
     * -SellerOrderId
     * -OrderStatus
     * -PaymentMethod
     * -FulfillmentChannel
     * -LastUpdatedAfter
     * -LastUpdatedBefore
     * @param string $filter string or null
     */
    public function setEmailFilter($filter = null){
        if (is_string($filter)){
            $this->options['BuyerEmail'] = $filter;
            //these fields must be disabled
            unset($this->options['SellerOrderId']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else {
            unset($this->options['BuyerEmail']);
        }
    }
    
    /**
     * Sets (or resets) the Seller Order ID Filter. This resets certain fields.
     * 
     * Sets (or resets) the Seller Order ID Filter. The following filter options are disabled by this function:
     * -BuyerEmail
     * -OrderStatus
     * -PaymentMethod
     * -FulfillmentChannel
     * -LastUpdatedAfter
     * -LastUpdatedBefore
     * @param string $filter string or null
     */
    public function setSellerOrderIdFilter($filter = null){
        if (is_string($filter)){
            $this->options['SellerOrderId'] = $filter;
            //these fields must be disabled
            unset($this->options['BuyerEmail']);
            $this->resetOrderStatusFilter();
            $this->resetPaymentMethodFilter();
            $this->setFulfillmentChannelFilter(null);
            unset($this->options['LastUpdatedAfter']);
            unset($this->options['LastUpdatedBefore']);
        } else {
            unset($this->options['SellerOrderId']);
        }
    }
    
    /**
     * Sets the max number of results per page for the next request
     * @param type $num
     * @throws InvalidArgumentException
     */
    public function setMaxResultsPerPage($num){
        if (is_int($num)){
            if ($num <= 100 && $num >= 1){
                $this->options['MaxResultsPerPage'] = $num;
            }
        } else {
            throw new InvalidArgumentException();
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
