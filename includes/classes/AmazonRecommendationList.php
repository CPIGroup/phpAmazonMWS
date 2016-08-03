<?php

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
 * Pulls a list of active recommendations from Amazon.
 *
 * This Amazon Recommendations Core object retrieves a list of active
 * recommendations from Amazon for a particular marketplace.
 * In order to do this, a marketplace ID is needed. The current store's
 * configured marketplace is used by default.
 * This class can be iterated over, but only when the category parameter is set.
 */
class AmazonRecommendationList extends AmazonRecommendationCore implements Iterator {
    protected $updated;
    protected $list;
    protected $listkey;
    protected $i = 0;
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;

    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken() {
        return $this->tokenFlag;
    }

    /**
     * Sets whether or not the object should automatically use tokens if it receives one.
     *
     * If this option is set to <b>TRUE</b>, the object will automatically perform
     * the necessary operations to retrieve the rest of the list using tokens. If
     * this option is off, the object will only ever retrieve the first section of
     * the list.
     * @param boolean $b [optional] <p>Defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setUseToken($b = true) {
        if (is_bool($b)) {
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }

    /**
     * Sets the category filter. (Optional)
     *
     * If this parameter is set, Amazon will only return recommendations from
     * the specified category. If this parameter is not sent, Amazon will return
     * recommendations from all categories.
     * Possible category values: "Inventory", "Selection", "Pricing", "Fulfillment",
     * "ListingQuality", "GlobalSelling", and "Advertising".
     * @param string $s <p>Category name</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setCategory($s) {
        if (is_string($s)) {
            $this->options['RecommendationCategory'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the category filter. (Optional)
     *
     * If this parameter is set, Amazon will only return recommendations that
     * match the given filters. If this parameter is not sent, Amazon will return
     * all recommendations for each category.
     * The given array should be two-dimensional, with the first level indexed by
     * the name of the category, and the second level as a list of key/value pairs
     * of filters for that specific category.
     * See <i>setCategory</i> for a list of valid categories.
     * See the comment inside for a list of valid filters.
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
     * @see setCategory
     */
    public function setFilter($a) {
        $this->resetFilters();
        if (is_array($a)) {
            $i = 1;
            foreach ($a as $c => $r) {
                if (empty($r) || !is_array($r)) {
                    $this->resetFilters();
                    return false;
                }
                $prefix = 'CategoryQueryList.CategoryQuery.'.$i;
                $this->options[$prefix.'.RecommendationCategory'] = $c;
                $j = 1;
                foreach ($r as $k => $x) {
                    $this->options[$prefix.'.FilterOptions.FilterOption.'.$j] = $k.'='.$x;
                    $j++;
                }
                $i++;
            }
        } else {
            return false;
        }
        /*
         * Valid filters for ListingQuality recommendations:
         *      QualitySet: "Defect" or "Quarantine"
         *      ListingStatus: "Active" or "Inactive"
         * Valid filters for Selection, Fulfillment, GlobalSelling, and Advertising recommendations:
         *      BrandName: any brand name
         *      ProductCategory: any product category
         * Valid filters for Selection recommendations:
         *      IncludeCommonRecommendations: "true" or "false"
         */
    }

    /**
     * Removes filter options.
     *
     * Use this in case you change your mind and want to remove the filter
     * parameters you previously set.
     */
    public function resetFilters() {
        foreach($this->options as $op=>$junk) {
            if(preg_match("#CategoryQueryList#",$op)) {
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Fetches recommendation update times from Amazon.
     *
     * Submits a <i>GetLastUpdatedTimeForRecommendations</i> request to Amazon.
     * Amazon will send dates back as a response, which can be retrieved using
     * <i>getLastUpdateTimes</i>.
     * Other methods are available for fetching individual times.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchLastUpdateTimes() {
        if (!array_key_exists('MarketplaceId', $this->options)) {
            $this->log("Marketplace ID must be set in order to fetch recommendation times!", 'Warning');
            return false;
        }

        $this->prepareTimes();

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        if ($this->mockMode){
            $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)){
                return false;
            }

            $xml = simplexml_load_string($response['body']);
        }

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>fetchLastUpdateTimes</i>.
     *
     * This changes key options for using <i>fetchLastUpdateTimes</i>.
     * Please note: because this operation does not use all of the parameters,
     * the following parameters are removed:
     * category, filters, and token.
     */
    protected function prepareTimes() {
        $this->options['Action'] = 'GetLastUpdatedTimeForRecommendations';
        $this->throttleGroup = 'GetLastUpdatedTimeForRecommendations';
        unset($this->options['NextToken']);
        unset($this->options['RecommendationCategory']);
        $this->resetFilters();
        $this->updated = array();
    }

    /**
     * Fetches a list of active recommendations from Amazon.
     *
     * Submits a <i>ListRecommendations</i> request to Amazon. Amazon will send
     * the data back as a response, categorized into seven lists. These lists
     * can be retrieved using <i>getLists</i>.
     * Other methods are available for fetching individual lists.
     * @param boolean $r [optional] <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchRecommendations($r = true) {
        if (!array_key_exists('MarketplaceId', $this->options)) {
            $this->log("Marketplace ID must be set in order to fetch recommendations!", 'Warning');
            return false;
        }

        $this->prepareToken();

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'] . 'Result';
        if ($this->mockMode) {
            $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)){
                return false;
            }

            $xml = simplexml_load_string($response['body'])->$path;
        }

        $this->parseXml($xml);

        $this->checkToken($xml);

        if ($this->tokenFlag && $this->tokenUseFlag && $r === true) {
            while ($this->tokenFlag) {
                $this->log("Recursively fetching more recommendations");
                $this->fetchRecommendations(false);
            }
        }
    }

    /**
     * Sets up options for using tokens.
     *
     * This changes key options for switching between simply fetching a list and
     * fetching the rest of a list using a token. Please note: because the
     * operation for using tokens does not use any other parameters, all other
     * parameters will be removed.
     */
    protected function prepareToken() {
        $this->throttleGroup = 'ListRecommendations';
        if ($this->tokenFlag && $this->tokenUseFlag) {
            $this->options['Action'] = 'ListRecommendationsByNextToken';

            //When using tokens, only the NextToken option should be used
            unset($this->options['RecommendationCategory']);
            $this->resetFilters();
        } else {
            $this->options['Action'] = 'ListRecommendations';
            unset($this->options['NextToken']);
            $this->list = array();
            $this->listkey = null;
            if (isset($this->options['RecommendationCategory'])) {
                $this->listkey = $this->options['RecommendationCategory'];
            }
        }
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXML($xml){
        if (!$xml) {
            return false;
        }

        if ($xml->InventoryRecommendationsLastUpdated) {
            $this->updated['Inventory'] = (string)$xml->InventoryRecommendationsLastUpdated;
        }
        if ($xml->SelectionRecommendationsLastUpdated) {
            $this->updated['Selection'] = (string)$xml->SelectionRecommendationsLastUpdated;
        }
        if ($xml->PricingRecommendationsLastUpdated) {
            $this->updated['Pricing'] = (string)$xml->PricingRecommendationsLastUpdated;
        }
        if ($xml->FulfillmentRecommendationsLastUpdated) {
            $this->updated['Fulfillment'] = (string)$xml->FulfillmentRecommendationsLastUpdated;
        }
        if ($xml->GlobalSellingRecommendationsLastUpdated) {
            $this->updated['GlobalSelling'] = (string)$xml->GlobalSellingRecommendationsLastUpdated;
        }
        if ($xml->AdvertisingRecommendationsLastUpdated) {
            $this->updated['Advertising'] = (string)$xml->AdvertisingRecommendationsLastUpdated;
        }

        if (isset($xml->InventoryRecommendations)) {
            foreach ($xml->InventoryRecommendations->children() as $x) {
                $this->list['Inventory'][] = $this->parseRecommendation($x);
            }
        }
        if (isset($xml->SelectionRecommendations)) {
            foreach ($xml->SelectionRecommendations->children() as $x) {
                $this->list['Selection'][] = $this->parseRecommendation($x);
            }
        }
        if (isset($xml->PricingRecommendations)) {
            foreach ($xml->PricingRecommendations->children() as $x) {
                $this->list['Pricing'][] = $this->parseRecommendation($x);
            }
        }
        if (isset($xml->FulfillmentRecommendations)) {
            foreach ($xml->FulfillmentRecommendations->children() as $x) {
                $this->list['Fulfillment'][] = $this->parseRecommendation($x);
            }
        }
        if (isset($xml->ListingQualityRecommendations)) {
            foreach ($xml->ListingQualityRecommendations->children() as $x) {
                $this->list['ListingQuality'][] = $this->parseRecommendation($x);
            }
        }
        if (isset($xml->GlobalSellingRecommendations)) {
            foreach ($xml->GlobalSellingRecommendations->children() as $x) {
                $this->list['GlobalSelling'][] = $this->parseRecommendation($x);
            }
        }
        if (isset($xml->AdvertisingRecommendations)) {
            foreach ($xml->AdvertisingRecommendations->children() as $x) {
                $this->list['Advertising'][] = $this->parseRecommendation($x);
            }
        }
    }

    /**
     * Parses XML response for a single recommendation into an array.
     * @param SimpleXMLElement $xml
     * @return array parsed structure from XML
     */
    protected function parseRecommendation($xml) {
        $r = array();
        foreach ($xml->children() as $x) {
            if (isset($x->Asin)) {
                $r[$x->getName()]['ASIN'] = (string)$x->Asin;
                $r[$x->getName()]['SKU'] = (string)$x->Sku;
                $r[$x->getName()]['UPC'] = (string)$x->Upc;
            } else if (isset($x->CurrencyCode)) {
                $r[$x->getName()]['Amount'] = (string)$x->Amount;
                $r[$x->getName()]['CurrencyCode'] = (string)$x->CurrencyCode;
            } else if (isset($x->Height)) {
                $r[$x->getName()]['Height']['Value'] = (string)$x->Height->Value;
                $r[$x->getName()]['Height']['Unit'] = (string)$x->Height->Unit;
                $r[$x->getName()]['Width']['Value'] = (string)$x->Width->Value;
                $r[$x->getName()]['Width']['Unit'] = (string)$x->Width->Unit;
                $r[$x->getName()]['Length']['Value'] = (string)$x->Length->Value;
                $r[$x->getName()]['Length']['Unit'] = (string)$x->Length->Unit;
                $r[$x->getName()]['Weight']['Value'] = (string)$x->Weight->Value;
                $r[$x->getName()]['Weight']['Unit'] = (string)$x->Weight->Unit;
            } else {
                $r[$x->getName()] = (string)$x;
            }
        }
        return $r;
    }

    /**
     * Returns a list of all update times.
     *
     * The returned array will have keys from any of the categories listed in <i>setCategory</i>.
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean array of timestamps, or <b>FALSE</b> if list not set yet
     */
    public function getLastUpdateTimes(){
        if (isset($this->updated)){
            return $this->updated;
        } else {
            return false;
        }
    }

    /**
     * Returns the last update time for Inventory recommendations.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean date in ISO 8601 date time format, or <b>FALSE</b> if not set yet
     */
    public function getInventoryLastUpdateTime(){
        if (isset($this->updated['Inventory'])){
            return $this->updated['Inventory'];
        } else {
            return false;
        }
    }

    /**
     * Returns the last update time for Selection recommendations.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean date in ISO 8601 date time format, or <b>FALSE</b> if not set yet
     */
    public function getSelectionLastUpdateTime(){
        if (isset($this->updated['Selection'])){
            return $this->updated['Selection'];
        } else {
            return false;
        }
    }

    /**
     * Returns the last update time for Pricing recommendations.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean date in ISO 8601 date time format, or <b>FALSE</b> if not set yet
     */
    public function getPricingLastUpdateTime(){
        if (isset($this->updated['Pricing'])){
            return $this->updated['Pricing'];
        } else {
            return false;
        }
    }

    /**
     * Returns the last update time for Fulfillment recommendations.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean date in ISO 8601 date time format, or <b>FALSE</b> if not set yet
     */
    public function getFulfillmentLastUpdateTime(){
        if (isset($this->updated['Fulfillment'])){
            return $this->updated['Fulfillment'];
        } else {
            return false;
        }
    }

    /**
     * Returns the last update time for Global Selling recommendations.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean date in ISO 8601 date time format, or <b>FALSE</b> if not set yet
     */
    public function getGlobalSellingLastUpdateTime(){
        if (isset($this->updated['GlobalSelling'])){
            return $this->updated['GlobalSelling'];
        } else {
            return false;
        }
    }

    /**
     * Returns the last update time for Advertising recommendations.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean date in ISO 8601 date time format, or <b>FALSE</b> if not set yet
     */
    public function getAdvertisingLastUpdateTime(){
        if (isset($this->updated['Advertising'])){
            return $this->updated['Advertising'];
        } else {
            return false;
        }
    }

    /**
     * Returns all recommendations from all categories.
     *
     * The returned array will have keys from any of the categories listed in <i>setCategory</i>.
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     * @see setCategory
     */
    public function getLists(){
        if (isset($this->list)){
            return $this->list;
        } else {
            return false;
        }
    }

    /**
     * Returns recommendations from the Inventory category.
     *
     * Each recommendation array will have the following keys:
     * <ul>
     * <li><b>RecommendationId</b> - {Category}-{RecommendationReason}-{ASIN}-{SKU}-{UPC}-{ItemName}</li>
     * <li><b>RecommendationReason</b></li>
     * <li><b>LastUpdated</b> - ISO 8601 date format</li>
     * <li><b>ItemIdentifier</b> - array</li>
     * <ul>
     * <li><b>ASIN</b></li>
     * <li><b>SKU</b></li>
     * <li><b>UPC</b></li>
     * </ul>
     * <li><b>ItemName</b> (optional)</li>
     * <li><b>FulfillmentChannel</b> (optional) - "MFN" or "AFN"</li>
     * <li><b>SalesForTheLast14Days</b> (optional) - integer</li>
     * <li><b>SalesForTheLast30Days</b> (optional) - integer</li>
     * <li><b>AvailableQuantity</b> (optional) - integer</li>
     * <li><b>DaysUntilStockRunsOut</b> (optional) - integer</li>
     * <li><b>InboundQuantity</b> (optional) - integer</li>
     * <li><b>RecommendedInboundQuantity</b> (optional) - integer</li>
     * <li><b>DaysOutOfStockLast30Days</b> (optional) - integer</li>
     * <li><b>LostSalesInLast30Days</b> (optional) - integer</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getInventoryList(){
        if (isset($this->list['Inventory'])){
            return $this->list['Inventory'];
        } else {
            return false;
        }
    }

    /**
     * Returns recommendations from the Selection category.
     *
     * Each recommendation array will have the following keys:
     * <ul>
     * <li><b>RecommendationId</b> - {Category}-{RecommendationReason}-{ASIN}-{SKU}-{UPC}-{ItemName}</li>
     * <li><b>RecommendationReason</b></li>
     * <li><b>LastUpdated</b> - ISO 8601 date format</li>
     * <li><b>ItemIdentifier</b> - array</li>
     * <ul>
     * <li><b>ASIN</b></li>
     * <li><b>SKU</b></li>
     * <li><b>UPC</b></li>
     * </ul>
     * <li><b>ItemName</b> (optional)</li>
     * <li><b>BrandName</b> (optional)</li>
     * <li><b>ProductCategory</b> (optional)</li>
     * <li><b>SalesRank</b> (optional)</li>
     * <li><b>BuyboxPrice</b> (optional) - array</li>
     * <ul>
     * <li><b>Amount</b> - decimal number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 format</li>
     * </ul>
     * <li><b>NumberOfOffers</b> (optional) - integer</li>
     * <li><b>AverageCustomerReview</b> (optional) - decimal number</li>
     * <li><b>NumberOfCustomerReviews</b> (optional) - integer</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getSelectionList(){
        if (isset($this->list['Selection'])){
            return $this->list['Selection'];
        } else {
            return false;
        }
    }

    /**
     * Returns recommendations from the Pricing category.
     *
     * Each recommendation array will have the following keys:
     * <ul>
     * <li><b>RecommendationId</b> - {Category}-{RecommendationReason}-{ASIN}-{SKU}-{UPC}-{ItemName}</li>
     * <li><b>RecommendationReason</b></li>
     * <li><b>LastUpdated</b> - ISO 8601 date format</li>
     * <li><b>ItemIdentifier</b> - array</li>
     * <ul>
     * <li><b>ASIN</b></li>
     * <li><b>SKU</b></li>
     * <li><b>UPC</b></li>
     * </ul>
     * <li><b>ItemName</b> (optional)</li>
     * <li><b>Condition</b> (optional)</li>
     * <li><b>SubCondition</b> (optional)</li>
     * <li><b>FulfillmentChannel</b> (optional) - "MFN" or "AFN"</li>
     * <li><b>YourPricePlusShipping</b> (optional) - array</li>
     * <ul>
     * <li><b>Amount</b> - decimal number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 format</li>
     * </ul>
     * <li><b>LowestPricePlusShipping</b> (optional) - array with "Amount" and "CurrencyCode"</li>
     * <li><b>PriceDifferenceToLowPrice</b> (optional) - array with "Amount" and "CurrencyCode"</li>
     * <li><b>MedianPricePlusShipping</b> (optional) - array with "Amount" and "CurrencyCode"</li>
     * <li><b>LowestMerchantFulfilledOfferPrice</b> (optional) - array with "Amount" and "CurrencyCode"</li>
     * <li><b>LowestAmazonFulfilledOfferPrice</b> (optional) - array with "Amount" and "CurrencyCode"</li>
     * <li><b>NumberOfOffers</b> (optional) - integer</li>
     * <li><b>NumberOfMerchantFulfilledOffers</b> (optional) - integer</li>
     * <li><b>NumberOfAmazonFulfilledOffers</b> (optional) - integer</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getPricingList(){
        if (isset($this->list['Pricing'])){
            return $this->list['Pricing'];
        } else {
            return false;
        }
    }

    /**
     * Returns recommendations from the Fulfillment category.
     *
     * Each recommendation array will have the following keys:
     * <ul>
     * <li><b>RecommendationId</b> - {Category}-{RecommendationReason}-{ASIN}-{SKU}-{UPC}-{ItemName}</li>
     * <li><b>RecommendationReason</b></li>
     * <li><b>LastUpdated</b> - ISO 8601 date format</li>
     * <li><b>ItemIdentifier</b> - array</li>
     * <ul>
     * <li><b>ASIN</b></li>
     * <li><b>SKU</b></li>
     * <li><b>UPC</b></li>
     * </ul>
     * <li><b>ItemName</b> (optional)</li>
     * <li><b>BrandName</b> (optional)</li>
     * <li><b>ProductCategory</b> (optional)</li>
     * <li><b>SalesRank</b> (optional) - integer</li>
     * <li><b>BuyboxPrice</b> (optional) - array</li>
     * <ul>
     * <li><b>Amount</b> - decimal number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 format</li>
     * </ul>
     * <li><b>NumberOfOffers</b> (optional) - integer</li>
     * <li><b>NumberOfOffersFulfilledByAmazon</b> (optional) - integer</li>
     * <li><b>AverageCustomerReview</b> (optional) - decimal number</li>
     * <li><b>NumberOfCustomerReviews</b> (optional) - integer</li>
     * <li><b>ItemDimensions</b> (optional) - array</li>
     * <ul>
     * <li><b>Height</b> - array</li>
     * <ul>
     * <li><b>Value</b> - decimal number</li>
     * <li><b>Unit</b></li>
     * </ul>
     * <li><b>Width</b> - array with "Value" and "Unit"</li>
     * <li><b>Length</b> - array with "Value" and "Unit"</li>
     * <li><b>Weight</b> - array with "Value" and "Unit"</li>
     * </ul>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getFulfillmentList(){
        if (isset($this->list['Fulfillment'])){
            return $this->list['Fulfillment'];
        } else {
            return false;
        }
    }

    /**
     * Returns recommendations from the Listing Quality category.
     *
     * Each recommendation array will have the following keys:
     * <ul>
     * <li><b>RecommendationId</b> - {Category}-{RecommendationReason}-{ASIN}-{SKU}-{UPC}-{ItemName}</li>
     * <li><b>RecommendationReason</b></li>
     * <li><b>QualitySet</b> - "Defect" or "Quarantine"</li>
     * <li><b>DefectGroup</b> (optional) - description, for Defect quality set</li>
     * <li><b>DefectAttribute</b> - for Defect quality set</li>
     * <li><b>ItemIdentifier</b> - array</li>
     * <ul>
     * <li><b>ASIN</b></li>
     * <li><b>SKU</b></li>
     * <li><b>UPC</b></li>
     * </ul>
     * <li><b>ItemName</b> (optional)</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getListingList(){
        if (isset($this->list['ListingQuality'])){
            return $this->list['ListingQuality'];
        } else {
            return false;
        }
    }

    /**
     * Returns recommendations from the Global Selling category.
     *
     * Each recommendation array will have the following keys:
     * <ul>
     * <li><b>RecommendationId</b> - {Category}-{RecommendationReason}-{ASIN}-{SKU}-{UPC}-{ItemName}</li>
     * <li><b>RecommendationReason</b></li>
     * <li><b>LastUpdated</b> - ISO 8601 date format</li>
     * <li><b>ItemIdentifier</b> - array</li>
     * <ul>
     * <li><b>ASIN</b></li>
     * <li><b>SKU</b></li>
     * <li><b>UPC</b></li>
     * </ul>
     * <li><b>ItemName</b> (optional)</li>
     * <li><b>BrandName</b> (optional)</li>
     * <li><b>ProductCategory</b> (optional)</li>
     * <li><b>SalesRank</b> (optional) - integer</li>
     * <li><b>BuyboxPrice</b> (optional) - array</li>
     * <ul>
     * <li><b>Amount</b> - decimal number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 format</li>
     * </ul>
     * <li><b>NumberOfOffers</b> (optional) - integer</li>
     * <li><b>NumberOfOffersFulfilledByAmazon</b> (optional) - integer</li>
     * <li><b>AverageCustomerReview</b> (optional) - decimal number</li>
     * <li><b>NumberOfCustomerReviews</b> (optional) - integer</li>
     * <li><b>ItemDimensions</b> (optional) - array</li>
     * <ul>
     * <li><b>Height</b> - array</li>
     * <ul>
     * <li><b>Value</b> - decimal number</li>
     * <li><b>Unit</b></li>
     * </ul>
     * <li><b>Width</b> - array with "Value" and "Unit"</li>
     * <li><b>Length</b> - array with "Value" and "Unit"</li>
     * <li><b>Weight</b> - array with "Value" and "Unit"</li>
     * </ul>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getGlobalSellingList(){
        if (isset($this->list['GlobalSelling'])){
            return $this->list['GlobalSelling'];
        } else {
            return false;
        }
    }

    /**
     * Returns recommendations from the Advertising category.
     *
     * Each recommendation array will have the following keys:
     * <ul>
     * <li><b>RecommendationId</b> - {Category}-{RecommendationReason}-{ASIN}-{SKU}-{UPC}-{ItemName}</li>
     * <li><b>RecommendationReason</b></li>
     * <li><b>LastUpdated</b> - ISO 8601 date format</li>
     * <li><b>ItemIdentifier</b> - array</li>
     * <ul>
     * <li><b>ASIN</b></li>
     * <li><b>SKU</b></li>
     * <li><b>UPC</b></li>
     * </ul>
     * <li><b>ItemName</b> (optional)</li>
     * <li><b>BrandName</b> (optional)</li>
     * <li><b>ProductCategory</b> (optional)</li>
     * <li><b>SalesRank</b> (optional) - integer</li>
     * <li><b>YourPricePlusShipping</b> (optional) - array</li>
     * <ul>
     * <li><b>Amount</b> - decimal number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 format</li>
     * </ul>
     * <li><b>LowestPricePlusShipping</b> (optional) - array with "Amount" and "CurrencyCode"</li>
     * <li><b>AvailableQuantity</b> (optional) - integer</li>
     * <li><b>SalesForTheLast30Days</b> (optional) - integer</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getAdvertisingList(){
        if (isset($this->list['Advertising'])){
            return $this->list['Advertising'];
        } else {
            return false;
        }
    }

    /**
     * Iterator function
     * @return array
     */
    public function current(){
        return $this->list[$this->listkey][$this->i];
    }

    /**
     * Iterator function
     */
    public function rewind(){
        $this->i = 0;
    }

    /**
     * Iterator function
     * @return int
     */
    public function key(){
        return $this->i;
    }

    /**
     * Iterator function
     */
    public function next(){
        $this->i++;
    }

    /**
     * Iterator function
     * @return boolean
     */
    public function valid(){
        return isset($this->list[$this->listkey][$this->i]);
    }

}
