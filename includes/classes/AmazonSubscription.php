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
 * Creates a subscription on Amazon or updates it, or registers subscription destinations.
 *
 * This Amazon Subscriptions Core object creates or updates subscriptions and
 * subscription destinations on Amazon for a particular marketplace.
 * In order to do any of these actions, a marketplace ID is needed in addition to
 * a destination's delivery channel and list of attributes.
 * The current store's configured marketplace is used by default.
 * In order to fetch or delete a subscription, a notification type is also needed.
 * In order to create a subscription, an indicator of whether or not the subscription
 * is enabled is required in addition to a notification type.
 */
class AmazonSubscription extends AmazonSubscriptionCore {
    protected $data;

    /**
     * Sets the delivery channel. (Required)
     *
     * This parameter is required for performing any actions with subscription destinations.
     * Possible delivery channel values: "SQS".
     * @param string $s <p>Delivery channel</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setDeliveryChannel($s) {
        if (is_string($s)){
            $this->options['Destination.DeliveryChannel'] = $s;
            $this->options['Subscription.Destination.DeliveryChannel'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the destination attributes. (Required)
     *
     * This parameter is required for performing any actions with subscription destinations.
     * The array provided should be an array of key/value pairs.
     * Possible attribute keys: "sqsQueueUrl".
     * @param array $a <p>Array of key/value pairs</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setAttributes($a) {
        if (empty($a) || !is_array($a)){
            $this->log("Tried to set AttributeList to invalid values", 'Warning');
            return false;
        }
        $this->resetAttributes();
        $i = 1;
        foreach ($a as $k => $v){
            $this->options['Destination.AttributeList.member.'.$i.'.Key'] = $k;
            $this->options['Destination.AttributeList.member.'.$i.'.Value'] = $v;
            $this->options['Subscription.Destination.AttributeList.member.'.$i.'.Key'] = $k;
            $this->options['Subscription.Destination.AttributeList.member.'.$i.'.Value'] = $v;
            $i++;
        }
    }

    /**
     * Resets the destination attribute options.
     *
     * Since the list of attributes is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetAttributes() {
        foreach($this->options as $op=>$junk){
            if(preg_match("#Destination.AttributeList#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the notification type. (Required for subscriptions)
     *
     * This parameter is required for performing any actions with subscriptions.
     * @param string $s <p>See the comment inside for a list of valid values.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setNotificationType($s) {
        if (is_string($s)){
            $this->options['Subscription.NotificationType'] = $s;
            $this->options['NotificationType'] = $s;
        } else {
            return false;
        }
        /*
         * List of valid Notification Types:
         * AnyOfferChanged
         * FulfillmentOrderStatus
         */
    }

    /**
     * Sets whether or not the subscription is enabled. (Required for subscriptions)
     *
     * This parameter is required for performing any actions with subscriptions.
     * @param boolean $b <p>Defaults to <b>TRUE</b></p>
     */
    public function setIsEnabled($b = TRUE) {
        if ($b) {
            $this->options['Subscription.IsEnabled'] = 'true';
        } else {
            $this->options['Subscription.IsEnabled'] = 'false';
        }
    }

    /**
     * Resets the destination-specific parameters.
     *
     * Since these are required parameters, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetDestinationParams() {
        foreach($this->options as $op=>$junk){
            if(preg_match("#^Destination.#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Resets the subscription-specific parameters.
     *
     * Since these are required parameters, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetSubscriptionParams() {
        foreach($this->options as $op=>$junk){
            if(preg_match("#Subscription.#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Registers a subscription destination on Amazon.
     *
     * Submits a <i>RegisterDestination</i> request to Amazon. Amazon will send
     * back an empty response. The following parameters are required:
     * marketplace ID, delivery channel, and attributes.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function registerDestination() {
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to register a subscription destination!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.DeliveryChannel', $this->options)){
            $this->log("Delivery channel must be set in order to register a subscription destination!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.AttributeList.member.1.Key', $this->options)){
            $this->log("Attributes must be set in order to register a subscription destination!", 'Warning');
            return false;
        }

        $this->prepareRegister();

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

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>registerDestination</i>.
     *
     * This changes key options for using <i>registerDestination</i>.
     * Please note: because this operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * notification type and enabled status.
     */
    protected function prepareRegister() {
        $this->options['Action'] = 'RegisterDestination';
        $this->throttleGroup = 'RegisterDestination';
        $this->resetSubscriptionParams();
        unset($this->options['NotificationType']);
    }

    /**
     * Deregisters a subscription destination on Amazon.
     *
     * Submits a <i>DeregisterDestination</i> request to Amazon. Amazon will send
     * back an empty response. The following parameters are required:
     * marketplace ID, delivery channel, and attributes.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function deregisterDestination() {
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to deregister a subscription destination!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.DeliveryChannel', $this->options)){
            $this->log("Delivery channel must be set in order to deregister a subscription destination!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.AttributeList.member.1.Key', $this->options)){
            $this->log("Attributes must be set in order to deregister a subscription destination!", 'Warning');
            return false;
        }

        $this->prepareDeregister();

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

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>deregisterDestination</i>.
     *
     * This changes key options for using <i>deregisterDestination</i>.
     * Please note: because this operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * notification type and enabled status.
     */
    protected function prepareDeregister() {
        $this->options['Action'] = 'DeregisterDestination';
        $this->throttleGroup = 'DeregisterDestination';
        $this->resetSubscriptionParams();
        unset($this->options['NotificationType']);
    }

    /**
     * Sends a request to Amazon to send a test notification to a subscription destination.
     *
     * Submits a <i>SendTestNotificationToDestination</i> request to Amazon. Amazon will send
     * back an empty response. The following parameters are required:
     * marketplace ID, delivery channel, and attributes.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function testDestination() {
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to test a subscription destination!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.DeliveryChannel', $this->options)){
            $this->log("Delivery channel must be set in order to test a subscription destination!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.AttributeList.member.1.Key', $this->options)){
            $this->log("Attributes must be set in order to test a subscription destination!", 'Warning');
            return false;
        }

        $this->prepareTest();

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

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>testDestination</i>.
     *
     * This changes key options for using <i>testDestination</i>.
     * Please note: because this operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * notification type and enabled status.
     */
    protected function prepareTest() {
        $this->options['Action'] = 'SendTestNotificationToDestination';
        $this->throttleGroup = 'SendTestNotificationToDestination';
        $this->resetSubscriptionParams();
        unset($this->options['NotificationType']);
    }

    /**
     * Creates a subscription on Amazon.
     *
     * Submits a <i>CreateSubscription</i> request to Amazon. Amazon will send
     * back an empty response. The following parameters are required:
     * marketplace ID, delivery channel, attributes, notification type, and enabled status.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function createSubscription() {
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to create a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.Destination.DeliveryChannel', $this->options)){
            $this->log("Delivery channel must be set in order to create a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.Destination.AttributeList.member.1.Key', $this->options)){
            $this->log("Attributes must be set in order to create a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.NotificationType', $this->options)){
            $this->log("Notification type must be set in order to create a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.IsEnabled', $this->options)){
            $this->log("Enabled status must be set in order to create a subscription!", 'Warning');
            return false;
        }

        $this->prepareCreate();

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

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>createSubscription</i>.
     *
     * This changes key options for using <i>createSubscription</i>.
     */
    protected function prepareCreate() {
        $this->options['Action'] = 'CreateSubscription';
        $this->throttleGroup = 'CreateSubscription';
        $this->resetDestinationParams();
        unset($this->options['NotificationType']);
    }

    /**
     * Fetches a subscription from Amazon.
     *
     * Submits a <i>GetSubscription</i> request to Amazon. Amazon will send
     * the data back as a response, which can be retrived using <i>getSubscription</i>.
     * The following parameters are required:
     * marketplace ID, delivery channel, attributes, notification type, and enabled status.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchSubscription() {
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to fetch a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.DeliveryChannel', $this->options)){
            $this->log("Delivery channel must be set in order to fetch a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.AttributeList.member.1.Key', $this->options)){
            $this->log("Attributes must be set in order to fetch a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('NotificationType', $this->options)){
            $this->log("Notification type must be set in order to fetch a subscription!", 'Warning');
            return false;
        }

        $this->prepareGet();

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

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>fetchSubscription</i>.
     *
     * This changes key options for using <i>fetchSubscription</i>.
     * Please note: because this operation does not use all of the parameters,
     * the enabled status parameter is removed.
     */
    protected function prepareGet() {
        $this->options['Action'] = 'GetSubscription';
        $this->throttleGroup = 'GetSubscription';
        $this->resetSubscriptionParams();
    }

    /**
     * Updates a subscription on Amazon.
     *
     * Submits an <i>UpdateSubscription</i> request to Amazon. Amazon will send
     * back an empty response. The following parameters are required:
     * marketplace ID, delivery channel, attributes, notification type, and enabled status.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function updateSubscription() {
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to update a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.Destination.DeliveryChannel', $this->options)){
            $this->log("Delivery channel must be set in order to update a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.Destination.AttributeList.member.1.Key', $this->options)){
            $this->log("Attributes must be set in order to update a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.NotificationType', $this->options)){
            $this->log("Notification type must be set in order to update a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Subscription.IsEnabled', $this->options)){
            $this->log("Enabled status must be set in order to update a subscription!", 'Warning');
            return false;
        }

        $this->prepareUpdate();

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

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>updateSubscription</i>.
     *
     * This changes key options for using <i>updateSubscription</i>.
     */
    protected function prepareUpdate() {
        $this->options['Action'] = 'UpdateSubscription';
        $this->throttleGroup = 'UpdateSubscription';
        $this->resetDestinationParams();
        unset($this->options['NotificationType']);
    }

    /**
     * Deletes a subscription on Amazon.
     *
     * Submits a <i>DeleteSubscription</i> request to Amazon. Amazon will send
     * back an empty response. The following parameters are required:
     * marketplace ID, delivery channel, attributes, notification type, and enabled status.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function deleteSubscription() {
        if (!array_key_exists('MarketplaceId', $this->options)){
            $this->log("Marketplace ID must be set in order to delete a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.DeliveryChannel', $this->options)){
            $this->log("Delivery channel must be set in order to delete a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('Destination.AttributeList.member.1.Key', $this->options)){
            $this->log("Attributes must be set in order to delete a subscription!", 'Warning');
            return false;
        }
        if (!array_key_exists('NotificationType', $this->options)){
            $this->log("Notification type must be set in order to delete a subscription!", 'Warning');
            return false;
        }

        $this->prepareDelete();

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

        $this->parseXml($xml);
    }

    /**
     * Sets up options for using <i>deleteSubscription</i>.
     *
     * This changes key options for using <i>deleteSubscription</i>.
     * Please note: because this operation does not use all of the parameters,
     * the enabled status parameter is removed.
     */
    protected function prepareDelete() {
        $this->options['Action'] = 'DeleteSubscription';
        $this->throttleGroup = 'DeleteSubscription';
        $this->resetSubscriptionParams();
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXml($xml) {
        if (!$xml){
            return false;
        }

        if (isset($xml->Subscription)) {
            $this->data = array();
            $this->data['NotificationType'] = (string)$xml->Subscription->NotificationType;
            $this->data['IsEnabled'] = (string)$xml->Subscription->IsEnabled;
            $this->data['Destination']['DeliveryChannel'] = (string)$xml->Subscription->Destination->DeliveryChannel;
            foreach ($xml->Subscription->Destination->AttributeList->children() as $x) {
                $this->data['Destination']['AttributeList'][(string)$x->Key] = (string)$x->Value;
            }
        }
    }

    /**
     * Returns the full array of subscription information.
     *
     * This method will return <b>FALSE</b> if the response data has not yet been filled.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>NotificationType</b> - see <i>setNotificationType</i> for list of valid values</li>
     * <li><b>IsEnabled</b> - "true" or "false"</li>
     * <li><b>Destination</b> - array</li>
     * <ul>
     * <li><b>DeliveryChannel</b> - see <i>setDeliveryChannel</i> for list of valid values</li>
     * <li><b>AttributeList</b> - array of key/value pairs</li>
     * </ul>
     * </ul>
     * @return array|boolean data array, or <b>FALSE</b> if list not filled yet
     * @see setNotificationType
     * @see setDeliveryChannel
     */
    public function getSubscription(){
        if (isset($this->data)){
            return $this->data;
        } else {
            return false;
        }
    }

    /**
     * Returns the notification type for the retrieved subscription.
     *
     * See <i>setNotificationType</i> for list of possible values.
     * This method will return <b>FALSE</b> if the data has not been set yet.
     * @return string|boolean single value, or <b>FALSE</b> if not set yet
     * @see setNotificationType
     */
    public function getNotificationType(){
        if (isset($this->data['NotificationType'])){
            return $this->data['NotificationType'];
        } else {
            return false;
        }
    }

    /**
     * Returns whether the retrieved subscription is enabled or not.
     *
     * Note that this method will return the string "false" if Amazon indicates
     * that the subscription is not enabled.
     * This method will return boolean <b>FALSE</b> if the date has not been set yet.
     * @return string|boolean "true" or "false", or <b>FALSE</b> if not set yet
     */
    public function getIsEnabled(){
        if (isset($this->data['IsEnabled'])){
            return $this->data['IsEnabled'];
        } else {
            return false;
        }
    }

    /**
     * Returns the delivery channel for the retrieved subscription's destination.
     *
     * See <i>setDeliveryChannel</i> for list of possible values.
     * This method will return <b>FALSE</b> if the data has not been set yet.
     * @return string|boolean single value, or <b>FALSE</b> if not set yet
     * @see setDeliveryChannel
     */
    public function getDeliveryChannel(){
        if (isset($this->data['Destination']['DeliveryChannel'])){
            return $this->data['Destination']['DeliveryChannel'];
        } else {
            return false;
        }
    }

    /**
     * Returns the attribute list for the retrieved subscription's destination.
     *
     * See <i>setAttributes</i> for list of possible keys.
     * This method will return <b>FALSE</b> if the data has not been set yet.
     * @return array|boolean associative array, or <b>FALSE</b> if not set yet
     * @see setAttributes
     */
    public function getAttributes(){
        if (isset($this->data['Destination']['AttributeList'])){
            return $this->data['Destination']['AttributeList'];
        } else {
            return false;
        }
    }

}
