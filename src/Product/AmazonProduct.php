<?php namespace CPIGroup\Product;

    /**
     * Copyright 2013 CPI Group, LLC
     *
     * Licensed under the Apache License, Version 2.0 (the "License");
     *
     * you may not use this file except in compliance with the License.
     * You may obtain a copy of the License at
     *
     *     http://www.apache.org/licenses/LICENSE-2.0
     *
     * Unless required by applicable law or agreed to in writing, software
     * distributed under the License is distributed on an "AS IS" BASIS,
     * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
     * See the License for the specific language governing permissions and
     * limitations under the License.
     */

/**
 * Contains Amazon product data.
 *
 * This Amazon Products Core object acts as a container for data fetched by
 * other Products Core objects. It has no Amazon functions in itself.
 */
class AmazonProduct extends AmazonProductsCore
{

    protected $data;

    /**
     * AmazonProduct acts as a container for various results from other classes.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     *
     * @param string           $s      [optional] <p>Name for the store you want to use.
     *                                 This parameter is optional if only one store is defined in the config file.</p>
     * @param SimpleXMLElement $data   [optional] <p>XML data from Amazon to be parsed.</p>
     * @param boolean          $mock   [optional] <p>This is a flag for enabling Mock Mode.
     *                                 This defaults to <b>FALSE</b>.</p>
     * @param array|string     $m      [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string           $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct( $s = null, $data = null, $mock = false, $m = null, $config = null )
    {

        parent::__construct( $s, $mock, $m, $config );

        if ($data) {
            $this->loadXML( $data );
        }

        unset( $this->productList );

    }

    /**
     * Takes in XML data and converts it to an array for the object to use.
     *
     * @param SimpleXMLObject $xml <p>XML Product data from Amazon</p>
     *
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    public function loadXML( $xml )
    {

        if (!$xml) {
            return false;
        }

        $this->data = [ ];

        //Categories first
        if ($xml->getName() == 'GetProductCategoriesForSKUResult'
            || $xml->getName() == 'GetProductCategoriesForASINResult'
        ) {
            $this->loadCategories( $xml );

            return;
        }

        if ($xml->getName() != 'Product') {
            return;
        }

        //Identifiers
        if ($xml->Identifiers) {
            foreach ($xml->Identifiers->children() as $x) {
                foreach ($x->children() as $z) {
                    $this->data[ 'Identifiers' ][ $x->getName() ][ $z->getName() ] = (string) $z;
                }
            }
        }

        //AttributeSets
        if ($xml->AttributeSets) {
            $anum = 0;
            foreach ($xml->AttributeSets->children( 'ns2', true ) as $aset) {
                foreach ($aset->children( 'ns2', true ) as $x) {
                    if ($x->children( 'ns2', true )
                          ->count() > 0
                    ) {
                        //another layer
                        foreach ($x->children( 'ns2', true ) as $y) {
                            if ($y->children( 'ns2', true )
                                  ->count() > 0
                            ) {
                                //we need to go deeper
                                foreach ($y->children( 'ns2', true ) as $z) {
                                    if ($z->children( 'ns2', true )
                                          ->count() > 0
                                    ) {
                                        //we need to go deeper
                                        $this->log( 'Warning! Attribute ' . $z->getName() . ' is too deep for this!',
                                            'Urgent' );
                                    } else {
                                        $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ][ $y->getName() ][ $z->getName() ] = (string) $z;
                                    }
                                }
                            } else {
                                $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ][ $y->getName() ] = (string) $y;
                            }
                        }

                    } else {
                        //Check for duplicates
                        if (array_key_exists( 'AttributeSets', $this->data )
                            && array_key_exists( $anum, $this->data[ 'AttributeSets' ] )
                            && array_key_exists( $x->getName(), $this->data[ 'AttributeSets' ][ $anum ] )
                        ) {

                            //check for previous cases of duplicates
                            if (is_array( $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ] )) {
                                $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ][ ] = (string) $x;
                            } else {
                                //first instance of duplicates, make into array
                                $temp                                                       = [ $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ] ];
                                $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ]    = $temp;
                                $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ][ ] = (string) $x;
                            }
                        } else {
                            //no duplicates
                            $this->data[ 'AttributeSets' ][ $anum ][ $x->getName() ] = (string) $x;
                        }
                    }
                }
                $anum++;
            }
        }

        //Relationships
        if ($xml->Relationships) {
            foreach ($xml->Relationships->children() as $x) {
                foreach ($x->children() as $y) {
                    foreach ($y->children() as $z) {
                        foreach ($z->children() as $zzz) {
                            $this->data[ 'Relationships' ][ $x->getName() ][ $y->getName() ][ $z->getName() ][ $zzz->getName() ] = (string) $zzz;
                        }
                    }
                }
            }
            //child relations use namespace but parent does not
            foreach ($xml->Relationships->children( 'ns2', true ) as $x) {
                foreach ($x->children() as $y) {
                    foreach ($y->children() as $z) {
                        foreach ($z->children() as $zzz) {
                            $this->data[ 'Relationships' ][ $x->getName() ][ $y->getName() ][ $z->getName() ][ $zzz->getName() ] = (string) $zzz;
                        }
                    }
                }
            }
        }

        //CompetitivePricing
        if ($xml->CompetitivePricing) {
            //CompetitivePrices
            foreach ($xml->CompetitivePricing->CompetitivePrices->children() as $pset) {
                $pnum                                                                                       = (string) $pset->CompetitivePriceId;
                $temp                                                                                       = (array) $pset->attributes();
                $belongs                                                                                    = $temp[ '@attributes' ][ 'belongsToRequester' ];
                $con                                                                                        = $temp[ '@attributes' ][ 'condition' ];
                $sub                                                                                        = $temp[ '@attributes' ][ 'subcondition' ];
                $this->data[ 'CompetitivePricing' ][ 'CompetitivePrices' ][ $pnum ][ 'belongsToRequester' ] = $belongs;
                $this->data[ 'CompetitivePricing' ][ 'CompetitivePrices' ][ $pnum ][ 'condition' ]          = $con;
                $this->data[ 'CompetitivePricing' ][ 'CompetitivePrices' ][ $pnum ][ 'subcondition' ]       = $sub;

                foreach ($pset->Price->children() as $x) {
                    //CompetitivePrice->Price
                    foreach ($x->children() as $y) {
                        $this->data[ 'CompetitivePricing' ][ 'CompetitivePrices' ][ $pnum ][ 'Price' ][ $x->getName() ][ $y->getName() ] = (string) $y;
                    }

                }

                $pnum++;
            }
            //NumberOfOfferListings
            if ($xml->CompetitivePricing->NumberOfOfferListings) {
                foreach ($xml->CompetitivePricing->NumberOfOfferListings->children() as $x) {
                    $temp                                                                                   = (array) $x->attributes();
                    $att                                                                                    = $temp[ '@attributes' ][ 'condition' ];
                    $this->data[ 'CompetitivePricing' ][ 'NumberOfOfferListings' ][ $x->getName() ][ $att ] = (string) $x;
                }
            }

            //TradeInValue
            if ($xml->CompetitivePricing->TradeInValue) {
                foreach ($xml->CompetitivePricing->TradeInValue->children() as $x) {
                    $this->data[ 'CompetitivePricing' ][ 'TradeInValue' ][ $x->getName() ] = (string) $x;
                }
            }
        }

        //SalesRankings
        if ($xml->SalesRankings) {
            foreach ($xml->SalesRankings->children() as $x) {
                foreach ($x->children() as $y) {
                    $this->data[ 'SalesRankings' ][ $x->getName() ][ $y->getName() ] = (string) $y;
                }
            }
        }

        //LowestOfferListings
        if ($xml->LowestOfferListings) {
            $lnum = 0;
            foreach ($xml->LowestOfferListings->children() as $x) {
                //LowestOfferListing
                foreach ($x->children() as $y) {
                    if ($y->children()
                          ->count() > 0
                    ) {
                        foreach ($y->children() as $z) {
                            if ($z->children()
                                  ->count() > 0
                            ) {
                                foreach ($z->children() as $zzz) {
                                    $this->data[ 'LowestOfferListings' ][ $lnum ][ $y->getName() ][ $z->getName() ][ $zzz->getName() ] = (string) $zzz;
                                }
                            } else {
                                $this->data[ 'LowestOfferListings' ][ $lnum ][ $y->getName() ][ $z->getName() ] = (string) $z;
                            }

                        }
                    } else {
                        $this->data[ 'LowestOfferListings' ][ $lnum ][ $y->getName() ] = (string) $y;
                    }
                }
                $lnum++;
            }
        }

        //Offers
        if ($xml->Offers) {
            $onum = 0;
            foreach ($xml->Offers->children() as $x) {
                //Offer
                foreach ($x->children() as $y) {
                    if ($y->children()
                          ->count() > 0
                    ) {
                        foreach ($y->children() as $z) {
                            if ($z->children()
                                  ->count() > 0
                            ) {
                                foreach ($z->children() as $zzz) {
                                    $this->data[ 'Offers' ][ $onum ][ $y->getName() ][ $z->getName() ][ $zzz->getName() ] = (string) $zzz;
                                }
                            } else {
                                $this->data[ 'Offers' ][ $onum ][ $y->getName() ][ $z->getName() ] = (string) $z;
                            }

                        }
                    } else {
                        $this->data[ 'Offers' ][ $onum ][ $y->getName() ] = (string) $y;
                    }
                }
                $onum++;
            }
        }

    }

    /**
     * Takes in XML data for Categories and parses it for the object to use
     *
     * @param SimpleXMLObject $xml <p>The XML data from Amazon.</p>
     *
     * @return boolean <b>FALSE</b> if no valid XML data is found
     */
    protected function loadCategories( $xml )
    {

        //Categories
        if (!$xml->Self) {
            return false;
        }
        $cnum = 0;
        foreach ($xml->children() as $x) {
            $this->data[ 'Categories' ][ $cnum ] = $this->genHierarchy( $x );
            $cnum++;
        }
    }

    /**
     * Recursively builds the hierarchy array.
     *
     * The returned array will have the fields <b>ProductCategoryId</b> and
     * <b>ProductCategoryName</b>, as well as maybe a <b>Parent</b> field with the same
     * structure as the array containing it.
     *
     * @param SimpleXMLObject $xml <p>The XML data from Amazon.</p>
     *
     * @return array Recursive, multi-dimensional array
     */
    protected function genHierarchy( $xml )
    {

        if (!$xml) {
            return false;
        }
        $a                          = [ ];
        $a[ 'ProductCategoryId' ]   = (string) $xml->ProductCategoryId;
        $a[ 'ProductCategoryName' ] = (string) $xml->ProductCategoryName;
        if ($xml->Parent) {
            $a[ 'Parent' ] = $this->genHierarchy( $xml->Parent );
        }

        return $a;
    }

    /**
     * See <i>getData</i>.
     *
     * @return array Huge array of Product data.
     */
    public function getProduct( $num = null )
    {

        return $this->getData();
    }

    /**
     * Returns all product data.
     *
     * The array returned will likely be very large and contain data too varied
     * to be described here.
     *
     * @return array Huge array of Product data.
     */
    public function getData()
    {

        if (isset( $this->data )) {
            return $this->data;
        } else {
            return false;
        }
    }

}

?>