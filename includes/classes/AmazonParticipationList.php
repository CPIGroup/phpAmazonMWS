<?php

class AmazonParticipationList extends AmazonSellersCore{
    private $tokenFlag;
    private $tokenUseFlag;
    private $token;
    private $xmldata;
    private $participationList;
    private $marketplaceList;
    
    /**
     * Gets list of marketplaces run by seller
     * @param string $s store name, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     */
    public function __construct($s, $mock = false) {
        parent::__construct($s, $mock);
        include($this->config);
        
        $this->options['Action'] = 'ListMarketplaceParticipations';
        
        $this->throttleLimit = $throttleLimitSellers;
        $this->throttleTime = $throttleTimeSellers;
    }
    
    /**
     * Returns whether or not the Participation List has a token available
     * @return boolean
     */
    public function hasToken(){
        return $this->tokenFlag;
    }
    
    /**
     * Sets whether or not the Participation List should automatically use tokens if it receives one.
     * @param boolean $b
     * @return boolean false if invalid paramter
     */
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    public function fetchParticipationList(){
        $this->options['Timestamp'] = $this->genTime();
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListMarketplaceParticipationsByNextToken';
        }
        
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        
        $this->throttle();
        $response = fetchURL($url,array('Post'=>$query));
        $this->logRequest();
        
        $path = $this->options['Action'].'Result';
        
        var_dump(simplexml_load_string($response['body']));
        var_dump($path);
        
        $xml = simplexml_load_string($response['body'])->$path;
        
        echo 'the lime must be drawn here';
        myPrint($xml);
        
        $xmlP = $xml->ListParticipations;
        $xmlM = $xml->ListMarketplaces;
        
        var_dump($xmlP);
        var_dump($xmlM);
        
        $i = 0;
        $this->marketplaceList = array();
        foreach($xmlP->children() as $x){
            var_dump($x);
            $this->marketplaceList[$i]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->marketplaceList[$i]['SellerId'] = (string)$x->SellerId;
            $this->marketplaceList[$i]['Suspended'] = (string)$x->HasSellerSuspendedListings;
            $i++;
        }
        
        myPrint($this->orderList);
        
    }
    
    protected function parseXML(){
        
    }
    
}
?>
