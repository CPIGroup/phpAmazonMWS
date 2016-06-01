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
 * Fetches documents for a fulfillment shipment.
 *
 * This Amazon Inbound Core object retrieves documents for an inbound
 * fulfillment shipment. Documents are stored in PDF format. In order to
 * retrieve any documents, the ID of an inbound fulfillment shipment is needed.
 * In order to fetch labels, the paper type must be specified. Use the
 * AmazonShipment object to create an inbound shipment and acquire a shipment ID.
 */
class AmazonTransportDocument extends AmazonInboundCore {
    protected $doc;
    protected $checksum;

    /**
     * AmazonShipmentDocument gets documents for a shipment from Amazon.
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
     * Sets the page type. (Required for labels)
     *
     * This parameter is required for fetching label documents from Amazon.
     * @param string $s <p>See the comment inside for a list of valid values.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setPageType($s) {
        if (is_string($s) && $s){
            $this->options['PageType'] = $s;
        } else {
            $this->log('Tried to set PageType to invalid value', 'Warning');
            return false;
        }
        /*
         * Valid page types:
         * PackageLabel_Plain_Paper (1 per sheet)
         * PackageLabel_Letter_2 (2 per sheet)
         * PackageLabel_Letter_6 (6 per sheet)
         * PackageLabel_A4_2 (2 per sheet)
         * PackageLabel_A4_4 (4 per sheet)
         */
    }

    /**
     * Sets the package ID(s) to get labels for. (Required for getting package labels)
     *
     * The package identifiers should match the <i>CartonId</i> values sent in a
     * previous <i>FBA Inbound Shipment Carton Information Feed</i>.
     * Use the <i>AmazonFeed</i> object to send a feed.
     * @param array|string $s <p>A list of package IDs, or a single ID string.</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setPackageIds($s) {
        if (is_string($s) || is_numeric($s)) {
            $s = array($s);
        }
        if (is_array($s)){
            $this->resetPackageIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['PackageLabelsToPrint.member.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Resets the package ID parameters.
     *
     * Since package IDs are required, these parameters should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetPackageIds() {
        foreach($this->options as $op=>$junk){
            if(preg_match("#PackageLabelsToPrint#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the number of pallets to get labels for. (Required for getting pallet labels)
     *
     * @param int $n <p>number of boxes</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setPalletCount($n){
        if (is_numeric($n) && $n >= 1){
            $this->options['NumberOfPallets'] = $n;
        } else {
            return false;
        }
    }

    /**
     * Gets a document containing package labels for a shipment from Amazon.
     *
     * Submits a <i>GetUniquePackageLabels</i> request to Amazon. In order to do this,
     * a fulfillment shipment ID and list of package IDs are required.
     * Amazon will send a document back as a response, which can be retrieved using <i>getDocument</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchPackageLabels() {
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('ShipmentId must be set in order to get package labels!', 'Warning');
            return false;
        }
        if (!array_key_exists('PackageLabelsToPrint.member.1', $this->options)) {
            $this->log('Package IDs must be set in order to get package labels!', 'Warning');
            return false;
        }

        $this->preparePackage();

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
     * Sets up options for using <i>fetchPackageLabels</i>.
     *
     * This changes key options for using <i>fetchPackageLabels</i>.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * number of pallets.
     * @see resetSendParams
     */
    protected function preparePackage() {
        $this->throttleGroup = 'GetUniquePackageLabels';
        $this->options['Action'] = 'GetUniquePackageLabels';
        unset($this->options['NumberOfPallets']);
    }

    /**
     * Gets a document containing package labels for a shipment from Amazon.
     *
     * Submits a <i>GetPalletLabels</i> request to Amazon. In order to do this,
     * a fulfillment shipment ID and the number of pallets are required.
     * Amazon will send a document back as a response, which can be retrieved using <i>getDocument</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchPalletLabels() {
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('ShipmentId must be set in order to get pallet labels!', 'Warning');
            return false;
        }
        if (!array_key_exists('NumberOfPallets', $this->options)) {
            $this->log('Number of pallets must be set in order to get pallet labels!', 'Warning');
            return false;
        }

        $this->preparePallet();

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
     * Sets up options for using <i>fetchPalletLabels</i>.
     *
     * This changes key options for using <i>fetchPalletLabels</i>.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * package IDs.
     * @see resetSendParams
     */
    protected function preparePallet() {
        $this->throttleGroup = 'GetPalletLabels';
        $this->options['Action'] = 'GetPalletLabels';
        $this->resetPackageIds();
    }

    /**
     * Gets a bill of lading document for a shipment from Amazon.
     *
     * Submits a <i>GetBillOfLading</i> request to Amazon. In order to do this,
     * a fulfillment shipment ID for a Less Than Truckload/Full Truckload shipment is required.
     * Amazon will send a document back as a response, which can be retrieved using <i>getDocument</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchBillOfLading() {
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('ShipmentId must be set in order to get a bill of lading!', 'Warning');
            return false;
        }

        $this->prepareBillOfLading();

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
     * Sets up options for using <i>fetchBillOfLading</i>.
     *
     * This changes key options for using <i>fetchBillOfLading</i>.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * package IDs, number of pallets.
     * @see resetSendParams
     */
    protected function prepareBillOfLading() {
        $this->throttleGroup = 'GetBillOfLading';
        $this->options['Action'] = 'GetBillOfLading';
        $this->resetPackageIds();
        unset($this->options['NumberOfPallets']);
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

        //response from send, confirm, estimate, void
        if (isset($xml->TransportDocument)) {
            $this->doc = (string)$xml->TransportDocument->PdfDocument;
            $this->checksum = (string)$xml->TransportDocument->Checksum;
        }
    }

    /**
     * Returns the file contents for the transport document.
     *
     * The contents of the document depends on which action was used to retrieve the document.
     * This method will return <b>FALSE</b> if the file has not been fetched yet.
     * @param boolean $raw [optional] <p>Set to TRUE to get the raw, base64-encoded file contents.</p>
     * @return string|boolean file contents, encoded file, or <b>FALSE</b> if file not fetched yet
     */
    public function getDocument($raw = FALSE) {
        if (isset($this->doc)) {
            if ($raw) {
                return $this->doc;
            }
            try {
                return base64_decode($this->doc);
            } catch (Exception $ex) {
                $this->log('Failed to convert transport document file, file might be corrupt: '.
                        $ex->getMessage(), 'Urgent');
            }
        }
        return false;
    }

    /**
     * Returns the checksum the transport document.
     *
     * This method will return <b>FALSE</b> if the file has not been fetched yet.
     * @param boolean $raw [optional] <p>Set to TRUE to get the raw, base64-encoded checksum.</p>
     * @return string|boolean checksum, or <b>FALSE</b> if file not fetched yet
     */
    public function getChecksum($raw = FALSE) {
        if (isset($this->checksum)) {
            if ($raw) {
                return $this->checksum;
            }
            try {
                return base64_decode($this->checksum);
            } catch (Exception $ex) {
                $this->log('Failed to convert transport document checksum, file might be corrupt: '.
                        $ex->getMessage(), 'Urgent');
            }
        }
        return false;
    }

}
