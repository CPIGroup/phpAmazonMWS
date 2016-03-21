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
