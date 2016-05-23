<?php

class AmazonPreorderTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonPreorder
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonPreorder('testStore', null, true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetUp() {
        $obj = new AmazonPreorder('testStore', '77', true, null, __DIR__.'/../../test-config.php');

        $o = $obj->getOptions();
        $this->assertArrayHasKey('ShipmentId',$o);
        $this->assertEquals('77', $o['ShipmentId']);
    }

    public function testSetShipmentId() {
        $this->assertNull($this->object->setShipmentId('777'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('ShipmentId', $o);
        $this->assertEquals('777', $o['ShipmentId']);
        $this->assertFalse($this->object->setShipmentId(77)); //won't work for numbers
        $this->assertFalse($this->object->setShipmentId(array())); //won't work for this
        $this->assertFalse($this->object->setShipmentId(null)); //won't work for other things
    }

    public function testsetNeedByDate() {
        $this->assertNull($this->object->setNeedByDate('+50 min'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('NeedByDate', $o);
        $this->assertNotEmpty($o['NeedByDate']);
        $this->assertRegExp('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $o['NeedByDate']);

        $this->assertFalse($this->object->setNeedByDate(array(5))); //won't work for this

        $check = parseLog();
        $this->assertEquals('Error: Invalid time input given',$check[1]);
    }

    public function testFetchPreorderInfo() {
        resetLog();
        $this->object->setMock(true,'fetchPreorderInfo.xml');
        $this->assertFalse($this->object->fetchPreorderInfo()); //no ID set yet
        $this->object->setShipmentId('77');
        $this->assertNull($this->object->fetchPreorderInfo());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchPreorderInfo.xml',$check[1]);
        $this->assertEquals('Shipment ID must be set in order to get preorder info!',$check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchPreorderInfo.xml',$check[3]);

        return $this->object;
    }

    public function testConfirmPreorder() {
        resetLog();
        $this->object->setMock(true,'confirmPreorder.xml');
        $this->assertFalse($this->object->confirmPreorder()); //no ID set yet
        $this->object->setShipmentId('77');
        $this->assertFalse($this->object->confirmPreorder()); //no date set yet
        $this->object->setNeedByDate('+3 days');
        $this->assertNull($this->object->confirmPreorder());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: confirmPreorder.xml',$check[1]);
        $this->assertEquals('Shipment ID must be set in order to confirm preorder info!',$check[2]);
        $this->assertEquals('NeedByDate must be set in order to confirm preorder info!',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/confirmPreorder.xml',$check[4]);

        return $this->object;
    }

    /**
     * @depends testFetchPreorderInfo
     * @param AmazonPreorder $o
     */
    public function testGetNeedByDateWithFetch($o) {
        $this->assertEquals('2015-12-27', $o->getNeedByDate());

        $this->assertFalse($this->object->getNeedByDate()); //not fetched yet for this object
    }

    /**
     * @depends testConfirmPreorder
     * @param AmazonPreorder $o
     */
    public function testGetNeedByDateWithConfirm($o) {
        $this->assertEquals('2015-12-28', $o->getNeedByDate());

        $this->assertFalse($this->object->getNeedByDate()); //not fetched yet for this object
    }

    /**
     * @depends testFetchPreorderInfo
     * @param AmazonPreorder $o
     */
    public function testGetFulfillableDateWithFetch($o) {
        $this->assertEquals('2015-12-31', $o->getFulfillableDate());

        $this->assertFalse($this->object->getFulfillableDate()); //not fetched yet for this object
    }

    /**
     * @depends testConfirmPreorder
     * @param AmazonPreorder $o
     */
    public function testGetFulfillableDateWithConfirm($o) {
        $this->assertEquals('2015-12-30', $o->getFulfillableDate());

        $this->assertFalse($this->object->getFulfillableDate()); //not fetched yet for this object
    }

    /**
     * @depends testFetchPreorderInfo
     * @param AmazonPreorder $o
     */
    public function testGetHasPreorderableItems($o) {
        $this->assertEquals('true', $o->getHasPreorderableItems());

        $this->assertFalse($this->object->getHasPreorderableItems()); //not fetched yet for this object
    }

    /**
     * @depends testFetchPreorderInfo
     * @param AmazonPreorder $o
     */
    public function testGetIsConfirmed($o) {
        $this->assertEquals('true', $o->getIsConfirmed());

        $this->assertFalse($this->object->getIsConfirmed()); //not fetched yet for this object
    }

}

require_once('helperFunctions.php');
