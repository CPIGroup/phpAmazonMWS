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
 * Pulls a list of registered subscription destinations from Amazon.
 *
 * This Amazon Subscriptions Core object retrieves a list of registered
 * subscription destinations from Amazon for a particular marketplace.
 * In order to do this, a marketplace ID is needed. The current store's
 * configured marketplace is used by default.
 */
class AmazonSubscriptionDestinationList extends AmazonSubscriptionCore implements Iterator{
    protected $destinationList;
    protected $i = 0;

    /**
     * Fetches a list of registered subscription destinations from Amazon.
     *
     * Submits a <i>ListRegisteredDestinations</i> request to Amazon. Amazon will send
     * the data back as a response, which can be retrieved using <i>getDestinations</i>.
     * Other methods are available for fetching specific values from the order.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchDestinations(){
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to fetch subscription destinations!", 'Warning');
            return false;
        }

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
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXML($xml){
        $this->destinationList = array();
        if (!$xml){
            return false;
        }

        $i = 0;
        foreach ($xml->DestinationList->children() as $item) {
            $this->destinationList[$i]['DeliveryChannel'] = (string)$item->DeliveryChannel;

            foreach ($item->AttributeList->children() as $member) {
                $this->destinationList[$i]['AttributeList'][(string)$member->Key] = (string)$member->Value;
            }

            $i++;
        }
    }

    /**
     * Returns the specified destination, or all of them.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single order item will have the following fields:
     * <ul>
     * <li><b>DeliveryChannel</b> - the technology used to receive notifications</li>
     * <li><b>AttributeList</b> - array of key/value pairs</li>
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
     * Returns the delivery channel for the specified entry.
     *
     * Possible values for this field: "SQS".
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getDeliveryChannel($i = 0){
        if (isset($this->destinationList[$i]['DeliveryChannel'])){
            return $this->destinationList[$i]['DeliveryChannel'];
        } else {
            return false;
        }
    }

    /**
     * Returns the specified attribute set for the specified entry.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param string $j [optional] <p>Second list index to retrieve the value from. Defaults to NULL.</p>
     * @return array|boolean associative array, or <b>FALSE</b> if Non-numeric index
     */
    public function getAttributes($i = 0, $j = null){
        if (isset($this->destinationList[$i]['AttributeList'])){
            if (isset($this->destinationList[$i]['AttributeList'][$j])){
                return $this->destinationList[$i]['AttributeList'][$j];
            } else {
                return $this->destinationList[$i]['AttributeList'];
            }
        } else {
            return false;
        }
    }

    /**
     * Iterator function
     * @return array
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
        return isset($this->destinationList[$this->i]);
    }

}
