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
    protected $secretKey;
    protected $options;
    protected $config;
    protected $mockMode;
    
    /**
     * 
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
        $this->options['SignatureMethod'] = 'HmacSHA1';
        $this->options['Version'] = '2011-01-01';
    }
    
    protected function parseXML(){
        
    }
    
    /**
     * Manages the object's throttling
     */
    protected function throttle(){
        $this->throttleCount--;
        if ($this->throttleCount < 1){
            sleep($this->throttleTime);
            $this->throttleCount++;
        }
        
    }
    
    /**
     * Resets throttle count
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
     * DEPRECATED
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
    
    // -- test --
    /**
     * Reformats the provided string using rawurlencode while also replacing ~
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
     * Runs the hash, copies from amazon
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

//handles order retrieval
class AmazonOrder extends AmazonCore{
    private $itemFlag;
    private $tokenItemFlag;
    private $data;
    private $xmldata;

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
        
        $response = fetchURL($url,array('Post'=>$query));
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

//makes a list of order objects from source
class AmazonOrderList extends AmazonCore implements Iterator{
    private $orderList;
    private $i;
    private $tokenFlag;
    private $itemFlag;
    private $tokenUseFlag;
    private $tokenItemFlag;

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
    
    public function fetchOrders(){
        //STILL TO DO: USE TOKENS
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'ListOrders';
        
        if (!array_key_exists('CreatedAfter', $this->options) && !array_key_exists('LastUpdatedAfter', $this->options)){
            $this->setLimits('Created');
        }
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->prepareToken();
        } else {
            unset($this->options['NextToken']);
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
//        myPrint($this->options);
//        myPrint($query);
        
//        myPrint($this->options);
//        $query = $this->genRequest();
//        myPrint($query);
        
        $response = fetchURL($url,array('Post'=>$query));
//        myPrint($response);
        
        $xml = simplexml_load_string($response['body']);
        
        $this->orderList = array();
        
        foreach($xml->ListOrdersResult->Orders->children() as $key => $order){
            if ($key != 'Order'){
                break;
            }
            $this->orderList[] = new AmazonOrder($this->storeName,null,$order);
        }
        
        foreach($this->orderList as $x){
            $x->parseXML();
            
            if($this->itemFlag){
                $x->setUseItemToken($this->tokenItemFlag);
                $x->fetchItems();
            }
        }
        
        myPrint($this->orderList);
        
        
    }

    /**
     * Makes the preparations necessary for using tokens
     * @return boolean returns false if no token to use
     */
    protected function prepareToken(){
        if (!$this->tokenFlag){
            return false;
        } else {
            $this->options['NextToken'] = $this->data['NextToken'];
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

//contains info for a single item
class AmazonItemList extends AmazonCore implements Iterator{
    private $itemList;
    private $tokenFlag;
    private $tokenUseFlag;
    private $i;
    private $xmldata;

    public function __construct($s, $id=null){
        parent::__construct($s);
        include($this->config);
        
        $this->urlbranch = 'Orders/2011-01-01';
        
        if (!is_null($id)){
            $this->options['AmazonOrderId'] = $id;
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
     * @return boolean if no XML data
     */
    protected function parseXML(){
        if (!$this->xmldata){
            return false;
        }
        $this->itemList = array();
        foreach($this->xmldata->children() as $item){
            
            $this->itemList['AmazonOrderId'] = (string)$this->xmldata->AmazonOrderId;
            $this->itemList['SellerOrderId'] = (string)$this->xmldata->SellerOrderId;
            $this->itemList['PurchaseDate'] = (string)$this->xmldata->PurchaseDate;
            $this->itemList['LastUpdateDate'] = (string)$this->xmldata->LastUpdateDate;
            $this->itemList['OrderStatus'] = (string)$this->xmldata->OrderStatus;
            $this->itemList['FulfillmentChannel'] = (string)$this->xmldata->FulfillmentChannel;
            $this->itemList['SalesChannel'] = (string)$this->xmldata->SalesChannel;
            $this->itemList['OrderChannel'] = (string)$this->xmldata->OrderChannel;
            $this->itemList['ShipServiceLevel'] = (string)$this->xmldata->ShipServiceLevel;

            if (isset($this->xmldata->ShippingAddress)){
                $this->itemList['ShippingAddress'] = array();
                $this->itemList['ShippingAddress']['Phone'] = (string)$this->xmldata->ShippingAddress->Phone;
                $this->itemList['ShippingAddress']['PostalCode'] = (string)$this->xmldata->ShippingAddress->PostalCode;
                $this->itemList['ShippingAddress']['Name'] = (string)$this->xmldata->ShippingAddress->Name;
                $this->itemList['ShippingAddress']['CountryCode'] = (string)$this->xmldata->ShippingAddress->CountryCode;
                $this->itemList['ShippingAddress']['StateOrRegion'] = (string)$this->xmldata->ShippingAddress->StateOrRegion;
                $this->itemList['ShippingAddress']['AddressLine1'] = (string)$this->xmldata->ShippingAddress->AddressLine1;
                $this->itemList['ShippingAddress']['AddressLine2'] = (string)$this->xmldata->ShippingAddress->AddressLine2;
                $this->itemList['ShippingAddress']['AddressLine3'] = (string)$this->xmldata->ShippingAddress->AddressLine3;
                $this->itemList['ShippingAddress']['City'] = (string)$this->xmldata->ShippingAddress->City;
            }



            if (isset($this->xmldata->OrderTotal)){
                $this->itemList['OrderTotal'] = array();
                $this->itemList['OrderTotal']['Amount'] = (string)$this->xmldata->OrderTotal->Amount;
                $this->itemList['OrderTotal']['CurrencyCode'] = (string)$this->xmldata->OrderTotal->CurrencyCode;
            }

            $this->itemList['NumberOfItemsShipped'] = (string)$this->xmldata->NumberOfItemsShipped;
            $this->itemList['NumberOfItemsUnshipped'] = (string)$this->xmldata->NumberOfItemsUnshipped;

            if (isset($this->xmldata->PaymentExecutionDetail)){
                $this->itemList['PaymentExecutionDetail'] = array();

                $i = 0;
                foreach($this->xmldata->PaymentExecutionDetail->children() as $x){
                    $this->data['PaymentExecutionDetail']['Payment'.$i]['Amount'] = (string)$x->Payment->Amount;
                    $this->data['PaymentExecutionDetail']['Payment'.$i]['CurrencyCode'] = (string)$x->Payment->CurrencyCode;
                    $this->data['PaymentExecutionDetail']['Payment'.$i]['SubPaymentMethod'] = (string)$x->SubPaymentMethod;
                }
            }

            $this->data['MarketplaceId'] = (string)$this->xmldata->MarketplaceId;
            $this->data['BuyerName'] = (string)$this->xmldata->BuyerName;
            $this->data['BuyerEmail'] = (string)$this->xmldata->BuyerEmail;
            $this->data['ShipServiceLevelCategory'] = (string)$this->xmldata->ShipServiceLevelCategory;
        }
            
    }
    
    public function setOrderId($id){
        if (!is_null($id)){
            $this->options['AmazonOrderId'] = $id;
        } else {
            throw new InvalidArgumentException('Order ID was Null');
        }
    }

    public function fetchItems(){
        $this->options['Timestamp'] = $this->genTime();
        $this->options['Action'] = 'ListOrderItems';
        
        if($this->tokenFlag && $this->tokenUseFlag){
            $this->prepareToken();
        } else {
            unset($this->options['NextToken']);
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
//        myPrint($this->options);
//        myPrint($query);
        
//        myPrint($this->options);
//        $query = $this->genRequest();
//        myPrint($query);
        
        $response = fetchURL($url,array('Post'=>$query));
        myPrint($response);
        
        $this->xmldata = simplexml_load_string($response['body'])->ListOrderItemsResult->OrderItems;
        
        $this->itemList = array();
        $this->parseXML();
        
    }

    /**
     * Makes the preparations necessary for using tokens
     * @return boolean returns false if no token to use
     */
    protected function prepareToken(){
        if (!$this->tokenFlag){
            return false;
        } else {
            $this->options['NextToken'] = $this->data['NextToken'];
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
