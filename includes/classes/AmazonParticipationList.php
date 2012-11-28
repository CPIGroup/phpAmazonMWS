    <?php

class AmazonParticipationList extends AmazonSellersCore{
    private $tokenFlag;
    private $tokenUseFlag;
    private $participationList;
    private $marketplaceList;
    private $indexM = 0;
    private $indexP = 0;
    
    /**
     * Gets list of marketplaces run by seller
     * @param string $s store name, as seen in the config file
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        include($this->config);
        
        $this->throttleLimit = $throttleLimitSellers;
        $this->throttleTime = $throttleTimeSellers;
        $this->throttleGroup = 'ParticipationList';
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
     * @param boolean $refresh set false to preserve current list (for internal use)
     */
    public function fetchParticipationList($refresh = true){
        $this->options['Timestamp'] = $this->genTime();
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListMarketplaceParticipationsByNextToken';
        } else {
            $this->options['Action'] = 'ListMarketplaceParticipations';
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
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $xmlP = $xml->ListParticipations;
        $xmlM = $xml->ListMarketplaces;
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        if ($refresh){
          $this->marketplaceList = array();  
          $this->participationList = array();
        }
        
        
        foreach($xmlP->children() as $x){
            $this->marketplaceList[$this->indexP]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->marketplaceList[$this->indexP]['SellerId'] = (string)$x->SellerId;
            $this->marketplaceList[$this->indexP]['Suspended'] = (string)$x->HasSellerSuspendedListings;
            $this->indexP++;
        }
        
        
        foreach($xmlM->children() as $x){
            $this->participationList[$this->indexM]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->participationList[$this->indexM]['Name'] = (string)$x->Name;
            $this->participationList[$this->indexM]['Country'] = (string)$x->DefaultCountryCode;
            $this->participationList[$this->indexM]['Currency'] = (string)$x->DefaultCurrencyCode;
            $this->participationList[$this->indexM]['Language'] = (string)$x->DefaultLanguageCode;
            $this->participationList[$this->indexM]['Domain'] = (string)$x->DomainName;
            $this->indexM++;
        }
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Participationseses");
            $this->fetchParticipationList(false);
        }
        
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
     * @return string|boolean MarketplaceId, or False if Non-numeric index
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
     * @return string|boolean SellerId, or False if Non-numeric index
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
     * @return string|boolean name, or False if Non-numeric index
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
     * @return string|boolean country code, or False if Non-numeric index
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
     * @return string|boolean currency code, or False if Non-numeric index
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
     * @return string|boolean language code, or False if Non-numeric index
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
     * @return string|boolean language code, or False if Non-numeric index
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
