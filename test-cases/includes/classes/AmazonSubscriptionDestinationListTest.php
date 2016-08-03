<?php

class AmazonSubscriptionDestinationListTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonSubscriptionDestinationList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonSubscriptionDestinationList('testStore', true, null, __DIR__.'/../../test-config.php');
    }

    public function testFetchDestinations() {
        resetLog();
        $this->object->setMock(true, 'fetchDestinations.xml');
        $this->assertNull($this->object->fetchDestinations());
        $o = $this->object->getOptions();
        $this->assertEquals('ListRegisteredDestinations', $o['Action']);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchDestinations.xml',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchDestinations.xml',$check[2]);

        return $this->object;
    }

    /**
     * @param AmazonSubscriptionDestinationList $o
     * @depends testFetchDestinations
     */
    public function testGetDeliveryChannel($o) {
        $this->assertEquals('SQS', $o->getDeliveryChannel(0));
        $this->assertEquals('SQS2', $o->getDeliveryChannel(1));
        $this->assertEquals($o->getDeliveryChannel(0), $o->getDeliveryChannel());
        //invalid keys
        $this->assertFalse($o->getDeliveryChannel(4));
        $this->assertFalse($o->getDeliveryChannel('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getDeliveryChannel());
    }

    /**
     * @param AmazonSubscriptionDestinationList $o
     * @depends testFetchDestinations
     */
    public function testGetAttributes($o) {
        $data1 = array(
            'sqsQueueUrl' => 'https://sqs.us-east-1.amazonaws.com/51471EXAMPLE/mws_notifications',
        );
        $data2 = array(
            'url' => 'https://sqs.us-west-1.amazonaws.com/51471EXAMPLE/mws_notifications',
            'something' => '5',
        );
        $this->assertEquals($data1, $o->getAttributes(0));
        $this->assertEquals($data2, $o->getAttributes(1));
        $this->assertEquals($o->getAttributes(0), $o->getAttributes());
        //invalid keys
        $this->assertFalse($o->getAttributes(4));
        $this->assertFalse($o->getAttributes('no'));
        //not fetched yet for this object
        $this->assertFalse($this->object->getAttributes());
    }

}

require_once('helperFunctions.php');
