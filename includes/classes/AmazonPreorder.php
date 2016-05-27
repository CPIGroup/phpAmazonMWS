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
 * Fetches preorder info for an inbound fulfillment shipment or confirms it.
 *
 * This Amazon Inbound Core object submits a request to get item preorder
 * information for an inbound fulfillment shipment with Amazon. It can also
 * send a confirmation of the info. In order to get preorder info, the ID of
 * an inbound fulfillment shipment is required. In order to confirm the info,
 * a NeedByDate is also required. The NeedByDate need to confirm preorder is
 * the same date given when retrieving preorder info. Use the AmazonShipment
 * object to create an inbound shipment and acquire a shipment ID.
 */
class AmazonPreorder extends AmazonInboundCore {
    protected $hasPreorderItems = false;
    protected $needByDate;
    protected $fulfillableDate;
    protected $isConfirmed = false;

    /**
     * AmazonPreorder gets preorder information about a shipment from Amazon.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s [optional] <p>Name for the store you want to use.</p>
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param string $id [optional] <p>The Fulfillment Shipment ID to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $id = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);

        if($id){
            $this->setShipmentId($id);
        }
    }

    /**
     * Sets the shipment ID. (Required)
     * @param string $s <p>Shipment ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setShipmentId($s){
        if (is_string($s) && $s){
            $this->options['ShipmentId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the maximum arrival date for the shipment. (Required to confirm)
     *
     * This method sets the max arrival date to be sent in the next request.
     * This parameter is required to use <i>confirmPreorder</i> and is removed
     * by <i>fetchPreorderInfo</i>.
     * @param string $d <p>A time string</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setNeedByDate($d) {
        try{
            $this->options['NeedByDate'] = strstr($this->genTime($d), 'T', true);
        } catch (Exception $e){
            unset($this->options['NeedByDate']);
            $this->log('Error: '.$e->getMessage(), 'Warning');
            return false;
        }
    }

    /**
     * Fetches preorder information from Amazon.
     *
     * Submits a <i>GetPreorderInfo</i> request to Amazon. In order to do this,
     * a fulfillment shipment ID is required. Amazon will send the data back as
     * a response, which can be retrieved using <i>getNeedByDate</i>,
     * <i>getFulfillableDate</i>, <i>getHasPreorderableItems</i>,
     * and <i>getIsConfirmed</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchPreorderInfo(){
        if (!array_key_exists('ShipmentId',$this->options)) {
            $this->log("Shipment ID must be set in order to get preorder info!", 'Warning');
            return false;
        }

        $this->prepareGet();

        $url = $this->urlbase.$this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
            $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));

            if (!$this->checkResponse($response)){
                return false;
            }

            $xml = simplexml_load_string($response['body']);
        }

        $this->parseXml($xml->$path);
    }

    /**
     * Sets up options for using <i>fetchPreorderInfo</i>.
     *
     * This changes key options for using <i>fetchPreorderInfo</i>.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * NeedByDate.
     */
    protected function prepareGet() {
        $this->throttleGroup = 'GetPreorderInfo';
        $this->options['Action'] = 'GetPreorderInfo';
        unset($this->options['NeedByDate']);
    }

    /**
     * Confirms preorder information for a shipment with Amazon.
     *
     * Submits a <i>ConfirmPreorder</i> request to Amazon. In order to do this,
     * a fulfillment shipment ID and an arrival date are required.
     * Amazon will send the data back as a response, which can be retrieved
     * using <i>getNeedByDate</i> and <i>getFulfillableDate</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function confirmPreorder(){
        if (!array_key_exists('ShipmentId',$this->options)) {
            $this->log("Shipment ID must be set in order to confirm preorder info!", 'Warning');
            return false;
        }
        if (!array_key_exists('NeedByDate',$this->options)) {
            $this->log("NeedByDate must be set in order to confirm preorder info!", 'Warning');
            return false;
        }

        $this->prepareConfirm();

        $url = $this->urlbase.$this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
            $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));

            if (!$this->checkResponse($response)){
                return false;
            }

            $xml = simplexml_load_string($response['body']);
        }

        $this->parseXml($xml->$path);
    }

    /**
     * Sets up options for using <i>confirmPreorder</i>.
     *
     * This changes key options for using <i>confirmPreorder</i>.
     */
    protected function prepareConfirm() {
        $this->throttleGroup = 'ConfirmPreorder';
        $this->options['Action'] = 'ConfirmPreorder';
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

        $this->needByDate = null;
        if (isset($xml->NeedByDate)) {
            $this->needByDate = (string)$xml->NeedByDate;
        }
        if (isset($xml->ConfirmedNeedByDate)) {
            $this->needByDate = (string)$xml->ConfirmedNeedByDate;
        }
        $this->fulfillableDate = (string)$xml->ConfirmedFulfillableDate;
        if (isset($xml->ShipmentContainsPreorderableItems)) {
            $this->hasPreorderItems = (string)$xml->ShipmentContainsPreorderableItems;
        }
        if (isset($xml->ShipmentConfirmedForPreorder)) {
            $this->isConfirmed = (string)$xml->ShipmentConfirmedForPreorder;
        }
    }

    /**
     * Returns the date that the shipment must arrive by in order to fulfill preorders.
     *
     * After <i>confirmPreorder</i>, this date should be the same as
     * the <i>NeedByDate</i> option that was sent with the request.
     * This method will return <b>FALSE</b> if the date has not been set yet.
     * @return string|boolean date in YYYY-MM-DD format, or <b>FALSE</b> if date not set yet
     */
    public function getNeedByDate(){
        if (isset($this->needByDate)){
            return $this->needByDate;
        } else {
            return false;
        }
    }

    /**
     * Returns the date that preorderable items in the shipment can be purchased.
     *
     * This method will return <b>FALSE</b> if the date has not been set yet.
     * @return string|boolean date in YYYY-MM-DD format, or <b>FALSE</b> if date not set yet
     */
    public function getFulfillableDate(){
        if (isset($this->fulfillableDate)){
            return $this->fulfillableDate;
        } else {
            return false;
        }
    }

    /**
     * Indicates whether or not the shipment has items that are preorderable.
     *
     * Note that this method will return the string "false" if Amazon indicates
     * that the shipment does not have preorderable items.
     * This method will return boolean <b>FALSE</b> if the date has not been set yet.
     * @return string|boolean "true" or "false", or <b>FALSE</b> if date not set yet
     */
    public function getHasPreorderableItems(){
        return $this->hasPreorderItems;
    }

    /**
     * Indicates whether or not the shipment has been confirmed for preorder.
     *
     * Note that this method will return the string "false" if Amazon indicates
     * that the shipment information has not yet been confirmed.
     * This method will return boolean <b>FALSE</b> if the date has not been set yet.
     * @return string|boolean "true" or "false", or <b>FALSE</b> if date not set yet
     */
    public function getIsConfirmed(){
        return $this->isConfirmed;
    }

}
