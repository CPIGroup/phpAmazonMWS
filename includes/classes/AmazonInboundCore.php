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
 * Core class for Amazon Inbound Shipment API.
 * 
 * This is the core class for all objects in the Amazon Inbound section.
 * It contains no methods in itself other than the constructor.
 */
abstract class AmazonInboundCore extends AmazonCore{
    /**
     * AmazonInboundCore constructor sets up key information used in all Amazon Inbound Core requests
     * 
     * This constructor is called when initializing all objects in the Amazon Inbound Core.
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
        
        if(isset($AMAZON_VERSION_INBOUND)){
            $this->urlbranch = 'FulfillmentInboundShipment/'.$AMAZON_VERSION_INBOUND;
            $this->options['Version'] = $AMAZON_VERSION_INBOUND;
        }
        
        
        if(isset($THROTTLE_LIMIT_INVENTORY)) {
            $this->throttleLimit = $THROTTLE_LIMIT_INVENTORY;
        }
        if(isset($THROTTLE_TIME_INVENTORY)) {
            $this->throttleTime = $THROTTLE_TIME_INVENTORY;
        }
        $this->throttleGroup = 'Inventory';
    }
}
?>
