<?php

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2012-12-12 at 13:17:14.
 */
class AmazonInventoryListTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonInventoryList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->resetLog();
        $this->object = new AmazonInventoryList('BigKitchen', true, null, '/var/www/athena/plugins/amazon/newAmazon/test-cases/test-config.php');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {
        
    }
    
    public function testSetUseToken(){
        $this->assertNull($this->object->setUseToken());
        $this->assertNull($this->object->setUseToken(true));
        $this->assertNull($this->object->setUseToken(false));
        $this->assertFalse($this->object->setUseToken('wrong'));
    }
    
    public function testSetStartTime(){
        $this->assertNull($this->object->setStartTime(null)); //default
        
        $this->object->setSellerSkus('123');
        $this->assertNull($this->object->setStartTime('-1 min'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('QueryStartDateTime',$o);
        $this->assertNotEquals('1969-12-31T18:58:00-0500',$o['QueryStartDateTime']);
        $this->assertArrayNotHasKey('SellerSkus.member.1',$o);
    }
    
    public function testSetSellerSkus(){
        $this->object->setStartTime('null');
        $this->assertNull($this->object->setSellerSkus(array('404','808')));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('SellerSkus.member.1',$o);
        $this->assertEquals('404',$o['SellerSkus.member.1']);
        $this->assertArrayHasKey('SellerSkus.member.2',$o);
        $this->assertEquals('808',$o['SellerSkus.member.2']);
        $this->assertArrayNotHasKey('QueryStartDateTime',$o);
        
        $this->assertNull($this->object->setSellerSkus('808')); //causes reset
        $o2 = $this->object->getOptions();
        $this->assertArrayNotHasKey('SellerSkus.member.2',$o2);
        
        $this->assertFalse($this->object->setSellerSkus(null));
        $this->assertFalse($this->object->setSellerSkus(707));
    }
    
    public function testSetResponseGroup(){
        $this->assertFalse($this->object->setResponseGroup(null)); //can't be nothing
        $this->assertFalse($this->object->setResponseGroup(5)); //can't be an int
        $this->assertFalse($this->object->setResponseGroup('wrong')); //not a valid value
        $this->assertNull($this->object->setResponseGroup('Basic'));
        $this->assertNull($this->object->setResponseGroup('Detailed'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('ResponseGroup',$o);
        $this->assertEquals('Detailed',$o['ResponseGroup']);
    }
    
    public function testFetchInventoryList(){
        $this->resetLog();
        $this->object->setResponseGroup('Detailed');
        $this->object->setMock(true,'fetchInventoryList.xml'); //no token
        $this->assertNull($this->object->fetchInventoryList());
        
        $o = $this->object->getOptions();
        $this->assertEquals('ListInventorySupply',$o['Action']);
        
        $check = $this->parseLog();
        $this->assertEquals('Single Mock File set: fetchInventoryList.xml',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchInventoryList.xml',$check[2]);
        
        $this->assertFalse($this->object->hasToken());
        
        return $this->object;
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetSupply($o){
        $supply = $o->getSupply(0);
        $this->assertInternalType('array',$supply);
        
        $list = $o->getSupply(null);
        $this->assertInternalType('array',$list);
        $this->assertArrayHasKey(0,$list);
        $this->assertEquals($supply,$list[0]);
        
        $default = $o->getSupply();
        $this->assertEquals($list,$default);
        
        
        
        $x = array();
        $x1 = array();
        $x1['SellerSKU'] = 'SampleSKU1';
        $x1['ASIN'] = 'B00000K3CQ';
        $x1['TotalSupplyQuantity'] = '20';
        $x1['FNSKU'] = 'X0000000FM';
        $x1['Condition'] = 'NewItem';
        $x1['SupplyDetail'][0]['EarliestAvailableToPick'] = 'Immediately';
        $x1['SupplyDetail'][0]['LatestAvailableToPick'] = 'Immediately';
        $x1['SupplyDetail'][0]['Quantity'] = '1';
        $x1['SupplyDetail'][0]['SupplyType'] = 'Normal';
        $x1['SupplyDetail'][1]['EarliestAvailableToPick'] = 'today';
        $x1['SupplyDetail'][1]['LatestAvailableToPick'] = 'tomorrow';
        $x1['SupplyDetail'][1]['Quantity'] = '1';
        $x1['SupplyDetail'][1]['SupplyType'] = 'Normal';
        $x1['InStockSupplyQuantity'] = '15';
        $x1['EarliestAvailability'] = 'Immediately';
        $x[0] = $x1;
        $x2 = array();
        $x2['SellerSKU'] = 'SampleSKU2';
        $x2['ASIN'] = 'B00004RWQR';
        $x2['TotalSupplyQuantity'] = '0';
        $x2['FNSKU'] = 'X00008FZR1';
        $x2['Condition'] = 'UsedLikeNew';
        $x2['InStockSupplyQuantity'] = '0';
        $x[1] = $x2;
        
        $this->assertEquals($x, $list);
        
        $this->assertFalse($this->object->getSupply()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetSellerSku($o){
        $get = $o->getSellerSku(0);
        $this->assertEquals('SampleSKU1',$get);
        
        $this->assertFalse($o->getSellerSku('wrong')); //not number
        $this->assertFalse($this->object->getSellerSku()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetASIN($o){
        $get = $o->getASIN(0);
        $this->assertEquals('B00000K3CQ',$get);
        
        $this->assertFalse($o->getASIN('wrong')); //not number
        $this->assertFalse($this->object->getASIN()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetTotalSupplyQuantity($o){
        $get = $o->getTotalSupplyQuantity(0);
        $this->assertEquals('20',$get);
        
        $this->assertFalse($o->getTotalSupplyQuantity('wrong')); //not number
        $this->assertFalse($this->object->getTotalSupplyQuantity()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetFNSKU($o){
        $get = $o->getFNSKU(0);
        $this->assertEquals('X0000000FM',$get);
        
        $this->assertFalse($o->getFNSKU('wrong')); //not number
        $this->assertFalse($this->object->getFNSKU()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetCondition($o){
        $get = $o->getCondition(0);
        $this->assertEquals('NewItem',$get);
        
        $this->assertFalse($o->getCondition('wrong')); //not number
        $this->assertFalse($this->object->getCondition()); //not fetched yet for this object
    }
        
    /**
     * @depends testFetchInventoryList
     */
    public function testGetInStockSupplyQuantity($o){
        $get = $o->getInStockSupplyQuantity(0);
        $this->assertEquals('15',$get);
        
        $this->assertFalse($o->getInStockSupplyQuantity('wrong')); //not number
        $this->assertFalse($this->object->getInStockSupplyQuantity()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetEarliestAvailability($o){
        $get = $o->getEarliestAvailability(0);
        $this->assertEquals('Immediately',$get);
        
        $this->assertFalse($o->getEarliestAvailability('wrong')); //not number
        $this->assertFalse($this->object->getEarliestAvailability()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetSupplyDetails($o){
        $get = $o->getSupplyDetails(0);
        
        $x = array();
        $x[0]['EarliestAvailableToPick'] = 'Immediately';
        $x[0]['LatestAvailableToPick'] = 'Immediately';
        $x[0]['Quantity'] = '1';
        $x[0]['SupplyType'] = 'Normal';
        $x[1]['EarliestAvailableToPick'] = 'today';
        $x[1]['LatestAvailableToPick'] = 'tomorrow';
        $x[1]['Quantity'] = '1';
        $x[1]['SupplyType'] = 'Normal';
        
        $this->assertEquals($x,$get);
        
        $get2 = $o->getSupplyDetails(0, 0);
        $this->assertEquals($x[0],$get2);
        
        $get3 = $o->getSupplyDetails(0, 1);
        $this->assertEquals($x[1],$get3);
        
        $get4 = $o->getSupplyDetails(0, 'wrong');
        $this->assertEquals($x,$get4);
        
        $this->assertFalse($o->getSupplyDetails('wrong')); //not number
        $this->assertFalse($this->object->getSupplyDetails()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetEarliestAvailableToPick($o){
        $get = $o->getEarliestAvailableToPick(0,0);
        $this->assertEquals('Immediately',$get);
        
        $this->assertFalse($o->getEarliestAvailableToPick('wrong')); //not number
        $this->assertFalse($o->getEarliestAvailableToPick(0,'wrong')); //not number
        $this->assertFalse($this->object->getEarliestAvailableToPick()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetLatestAvailableToPick($o){
        $get = $o->getLatestAvailableToPick(0,0);
        $this->assertEquals('Immediately',$get);
        
        $this->assertFalse($o->getLatestAvailableToPick('wrong')); //not number
        $this->assertFalse($o->getLatestAvailableToPick(0,'wrong')); //not number
        $this->assertFalse($this->object->getLatestAvailableToPick()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetQuantity($o){
        $get = $o->getQuantity(0,0);
        $this->assertEquals('1',$get);
        
        $this->assertFalse($o->getQuantity('wrong')); //not number
        $this->assertFalse($o->getQuantity(0,'wrong')); //not number
        $this->assertFalse($this->object->getQuantity()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchInventoryList
     */
    public function testGetSupplyType($o){
        $get = $o->getSupplyType(0,0);
        $this->assertEquals('Normal',$get);
        
        $this->assertFalse($o->getSupplyType('wrong')); //not number
        $this->assertFalse($o->getSupplyType(0,'wrong')); //not number
        $this->assertFalse($this->object->getSupplyType()); //not fetched yet for this object
    }
    
    public function testFetchInventoryListToken1(){
        $this->resetLog();
        $this->object->setMock(true,'fetchInventoryListToken.xml'); //no token
        $this->object->setResponseGroup('Detailed');
        
        //without using token
        $this->assertNull($this->object->fetchInventoryList());
        $check = $this->parseLog();
        $this->assertEquals('Single Mock File set: fetchInventoryListToken.xml',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchInventoryListToken.xml',$check[2]);
        
        $this->assertTrue($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListInventorySupply',$o['Action']);
        $r = $this->object->getSupply(null);
        $this->assertArrayHasKey(0,$r);
        $this->assertEquals('SampleSKU1',$r[0]['SellerSKU']);
        $this->assertArrayNotHasKey(1,$r);
    }
    
    public function testFetchInventoryListToken2(){
        $this->resetLog();
        $this->object->setMock(true,array('fetchInventoryListToken.xml','fetchInventoryListToken2.xml'));
        
        //with using token
        $this->object->setUseToken();
        $this->assertNull($this->object->fetchInventoryList());
        $check = $this->parseLog();
        $this->assertEquals('Mock files array set.',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchInventoryListToken.xml',$check[2]);
        $this->assertEquals('Recursively fetching more Inventory Supplies',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/fetchInventoryListToken2.xml',$check[4]);
        $this->assertFalse($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListInventorySupplyByNextToken',$o['Action']);
        $this->assertArrayNotHasKey('QueryStartDateTime',$o);
        $this->assertArrayNotHasKey('ResponseGroup',$o);
        $r = $this->object->getSupply(null);
        $this->assertArrayHasKey(0,$r);
        $this->assertArrayHasKey(1,$r);
        $this->assertEquals('SampleSKU1',$r[0]['SellerSKU']);
        $this->assertEquals('SampleSKU2',$r[1]['SellerSKU']);
        $this->assertEquals(2,count($r));
        $this->assertNotEquals($r[0],$r[1]);
    }
    
    /**
     * Resets log for next test
     */
    protected function resetLog(){
        file_put_contents('log.txt','');
    }
    
    /**
     * gets the log contents
     */
    protected function getLog(){
        return file_get_contents('log.txt');
    }
    
    /**
     * gets log and returns messages in an array
     * @param string $s pre-fetched log contents
     * @return array list of message strings
     */
    protected function parseLog($s = null){
        if (!$s){
            $s = $this->getLog();
        }
        $temp = explode("\n",$s);
        
        $return = array();
        foreach($temp as $x){
            $tempo = explode('] ',$x);
            $return[] = trim($tempo[1]);
        }
        array_pop($return);
        return $return;
    }

}
