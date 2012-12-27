<?php

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2012-12-12 at 13:17:14.
 */
class AmazonCoreTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonServiceStatus
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->resetLog();
        $this->object = new AmazonServiceStatus('BigKitchen', 'Inbound', true);
        $this->object->setConfig('/var/www/athena/plugins/amazon/newAmazon/test-cases/test-config.php');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {
        
    }

    /**
    * @return array
    */
    public function mockProvider() {
        return array(
            array(true,null, 'Mock Mode set to ON'),
            array(false,null, 'Mock Mode set to OFF'),
            array(true,'test', 'Mock Mode set to ON','Single Mock File set: test'),
            array(true,array('test'), 'Mock Mode set to ON','Mock files array set.'),
            array(false,'test', 'Mock Mode set to OFF','Single Mock File set: test'),
            array(false,array('test'), 'Mock Mode set to OFF','Mock files array set.'),
            array('no',null, null),
        );
    }
    
    /**
     * @covers AmazonCore::setMock
     * @dataProvider mockProvider
     */
    public function testSetMock($a, $b, $c, $d = null) {
        $this->resetLog();
        $this->object->setMock($a, $b);
        $check = $this->parseLog();
        $this->assertEquals($c,$check[0]);
        if ($d){
            $this->assertEquals($d,$check[1]);
        }
    }

    /**
     * @covers AmazonCore::setConfig
     * @expectedException Exception
     * @expectedExceptionMessage Config file does not exist or cannot be read! (no)
     */
    public function testSetConfig() {
        $this->object->setConfig('no');
    }

    /**
     * @covers AmazonCore::setLogPath
     * @expectedException Exception
     * @expectedExceptionMessage Log file does not exist or cannot be read! (no)
     */
    public function testSetLogPath() {
        $this->object->setLogPath('no');
    }

    /**
     * @covers AmazonCore::setStore
     * @todo   Implement testSetStore().
     */
    public function testSetStore() {
        $this->object->setStore('no');
        $check = $this->parseLog();
        $this->assertEquals('Mock Mode set to ON',$check[0]);
        $this->assertEquals('Store no does not exist!',$check[1]);
        $this->resetLog();
        $this->object->setStore('bad');
        $bad = $this->parseLog();
        $this->assertEquals('Merchant ID is missing!',$bad[0]);
        $this->assertEquals('Access Key ID is missing!',$bad[1]);
        $this->assertEquals('Secret Key is missing!',$bad[2]);
    }
    
    public function testGetOptions(){
        $o = $this->object->getOptions();
        $this->assertInternalType('array',$o);
        $this->assertArrayHasKey('SellerId',$o);
        $this->assertArrayHasKey('AWSAccessKeyId',$o);
        $this->assertArrayHasKey('SignatureVersion',$o);
        $this->assertArrayHasKey('SignatureMethod',$o);
        $this->assertArrayHasKey('Version',$o);
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
