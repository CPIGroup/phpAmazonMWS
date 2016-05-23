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
 * Fetches transport info for a fulfillment shipment or updates it.
 *
 * This Amazon Inbound Core object retrieves transportation information and
 * related documents for an inbound fulfillment shipment. It can also update
 * transport information and confirm or cancel the transport request. Documents
 * that it can fetch are stored in PDF format. In order to retrieve or send
 * any information, the ID of an inbound fulfillment shipment is needed.
 * In order to fetch labels, the paper type must be specified. In order to
 * update the transport information, additional details about the shipment
 * are required, such as shipment type. Use the AmazonShipment object to create
 * an inbound shipment and acquire a shipment ID.
 */
class AmazonTransport extends AmazonInboundCore {
    protected $status;

    /**
     * AmazonTransport gets or sends transport information about a shipment from Amazon.
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
     * Sets the parameter for whether or not the shipment is with an Amazon-partnered carrier. (Required for send)
     *
     * The other parameters that will be required will change depending on this setting.
     * This parameter is required for sending transport content information to Amazon.
     * This parameter is removed by all other actions.
     * @param boolean $b <p>Whether or not the shipment's carrier is partnered</p>
     */
    public function setIsPartnered($b) {
        if ($b) {
            $v = 'true';
        } else {
            $v = 'false';
        }
        $this->options['IsPartnered'] = $v;
    }

    /**
     * Sets the shipment type. (Required for send)
     *
     * The other parameters that will be required will change depending on this setting.
     * Use "SP" if the shipment is for small parcels and "LTL" when the shipment is for pallets in a truck.
     * This parameter is required for sending transport content information to Amazon.
     * This parameter is removed by all other actions.
     * @param string $s <p>"SP" or "LTL"</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setShipmentType($s) {
        $options = array(
            'SP',
            'LTL',
        );
        if (in_array($s, $options)){
            $this->options['ShipmentType'] = $s;
        } else {
            $this->log('Tried to set ShipmentType to invalid value', 'Warning');
            return false;
        }
    }

    /**
     * Determines which of the four possible transport detail parameter prefixes should be used.
     * The parameter to use depends on the partnered and shipment type parameters.
     * @return string|boolean parameter prefix or <b>FALSE</b> if it could not be determined
     */
    protected function determineDetailOption() {
        if (!isset($this->options['IsPartnered']) || !isset($this->options['ShipmentType'])) {
            $this->log('Cannot set transport details without shipment type and partner parameters!', 'Warning');
            return false;
        }
        $op = 'TransportDetails.';
        if ($this->options['ShipmentType'] == 'SP') {
            //CarrierName, PackageList
            if ($this->options['IsPartnered'] == 'true') {
                return $op . 'PartneredSmallParcelData';
            } else {
                return $op . 'NonPartneredSmallParcelData';
            }
        } else if ($this->options['ShipmentType'] == 'LTL') {
            if ($this->options['IsPartnered'] == 'true') {
                //Contact
                //BoxCount
                //SellerFreightClass
                //FreightReadyDate
                //PalletList
                //TotalWeight
                //SellerDeclaredValue
                return $op . 'PartneredLtlData';
            } else {
                //CarrierName
                //ProNumber
                return $op . 'NonPartneredLtlData';
            }
        }
        $this->log('Unknown shipment type, cannot set transport details!', 'Warning');
        return false;
    }

    /**
     * Sets the carrier name used for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is required for sending transport content information to Amazon when the
     * carrier is not partnered. This parameter is optional when the carrier is partnered and the
     * shipment type is set to "SP" for Small Parcel.
     * This parameter is removed by all other actions.
     * @param string $s <p>See the comment inside for a list of valid values.</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setCarrier($s){
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set carrier name because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_string($s) && $s){
            $this->options[$op.'.CarrierName'] = $s;
        } else {
            return false;
        }
        /*
         * Valid carrier names when shipment type is set to LTL:
         * BUSINESS_POST
         * DHL_AIRWAYS_INC
         * DHL_UK
         * PARCELFORCE
         * DPD
         * TNT_LOGISTICS_CORPORATION
         * TNT
         * YODEL
         * UNITED_PARCEL_SERVICE_INC
         * DHL_EXPRESS_USA_INC
         * FEDERAL_EXPRESS_CORP
         * UNITED_STATES_POSTAL_SERVICE
         * OTHER
         *
         * Valid carrier names when shipment type is set to SP:
         * UNITED_PARCEL_SERVICE_INC
         * DHL_STANDARD
         */
    }

    /**
     * Sets the list of packages. (Required for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is required for sending transport content information to Amazon when the
     * shipment type is set to "SP" for Small Parcel.
     * If the carrier is partnered with Amazon, each package array should have the following keys:
     * <ul>
     * <li><b>Length</b> - positive decimal number</li>
     * <li><b>Width</b> - positive decimal number</li>
     * <li><b>Height</b> - positive decimal number</li>
     * <li><b>Weight</b> - integer</li>
     * </ul>
     * If the carrier is not partnered with Amazon, each package array should have this instead:
     * <ul>
     * <li><b>TrackingId</b> - tracking number, maximum 30 characters</li>
     * </ul>
     * This parameter is removed by all other actions.
     * @param array $a <p>See above.</p>
     * @param string $du <p>Dimensions unit: "inches" or "centimeters", defaults to centimeters</p>
     * @param string $wu <p>Weight unit: "pounds" or "kilograms", defaults to kilograms</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setPackages($a, $du = 'centimeters', $wu = 'kilograms'){
        if (empty($a) || !is_array($a)) {
            $this->log("Tried to set package list to invalid values",'Warning');
            return false;
        }
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set packages because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        $this->resetPackages();
        $i = 1;
        foreach ($a as $x) {
            $prefix = $op.'PackageList.member.'.$i;
            if (is_array($x)) {
                if (isset($x['Length']) && isset($x['Width']) && isset($x['Height'])) {
                    $this->options[$prefix.'.Dimensions.Length'] = $x['Length'];
                    $this->options[$prefix.'.Dimensions.Width'] = $x['Width'];
                    $this->options[$prefix.'.Dimensions.Height'] = $x['Height'];
                    $this->options[$prefix.'.Dimensions.Unit'] = $du;
                }
                if (isset($x['Weight'])) {
                    $this->options[$prefix.'.Weight.Value'] = $x['Weight'];
                    $this->options[$prefix.'.Weight.Unit'] = $wu;
                }
                if (isset($x['TrackingId'])) {
                    $this->options[$prefix.'.TrackingId'] = $x['TrackingId'];
                }
                $i++;
            } else {
                $this->resetPackages();
                $this->log("Tried to set packages with invalid array",'Warning');
                return false;
            }
        }
    }

    /**
     * Resets the package list parameters.
     *
     * Since package details are required, these parameters should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetPackages() {
        foreach($this->options as $op=>$junk){
            if(preg_match("#PackageList#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the PRO number for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is required when the carrier is not partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $s <p>PRO number for the shipment given by the carrier</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setProNumber($s){
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set PRO number because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_string($s) && $s){
            $this->options[$op.'.ProNumber'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the contact information for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is required when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $n <p>Name of the contact person, maximum 50 characters</p>
     * @param string $p <p>Phone number of the contact person, maximum 20 characters</p>
     * @param string $e <p>E-mail address of the contact person, maximum 50 characters</p>
     * @param string $f <p>Fax number of the contact person, maximum 20 characters</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setContact($n, $p, $e, $f){
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set contact info because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if ($n && $p && $e && $f && is_string($n) && is_string($p) && is_string($e) && is_string($f)){
            $this->options[$op.'.Contact.Name'] = $n;
            $this->options[$op.'.Contact.Phone'] = $p;
            $this->options[$op.'.Contact.Email'] = $e;
            $this->options[$op.'.Contact.Fax'] = $f;
        } else {
            return false;
        }
    }

    /**
     * Sets the box count for the shipment. (Required for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is required when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param int $n <p>number of boxes</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setBoxCount($n){
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set PRO number because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_numeric($n) && $n){
            $this->options[$op.'.BoxCount'] = $n;
        } else {
            return false;
        }
    }

    /**
     * Sets the box count for the shipment. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * If this parameter is not sent, Amazon will estimate the freight class on their own.
     * This parameter is removed by all other actions.
     * @param int $n <p>See the comment inside for a list of valid values.</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setFreightClass($n){
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set freight class because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (is_numeric($n) && $n){
            $this->options[$op.'.SellerFreightClass'] = $n;
        } else {
            return false;
        }
        /*
         * Valid freight class values:
         * 50
         * 55
         * 60
         * 65
         * 70
         * 77.5
         * 85
         * 92.5
         * 100
         * 110
         * 125
         * 150
         * 175
         * 200
         * 250
         * 300
         * 400
         * 500
         */
    }

    /**
     * Sets the date that the shipment will be ready for pickup. (Required to send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is required when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $d <p>A time string</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setReadyDate($d) {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set ready date because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        try{
            $this->options[$op.'FreightReadyDate'] = strstr($this->genTime($d), 'T', true);
        } catch (Exception $e){
            unset($this->options[$op.'FreightReadyDate']);
            $this->log('Error: '.$e->getMessage(), 'Warning');
            return false;
        }
    }

    /**
     * Sets the list of packages. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * Each pallet array should have the following keys:
     * <ul>
     * <li><b>Length</b> - positive decimal number</li>
     * <li><b>Width</b> - positive decimal number</li>
     * <li><b>Height</b> - positive decimal number</li>
     * <li><b>IsStacked</b> - boolean</li>
     * <li><b>Weight</b> (optional) - integer</li>
     * </ul>
     * This parameter is removed by all other actions.
     * @param array $a <p>See above.</p>
     * @param string $du <p>Dimensions unit: "inches" or "centimeters", defaults to centimeters</p>
     * @param string $wu <p>Weight unit: "pounds" or "kilograms", defaults to kilograms</p>
     * @return boolean <b>FALSE</b> if improper input or needed parameters are not set
     */
    public function setPallets($a, $du = 'centimeters', $wu = 'kilograms'){
        if (empty($a) || !is_array($a)) {
            $this->log("Tried to set pallet list to invalid values",'Warning');
            return false;
        }
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set pallets because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        $this->resetPackages();
        $i = 1;
        foreach ($a as $x) {
            $prefix = $op.'PalletList.member.'.$i;
            if (is_array($x)) {
                if (isset($x['Length']) && isset($x['Width']) && isset($x['Height'])) {
                    $this->options[$prefix.'.Dimensions.Length'] = $x['Length'];
                    $this->options[$prefix.'.Dimensions.Width'] = $x['Width'];
                    $this->options[$prefix.'.Dimensions.Height'] = $x['Height'];
                    $this->options[$prefix.'.Dimensions.Unit'] = $du;
                }
                if (isset($x['Weight'])) {
                    $this->options[$prefix.'.Weight.Value'] = $x['Weight'];
                    $this->options[$prefix.'.Weight.Unit'] = $wu;
                }
                if (isset($x['IsStacked'])) {
                    if ($x['IsStacked']) {
                        $this->options[$prefix.'.IsStacked'] = 'true';
                    } else {
                        $this->options[$prefix.'.IsStacked'] = 'false';
                    }
                }
                $i++;
            } else {
                $this->resetPackages();
                $this->log("Tried to set packages with invalid array",'Warning');
                return false;
            }
        }
    }

    /**
     * Resets the pallet list parameters.
     *
     * Use this in case you change your mind and want to remove the pallet parameters you previously set.
     */
    public function resetPallets() {
        foreach($this->options as $op=>$junk){
            if(preg_match("#PalletList#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the total weight for the shipment. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $v <p>Decimal number</p>
     * @param string $u <p>"oz" for ounces, or "g" for grams, defaults to grams</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setTotalWeight($v, $u = 'g') {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set total weight because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (!empty($v) && !empty($u) && is_numeric($v) && ($u == 'oz' || $u == 'g')){
            $this->options[$op.'TotalWeight.Value'] = $v;
            $this->options[$op.'TotalWeight.Unit'] = $u;
        } else {
            return false;
        }
    }

    /**
     * Sets the declared value for the shipment. (Optional for send*)
     *
     * The partnered and shipment type parameters must be set <i>before</i> setting this parameter.
     * This parameter is optional when the carrier is partnered and the
     * shipment type is set to "LTL" for Less Than Truckload/Full Truckload.
     * This parameter is removed by all other actions.
     * @param string $v <p>Money amount</p>
     * @param string $c <p>ISO 4217 currency code (ex: USD)</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setDeclaredValue($v, $c) {
        $op = $this->determineDetailOption();
        if (!$op) {
            $this->log('Cannot set total weight because of the shipment type and partnered parameters.', 'Warning');
            return false;
        }
        if (!empty($v) && !empty($c) && is_numeric($v) && is_string($c) && !is_numeric($c)){
            $this->options['SellerDeclaredValue.Amount'] = $v;
            $this->options['SellerDeclaredValue.CurrencyCode'] = $c;
        } else {
            return false;
        }
    }

    /**
     * Resets the transport detail parameters.
     *
     * Since transport details are required, these parameters should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetTransportDetails() {
        foreach($this->options as $op=>$junk){
            if(preg_match("#TransportDetails#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sends transport content information for a shipment with Amazon.
     *
     * Submits a <i>PutTransportContent</i> request to Amazon. In order to do this,
     * a fulfillment shipment ID, shipment type, partnered carrier flag, and
     * various details are required. The exact details required depend on the partnered
     * flag and shipment type parameters set.
     * Amazon will send a status back as a response, which can be retrieved
     * using <i>getStatus</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function sendTransportContents() {
        if (!$this->verifySendParams()) {
            return false;
        }

        $this->prepareSend();

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
     * Sets up options for using <i>sendTransportContents</i>.
     *
     * This changes key options for using <i>GetPreorderInfo</i>.
     * Please note: because the operation does not use all of the parameters,
     * some of the parameters will be removed. The following parameters are removed:
     * PageType, NumberOfPackages, PackageLabelsToPrint, and NumberOfPallets.
     */
    protected function prepareSend() {
        $this->throttleGroup = 'PutTransportContent';
        $this->options['Action'] = 'PutTransportContent';
        unset($this->options['PageType']);
        unset($this->options['NumberOfPackages']);
        unset($this->options['PackageLabelsToPrint']);
        unset($this->options['NumberOfPallets']);
    }

    /**
     * Checks to see if all of the parameters needed for <i>sendTransportContents</i> are set.
     * @return boolean <b>TRUE</b> if everything is good, <b>FALSE</b> if something is missing
     */
    protected function verifySendParams() {
        $m = ' must be set in order to send transport content!';
        //common requirements
        if (!array_key_exists('ShipmentId', $this->options)) {
            $this->log('ShipmentId'.$m, 'Warning');
            return false;
        }
        if (!array_key_exists('IsPartnered', $this->options)) {
            $this->log('IsPartnered'.$m, 'Warning');
            return false;
        }
        if (!array_key_exists('ShipmentType', $this->options)) {
            $this->log('ShipmentType'.$m, 'Warning');
            return false;
        }
        //requirements based on partnership and type
        $p = $this->options['IsPartnered'] == 'true';
        $sp = $this->options['ShipmentType'] == 'SP';
        $ltl = $this->options['ShipmentType'] == 'LTL';
        //options could be in four possible places, so a search is needed
        $foundCarrier = false;
        $foundPackages = false;
        $foundPro = false;
        $foundContact = false;
        $foundBoxCount = false;
        $foundReady = false;
        foreach ($this->options as $op=>$junk) {
            if(preg_match("#CarrierName#",$op)){
                $foundCarrier = true;
            }
            if(preg_match("#PackageList\.member\.1#",$op)){
                $foundPackages = true;
            }
            if(preg_match("#ProNumber#",$op)){
                $foundPro = true;
            }
            if(preg_match("#Contact\.Name#",$op)){
                $foundContact = true;
            }
            if(preg_match("#BoxCount#",$op)){
                $foundBoxCount = true;
            }
            if(preg_match("#FreightReadyDate#",$op)){
                $foundReady = true;
            }
        }
        if (!$p && !$foundCarrier) {
            $this->log('CarrierName'.$m, 'Warning');
            return false;
        }
        if ($sp && !$foundPackages) {
            $this->log('PackageList'.$m, 'Warning');
            return false;
        }
        if (!$p && $ltl && !$foundPro) {
            $this->log('CarrierName'.$m, 'Warning');
            return false;
        }
        if ($p && $ltl && !$foundContact) {
            $this->log('Contact'.$m, 'Warning');
            return false;
        }
        if ($p && $ltl && !$foundBoxCount) {
            $this->log('BoxCount'.$m, 'Warning');
            return false;
        }
        if ($p && $ltl && !$foundReady) {
            $this->log('FreightReadyDate'.$m, 'Warning');
            return false;
        }

        //all good
        return true;
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLObject $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXml($xml) {
        if (!$xml){
            return false;
        }

        //response from send, confirm, estimate, void
        if (isset($xml->TransportResult->TransportStatus)) {
            $this->status = $xml->TransportResult->TransportStatus;
        }
    }

    /**
     * Returns the transport status.
     *
     * Possible values for the status:
     * "WORKING","ERROR_ON_ESTIMATING","ESTIMATING","ESTIMATED","ERROR_ON_CONFIRMING",
     * "CONFIRMING","CONFIRMED","VOIDING","VOIDED", and "ERROR_IN_VOIDING".
     * This method will return <b>FALSE</b> if the status has not been set yet.
     * @return string|boolean status value, or <b>FALSE</b> if date not set yet
     */
    public function getStatus(){
        if (isset($this->status)){
            return $this->status;
        } else {
            return false;
        }
    }

}

