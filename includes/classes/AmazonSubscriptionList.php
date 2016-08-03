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
 * Pulls a list of subscriptions from Amazon.
 *
 * This Amazon Subscriptions Core object retrieves a list of subscriptions from Amazon
 * for a particular marketplace. In order to do this, a marketplace ID is needed.
 * The current store's configured marketplace is used by default.
 */
class AmazonSubscriptionList extends AmazonSubscriptionCore implements Iterator{
    protected $list;
    protected $i = 0;

    /**
     * Fetches a list of subscriptions from Amazon.
     *
     * Submits a <i>ListSubscriptions</i> request to Amazon. Amazon will send
     * the data back as a response, which can be retrieved using <i>getList</i>.
     * Other methods are available for fetching specific values from the order.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchSubscriptions(){
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to fetch subscriptions!", 'Warning');
            return false;
        }

        $this->options['Action'] = 'ListSubscriptions';

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
        $this->list = array();
        if (!$xml){
            return false;
        }

        foreach ($xml->SubscriptionList->children() as $x) {
            $temp = array();
            $temp['NotificationType'] = (string)$x->NotificationType;
            $temp['IsEnabled'] = (string)$x->IsEnabled;
            $temp['Destination']['DeliveryChannel'] = (string)$x->Destination->DeliveryChannel;
            foreach ($x->Destination->AttributeList->children() as $z) {
                $temp['Destination']['AttributeList'][(string)$z->Key] = (string)$z->Value;
            }
            $this->list[] = $temp;
        }
    }

    /**
     * Returns the specified subscription, or all of them.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from.
     * If none is given, the entire list will be returned. Defaults to NULL.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getList($i = null){
        if (isset($this->list)){
            if (is_numeric($i)){
                return $this->list[$i];
            } else {
                return $this->list;
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the notification type for the retrieved subscription.
     *
     * See <i>setNotificationType</i> for list of possible values.
     * This method will return <b>FALSE</b> if the data has not been set yet.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if not set yet or invalid index
     * @see setNotificationType
     */
    public function getNotificationType($i = 0){
        if (isset($this->list[$i]['NotificationType'])){
            return $this->list[$i]['NotificationType'];
        } else {
            return false;
        }
    }

    /**
     * Returns the notification type for the retrieved subscription.
     *
     * Note that this method will return the string "false" if Amazon indicates
     * that the subscription is not enabled.
     * This method will return boolean <b>FALSE</b> if the date has not been set yet.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean "true" or "false", or <b>FALSE</b> if not set yet or invalid index
     */
    public function getIsEnabled($i = 0){
        if (isset($this->list[$i]['IsEnabled'])){
            return $this->list[$i]['IsEnabled'];
        } else {
            return false;
        }
    }

    /**
     * Returns the delivery channel for the retrieved subscription's destination.
     *
     * See <i>setDeliveryChannel</i> for list of possible values.
     * This method will return <b>FALSE</b> if the data has not been set yet.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if not set yet or invalid index
     * @see setDeliveryChannel
     */
    public function getDeliveryChannel($i = 0){
        if (isset($this->list[$i]['Destination']['DeliveryChannel'])){
            return $this->list[$i]['Destination']['DeliveryChannel'];
        } else {
            return false;
        }
    }

    /**
     * Returns the attribute list for the retrieved subscription's destination.
     *
     * This method will return <b>FALSE</b> if the data has not been set yet.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @param string $j [optional] <p>Second list index to retrieve the value from. Defaults to NULL.</p>
     * @return array|boolean associative array, or <b>FALSE</b> if not set yet or invalid index
     */
    public function getAttributes($i = 0, $j = null){
        if (isset($this->list[$i]['Destination']['AttributeList'])){
            if (isset($this->list[$i]['Destination']['AttributeList'][$j])) {
                return $this->list[$i]['Destination']['AttributeList'][$j];
            } else {
                return $this->list[$i]['Destination']['AttributeList'];
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
        return $this->list[$this->i];
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
        return isset($this->list[$this->i]);
    }

}
