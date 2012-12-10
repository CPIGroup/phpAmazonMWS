<?php

class AmazonProduct extends AmazonProductsCore{
    protected $data;
    
    /**
     * AmazonProduct acts as a container for various results from other classes. Currently, has no Amazon functions
     * @param string $s store name as seen in config
     * @param boolean $mock set true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            return false;
        }
        
        $this->throttleLimit = $throttleLimitProduct;
        
    }
    
    /**
     * Takes in XML data and parses it for the object to use
     * @param SimpleXMLObject $xml
     */
    public function loadXML($xml){
        $this->data = array();
        if ($xml->getName() != 'Product'){
            return;
        }
        
        myPrint($xml);
        
        //Identifiers
        foreach($xml->Identifiers->children() as $x){
            foreach($x->children() as $z){
                $this->data['Identifiers'][$x->getName()][$z->getName()] = (string)$z;
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
            myPrint($xml->CompetitivePricing->CompetitivePrices);
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
        
        myPrint($this->data);
    }
    
    /**
     * Returns product data
     * @return array Product data
     */
    public function getProduct(){
        return $this->data;
    }
    
}
?>