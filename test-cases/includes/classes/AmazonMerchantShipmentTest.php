<?php

class AmazonMerchantShipmentTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonMerchantShipmentCreator
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonMerchantShipmentCreator('testStore', true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetOrderId(){
        $key = 'ShipmentRequestDetails.AmazonOrderId';
        $this->assertNull($this->object->setOrderId('777'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o);
        $this->assertEquals('777', $o[$key]);

        $this->assertFalse($this->object->setOrderId(77)); //won't work for this
        $this->assertFalse($this->object->setOrderId(array())); //won't work for other things
        $this->assertFalse($this->object->setOrderId(null)); //won't work for other things

        $check = parseLog();
        $this->assertEquals('Tried to set AmazonOrderId to invalid value',$check[1]);
        $this->assertEquals('Tried to set AmazonOrderId to invalid value',$check[2]);
        $this->assertEquals('Tried to set AmazonOrderId to invalid value',$check[3]);
    }

    public function testSetSellerOrderId(){
        $key = 'ShipmentRequestDetails.SellerOrderId';
        $this->assertNull($this->object->setSellerOrderId('777'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o);
        $this->assertEquals('777', $o[$key]);
        $this->assertNull($this->object->setSellerOrderId(77));
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o2);
        $this->assertEquals(77, $o2[$key]);

        $this->assertFalse($this->object->setSellerOrderId(array())); //won't work for this
        $this->assertFalse($this->object->setSellerOrderId(null)); //won't work for other things

        $check = parseLog();
        $this->assertEquals('Tried to set SellerOrderId to invalid value',$check[1]);
        $this->assertEquals('Tried to set SellerOrderId to invalid value',$check[2]);
    }

    public function testSetItems(){
        $key = 'ShipmentRequestDetails.ItemList.Item.';
        $items = array(
            array('OrderItemId' => '123987', 'Quantity' => 2),
            array('OrderItemId' => '555432', 'Quantity' => 1),
        );
        $this->assertNull($this->object->setItems($items));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key.'1.OrderItemId', $o);
        $this->assertArrayHasKey($key.'1.Quantity', $o);
        $this->assertArrayHasKey($key.'2.OrderItemId', $o);
        $this->assertArrayHasKey($key.'2.Quantity', $o);
        $this->assertEquals($items[0]['OrderItemId'], $o[$key.'1.OrderItemId']);
        $this->assertEquals($items[0]['Quantity'], $o[$key.'1.Quantity']);
        $this->assertEquals($items[1]['OrderItemId'], $o[$key.'2.OrderItemId']);
        $this->assertEquals($items[1]['Quantity'], $o[$key.'2.Quantity']);

        //remove one item and do it again
        unset($items[0]);
        $this->assertNull($this->object->setItems($items));
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey($key.'1.OrderItemId', $o2);
        $this->assertArrayHasKey($key.'1.Quantity', $o2);
        $this->assertArrayNotHasKey($key.'2.OrderItemId', $o2);
        $this->assertArrayNotHasKey($key.'2.Quantity', $o2);
        $this->assertEquals($items[1]['OrderItemId'], $o2[$key.'1.OrderItemId']);
        $this->assertEquals($items[1]['Quantity'], $o2[$key.'1.Quantity']);

        $this->assertFalse($this->object->setItems(array())); //won't work for this
        $this->assertFalse($this->object->setItems('something')); //won't work for other things
        $this->assertFalse($this->object->setItems(null)); //won't work for other things

        $check = parseLog();
        $this->assertEquals('Tried to set Items to invalid values',$check[1]);
        $this->assertEquals('Tried to set Items to invalid values',$check[2]);
        $this->assertEquals('Tried to set Items to invalid values',$check[3]);
    }

    public function testSetAddress(){
        $key = 'ShipmentRequestDetails.ShipFromAddress.';
        $address = $this->genAddress();
        $address['AddressLine2'] = 'line 2';
        $address['AddressLine3'] = 'line 3';
        $address['DistrictOrCounty'] = 'North';
        $this->assertNull($this->object->setAddress($address));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key.'Name', $o);
        $this->assertArrayHasKey($key.'AddressLine1', $o);
        $this->assertArrayHasKey($key.'AddressLine2', $o);
        $this->assertArrayHasKey($key.'AddressLine3', $o);
        $this->assertArrayHasKey($key.'DistrictOrCounty', $o);
        $this->assertArrayHasKey($key.'Email', $o);
        $this->assertArrayHasKey($key.'City', $o);
        $this->assertArrayHasKey($key.'StateOrProvinceCode', $o);
        $this->assertArrayHasKey($key.'PostalCode', $o);
        $this->assertArrayHasKey($key.'CountryCode', $o);
        $this->assertArrayHasKey($key.'Phone', $o);
        $this->assertEquals($address['Name'], $o[$key.'Name']);
        $this->assertEquals($address['AddressLine1'], $o[$key.'AddressLine1']);
        $this->assertEquals($address['AddressLine2'], $o[$key.'AddressLine2']);
        $this->assertEquals($address['AddressLine3'], $o[$key.'AddressLine3']);
        $this->assertEquals($address['DistrictOrCounty'], $o[$key.'DistrictOrCounty']);
        $this->assertEquals($address['Email'], $o[$key.'Email']);
        $this->assertEquals($address['City'], $o[$key.'City']);
        $this->assertEquals($address['StateOrProvinceCode'], $o[$key.'StateOrProvinceCode']);
        $this->assertEquals($address['PostalCode'], $o[$key.'PostalCode']);
        $this->assertEquals($address['CountryCode'], $o[$key.'CountryCode']);
        $this->assertEquals($address['Phone'], $o[$key.'Phone']);

        $this->assertFalse($this->object->setAddress(array())); //won't work for this
        $this->assertFalse($this->object->setAddress('something')); //won't work for other things
        $this->assertFalse($this->object->setAddress(null)); //won't work for other things

        $check = parseLog();
        $this->assertEquals('Tried to set ShipFromAddress to invalid values',$check[1]);
        $this->assertEquals('Tried to set ShipFromAddress to invalid values',$check[2]);
        $this->assertEquals('Tried to set ShipFromAddress to invalid values',$check[3]);
    }

    public function testSetPackageDimensions(){
        $key = 'ShipmentRequestDetails.PackageDimensions.';
        $dims = array('Length' => 5, 'Width' => 5, 'Height' => 5, 'Unit' => 'inches');
        $this->assertNull($this->object->setPredefinedPackage('something'));
        $this->assertNull($this->object->setPackageDimensions($dims));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key.'Length', $o);
        $this->assertArrayHasKey($key.'Width', $o);
        $this->assertArrayHasKey($key.'Height', $o);
        $this->assertArrayHasKey($key.'Unit', $o);
        $this->assertArrayNotHasKey($key.'PredefinedPackageDimensions', $o);
        $this->assertEquals($dims['Length'], $o[$key.'Length']);
        $this->assertEquals($dims['Width'], $o[$key.'Width']);
        $this->assertEquals($dims['Height'], $o[$key.'Height']);
        $this->assertEquals($dims['Unit'], $o[$key.'Unit']);

        $this->assertFalse($this->object->setPackageDimensions(array())); //won't work for this
        $this->assertFalse($this->object->setPackageDimensions('something')); //won't work for other things
        $this->assertFalse($this->object->setPackageDimensions(null)); //won't work for other things

        $check = parseLog();
        $this->assertEquals('Tried to set PackageDimensions to invalid values',$check[1]);
        $this->assertEquals('Tried to set PackageDimensions to invalid values',$check[2]);
        $this->assertEquals('Tried to set PackageDimensions to invalid values',$check[3]);
    }

    public function testSetPredefinedPackage(){
        $key = 'ShipmentRequestDetails.PackageDimensions.';
        $dims = array('Length' => 5, 'Width' => 5, 'Height' => 5, 'Unit' => 'inches');
        $this->assertNull($this->object->setPackageDimensions($dims));
        $this->assertNull($this->object->setPredefinedPackage('something'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key.'PredefinedPackageDimensions', $o);
        $this->assertArrayNotHasKey($key.'Length', $o);
        $this->assertArrayNotHasKey($key.'Width', $o);
        $this->assertArrayNotHasKey($key.'Height', $o);
        $this->assertArrayNotHasKey($key.'Unit', $o);
        $this->assertEquals('something', $o[$key.'PredefinedPackageDimensions']);

        $this->assertFalse($this->object->setPredefinedPackage(77)); //won't work for this
        $this->assertFalse($this->object->setPredefinedPackage(array())); //won't work for other things
        $this->assertFalse($this->object->setPredefinedPackage(null)); //won't work for other things
    }

    public function testSetWeight() {
        $key = 'ShipmentRequestDetails.Weight.';
        $this->assertNull($this->object->setWeight('777'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key.'Value', $o);
        $this->assertArrayHasKey($key.'Unit', $o);
        $this->assertEquals('777', $o[$key.'Value']);
        $this->assertEquals('g', $o[$key.'Unit']);
        $this->assertNull($this->object->setWeight(77, 'oz'));
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey($key.'Value', $o2);
        $this->assertArrayHasKey($key.'Unit', $o2);
        $this->assertEquals(77, $o2[$key.'Value']);
        $this->assertEquals('oz', $o2[$key.'Unit']);

        $this->assertFalse($this->object->setWeight('word')); //won't work for this
        $this->assertFalse($this->object->setWeight(array())); //won't work for other things
        $this->assertFalse($this->object->setWeight(null)); //won't work for other things
    }

    public function testSetMaxArrivalDate(){
        $key = 'ShipmentRequestDetails.MustArriveByDate';
        $this->assertNull($this->object->setMaxArrivalDate('+50 min'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o);
        $this->assertNotEmpty($o[$key]);

        $this->assertFalse($this->object->setMaxArrivalDate(array(5))); //won't work for this

        $check = parseLog();
        $this->assertEquals('Error: Invalid time input given',$check[1]);
    }

    public function testSetShipDate(){
        $key = 'ShipmentRequestDetails.ShipDate';
        $this->assertNull($this->object->setShipDate('+50 min'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o);
        $this->assertNotEmpty($o[$key]);

        $this->assertFalse($this->object->setShipDate(array(5))); //won't work for this

        $check = parseLog();
        $this->assertEquals('Error: Invalid time input given',$check[1]);
    }

    public function testSetDeliveryOption(){
        $key = 'ShipmentRequestDetails.ShippingServiceOptions.DeliveryExperience';
        $this->assertNull($this->object->setDeliveryOption('NoTracking'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o);
        $this->assertEquals('NoTracking', $o[$key]);

        $this->assertFalse($this->object->setDeliveryOption('something')); //won't work for this
        $this->assertFalse($this->object->setDeliveryOption(array())); //won't work for other things
        $this->assertFalse($this->object->setDeliveryOption(null)); //won't work for other things

        $check = parseLog();
        $this->assertEquals('Tried to set DeliveryExperience to invalid value',$check[1]);
        $this->assertEquals('Tried to set DeliveryExperience to invalid value',$check[2]);
        $this->assertEquals('Tried to set DeliveryExperience to invalid value',$check[3]);
    }

    public function testSetDeclaredValue() {
        $key = 'ShipmentRequestDetails.ShippingServiceOptions.DeclaredValue.';
        $this->assertNull($this->object->setDeclaredValue('777', 'USD'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key.'Amount', $o);
        $this->assertArrayHasKey($key.'CurrencyCode', $o);
        $this->assertEquals('777', $o[$key.'Amount']);
        $this->assertEquals('USD', $o[$key.'CurrencyCode']);

        $this->assertFalse($this->object->setDeclaredValue('word', 'USD')); //won't work for this
        $this->assertFalse($this->object->setDeclaredValue('777', '77')); //won't work for this
        $this->assertFalse($this->object->setDeclaredValue('777', array())); //won't work for this
        $this->assertFalse($this->object->setDeclaredValue(array(), 'USD')); //won't work for this
        $this->assertFalse($this->object->setDeclaredValue('777', NULL)); //won't work for this
        $this->assertFalse($this->object->setDeclaredValue(NULL, 'USD')); //won't work for this
    }

    public function testSetCarrierWillPickUp() {
        $key = 'ShipmentRequestDetails.ShippingServiceOptions.CarrierWillPickUp';
        $this->assertNull($this->object->setCarrierWillPickUp());
        $o = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o);
        $this->assertEquals('true', $o[$key]);
        $this->assertNull($this->object->setCarrierWillPickUp(false));
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o2);
        $this->assertEquals('false', $o2[$key]);
        $this->assertNull($this->object->setCarrierWillPickUp(1));
        $o3 = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o3);
        $this->assertEquals('true', $o3[$key]);
        $this->assertNull($this->object->setCarrierWillPickUp(0));
        $o4 = $this->object->getOptions();
        $this->assertArrayHasKey($key, $o4);
        $this->assertEquals('false', $o4[$key]);
    }


    public function testCreateShipment(){
        resetLog();
        $this->object->setMock(true,'createMerchantShipment.xml');
        $this->assertFalse($this->object->getShipment()); //no data yet
        $this->assertFalse($this->object->createShipment()); //no order ID yet
        $this->object->setOrderId('903-1713775-3598252');
        $this->assertFalse($this->object->createShipment()); //no items yet
        $this->object->setItems(array(
            array('OrderItemId' => '40525960574974', 'Quantity' => 1)
        ));
        $this->assertFalse($this->object->createShipment()); //no address yet
        $this->object->setAddress($this->genAddress());
        $this->assertFalse($this->object->createShipment()); //no dimensions yet
        $this->object->setPackageDimensions(array('Length' => 5, 'Width' => 5, 'Height' => 5, 'Unit' => 'inches'));
        $this->assertFalse($this->object->createShipment()); //no weight yet
        $this->object->setWeight(10, 'oz');
        $this->assertFalse($this->object->createShipment()); //no delivery option yet
        $this->object->setDeliveryOption('DeliveryConfirmationWithoutSignature');
        $this->assertFalse($this->object->createShipment()); //no pickup option yet
        $this->object->setCarrierWillPickUp();
        $this->assertFalse($this->object->createShipment()); //no service yet
        $this->object->setService('UPS_PTP_GND');
        $this->assertFalse($this->object->getShipment()); //still no data yet
        $this->assertNull($this->object->createShipment()); //now it is good

        $check = parseLog();
        $this->assertEquals('Single Mock File set: createMerchantShipment.xml', $check[1]);

        $ship = $this->object->getShipment();
        $this->assertInternalType('object', $ship);
        $this->assertRegexp('/AmazonMerchantShipment$/', get_class($ship));

        return $ship;
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetShipmentId($o) {
        $this->assertEquals('903-1713775-3598252', $o->getAmazonOrderId());

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getAmazonOrderId()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetItems($o) {
        $get = $o->getItems();
        $this->assertInternalType('array', $get);
        $this->assertCount(1, $get);
        $this->assertArrayHasKey(0, $get);
        $this->assertArrayHasKey('OrderItemId', $get[0]);
        $this->assertArrayHasKey('Quantity', $get[0]);
        $this->assertEquals('40525960574974', $get[0]['OrderItemId']);
        $this->assertEquals(1, $get[0]['Quantity']);

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getItems()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetShipFromAddress($o) {
        $get = $o->getShipFromAddress();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Name'] = 'John Doe';
        $x['AddressLine1'] = '1234 Westlake Ave';
        $x['Email'] = '';
        $x['City'] = 'Seattle';
        $x['StateOrProvinceCode'] = 'WA';
        $x['PostalCode'] = '98121';
        $x['CountryCode'] = 'US';
        $x['Phone'] = '2061234567';
        $this->assertEquals($x, $get);

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getShipFromAddress()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetShipToAddress($o) {
        $get = $o->getShipToAddress();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Name'] = 'Jane Smith';
        $x['AddressLine1'] = '321 Main St';
        $x['Email'] = '';
        $x['City'] = 'Seattle';
        $x['StateOrProvinceCode'] = 'WA';
        $x['PostalCode'] = '98121-2778';
        $x['CountryCode'] = 'US';
        $x['Phone'] = '';
        $this->assertEquals($x, $get);

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getShipToAddress()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetPackageDimensions($o) {
        $get = $o->getPackageDimensions();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Length'] = '5';
        $x['Width'] = '5';
        $x['Height'] = '5';
        $x['Unit'] = 'inches';
        $this->assertEquals($x, $get);

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getPackageDimensions()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetWeight($o) {
        $get = $o->getWeight();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Value'] = '10';
        $x['Unit'] = 'oz';
        $this->assertEquals($x, $get);
        $this->assertEquals($x['Value'], $o->getWeight(true));

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getWeight()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetInsurance($o) {
        $get = $o->getInsurance();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Amount'] = '10.00';
        $x['CurrencyCode'] = 'USD';
        $this->assertEquals($x, $get);
        $this->assertEquals($x['Amount'], $o->getInsurance(true));

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getInsurance()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetService($o) {
        $get = $o->getService();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['ShippingServiceName'] = 'FedEx Priority Overnight®';
        $x['CarrierName'] = 'FEDEX';
        $x['ShippingServiceId'] = 'FEDEX_PTP_PRIORITY_OVERNIGHT';
        $x['ShippingServiceOfferId'] = 'HDDUKqtQVFetpNRMgVERYLONGefNLP8t5RyLXa4ZOjc=';
        $x['ShipDate'] = '2015-09-23T20:10:56.829Z';
        $x['EarliestEstimatedDeliveryDate'] = '2015-09-24T10:30:00Z';
        $x['LatestEstimatedDeliveryDate'] = '2015-09-24T10:30:00Z';
        $x['Rate'] = array();
        $x['Rate']['Amount'] = '27.81';
        $x['Rate']['CurrencyCode'] = 'USD';
        $x['DeliveryExperience'] = 'DELIVERY_CONFIRMATION';
        $x['CarrierWillPickUp'] = 'false';
        $x['DeclaredValue'] = array();
        $x['DeclaredValue']['Amount'] = '10.00';
        $x['DeclaredValue']['CurrencyCode'] = 'USD';
        $this->assertEquals($x, $get);

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getService()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetServiceRate($o) {
        $get = $o->getServiceRate();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Amount'] = '27.81';
        $x['CurrencyCode'] = 'USD';
        $this->assertEquals($x, $get);
        $this->assertEquals($x['Amount'], $o->getServiceRate(true));

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getServiceRate()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetDeclaredValue($o) {
        $get = $o->getDeclaredValue();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Amount'] = '10.00';
        $x['CurrencyCode'] = 'USD';
        $this->assertEquals($x, $get);
        $this->assertEquals($x['Amount'], $o->getDeclaredValue(true));

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getDeclaredValue()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetLabelData($o) {
        $get = $o->getLabelData();
        $this->assertInternalType('array', $get);
        $x = array();
        $x['Dimensions'] = array();
        $x['Dimensions']['Length'] = '11.00000';
        $x['Dimensions']['Width'] = '8.50000';
        $x['Dimensions']['Unit'] = 'inches';
        $x['FileContents'] = array();
        $x['FileContents']['Contents'] = 'This is a test';
        $x['FileContents']['FileType'] = 'application/pdf';
        $x['FileContents']['Checksum'] = 'DmsWbJpdMPALN3jV4wHOrg==';
        $this->assertEquals($x, $get);
        $this->assertEquals($x['FileContents']['Contents'], $o->getLabelFileContents());

        //try with raw file
        $x['FileContents']['Contents'] = 'H4sIAAAAAAAAAwvJyCxWAKJEhZLU4hIAMp96wA4AAAA=';
        $get2 = $o->getLabelData(TRUE);
        $this->assertInternalType('array', $get2);
        $this->assertEquals($x, $get2);
        $this->assertEquals($x['FileContents']['Contents'], $o->getLabelFileContents(TRUE));

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getLabelData()); //not fetched yet for this object
        $this->assertFalse($new->getLabelData(TRUE)); //not fetched yet for this object
        $this->assertFalse($new->getLabelFileContents()); //not fetched yet for this object
        $this->assertFalse($new->getLabelFileContents(TRUE)); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetStatus($o) {
        $this->assertEquals('Purchased', $o->getStatus());

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getStatus()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetTrackingId($o) {
        $this->assertEquals('794657111237', $o->getTrackingId());

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getTrackingId()); //not fetched yet for this object
    }

    /**
     * @depends testCreateShipment
     * @param AmazonMerchantShipment $o
     */
    public function testGetDateCreated($o) {
        $this->assertEquals('2015-09-23T20:11:12.908Z', $o->getDateCreated());

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getDateCreated()); //not fetched yet for this object
    }

    /**
     * Creates a basic adress with the minimum amount of information.
     * @return array
     */
    private function genAddress() {
        return array(
            'Name' => 'Jane Smith',
            'AddressLine1' => '321 Main St',
            'City' => 'Seattle',
            'StateOrProvinceCode' => 'WA',
            'PostalCode' => '98121-2778',
            'CountryCode' => 'US',
            'Phone' => '5551237777',
            'Email' => 'test@test.com',
        );
    }

    /**
     * Creates a new AmazonMerchantShipment object
     * @return \AmazonMerchantShipment
     */
    private function genEmptyShipment() {
        return new AmazonMerchantShipment('testStore', null, null, true, null, __DIR__.'/../../test-config.php');
    }
}

require_once('helperFunctions.php');
