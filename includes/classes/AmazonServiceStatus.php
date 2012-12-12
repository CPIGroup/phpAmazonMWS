<?php
/**
 * A simple object that fetches the status of the a specific service from Amazon.
 * 
 * Please note that it has a 5 minute throttle time.
 */
class AmazonServiceStatus extends AmazonCore{
    private $lastTimestamp;
    private $status;
    
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
     * Fetches the status of the service from Amazon
     */
    public function fetchServiceStatus(){
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = $this->options['Action'].'Result';
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
        
        $this->lastTimestamp = (string)$xml->$path->Timestamp;
        $this->status = (string)$xml->$path->Status;
    }
    
    public function setService($s){
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        switch($s){
            case 'Inbound':
                $this->urlbranch = 'FulfillmentInboundShipment/'.$versionInbound;
                $this->options['Version'] = $versionInbound;
                return true;
            case 'Inventory':
                $this->urlbranch = 'FulfillmentInventory/'.$versionInventory;
                $this->options['Version'] = $versionInventory;
                return true;
            case 'Orders':
                $this->urlbranch = 'Orders/'.$versionOrders;
                $this->options['Version'] = $versionOrders;
                return true;
            case 'Outbound':
                $this->urlbranch = 'FulfillmentOutboundShipment/'.$versionOutbound;
                $this->options['Version'] = $versionOutbound;
                return true;
            case 'Products':
                $this->urlbranch = 'Products/'.$versionProducts;
                $this->options['Version'] = $versionProducts;
                return true;
            case 'Sellers':
                $this->urlbranch = 'Sellers/'.$versionSellers;
                $this->options['Version'] = $versionSellers;
                return true;
            default:
                return false;
        }
        
    }
    
    /**
     * returns the fetched service status, and fetches it if it has not done so already
     * @return string|boolean false if status not yet retrieved
     */
    public function getServiceStatus(){
        if (isset($this->status)){
            return $this->status;
        } else {
            return false;
        }
    }
    
    /**
     * returns the timestamp of the last response, and fetches it if it has not done so already
     * @return string|boolean false if status not yet retrieved
     */
    public function getTimestamp(){
        if (isset($this->status)){
            return $this->lastTimestamp;
        } else {
            return false;
        }
    }
    
}

?>
