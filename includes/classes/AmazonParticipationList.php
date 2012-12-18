<?php
/**
 * Gets the Participation list from Amazon.
 * 
 * This Amazon Sellers Core object retrieves the list of the sellers'
 * Marketplace Participations from Amazon. It has no parameters other
 * than potential use of tokens.
 */
class AmazonParticipationList extends AmazonSellersCore{
    private $tokenFlag = false;
    private $tokenUseFlag = false;
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
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
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
    public function fetchParticipationList(){
        $this->options['Timestamp'] = $this->genTime();
        $this->prepareToken();
        
        
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
        
        if ($xml->NextToken){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
        
        $this->parseXML($xml);
        
    }
    
    private function prepareToken(){
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'ListMarketplaceParticipationsByNextToken';
        } else {
            $this->options['Action'] = 'ListMarketplaceParticipations';
            unset($this->options['NextToken']);
            $this->marketplaceList = array();  
            $this->participationList = array();
            $this->indexM = 0;
            $this->indexP = 0;
        }
    }
    
    /**
     * converts XML into arrays
     */
    protected function parseXML($xml){
        $xmlP = $xml->ListParticipations;
        $xmlM = $xml->ListMarketplaces;
        
        foreach($xmlP->children() as $x){
            $this->participationList[$this->indexP]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->participationList[$this->indexP]['SellerId'] = (string)$x->SellerId;
            $this->participationList[$this->indexP]['Suspended'] = (string)$x->HasSellerSuspendedListings;
            $this->indexP++;
        }
        
        
        foreach($xmlM->children() as $x){
            $this->marketplaceList[$this->indexM]['MarketplaceId'] = (string)$x->MarketplaceId;
            $this->marketplaceList[$this->indexM]['Name'] = (string)$x->Name;
            $this->marketplaceList[$this->indexM]['Country'] = (string)$x->DefaultCountryCode;
            $this->marketplaceList[$this->indexM]['Currency'] = (string)$x->DefaultCurrencyCode;
            $this->marketplaceList[$this->indexM]['Language'] = (string)$x->DefaultLanguageCode;
            $this->marketplaceList[$this->indexM]['Domain'] = (string)$x->DomainName;
            $this->indexM++;
        }
        
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->log("Recursively fetching more Participationseses");
            $this->fetchParticipationList();
        }
    }
    
    /**
     * Returns entire list of marketplaces, for convenience
     * @return array
     */
    public function getMarketplaceList(){
        if (isset($this->marketplaceList)){
            return $this->marketplaceList;
        } else {
            return false;
        }
    }
    
    /**
     * Returns entire list of participations, for convenience
     * @return array
     */
    public function getParticipationList(){
        if (isset($this->participationList)){
            return $this->participationList;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the Marketplace ID for the specified entry, defaults to 0
     * @param int $i index
     * @return string|boolean MarketplaceId, or False if Non-numeric index
     */
    public function getMarketplaceId($i = 0){
        if (!isset($this->marketplaceList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->marketplaceList)){
            return $this->marketplaceList[$i]['MarketplaceId'];
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
        if (!isset($this->marketplaceList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->marketplaceList)){
            return $this->marketplaceList[$i]['Name'];
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
        if (!isset($this->marketplaceList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->marketplaceList)){
            return $this->marketplaceList[$i]['Country'];
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
        if (!isset($this->marketplaceList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->marketplaceList)){
            return $this->marketplaceList[$i]['Currency'];
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
        if (!isset($this->marketplaceList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->marketplaceList)){
            return $this->marketplaceList[$i]['Language'];
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
        if (!isset($this->marketplaceList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->marketplaceList)){
            return $this->marketplaceList[$i]['Domain'];
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
        if (!isset($this->participationList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->participationList)){
            return $this->participationList[$i]['SellerId'];
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
        if (!isset($this->participationList)){
            return false;
        }
        if (is_numeric($i) && array_key_exists($i, $this->participationList)){
            return $this->participationList[$i]['Suspended'];
        } else {
            return false;
        }
    }
}
?>
