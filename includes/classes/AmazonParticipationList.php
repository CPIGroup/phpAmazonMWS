<?php

class AmazonParticipationList extends AmazonSellersCore{
    private $tokenFlag;
    private $tokenUseFlag;
    private $xmldata;
    private $participationList;
    private $marketplaceList;
    
    /**
     * Gets list of marketplaces run by seller
     * @param string $s store name, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
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
    
    /**
     * Fetches the participation list from Amazon, using a token if available
     */
    public function fetchParticipationList(){
        $this->options['Timestamp'] = $this->genTime();
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListMarketplaceParticipationsByNextToken';
        } else {
            unset($this->options['NextToken']);
        }
        
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = $this->options['Action'].'Result';
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $this->throttle();
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();

//            var_dump(simplexml_load_string($response['body']));
//            var_dump($path);

            $xml = simplexml_load_string($response['body'])->$path;
        }
            

//        echo 'the lime must be drawn here';
//        myPrint($xml);
        
        $xmlP = $xml->ListParticipations;
        $xmlM = $xml->ListMarketplaces;
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        }
        
        $i = 0;
        $this->marketplaceList = array();
        foreach($xmlP->children() as $x){
            $this->marketplaceList[$i]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->marketplaceList[$i]['SellerId'] = (string)$x->SellerId;
            $this->marketplaceList[$i]['Suspended'] = (string)$x->HasSellerSuspendedListings;
            $i++;
        }
        
        $i = 0;
        $this->participationList = array();
        foreach($xmlM->children() as $x){
            $this->participationList[$i]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->participationList[$i]['Name'] = (string)$x->Name;
            $this->participationList[$i]['Country'] = (string)$x->DefaultCountryCode;
            $this->participationList[$i]['Currency'] = (string)$x->DefaultCurrencyCode;
            $this->participationList[$i]['Language'] = (string)$x->DefaultLanguageCode;
            $this->participationList[$i]['Domain'] = (string)$x->DomainName;
            $i++;
        }
        
//        myPrint($this->marketplaceList);
//        myPrint($this->participationList);
        
    }
    
    /**
     * Returns entire list of marketplaces, for convenience
     * @return array
     */
    public function getMarketplaceList(){
        return $this->marketplaceList;
    }
    
    /**
     * Returns entire list of participations, for convenience
     * @return array
     */
    public function getParticipationList(){
        return $this->participationList;
    }
    
    /**
     * Returns the Marketplace ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string MarketplaceId, or False if Non-numeric index
     */
    public function getMarketplaceId($i = 0){
        if (is_numeric($i)){
            return $this->marketplaceList[$i]['MarketplaceId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Seller ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string SellerId, or False if Non-numeric index
     */
    public function getSellerId($i = 0){
        if (is_numeric($i)){
            return $this->marketplaceList[$i]['SellerId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Seller ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string "Yes" or "No"
     */
    public function getSuspensionStatus($i = 0){
        if (is_numeric($i)){
            return $this->marketplaceList[$i]['Suspended'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the name for the specified entry, defaults to 0
     * @param int $i index
     * @return string name, or False if Non-numeric index
     */
    public function getName($i = 0){
        if (is_numeric($i)){
            return $this->participationList[$i]['Name'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the default country code for the specified entry, defaults to 0
     * @param int $i index
     * @return string country code, or False if Non-numeric index
     */
    public function getCountry($i = 0){
        if (is_numeric($i)){
            return $this->participationList[$i]['Country'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the default currency code for the specified entry, defaults to 0
     * @param int $i index
     * @return string currency code, or False if Non-numeric index
     */
    public function getCurreny($i = 0){
        if (is_numeric($i)){
            return $this->participationList[$i]['Currency'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the default language code for the specified entry, defaults to 0
     * @param int $i index
     * @return string language code, or False if Non-numeric index
     */
    public function getLanguage($i = 0){
        if (is_numeric($i)){
            return $this->participationList[$i]['Language'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the domain name for the specified entry, defaults to 0
     * @param int $i index
     * @return string language code, or False if Non-numeric index
     */
    public function getDomain($i = 0){
        if (is_numeric($i)){
            return $this->participationList[$i]['Domain'];
        } else {
            return false;
        }
    }
}
?>
