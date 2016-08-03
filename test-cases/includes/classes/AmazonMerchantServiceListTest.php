<?php

class AmazonMerchantServiceListTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonMerchantServiceList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonMerchantServiceList('testStore', true, null, __DIR__.'/../../test-config.php');
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

    public function testListFetchServices(){
        resetLog();
        $this->object->setMock(true,'fetchMerchantServiceList.xml');
        $this->assertFalse($this->object->getServiceList()); //no list yet
        $this->assertFalse($this->object->getUnavailableCarrierList()); //no list yet
        $this->assertFalse($this->object->getRestrictedCarrierList()); //no list yet
        $this->assertFalse($this->object->fetchServices()); //no order ID yet
        $this->object->setOrderId('903-1713775-3598252');
        $this->assertFalse($this->object->fetchServices()); //no items yet
        $this->object->setItems(array(
            array('OrderItemId' => '40525960574974', 'Quantity' => 1)
        ));
        $this->assertFalse($this->object->fetchServices()); //no address yet
        $this->object->setAddress($this->genAddress());
        $this->assertFalse($this->object->fetchServices()); //no dimensions yet
        $this->object->setPackageDimensions(array('Length' => 5, 'Width' => 5, 'Height' => 5, 'Unit' => 'inches'));
        $this->assertFalse($this->object->fetchServices()); //no weight yet
        $this->object->setWeight(10, 'oz');
        $this->assertFalse($this->object->fetchServices()); //no delivery option yet
        $this->object->setDeliveryOption('DeliveryConfirmationWithoutSignature');
        $this->assertFalse($this->object->fetchServices()); //no pickup option yet
        $this->object->setCarrierWillPickUp();
        $this->assertFalse($this->object->getServiceList()); //still no list yet
        $this->assertFalse($this->object->getUnavailableCarrierList()); //still no list yet
        $this->assertFalse($this->object->getRestrictedCarrierList()); //still no list yet
        $this->assertNull($this->object->fetchServices()); //now it is good

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchMerchantServiceList.xml', $check[1]);

        //check list
        $s = $this->object->getServiceList();
        $u = $this->object->getUnavailableCarrierList();
        $r = $this->object->getRestrictedCarrierList();
        $this->assertInternalType('array', $s);
        $this->assertInternalType('array', $u);
        $this->assertInternalType('array', $r);
        $this->assertNotEmpty($s);
        $this->assertNotEmpty($u);
        $this->assertNotEmpty($r);
        $this->assertCount(4, $s);
        $this->assertCount(1, $u);
        $this->assertCount(1, $r);
        $this->assertArrayHasKey(0, $s);
        $this->assertArrayHasKey(0, $u);
        $this->assertArrayHasKey(0, $r);
        $this->assertEquals('USPS', $u[0]);
        $this->assertEquals('ACME', $r[0]);
        $this->assertInternalType('array', $s[0]);
        $this->assertNotEmpty($s[0]);

        return $this->object;
    }

    public function testSetDetailsByCreator() {
        $creator = new AmazonMerchantShipmentCreator('testStore', true, null, __DIR__.'/../../test-config.php');

        //no settings transferred yet
        $o1 = $this->object->getOptions();
        $this->object->setDetailsByCreator($creator);
        $this->assertEquals($o1, $this->object->getOptions());
        $this->assertArrayNotHasKey('ShipmentRequestDetails.AmazonOrderId', $o1);

        //settings transferred
        $creator->setOrderId('903-1713775-3598252');
        $this->object->setDetailsByCreator($creator);
        $o2 = $this->object->getOptions();
        $this->assertNotEquals($o1, $o2);
        $this->assertArrayHasKey('ShipmentRequestDetails.AmazonOrderId', $o2);
        $this->assertEquals('903-1713775-3598252', $o2['ShipmentRequestDetails.AmazonOrderId']);
    }

    public function testListFetchServicesByCreator() {
        $creator = new AmazonMerchantShipmentCreator('testStore', true, null, __DIR__.'/../../test-config.php');
        $creator->setMock(true,'fetchMerchantServiceList.xml');

        $creator->setOrderId('903-1713775-3598252');
        $creator->setItems(array(
            array('OrderItemId' => '40525960574974', 'Quantity' => 1)
        ));
        $creator->setAddress($this->genAddress());
        $creator->setPackageDimensions(array('Length' => 5, 'Width' => 5, 'Height' => 5, 'Unit' => 'inches'));
        $creator->setWeight(10, 'oz');
        $creator->setDeliveryOption('DeliveryConfirmationWithoutSignature');
        $creator->setCarrierWillPickUp();

        $list = $creator->fetchServices();
        $this->assertInternalType('object', $list);
        $this->assertRegexp('/AmazonMerchantServiceList$/', get_class($list));

        $s = $list->getServiceList();
        $u = $list->getUnavailableCarrierList();
        $r = $list->getRestrictedCarrierList();
        $this->assertInternalType('array', $s);
        $this->assertInternalType('array', $u);
        $this->assertInternalType('array', $r);
        $this->assertNotEmpty($s);
        $this->assertNotEmpty($u);
        $this->assertNotEmpty($r);
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
}

require_once('helperFunctions.php');
