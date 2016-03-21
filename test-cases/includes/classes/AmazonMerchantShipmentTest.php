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
        $x['FileContents']['Contents'] = 'H4sIAAAAAAAAAK16WbeqyrLmO2Pc/zBVRLG6nycD/Bd+Zx3S8LwAA';
        $x['FileContents']['FileType'] = 'application/pdf';
        $x['FileContents']['Checksum'] = 'DmsWbJpdMPALN3jV4wHOrg==';
        $this->assertEquals($x, $get);
        $this->assertEquals($x['FileContents']['Contents'], $o->getLabelFileContents());

        $new = $this->genEmptyShipment();
        $this->assertFalse($new->getLabelData()); //not fetched yet for this object
        $this->assertFalse($new->getLabelFileContents()); //not fetched yet for this object
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
