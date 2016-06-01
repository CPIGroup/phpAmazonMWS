<?php

class AmazonTransportDocumentTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonTransportDocument
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonTransportDocument('testStore', null, true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetUp() {
        $obj = new AmazonTransportDocument('testStore', '77', true, null, __DIR__.'/../../test-config.php');

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

    public function testSetPackageIds(){
        $ok = $this->object->setPackageIds('string1');
        $this->assertNull($ok);
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('PackageLabelsToPrint.member.1', $o);
        $this->assertEquals('string1', $o['PackageLabelsToPrint.member.1']);
        $ok2 = $this->object->setPackageIds(array('string1', 'string2'));
        $this->assertNull($ok2);
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey('PackageLabelsToPrint.member.1', $o2);
        $this->assertArrayHasKey('PackageLabelsToPrint.member.2', $o2);
        $this->assertEquals('string1', $o2['PackageLabelsToPrint.member.1']);
        $this->assertEquals('string2', $o2['PackageLabelsToPrint.member.2']);
        $this->object->setPackageIds('stringx');
        $o3 = $this->object->getOptions();
        $this->assertArrayNotHasKey('PackageLabelsToPrint.member.2', $o3);
        $this->assertFalse($this->object->setPackageIds(null));
    }

    public function testSetPalletCount(){
        $this->assertFalse($this->object->setPalletCount(null)); //can't be nothing
        $this->assertFalse($this->object->setPalletCount('NaN')); //can't be a string
        $this->assertFalse($this->object->setPalletCount(-5)); //can't be negative
        $this->assertNull($this->object->setPalletCount(5));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('NumberOfPallets', $o);
        $this->assertEquals('5', $o['NumberOfPallets']);
    }

    public function testFetchPackageLabels() {
        //not fetched for the object yet
        $this->assertFalse($this->object->getDocument());
        $this->assertFalse($this->object->getChecksum());
        resetLog();
        $this->object->setMock(true,'fetchPackageLabels.xml');
        $this->assertFalse($this->object->fetchPackageLabels()); //no shipment ID set yet
        $this->object->setShipmentId('77');
        $this->assertFalse($this->object->fetchPackageLabels()); //no package IDs set yet
        $this->object->setPackageIds('88');
        $this->assertNull($this->object->fetchPackageLabels());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchPackageLabels.xml',$check[1]);
        $this->assertEquals('ShipmentId must be set in order to get package labels!',$check[2]);
        $this->assertEquals('Package IDs must be set in order to get package labels!',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/fetchPackageLabels.xml',$check[4]);

        $this->assertEquals('package test', $this->object->getDocument());
        $this->assertEquals(base64_encode('package test'), $this->object->getDocument(true));
        $this->assertEquals('this is a checksum', $this->object->getChecksum());
        $this->assertEquals(base64_encode('this is a checksum'), $this->object->getChecksum(true));
    }

    public function testFetchPalletLabels() {
        //not fetched for the object yet
        $this->assertFalse($this->object->getDocument());
        $this->assertFalse($this->object->getChecksum());
        resetLog();
        $this->object->setMock(true,'fetchPalletLabels.xml');
        $this->assertFalse($this->object->fetchPalletLabels()); //no shipment ID set yet
        $this->object->setShipmentId('77');
        $this->assertFalse($this->object->fetchPalletLabels()); //no number of pallets set
        $this->object->setPalletCount('88');
        $this->assertNull($this->object->fetchPalletLabels());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchPalletLabels.xml',$check[1]);
        $this->assertEquals('ShipmentId must be set in order to get pallet labels!',$check[2]);
        $this->assertEquals('Number of pallets must be set in order to get pallet labels!',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/fetchPalletLabels.xml',$check[4]);

        $this->assertEquals('pallet test', $this->object->getDocument());
        $this->assertEquals(base64_encode('pallet test'), $this->object->getDocument(true));
        $this->assertEquals('this is a checksum', $this->object->getChecksum());
        $this->assertEquals(base64_encode('this is a checksum'), $this->object->getChecksum(true));
    }

    public function testBillOfLading() {
        //not fetched for the object yet
        $this->assertFalse($this->object->getDocument());
        $this->assertFalse($this->object->getChecksum());
        resetLog();
        $this->object->setMock(true,'fetchBillOfLading.xml');
        $this->assertFalse($this->object->fetchBillOfLading()); //no shipment ID set yet
        $this->object->setShipmentId('77');
        $this->assertNull($this->object->fetchBillOfLading());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchBillOfLading.xml',$check[1]);
        $this->assertEquals('ShipmentId must be set in order to get a bill of lading!',$check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchBillOfLading.xml',$check[3]);

        $this->assertEquals('bill of lading', $this->object->getDocument());
        $this->assertEquals(base64_encode('bill of lading'), $this->object->getDocument(true));
        $this->assertEquals('this is a checksum', $this->object->getChecksum());
        $this->assertEquals(base64_encode('this is a checksum'), $this->object->getChecksum(true));
    }

}

require_once('helperFunctions.php');
