<?php

class AmazonConfigTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonMWSConfig
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonMWSConfig( __DIR__.'/../../test-config.php');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {

    }

    /**
     * @covers AmazonMWSConfig::__construct()
     */
    public function testParsing() {
        $this->assertEquals($this->object->getStoreCount(),2);
        $store = $this->object->getStoreCount('testStore');
        $this->assertTrue(count($store) != 0); // Check we have some records.

        $this->assertEquals($this->object->getEndPoint(),'https://mws.amazonservices.com/');
        $this->assertEquals($this->object->getLogCallback(),null);
        $this->assertEquals($this->object->isLoggingDisabled(),false);

        $store = $this->object->getStoreCount('bad');
        $this->assertTrue(count($store) == 1); // Check we have some 1 record
    }

    public function testCreateCopy() {
        $store = $this->object->getConfigFor('testStore');


        $this->assertEquals($this->object->getLogFile(),$store->getLogFile());
        $this->assertEquals($this->object->getLogCallback(),$store->getLogCallback());
        $this->assertEquals($this->object->getEndPoint(),$store->getEndPoint());
        $this->assertEquals($this->object->getStoreCount(),2);
        $this->assertEquals($this->object->getStore('testStore'),$store->getStore('testStore'));

    }

    public function testCreateCopyAll() {
        // Passing the already created object in should result in the config being loaded from it.
        $newConfig = new AmazonMWSConfig($this->object);

        $this->assertEquals($this->object->getStoreCount(),$newConfig->getStoreCount());
        $this->assertEquals($this->object->getLogFile(),$newConfig->getLogFile());
        $this->assertEquals($this->object->getLogCallback(),$newConfig->getLogCallback());
        $stores = array_keys($this->object->getStores());
        foreach($stores as $storeName) {
            $this->assertEquals($this->object->getStore($storeName),$newConfig->getStore($storeName));
        }
    }

}

require_once('helperFunctions.php');
