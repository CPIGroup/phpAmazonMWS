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
    

    protected function makesomekindofrequest(){
        include('/var/www/athena/includes/includes.php');

        

        fetchURL($this->urlbase);

    }
    
    protected function debug(){
        echo '<br>';
        myPrint($this->options);
        echo '<br>';
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
    
    public function genRequest(){
        $url = $this->urlbase;
        
        //options array... redo this using foreach because it's brilliant
        
        $sig = hash_hmac('sha256', $url, $this->secretKey);
        //print_r($this->options);
        
        $this->debug();
    }
    
    public function setFetchItems($b = true){
        if (is_bool($b)){
            $this->itemFlag = $b;
        } else {
            throw new InvalidArgumentException('The paramater for setFetchItems() should be either true or false.');
        }
    }

    public function getDetails(){
        
    }

    public function fetchOrder(){
        $this->options['Timestamp'] = date('Y-m-d\TH%3\Ai%3\AsO');
    }

    public function setOrderId(){
        
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
    
    public function hasToken(){
        return $this->tokenFlag;
    }

    public function setFetchItems($b = true){
        if (is_bool($b)){
            $this->itemFlag = $b;
        } else {
            throw new InvalidArgumentException('The paramater for setFetchItems() should be either true or false.');
        }
    }
    
    public function setUseToken($b){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    public function setUseItemToken($b){
        if (is_bool($b)){
            $this->tokenItemFlag = $b;
        } else {
            return false;
        }
    }
    
    public function fetchOrders(){
        $this->orderList = array();
    }

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

    public function current(){
       return $this->orderList[$this->i]; 
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
