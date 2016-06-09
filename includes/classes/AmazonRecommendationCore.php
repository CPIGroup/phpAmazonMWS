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
 * Core class for Amazon Recommendations API.
 *
 * This is the core class for all objects in the Amazon Recommendations section.
 * It contains a method that all Amazon Recommendations Core objects use.
 */
abstract class AmazonRecommendationCore extends AmazonCore{

    /**
     * AmazonRecommendationCore constructor sets up key information used in all Amazon Recommendations Core requests
     *
     * This constructor is called when initializing all objects in the Amazon Recommendations Core.
     * The parameters are passed by the child objects' constructors, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }

        if (isset($AMAZON_VERSION_RECOMMEND)){
            $this->urlbranch = 'Recommendations/' . $AMAZON_VERSION_RECOMMEND;
            $this->options['Version'] = $AMAZON_VERSION_RECOMMEND;
        }

        if(isset($THROTTLE_LIMIT_RECOMMEND)) {
            $this->throttleLimit = $THROTTLE_LIMIT_RECOMMEND;
        }
        if(isset($THROTTLE_TIME_RECOMMEND)) {
            $this->throttleTime = $THROTTLE_TIME_RECOMMEND;
        }

        if (isset($store[$this->storeName]['marketplaceId'])){
            $this->setMarketplace($store[$this->storeName]['marketplaceId']);
        } else {
            $this->log("Marketplace ID is missing", 'Urgent');
        }
    }

    /**
     * Sets the marketplace associated with the recommendations. (Optional)
     *
     * The current store's configured marketplace is used by default.
     * @param string $m <p>Marketplace ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setMarketplace($m){
        if (is_string($m)){
            $this->options['MarketplaceId'] = $m;
        } else {
            return false;
        }
    }

}

