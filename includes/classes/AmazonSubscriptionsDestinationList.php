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
 * Destination, such as an Amazon Simple Queue Service (Amazon SQS) queue
 */
class AmazonSubscriptionsDestinationList extends AmazonSubscriptionsCore implements Iterator{

    private $destinationList;
    private $index;
    private $i = 0;

    /**
     * AmazonSubscriptionsDestinationList contain all of the destination list for subscription.
     *
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

        if (isset($store[$this->storeName]) && array_key_exists('marketplaceId', $store[$this->storeName])){
            $this->options['MarketplaceId'] = $store[$this->storeName]['marketplaceId'];
        } else {
            $this->log("Marketplace ID is missing", 'Urgent');
        }
    }

    /**
     * Set market place id
     * @param string $s
     * @return boolean
     */
    public function setMarketplace($s){
        if (is_string($s) && $s){
            $this->options['MarketplaceId'] = $s;
            return true;
        }
        return false;
    }

    /**
     * Retrieves the destinations list from Amazon.
     *
     * Submits a <i>ListRegisteredDestinations</i> request to Amazon. Amazon will send
     * the data back as a response, which can be retrieved using <i>getDestinations</i>.
     * Other methods are available for fetching specific values from the order.
     * @param boolean <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchDestinations(){
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in subscriptions destination to fetch it!", 'Warning');
            return false;
        }
        $this->destinationList = array();
        $this->index = 0;

        $this->options['Action'] = 'ListRegisteredDestinations';

        $url = $this->urlbase . $this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'] . 'Result';
        if ($this->mockMode){

            $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)){
                return false;
            }

            $xml = simplexml_load_string($response['body'])->$path;
        }

        $this->parseXML($xml);
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLObject $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }

        foreach ($xml->DestinationList->children() as $item) {
            $n = $this->index;
            $this->destinationList[$n]['DeliveryChannel'] = (string) $item->DeliveryChannel;

            foreach ($item->AttributeList->children() as $member) {
                $this->destinationList[$n]['AttributeList'][] = array(
                    'Key' => (string) $member->Key,
                    'Value' => (string) $member->Value
                );
            }

            $this->index++;
        }
    }

    /**
     * Returns the specified destination, or all of them.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single order item will have the following fields:
     * <ul>
     * <li><b>DeliveryChannel</b> - The technology that you are using to receive notifications. (SQS)</li>
     * <li><b>AttributeList</b> - Contains attributes related to the specified DeliveryChannel.</li>
     *   <ul>
     *   <li><b>Key</b> - The name of the attribute. (sqsQueueUrl)</li>
     *   <li><b>Value</b> - The URL for the Amazon SQS queue you are using to receive notifications.</li>
     *   </ul>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.</p>
     * @return array|boolean array, multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getDestinations($i = null){
        if (isset($this->destinationList)){
            if (is_numeric($i)){
                return $this->destinationList[$i];
            } else {
                return $this->destinationList;
            }
        } else {
            return false;
        }
    }

    /**
     * Returns specified delivery channel for the specified delivery.
     *
     * This method will return the entire list of delivery channel if <i>$j</i> is not set.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Second list index to retrieve the value from. Defaults to NULL.</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if incorrect index
     */
    public function getDeliveryChannel($i = 0){
        if (isset($this->destinationList[$i]['DeliveryChannel'])){
            return $this->destinationList[$i]['DeliveryChannel'];
        } else {
            return false;
        }
    }

    /**
     * Returns specified attribute set for the specified delivery.
     *
     * This method will return the entire list of attribute set if <i>$j</i> is not set.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param int $j [optional] <p>Second list index to retrieve the value from. Defaults to NULL.</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if incorrect index
     */
    public function getAttributeList($i = 0, $j = null){
        if (isset($this->destinationList[$i]['AttributeList'])){
            if (isset($this->destinationList[$i]['AttributeList'][$j])){
                return $this->destinationList[$i]['AttributeList'];
            } else {
                return $this->destinationList[$i]['AttributeList'];
            }
        } else {
            return false;
        }
    }

    /**
     * Iterator function
     * @return type
     */
    public function current(){
        return $this->destinationList[$this->i];
    }

    /**
     * Iterator function
     */
    public function rewind(){
        $this->i = 0;
    }

    /**
     * Iterator function
     * @return type
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
     * @return type
     */
    public function valid(){
        return isset($this->destinationList[$this->i]);
    }

}
