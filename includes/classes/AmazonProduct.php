<?php
/**
 * Contains Amazon product data.
 * 
 * This Amazon Products Core object acts as a container for data fetched by
 * other Products Core objects. It has no Amazon functions in itself.
 */
class AmazonProduct extends AmazonProductsCore{
    protected $data;
    
    /**
     * AmazonProduct acts as a container for various results from other classes.
     * @param string $s store name as seen in config
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $data = null, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if ($data){
            $this->loadXML($data);
        }
        
        unset($this->productList);
        
        $this->throttleLimit = $throttleLimitProduct;
        
    }
    
    /**
     * Takes in XML data and parses it for the object to use
     * @param SimpleXMLObject $xml
     */
    public function loadXML($xml){
        $this->data = array();
        
        //Categories first
        if ($xml->getName() == 'GetProductCategoriesForSKUResult' || $xml->getName() == 'GetProductCategoriesForASINResult'){
            $this->loadCategories($xml);
            return;
        }
        
        if ($xml->getName() != 'Product'){
            return;
        }
        
        //Identifiers
        if ($xml->Identifiers){
            foreach($xml->Identifiers->children() as $x){
                foreach($x->children() as $z){
                    $this->data['Identifiers'][$x->getName()][$z->getName()] = (string)$z;
                }
            }
        }
        
        //AttributeSets
        if ($xml->AttributeSets){
            $anum = 0;
            foreach($xml->AttributeSets->children('ns2',true) as $aset){
                foreach($aset->children('ns2',true) as $x){
                    if ($x->children('ns2',true)->count() > 0){
                        //another layer
                        foreach($x->children('ns2',true) as $y){
                            if ($y->children('ns2',true)->count() > 0){
                                //we need to go deeper
                                foreach($y->children('ns2',true) as $z){
                                    if ($z->children('ns2',true)->count() > 0){
                                        //we need to go deeper
                                        $this->log('Warning! Attribute '.$z->getName().' is too deep for this!', 'Urgent');
                                    } else {
                                        $this->data['AttributeSets'][$anum][$x->getName()][$y->getName()][$z->getName()] = (string)$z;
                                    }
                                }
                            } else {
                                $this->data['AttributeSets'][$anum][$x->getName()][$y->getName()] = (string)$y;
                            }
                        }

                    } else {
                        //Check for duplicates
                        if (array_key_exists('AttributeSets', $this->data) && 
                                array_key_exists($anum, $this->data['AttributeSets']) && 
                                array_key_exists($x->getName(), $this->data['AttributeSets'][$anum])){

                            //check for previous cases of duplicates
                            if (is_array($this->data['AttributeSets'][$anum][$x->getName()])){
                                $this->data['AttributeSets'][$anum][$x->getName()][] = (string)$x;
                            } else {
                                //first instance of duplicates, make into array
                                $temp = array($this->data['AttributeSets'][$anum][$x->getName()]);
                                $this->data['AttributeSets'][$anum][$x->getName()] = $temp;
                                $this->data['AttributeSets'][$anum][$x->getName()][] = (string)$x;
                            }
                        } else {
                            //no duplicates
                            $this->data['AttributeSets'][$anum][$x->getName()] = (string)$x;
                        }
                    }
                }
                $anum++;
            }
        }
        
        //Relationships
        if ($xml->Relationships){
            foreach($xml->Relationships->children() as $x){
                foreach($x->children() as $y){
                    foreach($y->children() as $z){
                        foreach($z->children() as $zzz){
                            $this->data['Relationships'][$x->getName()][$y->getName()][$z->getName()][$zzz->getName()] = (string)$zzz;
                        }
                    }
                }
            }
        }
        
        //CompetitivePricing
        if ($xml->CompetitivePricing){
            //CompetitivePrices
            foreach($xml->CompetitivePricing->CompetitivePrices->children() as $pset){
                $pnum = (string)$pset->CompetitivePriceId;
                $temp = (array)$pset->attributes();
                $belongs = $temp['@attributes']['belongsToRequester'];
                $con = $temp['@attributes']['condition'];
                $sub = $temp['@attributes']['subcondition'];
                $this->data['CompetitivePricing']['CompetitivePrices'][$pnum]['belongsToRequester'] = $belongs;
                $this->data['CompetitivePricing']['CompetitivePrices'][$pnum]['condition'] = $con;
                $this->data['CompetitivePricing']['CompetitivePrices'][$pnum]['subcondition'] = $sub;
                
                
                foreach($pset->Price->children() as $x){
                    //CompetitivePrice->Price
                    foreach($x->children() as $y){
                        $this->data['CompetitivePricing']['CompetitivePrices'][$pnum]['Price'][$x->getName()][$y->getName()] = (string)$y;
                    }
                    
                }
                
                $pnum++;
            }
            //NumberOfOfferListings
            if ($xml->CompetitivePricing->NumberOfOfferListings){
                foreach($xml->CompetitivePricing->NumberOfOfferListings->children() as $x){
                    $temp = (array)$x->attributes();
                    $att = $temp['@attributes']['condition'];
                    $this->data['CompetitivePricing']['NumberOfOfferListings'][$x->getName()][$att] = (string)$x;
                }
            }
            
            //TradeInValue
            if ($xml->CompetitivePricing->TradeInValue){
                foreach($xml->CompetitivePricing->TradeInValue->children() as $x){
                    $this->data['CompetitivePricing']['TradeInValue'][$x->getName()] = (string)$x;
                }
            }
        }
            
        
        //SalesRankings
        if ($xml->SalesRankings){
            foreach($xml->SalesRankings->children() as $x){
                foreach($x->children() as $y){
                    $this->data['SalesRankings'][$x->getName()][$y->getName()] = (string)$y;
                }
            }
        }
        
        //LowestOfferListings
        if ($xml->LowestOfferListings){
            $lnum = 0;
            foreach($xml->LowestOfferListings->children() as $x){
                //LowestOfferListing
                foreach($x->children() as $y){
                    if ($y->children()->count() > 0){
                        foreach($y->children() as $z){
                            if ($z->children()->count() > 0){
                                foreach($z->children() as $zzz){
                                    $this->data['LowestOfferListings'][$lnum][$y->getName()][$z->getName()][$zzz->getName()] = (string)$zzz;
                                }
                            } else {
                                $this->data['LowestOfferListings'][$lnum][$y->getName()][$z->getName()] = (string)$z;
                            }
                            
                        }
                    } else {
                        $this->data['LowestOfferListings'][$lnum][$y->getName()] = (string)$y;
                    }
                }
                $lnum++;
            }
        }
        
        //Offers
        if ($xml->Offers){
            $onum = 0;
            foreach($xml->Offers->children() as $x){
                //Offer
                foreach($x->children() as $y){
                    if ($y->children()->count() > 0){
                        foreach($y->children() as $z){
                            if ($z->children()->count() > 0){
                                foreach($z->children() as $zzz){
                                    $this->data['Offers'][$onum][$y->getName()][$z->getName()][$zzz->getName()] = (string)$zzz;
                                }
                            } else {
                                $this->data['Offers'][$onum][$y->getName()][$z->getName()] = (string)$z;
                            }
                            
                        }
                    } else {
                        $this->data['Offers'][$onum][$y->getName()] = (string)$y;
                    }
                }
                $onum++;
            }
        }
        
        
        
    }
    
    /**
     * Takes in XML data for Categories and parses it for the object to use
     * @param SimpleXMLObject $xml
     */
    protected function loadCategories($xml){
        //Categories
        if (!$xml->Self){
            return false;
        }
        $cnum = 0;
        foreach($xml->children() as $x){
            $this->data['Categories'][$cnum] = $this->genHierarchy($x);
            $cnum++;
        }
    }
    
    /**
     * Recursively builds the hierarchy array
     * @param SimpleXMLObject $xml
     * @return array
     */
    protected function genHierarchy($xml){
        if (!$xml){
            return false;
        }
        $a = array();
        $a['ProductCategoryId'] = (string)$xml->ProductCategoryId;
        $a['ProductCategoryName'] = (string)$xml->ProductCategoryName;
        if ($xml->Parent){
            $a['Parent'] = $this->genHierarchy($xml->Parent);
        }
        return $a;
    }
    
    /**
     * Returns product data
     * @return array Product data
     */
    public function getProduct(){
        return $this->getData();
    }
    
    /**
     * Returns product data
     * @return array Product data
     */
    public function getData(){
        if (isset($this->data)){
            return $this->data;
        } else {
            return false;
        }
    }
    
}
?>