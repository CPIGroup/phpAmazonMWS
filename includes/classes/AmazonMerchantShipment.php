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
 * Fetches a merchant-fulfilled shipment from Amazon.
 *
 * This Amazon Merchant Fulfillment Core object can retrieve (or simply contain)
 * a merchant-fulfilled shipment from Amazon, or cancel it.
 * In order to fetch or cancel a shipment, a Shipment ID is needed.
 * Shipment IDs are given by Amazon by using the <i>AmazonMerchantShipmentCreator</i> object.
 */
class AmazonMerchantShipment extends AmazonMerchantCore {
    protected $data;

    /**
     * AmazonMerchantShipment object gets the details for a single object from Amazon.
     *
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that two extra parameters come before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param string $id [optional] <p>The Shipment ID to set for the object.</p>
     * @param SimpleXMLElement $data [optional] <p>XML data from Amazon to be parsed.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $id = null, $data = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);

        if($id){
            $this->setShipmentId($id);
        }
        if ($data) {
            $this->parseXML($data);
        }
    }

    /**
     * Sets the Shipment ID. (Required)
     *
     * This method sets the shipment ID to be sent in the next request.
     * This parameter is required for fetching the shipment from Amazon.
     * @param string $id <p>either string or number</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setShipmentId($id){
        if (is_string($id) || is_numeric($id)){
            $this->options['ShipmentId'] = $id;
        } else {
            $this->log("Tried to set ShipmentId to invalid value",'Warning');
            return false;
        }
    }

    /**
     * Fetches the specified merchant-fulfilled shipment from Amazon.
     *
     * Submits a <i>GetShipment</i> request to Amazon. In order to do this,
     * a shipment ID is required. Amazon will send
     * the data back as a response, which can be retrieved using <i>getShipment</i>.
     * Other methods are available for fetching specific values from the shipment.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchShipment(){
        if (!array_key_exists('ShipmentId',$this->options)){
            $this->log("Shipment ID must be set in order to fetch it!",'Warning');
            return false;
        }

        $this->options['Action'] = 'GetShipment';

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

        $this->parseXML($xml->$path);
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXML($xml) {
        if (!$xml || !$xml->Shipment){
            return false;
        }
        $d = $xml->Shipment;
        $this->data['ShipmentId'] = (string)$d->ShipmentId;
        $this->data['AmazonOrderId'] = (string)$d->AmazonOrderId;
        if (isset($d->SellerOrderId)) {
            $this->data['SellerOrderId'] = (string)$d->SellerOrderId;
        }
        $this->data['Status'] = (string)$d->Status;
        if (isset($d->TrackingId)) {
            $this->data['TrackingId'] = (string)$d->TrackingId;
        }
        $this->data['CreatedDate'] = (string)$d->CreatedDate;
        if (isset($d->LastUpdatedDate)) {
            $this->data['LastUpdatedDate'] = (string)$d->LastUpdatedDate;
        }
        $this->data['Weight']['Value'] = (string)$d->Weight->Value;
        $this->data['Weight']['Unit'] = (string)$d->Weight->Unit;
        $this->data['Insurance']['Amount'] = (string)$d->Insurance->Amount;
        $this->data['Insurance']['CurrencyCode'] = (string)$d->Insurance->CurrencyCode;
        if (isset($d->Label)) {
            $this->data['Label']['Dimensions']['Length'] = (string)$d->Label->Dimensions->Length;
            $this->data['Label']['Dimensions']['Width'] = (string)$d->Label->Dimensions->Width;
            $this->data['Label']['Dimensions']['Unit'] = (string)$d->Label->Dimensions->Unit;
            $this->data['Label']['FileContents']['Contents'] = (string)$d->Label->FileContents->Contents;
            $this->data['Label']['FileContents']['FileType'] = (string)$d->Label->FileContents->FileType;
            $this->data['Label']['FileContents']['Checksum'] = (string)$d->Label->FileContents->Checksum;
        }

        $this->data['ItemList'] = array();
        foreach ($d->ItemList->children() as $x) {
            $temp = array();
            $temp['OrderItemId'] = (string)$x->OrderItemId;
            $temp['Quantity'] = (string)$x->Quantity;
            $this->data['ItemList'][] = $temp;
        }
        if (isset($d->PackageDimensions->Length)) {
            $this->data['PackageDimensions']['Length'] = (string)$d->PackageDimensions->Length;
            $this->data['PackageDimensions']['Width'] = (string)$d->PackageDimensions->Width;
            $this->data['PackageDimensions']['Height'] = (string)$d->PackageDimensions->Height;
            $this->data['PackageDimensions']['Unit'] = (string)$d->PackageDimensions->Unit;
        }
        if (isset($d->PackageDimensions->PredefinedPackageDimensions)) {
            $this->data['PackageDimensions']['PredefinedPackageDimensions'] = (string)$d->PackageDimensions->PredefinedPackageDimensions;
        }

        //Ship From Address
        $this->data['ShipFromAddress']['Name'] = (string)$d->ShipFromAddress->Name;
        $this->data['ShipFromAddress']['AddressLine1'] = (string)$d->ShipFromAddress->AddressLine1;
        if (isset($d->ShipFromAddress->AddressLine3)) {
            $this->data['ShipFromAddress']['AddressLine2'] = (string)$d->ShipFromAddress->AddressLine2;
        }
        if (isset($d->ShipFromAddress->AddressLine3)) {
            $this->data['ShipFromAddress']['AddressLine2'] = (string)$d->ShipFromAddress->AddressLine3;
        }
        if (isset($d->ShipFromAddress->DistrictOrCounty)) {
            $this->data['ShipFromAddress']['DistrictOrCounty'] = (string)$d->ShipFromAddress->DistrictOrCounty;
        }
        $this->data['ShipFromAddress']['Email'] = (string)$d->ShipFromAddress->Email;
        $this->data['ShipFromAddress']['City'] = (string)$d->ShipFromAddress->City;
        if (isset($d->ShipFromAddress->StateOrProvinceCode)) {
            $this->data['ShipFromAddress']['StateOrProvinceCode'] = (string)$d->ShipFromAddress->StateOrProvinceCode;
        }
        $this->data['ShipFromAddress']['PostalCode'] = (string)$d->ShipFromAddress->PostalCode;
        $this->data['ShipFromAddress']['CountryCode'] = (string)$d->ShipFromAddress->CountryCode;
        $this->data['ShipFromAddress']['Phone'] = (string)$d->ShipFromAddress->Phone;

        //Ship To Address
        $this->data['ShipToAddress']['Name'] = (string)$d->ShipToAddress->Name;
        $this->data['ShipToAddress']['AddressLine1'] = (string)$d->ShipToAddress->AddressLine1;
        if (isset($d->ShipToAddress->AddressLine3)) {
            $this->data['ShipToAddress']['AddressLine2'] = (string)$d->ShipToAddress->AddressLine2;
        }
        if (isset($d->ShipToAddress->AddressLine3)) {
            $this->data['ShipToAddress']['AddressLine2'] = (string)$d->ShipToAddress->AddressLine3;
        }
        if (isset($d->ShipToAddress->DistrictOrCounty)) {
            $this->data['ShipToAddress']['DistrictOrCounty'] = (string)$d->ShipToAddress->DistrictOrCounty;
        }
        $this->data['ShipToAddress']['Email'] = (string)$d->ShipToAddress->Email;
        $this->data['ShipToAddress']['City'] = (string)$d->ShipToAddress->City;
        if (isset($d->ShipToAddress->StateOrProvinceCode)) {
            $this->data['ShipToAddress']['StateOrProvinceCode'] = (string)$d->ShipToAddress->StateOrProvinceCode;
        }
        $this->data['ShipToAddress']['PostalCode'] = (string)$d->ShipToAddress->PostalCode;
        $this->data['ShipToAddress']['CountryCode'] = (string)$d->ShipToAddress->CountryCode;
        $this->data['ShipToAddress']['Phone'] = (string)$d->ShipToAddress->Phone;

        //Service
        $this->data['ShippingService']['ShippingServiceName'] = (string)$d->ShippingService->ShippingServiceName;
        $this->data['ShippingService']['CarrierName'] = (string)$d->ShippingService->CarrierName;
        $this->data['ShippingService']['ShippingServiceId'] = (string)$d->ShippingService->ShippingServiceId;
        $this->data['ShippingService']['ShippingServiceOfferId'] = (string)$d->ShippingService->ShippingServiceOfferId;
        $this->data['ShippingService']['ShipDate'] = (string)$d->ShippingService->ShipDate;
        if (isset($d->ShippingService->EarliestEstimatedDeliveryDate)) {
            $this->data['ShippingService']['EarliestEstimatedDeliveryDate'] = (string)$d->ShippingService->EarliestEstimatedDeliveryDate;
        }
        if (isset($d->ShippingService->LatestEstimatedDeliveryDate)) {
            $this->data['ShippingService']['LatestEstimatedDeliveryDate'] = (string)$d->ShippingService->LatestEstimatedDeliveryDate;
        }
        $this->data['ShippingService']['Rate']['Amount'] = (string)$d->ShippingService->Rate->Amount;
        $this->data['ShippingService']['Rate']['CurrencyCode'] = (string)$d->ShippingService->Rate->CurrencyCode;
        $this->data['ShippingService']['DeliveryExperience'] = (string)$d->ShippingService->ShippingServiceOptions->DeliveryExperience;
        $this->data['ShippingService']['CarrierWillPickUp'] = (string)$d->ShippingService->ShippingServiceOptions->CarrierWillPickUp;
        if (isset($d->ShippingService->ShippingServiceOptions->DeclaredValue)) {
            $this->data['ShippingService']['DeclaredValue']['Amount'] = (string)$d->ShippingService->ShippingServiceOptions->DeclaredValue->Amount;
            $this->data['ShippingService']['DeclaredValue']['CurrencyCode'] = (string)$d->ShippingService->ShippingServiceOptions->DeclaredValue->CurrencyCode;
        }
    }

    /**
     * Cancels a merchant-fulfilled shipment on Amazon.
     *
     * Submits a <i>CancelShipment</i> request to Amazon. In order to do this,
     * a shipment ID is required. Amazon will send back data about the shipment
     * as a response, including its status, which can be retrieved using <i>getData</i>.
     * Other methods are available for fetching specific values from the shipment.
     * @return boolean <b>TRUE</b> if the cancellation was successful, <b>FALSE</b> if something goes wrong
     */
    public function cancelShipment(){
        if (!array_key_exists('ShipmentId',$this->options)){
            $this->log("Shipment ID must be set in order to cancel it!",'Warning');
            return false;
        }

        $this->options['Action'] = 'CancelShipment';

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

        $this->parseXML($xml->$path);
    }

    /**
     * Returns the shipment data.
     *
     * This method will return <b>FALSE</b> if the shipment data has not been retrieved yet.
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if shipment not set yet
     */
    public function getData(){
        if (isset($this->data)){
            return $this->data;
        } else {
            return false;
        }
    }

    /**
     * Returns the Amazon-generated shipment ID.
     *
     * This method will return <b>FALSE</b> if the shipment ID has not been set yet.
     * @return string|boolean single value, or <b>FALSE</b> if shipment ID not set yet
     */
    public function getShipmentId(){
        if (isset($this->data['ShipmentId'])){
            return $this->data['ShipmentId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Amazon Order ID.
     *
     * This method will return <b>FALSE</b> if the order ID has not been set yet.
     * @return string|boolean single value, or <b>FALSE</b> if order ID not set yet
     */
    public function getAmazonOrderId(){
        if (isset($this->data['AmazonOrderId'])){
            return $this->data['AmazonOrderId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Seller's Order ID.
     *
     * This method will return <b>FALSE</b> if the order ID has not been set yet.
     * @return string|boolean single value, or <b>FALSE</b> if order ID not set yet
     */
    public function getSellerOrderId(){
        if (isset($this->data['SellerOrderId'])){
            return $this->data['SellerOrderId'];
        } else {
            return false;
        }
    }

    /**
     * Returns a list of Items for the Shipment.
     *
     * This method will return <b>FALSE</b> if the items have not been set yet.
     * The array returned contains one or more arrays with the following fields:
     * <ul>
     * <li><b>OrderItemId</b></li>
     * <li><b>Quantity</b></li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if items not set yet
     */
    public function getItems(){
        if (isset($this->data['ItemList'])){
            return $this->data['ItemList'];
        } else {
            return false;
        }
    }

    /**
     * Returns an array containing all of the shipper's address information.
     *
     * This method will return <b>FALSE</b> if the label has not been set yet.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>Name</b></li>
     * <li><b>AddressLine1</b></li>
     * <li><b>AddressLine2</b></li>
     * <li><b>AddressLine3</b></li>
     * <li><b>DistrictOrCounty</b></li>
     * <li><b>Email</b></li>
     * <li><b>City</b></li>
     * <li><b>StateOrProvinceCode</b></li>
     * <li><b>PostalCode</b></li>
     * <li><b>CountryCode</b></li>
     * <li><b>Phone</b></li>
     * </ul>
     * @return array|boolean associative array, or <b>FALSE</b> if label not set yet
     */
    public function getShipFromAddress(){
        if (isset($this->data['ShipFromAddress'])){
            return $this->data['ShipFromAddress'];
        } else {
            return false;
        }
    }

    /**
     * Returns an array containing all of the customer's address information.
     *
     * This method will return <b>FALSE</b> if the label has not been set yet.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>Name</b></li>
     * <li><b>AddressLine1</b></li>
     * <li><b>AddressLine2</b></li>
     * <li><b>AddressLine3</b></li>
     * <li><b>DistrictOrCounty</b></li>
     * <li><b>Email</b> (always blank)</li>
     * <li><b>City</b></li>
     * <li><b>StateOrProvinceCode</b></li>
     * <li><b>PostalCode</b></li>
     * <li><b>CountryCode</b></li>
     * <li><b>Phone</b> (always blank)</li>
     * </ul>
     * @return array|boolean associative array, or <b>FALSE</b> if label not set yet
     */
    public function getShipToAddress(){
        if (isset($this->data['ShipToAddress'])){
            return $this->data['ShipToAddress'];
        } else {
            return false;
        }
    }

    /**
     * Returns an array containing all of the Package Dimension information.
     *
     * This method will return <b>FALSE</b> if the package dimensions have not been set yet.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>Length</b></li>
     * <li><b>Width</b></li>
     * <li><b>Height</b></li>
     * <li><b>Unit</b></li>
     * <li><b>PredefinedPackageDimensions</b> (optional)</li>
     * </ul>
     * @return array|boolean associative array, or <b>FALSE</b> if package dimensions not set yet
     */
    public function getPackageDimensions(){
        if (isset($this->data['PackageDimensions'])){
            return $this->data['PackageDimensions'];
        } else {
            return false;
        }
    }

    /**
     * Returns the weight.
     *
     * This method will return <b>FALSE</b> if the weight has not been set yet.
     * If an array is returned, it will have the fields <b>Value</b> and <b>Unit</b>.
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the value</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if weight not set yet
     */
    public function getWeight($only = false){
        if (isset($this->data['Weight'])){
            if ($only){
                return $this->data['Weight']['Value'];
            } else {
                return $this->data['Weight'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the insurance amount.
     *
     * This method will return <b>FALSE</b> if the insurance amount has not been set yet.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the value</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if insurance amount not set yet
     */
    public function getInsurance($only = false){
        if (isset($this->data['Insurance'])){
            if ($only){
                return $this->data['Insurance']['Amount'];
            } else {
                return $this->data['Insurance'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns an array containing all of the Service information.
     *
     * This method will return <b>FALSE</b> if the package dimensions have not been set yet.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>ShippingServiceName</b></li>
     * <li><b>CarrierName</b></li>
     * <li><b>ShippingServiceId</b></li>
     * <li><b>ShippingServiceOfferId</b></li>
     * <li><b>ShipDate</b></li>
     * <li><b>EarliestEstimatedDeliveryDate</b> (optional)</li>
     * <li><b>LatestEstimatedDeliveryDate</b> (optional)</li>
     * <li><b>Rate</b> - contains <b>Value</b> and <b>CurrencyCode</b></li>
     * <li><b>DeliveryExperience</b></li>
     * <li><b>DeclaredValue</b> (optional) - contains <b>Value</b> and <b>CurrencyCode</b></li>
     * <li><b>CarrierWillPickUp</b></li>
     * </ul>
     * @return array|boolean associative array, or <b>FALSE</b> if package dimensions not set yet
     */
    public function getService(){
        if (isset($this->data['ShippingService'])){
            return $this->data['ShippingService'];
        } else {
            return false;
        }
    }

    /**
     * Returns the service rate.
     *
     * This method will return <b>FALSE</b> if the service has not been set yet.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the value</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if service not set yet
     * @see getService
     */
    public function getServiceRate($only = false){
        if (isset($this->data['ShippingService'])){
            if ($only){
                return $this->data['ShippingService']['Rate']['Amount'];
            } else {
                return $this->data['ShippingService']['Rate'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the declared value.
     *
     * This method will return <b>FALSE</b> if the declared value has not been set yet.
     * If an array is returned, it will have the fields <b>Amount</b> and <b>CurrencyCode</b>.
     * @param boolean $only [optional] <p>set to <b>TRUE</b> to get only the value</p>
     * @return array|string|boolean array, single value, or <b>FALSE</b> if declared value not set yet
     * @see getService
     */
    public function getDeclaredValue($only = false){
        if (isset($this->data['ShippingService']['DeclaredValue'])){
            if ($only){
                return $this->data['ShippingService']['DeclaredValue']['Amount'];
            } else {
                return $this->data['ShippingService']['DeclaredValue'];
            }
        } else {
            return false;
        }
    }

    /**
     * Returns an array containing all of the Label information.
     *
     * This method will return <b>FALSE</b> if the label has not been set yet.
     * The returned array will have the following fields:
     * <ul>
     * <li><b>Dimensions</b>:
     * <ul>
     * <li><b>Length</b></li>
     * <li><b>Width</b></li>
     * <li><b>Unit</b></li>
     * </ul>
     * </li>
     * <li><b>FileContents</b>:
     * <ul>
     * <li><b>Contents</b></li>
     * <li><b>FileType</b></li>
     * <li><b>Checksum</b></li>
     * </ul>
     * </li>
     * </ul>
     * @param boolean $raw [optional] <p>Set to TRUE to get the raw, double-encoded file contents.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if label not set yet
     */
    public function getLabelData($raw = FALSE){
        if (isset($this->data['Label'])){
            if ($raw) {
                return $this->data['Label'];
            }
            //decode label file automatically
            $r = $this->data['Label'];
            $convert = $this->getLabelFileContents(FALSE);
            if ($convert) {
                $r['FileContents']['Contents'] = $convert;
            }
            return $r;
        } else {
            return false;
        }
    }

    /**
     * Returns the file contents for the Label.
     *
     * This method will return <b>FALSE</b> if the status has not been set yet.
     * @param boolean $raw [optional] <p>Set to TRUE to get the raw, double-encoded file contents.</p>
     * @return string|boolean single value, or <b>FALSE</b> if status not set yet
     */
    public function getLabelFileContents($raw = FALSE){
        if (isset($this->data['Label']['FileContents']['Contents'])){
            if ($raw) {
                return $this->data['Label']['FileContents']['Contents'];
            }
            try {
                return gzdecode(base64_decode($this->data['Label']['FileContents']['Contents']));
            } catch (Exception $ex) {
                $this->log('Failed to convert label file, file might be corrupt: '.$ex->getMessage(), 'Urgent');
            }
            return false;
        } else {
            return false;
        }
    }

    /**
     * Returns the Shipment's Status.
     *
     * This method will return <b>FALSE</b> if the status has not been set yet.
     * @return string|boolean single value, or <b>FALSE</b> if status not set yet
     */
    public function getStatus(){
        if (isset($this->data['Status'])){
            return $this->data['Status'];
        } else {
            return false;
        }
    }

    /**
     * Returns the Tracking ID.
     *
     * This method will return <b>FALSE</b> if the tracking ID has not been set yet.
     * @return string|boolean single value, or <b>FALSE</b> if tracking ID not set yet
     */
    public function getTrackingId(){
        if (isset($this->data['TrackingId'])){
            return $this->data['TrackingId'];
        } else {
            return false;
        }
    }

    /**
     * Returns the date at which the shipment was created.
     *
     * This method will return <b>FALSE</b> if the tracking ID has not been set yet.
     * @return string|boolean timestamp, or <b>FALSE</b> if tracking ID not set yet
     */
    public function getDateCreated(){
        if (isset($this->data['CreatedDate'])){
            return $this->data['CreatedDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the date at which the shipment was last updated.
     *
     * This method will return <b>FALSE</b> if the tracking ID has not been set yet.
     * @return string|boolean timestamp, or <b>FALSE</b> if tracking ID not set yet
     */
    public function getDateLastUpdated(){
        if (isset($this->data['LastUpdatedDate'])){
            return $this->data['LastUpdatedDate'];
        } else {
            return false;
        }
    }
}
