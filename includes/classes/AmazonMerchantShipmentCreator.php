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
 * Creates a Merchant Fulfillment Shipment Amazon.
 *
 * This Amazon Merchant Core object can create a merchant-fulfilled shipment on Amazon.
 * In order to do this, detailed information about the shipment and its contents must be given.
 * This information can be used to find eligible services before creating the shipment.
 */
class AmazonMerchantShipmentCreator extends AmazonMerchantCore {
    protected $shipment;

    /**
     * AmazonMerchantShipmentCreator creates a merchant-fulfilled shipment from Amazon.
     *
     * The parameters are passed to the parent constructor, which are
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

        $this->options['Action'] = 'CreateShipment';
    }

    /**
     * Sets the Amazon Order ID. (Required)
     *
     * This method sets the Amazon Order ID to be sent in the next request.
     * This parameter is required for creating a shipment on Amazon.
     * @param string $id <p>Amazon Order ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setOrderId($id) {
        if (is_string($id)){
            $this->options['ShipmentRequestDetails.AmazonOrderId'] = $id;
        } else {
            $this->log("Tried to set AmazonOrderId to invalid value",'Warning');
            return false;
        }
    }

    /**
     * Sets the Seller Order ID. (Optional)
     *
     * This method sets the Seller Order ID to be sent in the next request.
     * @param string $id <p>Maximum 64 characters.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setSellerOrderId($id) {
        if (is_string($id) || is_numeric($id)){
            $this->options['ShipmentRequestDetails.SellerOrderId'] = $id;
        } else {
            $this->log("Tried to set SellerOrderId to invalid value",'Warning');
            return false;
        }
    }

    /**
     * Sets the items. (Required)
     *
     * This method sets the items to be sent in the next request.
     * This parameter is required for creating a shipment on Amazon.
     * The array provided should contain a list of arrays, each with the following fields:
     * <ul>
     * <li><b>OrderItemId</b> - identifier later used in the response</li>
     * <li><b>Quantity</b> - numeric</li>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setItems($a){
        if (is_null($a) || is_string($a) || !$a){
            $this->log("Tried to set Items to invalid values",'Warning');
            return false;
        }
        $this->resetItems();
        $i = 1;
        foreach ($a as $x){
            if (is_array($x) && isset($x['OrderItemId']) && isset($x['Quantity'])){
                $this->options['ShipmentRequestDetails.ItemList.Item.'.$i.'.OrderItemId'] = $x['OrderItemId'];
                $this->options['ShipmentRequestDetails.ItemList.Item.'.$i.'.Quantity'] = $x['Quantity'];
                $i++;
            } else {
                $this->resetItems();
                $this->log("Tried to set Items with invalid array",'Warning');
                return false;
            }
        }
    }

    /**
     * Resets the item options.
     *
     * Since the list of items is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetItems(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShipmentRequestDetails.ItemList#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the address. (Required)
     *
     * This method sets the shipper's address to be sent in the next request.
     * This parameter is required for creating a shipment on Amazon.
     * The array provided should have the following fields:
     * <ul>
     * <li><b>Name</b> - max: 30 char</li>
     * <li><b>AddressLine1</b> - max: 180 char</li>
     * <li><b>AddressLine2</b> (optional) - max: 60 char</li>
     * <li><b>AddressLine3</b> (optional) - max: 60 char</li>
     * <li><b>DistrictOrCounty</b> (optional) - max: 30 char</li>
     * <li><b>Email</b> - max: 256 char</li>
     * <li><b>City</b> - max: 30 char</li>
     * <li><b>StateOrProvinceCode</b> (optional) - max: 30 char</li>
     * <li><b>PostalCode</b> - max: 30 char</li>
     * <li><b>CountryCode</b> - 2 digits</li>
     * <li><b>Phone</b> - max: 20 char</li>
     * </ul>
     * @param array $a <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setAddress($a){
        if (empty($a) || !is_array($a)){
            $this->log("Tried to set ShipFromAddress to invalid values",'Warning');
            return false;
        }
        $this->resetAddress();
        $this->options['ShipmentRequestDetails.ShipFromAddress.Name'] = $a['Name'];
        $this->options['ShipmentRequestDetails.ShipFromAddress.AddressLine1'] = $a['AddressLine1'];
        if (isset($a['AddressLine2'])){
            $this->options['ShipmentRequestDetails.ShipFromAddress.AddressLine2'] = $a['AddressLine2'];
        }
        if (isset($a['AddressLine3'])){
            $this->options['ShipmentRequestDetails.ShipFromAddress.AddressLine3'] = $a['AddressLine3'];
        }
        if (isset($a['DistrictOrCounty'])){
            $this->options['ShipmentRequestDetails.ShipFromAddress.DistrictOrCounty'] = $a['DistrictOrCounty'];
        }
        $this->options['ShipmentRequestDetails.ShipFromAddress.Email'] = $a['Email'];
        $this->options['ShipmentRequestDetails.ShipFromAddress.City'] = $a['City'];
        if (isset($a['StateOrProvinceCode'])){
            $this->options['ShipmentRequestDetails.ShipFromAddress.StateOrProvinceCode'] = $a['StateOrProvinceCode'];
        }
        $this->options['ShipmentRequestDetails.ShipFromAddress.PostalCode'] = $a['PostalCode'];
        $this->options['ShipmentRequestDetails.ShipFromAddress.CountryCode'] = $a['CountryCode'];
        $this->options['ShipmentRequestDetails.ShipFromAddress.Phone'] = $a['Phone'];
    }

    /**
     * Resets the address options.
     *
     * Since address is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetAddress(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShipmentRequestDetails.ShipFromAddress#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the package dimensions. (Required)
     *
     * This method sets the package dimensions to be sent in the next request.
     * This parameter is required for creating a shipment on Amazon.
     * If this parameter is set, the predefined package name cannot be set.
     * The array provided should have the following fields:
     * <ul>
     * <li><b>Length</b> - positive decimal number</li>
     * <li><b>Width</b> - positive decimal number</li>
     * <li><b>Height</b> - positive decimal number</li>
     * <li><b>Unit</b> - "inches" or "centimeters"</li>
     * </ul>
     * @param array $d <p>See above.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setPackageDimensions($d) {
        if (empty($d) || !is_array($d)){
            $this->log("Tried to set PackageDimensions to invalid values",'Warning');
            return false;
        }
        $this->resetPackageDimensions();
        $this->options['ShipmentRequestDetails.PackageDimensions.Length'] = $d['Length'];
        $this->options['ShipmentRequestDetails.PackageDimensions.Width'] = $d['Width'];
        $this->options['ShipmentRequestDetails.PackageDimensions.Height'] = $d['Height'];
        $this->options['ShipmentRequestDetails.PackageDimensions.Unit'] = $d['Unit'];
    }

    /**
     * Sets the Predefined Package Dimensions. (Required)
     *
     * This method sets the predefined package name to be sent in the next request.
     * Package dimensions are required for creating a shipment on Amazon.
     * This parameter can be used instead of setting the package dimensions.
     * If this parameter is set, the manual package dimensions cannot be set.
     * @param string $s <p>A value from the list of valid package names.
     * See the comment inside the function for the complete list.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setPredefinedPackage($s) {
        $this->resetPackageDimensions();
        if (is_string($s) && $s){
            $this->options['ShipmentRequestDetails.PackageDimensions.PredefinedPackageDimensions'] = $s;
        } else {
            return false;
        }
        /*
         * List of valid Predefined Packages:
         * FedEx_Box_10kg ~ 15.81 x 12.94 x 10.19 in
         * FedEx_Box_25kg ~ 54.80 x 42.10 x 33.50 in
         * FedEx_Box_Extra_Large_1 ~ 11.88 x 11.00 x 10.75 in
         * FedEx_Box_Extra_Large_2 ~ 15.75 x 14.13 x 6.00 in
         * FedEx_Box_Large_1 ~ 	17.50 x 12.38 x 3.00 in
         * FedEx_Box_Large_2 ~ 11.25 x 8.75 x 7.75 in
         * FedEx_Box_Medium_1 ~ 13.25 x 11.50 x 2.38 in
         * FedEx_Box_Medium_2 ~ 11.25 x 8.75 x 4.38 in
         * FedEx_Box_Small_1 ~ 12.38 x 10.88 x 1.50 in
         * FedEx_Box_Small_2 ~ 11.25 x 8.75 x 4.38 in
         * FedEx_Envelope ~ 12.50 x 9.50 x 0.80 in
         * FedEx_Padded_Pak ~ 11.75 x 14.75 x 2.00 in
         * FedEx_Pak_1 ~ 15.50 x 12.00 x 0.80 in
         * FedEx_Pak_2 ~ 12.75 x 10.25 x 0.80 in
         * FedEx_Tube ~ 38.00 x 6.00 x 6.00 in
         * FedEx_XL_Pak ~ 17.50 x 20.75 x 2.00 in
         * UPS_Box_10kg ~ 41.00 x 33.50 x 26.50 cm
         * UPS_Box_25kg ~ 48.40 x 43.30 x 35.00 cm
         * UPS_Express_Box ~ 46.00 x 31.50 x 9.50 cm
         * UPS_Express_Box_Large ~ 18.00 x 13.00 x 3.00 in
         * UPS_Express_Box_Medium ~ 15.00 x 11.00 x 3.00 in
         * UPS_Express_Box_Small ~ 13.00 x 11.00 x 2.00 in
         * UPS_Express_Envelope ~ 12.50 x 9.50 x 2.00 in
         * UPS_Express_Hard_Pak ~ 14.75 x 11.50 x 2.00 in
         * UPS_Express_Legal_Envelope ~ 15.00 x 9.50 x 2.00 in
         * UPS_Express_Pak ~ 16.00 x 12.75 x 2.00 in
         * UPS_Express_Tube ~ 97.00 x 19.00 x 16.50 cm
         * UPS_Laboratory_Pak ~ 17.25 x 12.75 x 2.00 in
         * UPS_Pad_Pak ~ 14.75 x 11.00 x 2.00 in
         * UPS_Pallet ~ 120.00 x 80.00 x 200.00 cm
         * USPS_Card ~ 6.00 x 4.25 x 0.01 in
         * USPS_Flat ~ 15.00 x 12.00 x 0.75 in
         * USPS_FlatRateCardboardEnvelope ~ 12.50 x 9.50 x 4.00 in
         * USPS_FlatRateEnvelope ~ 12.50 x 9.50 x 4.00 in
         * USPS_FlatRateGiftCardEnvelope ~ 10.00 x 7.00 x 4.00 in
         * USPS_FlatRateLegalEnvelope ~ 15.00 x 9.50 x 4.00 in
         * USPS_FlatRatePaddedEnvelope ~ 12.50 x 9.50 x 4.00 in
         * USPS_FlatRateWindowEnvelope ~ 10.00 x 5.00 x 4.00 in
         * USPS_LargeFlatRateBoardGameBox ~ 24.06 x 11.88 x 3.13 in
         * USPS_LargeFlatRateBox ~ 12.25 x 12.25 x 6.00 in
         * USPS_Letter ~ 11.50 x 6.13 x 0.25 in
         * USPS_MediumFlatRateBox1 ~ 11.25 x 8.75 x 6.00 in
         * USPS_MediumFlatRateBox2 ~ 14.00 x 12.00 x 3.50 in
         * USPS_RegionalRateBoxA1 ~ 10.13 x 7.13 x 5.00 in
         * USPS_RegionalRateBoxA2 ~ 13.06 x 11.06 x 2.50 in
         * USPS_RegionalRateBoxB1 ~ 16.25 x 14.50 x 3.00 in
         * USPS_RegionalRateBoxB2 ~ 12.25 x 10.50 x 5.50 in
         * USPS_RegionalRateBoxC ~ 15.00 x 12.00 x 12.00 in
         * USPS_SmallFlatRateBox ~ 8.69 x 5.44 x 1.75 in
         * USPS_SmallFlatRateEnvelope ~ 10.00 x 6.00 x 4.00 in
         */
    }

    /**
     * Resets the package dimension options.
     *
     * Since dimensions are a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetPackageDimensions(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ShipmentRequestDetails.PackageDimensions#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the weight. (Required)
     *
     * This method sets the shipment weight to be sent in the next request.
     * @param string $v <p>Decimal number</p>
     * @param string $u <p>"oz" for ounces, or "g" for grams, defaults to grams</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setWeight($v, $u = 'g') {
        if (!empty($v) && !empty($u) && is_numeric($v) && ($u == 'oz' || $u == 'g')){
            $this->options['ShipmentRequestDetails.Weight.Value'] = $v;
            $this->options['ShipmentRequestDetails.Weight.Unit'] = $u;
        } else {
            return false;
        }
    }

    /**
     * Sets the date by which the package must arrive. (Optional)
     *
     * This method sets the maximum date to be sent in the next request.
     * If this parameter is set, Amazon will only give services which
     * will be able to deliver by the given date.
     * @param string $d <p>A time string</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setMaxArrivalDate($d) {
        try{
            $this->options['ShipmentRequestDetails.MustArriveByDate'] = $this->genTime($d);
        } catch (Exception $e){
            unset($this->options['ShipmentRequestDetails.MustArriveByDate']);
            $this->log('Error: '.$e->getMessage(), 'Warning');
            return false;
        }
    }

    /**
     * Sets the date on which the package will be shipped. (Optional)
     *
     * This method sets the ship date to be sent in the next request.
     * @param string $d <p>A time string</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setShipDate($d) {
        try{
            $this->options['ShipmentRequestDetails.ShipDate'] = $this->genTime($d);
        } catch (Exception $e){
            unset($this->options['ShipmentRequestDetails.ShipDate']);
            $this->log('Error: '.$e->getMessage(), 'Warning');
            return false;
        }
    }

    /**
     * Sets the Delivery Experience Option. (Required)
     *
     * This method sets the delivery experience shipping option to be sent in the next request.
     * This parameter is required for creating a shipment on Amazon.
     * @param string $s <p>"DeliveryConfirmationWithAdultSignature",
     *      "DeliveryConfirmationWithSignature",
     *      "DeliveryConfirmationWithoutSignature",
     *      or "NoTracking"</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setDeliveryOption($s) {
        $options = array(
            'DeliveryConfirmationWithAdultSignature',
            'DeliveryConfirmationWithSignature',
            'DeliveryConfirmationWithoutSignature',
            'NoTracking'
        );
        if (in_array($s, $options)){
            $this->options['ShipmentRequestDetails.ShippingServiceOptions.DeliveryExperience'] = $s;
        } else {
            $this->log("Tried to set DeliveryExperience to invalid value",'Warning');
            return false;
        }
    }

    /**
     * Sets the Declared Value. (Optional)
     *
     * This method sets the declared value to be sent in the next request.
     * If this parameter is set and is higher than the carrier's minimum insurance amount,
     * the seller will be charged more for the additional insurance.
     * @param string $v <p>Money amount</p>
     * @param string $c <p>ISO 4217 currency code (ex: USD)</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setDeclaredValue($v, $c) {
        if (!empty($v) && !empty($c) && is_numeric($v) && is_string($c) && !is_numeric($c)){
            $this->options['ShipmentRequestDetails.ShippingServiceOptions.DeclaredValue.Amount'] = $v;
            $this->options['ShipmentRequestDetails.ShippingServiceOptions.DeclaredValue.CurrencyCode'] = $c;
        } else {
            return false;
        }
    }

    /**
     * Sets the option for whether or not the carrier will pick up the package. (Required)
     *
     * This method sets whether or not the carrier will pick up the package to be sent in the next request.
     * This parameter is required for creating a shipment on Amazon.
     * @param boolean $b [optional] <p>Defaults to TRUE</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setCarrierWillPickUp($b = true) {
        if ($b) {
            $v = 'true';
        } else {
            $v = 'false';
        }
        $this->options['ShipmentRequestDetails.ShippingServiceOptions.CarrierWillPickUp'] = $v;
    }

    /**
     * Sets the Shipping Service ID. (Required)
     *
     * This method sets the shipping service ID to be sent in the next request.
     * This parameter is required for creating a shipment on Amazon.
     * @param string $id <p>Service ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setService($id) {
        if (is_string($id)){
            $this->options['ShippingServiceId'] = $id;
        } else {
            $this->log("Tried to set ShippingServiceId to invalid value",'Warning');
            return false;
        }
    }

    /**
     * Sets the Shipping Service Offer ID. (Optional)
     *
     * This method sets the shipping service offer ID to be sent in the next request.
     * @param string $id <p>Service Offer ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setServiceOffer($id) {
        if (is_string($id)){
            $this->options['ShippingServiceOfferId'] = $id;
        } else {
            $this->log("Tried to set ShippingServiceOfferId to invalid value",'Warning');
            return false;
        }
    }

    /**
     * Fetches eligible services for the shipment from Amazon using the current options.
     *
     * See the <i>AmazonMerchantServiceList</i> class for more information on the returned object.
     * The following parameters are required: Amazon order ID, item list, shipping address,
     * package dimensions, shipment weight, delivery experience option, and carrier pick-up option.
     * @return AmazonMerchantServiceList container for services
     */
    public function fetchServices() {
        $services = new AmazonMerchantServiceList($this->storeName, $this->mockMode, $this->mockFiles, $this->config);
        $services->mockIndex = $this->mockIndex;
        $services->setLogPath($this->logpath);
        $services->setDetailsByCreator($this);
        $services->fetchServices();
        return $services;
    }

    /**
     * Fetches a list of eligible shipping Services from Amazon.
     *
     * Submits a <i>CreateShipment</i> request to Amazon. Amazon will send back information
     * about the shipment as a response, which can be retrieved using <i>getShipment</i>.
     * Other methods are available for fetching specific values from the valid services list.
     * The following parameters are required: Amazon order ID, item list, shipping address,
     * package dimensions, shipment weight, delivery experience option, carrier pick-up option,
     * and service ID.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function createShipment(){
        if (!array_key_exists('ShipmentRequestDetails.AmazonOrderId',$this->options)){
            $this->log("Amazon Order ID must be set in order to create a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentRequestDetails.ItemList.Item.1.OrderItemId',$this->options)){
            $this->log("Items must be set in order to create a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentRequestDetails.ShipFromAddress.Name',$this->options)){
            $this->log("Shipping Address must be set in order to create a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentRequestDetails.PackageDimensions.Length',$this->options) &&
                !array_key_exists('ShipmentRequestDetails.PackageDimensions.PredefinedPackageDimensions',$this->options)){
            $this->log("Package Dimensions must be set in order to create a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentRequestDetails.Weight.Value',$this->options)){
            $this->log("Weight must be set in order to create a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentRequestDetails.ShippingServiceOptions.DeliveryExperience',$this->options)){
            $this->log("Delivery Experience must be set in order to create a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentRequestDetails.ShippingServiceOptions.CarrierWillPickUp',$this->options)){
            $this->log("Carrier Pick-Up Option must be set in order to create a shipment",'Warning');
            return false;
        }
        if (!array_key_exists('ShippingServiceId',$this->options)){
            $this->log("Shipping Service must be set in order to create a shipment",'Warning');
            return false;
        }

        $url = $this->urlbase.$this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));

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
        if (!$xml){
            return false;
        }

        $this->shipment = new AmazonMerchantShipment($this->storeName, NULL, $xml, $this->mockMode, $this->mockFiles, $this->config);
        $this->shipment->setLogPath($this->logpath);
        $this->shipment->mockIndex = $this->mockIndex;
    }

    /**
     * Returns the object containing information about the shipment.
     * @return AmazonMerchantShipment|boolean shipment object, or FALSE if the shipment has not been created yet
     */
    public function getShipment() {
        if (!isset($this->shipment)) {
            return false;
        }
        return $this->shipment;
    }
}
