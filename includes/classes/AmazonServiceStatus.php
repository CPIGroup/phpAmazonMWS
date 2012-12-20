<?php
/**
 * Fetches the status of the a specific service from Amazon.
 * 
 * This Amazon Core object retrieves the status of a selected Amazon service.
 * Please note that it has a 5 minute throttle time.
 */
class AmazonServiceStatus extends AmazonCore{
    private $lastTimestamp;
    private $status;
    private $messageId;
    private $messageList;
    private $ready = false;
    
    /**
     * A simple object that fetches the status of the Inventory API from Amazon
     * @param string $s store name, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $service = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if ($service){
            $this->setService($service);
        }
        
        $this->options['Action'] = 'GetServiceStatus';
        
        $this->throttleLimit = $throttleLimitStatus;
        $this->throttleTime = $throttleTimeStatus;
        $this->throttleGroup = 'GetServiceStatus';
    }
    
    /**
     * Set the service to fetch the status of.
     * @param string $s service name
     * @return boolean true if success, false if failure
     */
    public function setService($s){
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        if (is_null($s)){
            $this->log("Service cannot be null",'Warning');
            return false;
        }
        
        if (is_bool($s)){
            $this->log("A boolean is not a service",'Warning');
            return false;
        }
        
        switch($s){
            case 'Inbound':
                $this->urlbranch = 'FulfillmentInboundShipment/'.$versionInbound;
                $this->options['Version'] = $versionInbound;
                $this->ready = true;
                return true;
            case 'Inventory':
                $this->urlbranch = 'FulfillmentInventory/'.$versionInventory;
                $this->options['Version'] = $versionInventory;
                $this->ready = true;
                return true;
            case 'Orders':
                $this->urlbranch = 'Orders/'.$versionOrders;
                $this->options['Version'] = $versionOrders;
                $this->ready = true;
                return true;
            case 'Outbound':
                $this->urlbranch = 'FulfillmentOutboundShipment/'.$versionOutbound;
                $this->options['Version'] = $versionOutbound;
                $this->ready = true;
                return true;
            case 'Products':
                $this->urlbranch = 'Products/'.$versionProducts;
                $this->options['Version'] = $versionProducts;
                $this->ready = true;
                return true;
            case 'Sellers':
                $this->urlbranch = 'Sellers/'.$versionSellers;
                $this->options['Version'] = $versionSellers;
                $this->ready = true;
                return true;
            default:
                $this->log("$s is not a valid service",'Warning');
                return false;
        }
        
    }
    
    /**
     * Fetches the status of the service from Amazon
     */
    public function fetchServiceStatus(){
        if (!$this->ready){
            $this->log("Service must be set in order to retrieve status",'Warning');
            return false;
        }
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
            $xml = $this->fetchMockFile()->$path;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $this->parseXML($xml);
    }
    
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        $this->lastTimestamp = (string)$xml->Timestamp;
        $this->status = (string)$xml->Status;
        
        if ($this->status == 'GREEN_I'){
            $this->messageId = (string)$xml->MessageId;
            $i = 0;
            foreach ($xml->Messages->children() as $x){
                $this->messageList[$i] = (string)$x->Text;
                $i++;
            }
        }
    }
    
    /**
     * Returns whether or not the object is ready to retrieve the status.
     * @return boolean
     */
    public function isReady(){
        return $this->ready;
    }
    
    /**
     * returns the fetched service status if set
     * @return string|boolean false if status not yet retrieved
     */
    public function getStatus(){
        if (isset($this->status)){
            return $this->status;
        } else {
            return false;
        }
    }
    
    /**
     * returns the timestamp of the last response if set
     * @return string|boolean false if timestamp not yet retrieved
     */
    public function getTimestamp(){
        if (isset($this->lastTimestamp)){
            return $this->lastTimestamp;
        } else {
            return false;
        }
    }
    
    /**
     * returns the message ID of the last response if set
     * @return string|boolean false if message ID not retrieved
     */
    public function getMessageId(){
        if (isset($this->messageId)){
            return $this->messageId;
        } else {
            return false;
        }
    }
    
    /**
     * returns the message list of the last response if set
     * @return string|boolean false if message list not retrieved
     */
    public function getMessageList(){
        if (isset($this->messageList)){
            return $this->messageList;
        } else {
            return false;
        }
    }
    
}

?>
