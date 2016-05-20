<?php

class AmazonPrepInfoTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonPrepInfo
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonPrepInfo('testStore', true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetSKUs() {
        $this->object->setASINs('123456789');
        $this->assertNull($this->object->setSKUs(array('123','456')));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('SellerSKUList.Id.1',$o);
        $this->assertEquals('123',$o['SellerSKUList.Id.1']);
        $this->assertArrayHasKey('SellerSKUList.Id.2',$o);
        $this->assertEquals('456',$o['SellerSKUList.Id.2']);
        $this->assertArrayNotHasKey('ASINList.Id.1',$o);

        $this->assertNull($this->object->setSKUs('789')); //causes reset
        $o2 = $this->object->getOptions();
        $this->assertEquals('789',$o2['SellerSKUList.Id.1']);
        $this->assertArrayNotHasKey('SellerSKUList.Id.2',$o2);

        $this->assertFalse($this->object->setSKUs(null));
        $this->assertFalse($this->object->setSKUs(707));
    }

    public function testSetASINs() {
        $this->object->setSKUs('123456789');
        $this->assertNull($this->object->setASINs(array('123','456')));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('ASINList.Id.1',$o);
        $this->assertEquals('123',$o['ASINList.Id.1']);
        $this->assertArrayHasKey('ASINList.Id.2',$o);
        $this->assertEquals('456',$o['ASINList.Id.2']);
        $this->assertArrayNotHasKey('SellerSKUList.Id.1',$o);

        $this->assertNull($this->object->setASINs('789')); //causes reset
        $o2 = $this->object->getOptions();
        $this->assertEquals('789',$o2['ASINList.Id.1']);
        $this->assertArrayNotHasKey('ASINList.Id.2',$o2);

        $this->assertFalse($this->object->setASINs(null));
        $this->assertFalse($this->object->setASINs(707));
    }

    public function testFetchPrepInstructionsAsin() {
        resetLog();
        $this->object->setMock(true, 'fetchPrepInstructionsAsin.xml');
        $this->assertFalse($this->object->fetchPrepInstructions());
        $this->assertNull($this->object->setASINs('123'));
        $this->assertNull($this->object->fetchPrepInstructions());
        $o = $this->object->getOptions();
        $this->assertEquals('GetPrepInstructionsForASIN',$o['Action']);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchPrepInstructionsAsin.xml',$check[1]);
        $this->assertEquals('Product IDs must be set in order to get prep instructions!',$check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchPrepInstructionsAsin.xml',$check[3]);

        return $this->object;
    }

    public function testFetchPrepInstructionsSku() {
        resetLog();
        $this->object->setMock(true, 'fetchPrepInstructionsSku.xml');
        $this->assertFalse($this->object->fetchPrepInstructions());
        $this->assertNull($this->object->setSKUs('123'));
        $this->assertNull($this->object->fetchPrepInstructions());
        $o = $this->object->getOptions();
        $this->assertEquals('GetPrepInstructionsForSKU',$o['Action']);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchPrepInstructionsSku.xml',$check[1]);
        $this->assertEquals('Product IDs must be set in order to get prep instructions!',$check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchPrepInstructionsSku.xml',$check[3]);

        return $this->object;
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetSku($o) {
        $this->assertEquals('ca_001', $o->getSku(0));
        $this->assertEquals('ca_002', $o->getSku(1));
        $this->assertEquals($o->getSku(0), $o->getSku());
        //invalid keys
        $this->assertFalse($o->getSku(4));
        $this->assertFalse($o->getSku('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getSku());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsAsin
     */
    public function testGetSkuWithAsin($o) {
        //no SKUs when getting by ASIN
        $this->assertFalse($o->getSku(0));
        $this->assertFalse($o->getSku(1));
        //not fetched yet for this object
        $this->assertFalse($this->object->getSku());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetAsin($o) {
        $this->assertEquals('B00EXAMPLE', $o->getAsin(0));
        $this->assertEquals('B00EXAMPLE2', $o->getAsin(1));
        $this->assertEquals($o->getAsin(0), $o->getAsin());
        //invalid keys
        $this->assertFalse($o->getAsin(4));
        $this->assertFalse($o->getAsin('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getAsin());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetBarcodeInstruction($o) {
        $this->assertEquals('RequiresFNSKULabel', $o->getBarcodeInstruction(0));
        $this->assertEquals('CanUseOriginalBarcode', $o->getBarcodeInstruction(1));
        $this->assertEquals($o->getBarcodeInstruction(0), $o->getBarcodeInstruction());
        //invalid keys
        $this->assertFalse($o->getBarcodeInstruction(4));
        $this->assertFalse($o->getBarcodeInstruction('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getBarcodeInstruction());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetPrepGuidance($o) {
        $this->assertEquals('SeePrepInstructionsList', $o->getPrepGuidance(0));
        $this->assertEquals('ConsultHelpDocuments', $o->getPrepGuidance(1));
        $this->assertEquals($o->getPrepGuidance(0), $o->getPrepGuidance());
        //invalid keys
        $this->assertFalse($o->getPrepGuidance(4));
        $this->assertFalse($o->getPrepGuidance('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getPrepGuidance());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetPrepInstructions($o) {
        $list1 = array(
            'Polybagging',
            'Taping',
            'Labeling',
        );
        $list2 = array(
            'Taping',
        );
        $this->assertEquals($list1, $o->getPrepInstructions(0));
        $this->assertEquals($list2, $o->getPrepInstructions(1));
        $this->assertEquals($o->getPrepInstructions(0), $o->getPrepInstructions());
        //invalid keys
        $this->assertFalse($o->getPrepInstructions(4));
        $this->assertFalse($o->getPrepInstructions('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getPrepInstructions());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetAmazonPrepFees($o) {
        $list1 = array(
            array(
                'PrepInstruction' => 'Polybagging',
                'Amount' => array(
                    'CurrencyCode' => 'USD',
                    'Value' => '0.2',
                ),
            ),
            array(
                'PrepInstruction' => 'Taping',
                'Amount' => array(
                    'CurrencyCode' => 'USD',
                    'Value' => '0.2',
                ),
            ),
            array(
                'PrepInstruction' => 'Labeling',
                'Amount' => array(
                    'CurrencyCode' => 'USD',
                    'Value' => '0.2',
                ),
            ),
        );
        $list2 = array(
            array(
                'PrepInstruction' => 'Taping',
                'Amount' => array(
                    'CurrencyCode' => 'USD',
                    'Value' => '0.4',
                ),
            ),
        );
        $this->assertEquals($list1, $o->getAmazonPrepFees(0));
        $this->assertEquals($list2, $o->getAmazonPrepFees(1));
        $this->assertEquals($o->getAmazonPrepFees(0), $o->getAmazonPrepFees());
        //invalid keys
        $this->assertFalse($o->getAmazonPrepFees(4));
        $this->assertFalse($o->getAmazonPrepFees('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getAmazonPrepFees());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsAsin
     */
    public function testGetAmazonPrepFeesWithAsin($o) {
        //no SKUs when getting by ASIN
        $this->assertFalse($o->getAmazonPrepFees(0));
        $this->assertFalse($o->getAmazonPrepFees(1));
        //not fetched yet for this object
        $this->assertFalse($this->object->getAmazonPrepFees());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetPrepList($o) {
        $list = $o->getPrepList();
        $this->assertInternalType('array', $list);
        $this->assertCount(2, $list);
        $this->assertArrayHasKey(0, $list);
        $this->assertArrayHasKey(1, $list);
        $this->assertEquals($list[0], $o->getPrepList(0));
        $this->assertEquals($list[1], $o->getPrepList(1));

        //check keys
        $this->assertArrayHasKey('SellerSKU', $list[0]);
        $this->assertEquals($list[0]['SellerSKU'], $o->getSku(0));
        $this->assertArrayHasKey('ASIN', $list[0]);
        $this->assertEquals($list[0]['ASIN'], $o->getAsin(0));
        $this->assertArrayHasKey('BarcodeInstruction', $list[0]);
        $this->assertEquals($list[0]['BarcodeInstruction'], $o->getBarcodeInstruction(0));
        $this->assertArrayHasKey('PrepGuidance', $list[0]);
        $this->assertEquals($list[0]['PrepGuidance'], $o->getPrepGuidance(0));
        $this->assertArrayHasKey('PrepInstructionList', $list[0]);
        $this->assertEquals($list[0]['PrepInstructionList'], $o->getPrepInstructions(0));
        $this->assertArrayHasKey('AmazonPrepFees', $list[0]);
        $this->assertEquals($list[0]['AmazonPrepFees'], $o->getAmazonPrepFees(0));

        //not fetched yet for this object
        $this->assertFalse($this->object->getPrepList());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsAsin
     */
    public function testGetPrepListWithAsin($o) {
        $list = $o->getPrepList();
        $this->assertInternalType('array', $list);
        $this->assertCount(1, $list);
        $this->assertArrayHasKey(0, $list);
        $this->assertEquals($list[0], $o->getPrepList(0));

        //check keys
        $this->assertArrayHasKey('ASIN', $list[0]);
        $this->assertEquals($list[0]['ASIN'], $o->getAsin(0));
        $this->assertArrayHasKey('BarcodeInstruction', $list[0]);
        $this->assertEquals($list[0]['BarcodeInstruction'], $o->getBarcodeInstruction(0));
        $this->assertArrayHasKey('PrepGuidance', $list[0]);
        $this->assertEquals($list[0]['PrepGuidance'], $o->getPrepGuidance(0));
        $this->assertArrayHasKey('PrepInstructionList', $list[0]);
        $this->assertEquals($list[0]['PrepInstructionList'], $o->getPrepInstructions(0));
        $this->assertArrayNotHasKey('SellerSKU', $list[0]);
        $this->assertArrayNotHasKey('AmazonPrepFees', $list[0]);

        //not fetched yet for this object
        $this->assertFalse($this->object->getPrepList());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testGetInvalidItemList($o) {
        $list = $o->getInvalidItemList();
        $this->assertInternalType('array', $list);
        $this->assertCount(2, $list);
        $this->assertArrayHasKey(0, $list);
        $this->assertArrayHasKey(1, $list);
        $this->assertEquals($list[0], $o->getInvalidItemList(0));
        $this->assertEquals($list[1], $o->getInvalidItemList(1));

        //check keys
        $this->assertArrayHasKey('SellerSKU', $list[0]);
        $this->assertEquals('ca_007', $list[0]['SellerSKU']);
        $this->assertArrayHasKey('ErrorReason', $list[0]);
        $this->assertEquals('DoesNotExist', $list[0]['ErrorReason']);
        $this->assertArrayNotHasKey('ASIN', $list[0]);
        $this->assertEquals(array_keys($list[0]), array_keys($list[1]));
        $this->assertEquals('ca_009', $list[1]['SellerSKU']);
        $this->assertEquals('DoesNotExist2', $list[1]['ErrorReason']);

        //not fetched yet for this object
        $this->assertFalse($this->object->getInvalidItemList());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsAsin
     */
    public function testGetInvalidItemListWithAsin($o) {
        $list = $o->getInvalidItemList();
        $this->assertInternalType('array', $list);
        $this->assertCount(2, $list);
        $this->assertArrayHasKey(0, $list);
        $this->assertArrayHasKey(1, $list);
        $this->assertEquals($list[0], $o->getInvalidItemList(0));
        $this->assertEquals($list[1], $o->getInvalidItemList(1));

        //check keys
        $this->assertArrayHasKey('ASIN', $list[0]);
        $this->assertEquals('B0INVALIDF', $list[0]['ASIN']);
        $this->assertArrayHasKey('ErrorReason', $list[0]);
        $this->assertEquals('InvalidASIN', $list[0]['ErrorReason']);
        $this->assertArrayNotHasKey('SellerSKU', $list[0]);
        $this->assertEquals(array_keys($list[0]), array_keys($list[1]));
        $this->assertEquals('B0INVALIDF2', $list[1]['ASIN']);
        $this->assertEquals('InvalidASIN2', $list[1]['ErrorReason']);

        //not fetched yet for this object
        $this->assertFalse($this->object->getInvalidItemList());
    }

    /**
     * @param AmazonPrepInfo
     * @depends testFetchPrepInstructionsSku
     */
    public function testIterator($o) {
        $passed = 0;
        foreach ($o as $k => $x) {
            $passed++;
            $this->assertEquals($o->getPrepList($k), $x);
            $this->assertEquals($k, $o->key());
        }
        $this->assertEquals(2, $passed, 'Did not loop the correct number of times');
        //not fetched yet for this object
        foreach ($this->object as $k => $x) {
            $this->fail('There should be nothing to loop though');
        }
    }

}

require_once('helperFunctions.php');
