<?php

class AmazonSubscriptionTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonSubscription
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonSubscription('testStore', true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetMarketplace() {
        $this->assertNull($this->object->setMarketplace('ATVPDKIKX0DER2'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('MarketplaceId', $o);
        $this->assertEquals('ATVPDKIKX0DER2', $o['MarketplaceId']);
        $this->assertFalse($this->object->setMarketplace(77)); //won't work for numbers
        $this->assertFalse($this->object->setMarketplace(array())); //won't work for this
        $this->assertFalse($this->object->setMarketplace(null)); //won't work for other things
    }

    public function testSetDeliveryChannel() {
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('Destination.DeliveryChannel', $o);
        $this->assertArrayHasKey('Subscription.Destination.DeliveryChannel', $o);
        $this->assertEquals('SQS', $o['Destination.DeliveryChannel']);
        $this->assertEquals('SQS', $o['Subscription.Destination.DeliveryChannel']);
        $this->assertFalse($this->object->setDeliveryChannel(77)); //won't work for numbers
        $this->assertFalse($this->object->setDeliveryChannel(array())); //won't work for this
        $this->assertFalse($this->object->setDeliveryChannel(null)); //won't work for other things
    }

    public function testSetAttributes() {
        $this->assertNull($this->object->setAttributes(array(
            'url' => '123',
            'another' => '456',
        )));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('Destination.AttributeList.member.1.Key', $o);
        $this->assertEquals('url', $o['Destination.AttributeList.member.1.Key']);
        $this->assertArrayHasKey('Destination.AttributeList.member.1.Value', $o);
        $this->assertEquals('123', $o['Destination.AttributeList.member.1.Value']);
        $this->assertArrayHasKey('Destination.AttributeList.member.2.Key', $o);
        $this->assertEquals('another', $o['Destination.AttributeList.member.2.Key']);
        $this->assertArrayHasKey('Destination.AttributeList.member.2.Value', $o);
        $this->assertEquals('456', $o['Destination.AttributeList.member.2.Value']);

        $this->assertNull($this->object->setAttributes(array('new' => '789'))); //causes reset
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey('Destination.AttributeList.member.1.Key', $o2);
        $this->assertEquals('new', $o2['Destination.AttributeList.member.1.Key']);
        $this->assertArrayHasKey('Destination.AttributeList.member.1.Value', $o2);
        $this->assertEquals('789', $o2['Destination.AttributeList.member.1.Value']);
        $this->assertArrayNotHasKey('Destination.AttributeList.member.2.Key', $o2);

        $this->assertFalse($this->object->setAttributes(null));
        $this->assertFalse($this->object->setAttributes('banana'));
        $this->assertFalse($this->object->setAttributes(707));
    }

    public function testSetNotificationType() {
        $this->assertNull($this->object->setNotificationType('special'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('Subscription.NotificationType', $o);
        $this->assertArrayHasKey('NotificationType', $o);
        $this->assertEquals('special', $o['Subscription.NotificationType']);
        $this->assertEquals('special', $o['NotificationType']);
        $this->assertFalse($this->object->setNotificationType(77)); //won't work for numbers
        $this->assertFalse($this->object->setNotificationType(array())); //won't work for this
        $this->assertFalse($this->object->setNotificationType(null)); //won't work for other things
    }

    public function testSetIsEnabled() {
        $this->assertNull($this->object->setIsEnabled());
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('Subscription.IsEnabled', $o);
        $this->assertEquals('true', $o['Subscription.IsEnabled']);
        $this->assertNull($this->object->setIsEnabled(false));
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey('Subscription.IsEnabled', $o2);
        $this->assertEquals('false', $o2['Subscription.IsEnabled']);
    }

    public function testRegisterDestination() {
        resetLog();
        $this->object->setMock(true, 'registerDestination.xml');
        $this->assertNull($this->object->setIsEnabled());
        $this->assertNull($this->object->setNotificationType('special'));
        $this->assertFalse($this->object->registerDestination()); //no channel yet
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $this->assertFalse($this->object->registerDestination()); //no attributes yet
        $this->assertNull($this->object->setAttributes(array('url'=>'google')));
        $this->assertNull($this->object->registerDestination());
        $o = $this->object->getOptions();
        $this->assertEquals('RegisterDestination', $o['Action']);
        $this->assertArrayNotHasKey('Subscription.Destination.DeliveryChannel', $o);
        $this->assertArrayNotHasKey('Subscription.Destination.AttributeList.member.1.Key', $o);
        $this->assertArrayNotHasKey('Subscription.IsEnabled', $o);
        $this->assertArrayNotHasKey('Subscription.NotificationType', $o);
        $this->assertArrayNotHasKey('NotificationType', $o);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: registerDestination.xml',$check[1]);
        $this->assertEquals('Delivery channel must be set in order to register a subscription destination!',$check[2]);
        $this->assertEquals('Attributes must be set in order to register a subscription destination!',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/registerDestination.xml',$check[4]);
    }

    public function testDeregisterDestination() {
        resetLog();
        $this->object->setMock(true, 'deregisterDestination.xml');
        $this->assertNull($this->object->setIsEnabled());
        $this->assertNull($this->object->setNotificationType('special'));
        $this->assertFalse($this->object->deregisterDestination()); //no channel yet
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $this->assertFalse($this->object->deregisterDestination()); //no attributes yet
        $this->assertNull($this->object->setAttributes(array('url'=>'google')));
        $this->assertNull($this->object->deregisterDestination());
        $o = $this->object->getOptions();
        $this->assertEquals('DeregisterDestination', $o['Action']);
        $this->assertArrayNotHasKey('Subscription.Destination.DeliveryChannel', $o);
        $this->assertArrayNotHasKey('Subscription.Destination.AttributeList.member.1.Key', $o);
        $this->assertArrayNotHasKey('Subscription.IsEnabled', $o);
        $this->assertArrayNotHasKey('Subscription.NotificationType', $o);
        $this->assertArrayNotHasKey('NotificationType', $o);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: deregisterDestination.xml',$check[1]);
        $this->assertEquals('Delivery channel must be set in order to deregister a subscription destination!',$check[2]);
        $this->assertEquals('Attributes must be set in order to deregister a subscription destination!',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/deregisterDestination.xml',$check[4]);
    }

    public function testTestDestination() {
        resetLog();
        $this->object->setMock(true, 'pingDestination.xml');
        $this->assertNull($this->object->setIsEnabled());
        $this->assertNull($this->object->setNotificationType('special'));
        $this->assertFalse($this->object->testDestination()); //no channel yet
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $this->assertFalse($this->object->testDestination()); //no attributes yet
        $this->assertNull($this->object->setAttributes(array('url'=>'google')));
        $this->assertNull($this->object->testDestination());
        $o = $this->object->getOptions();
        $this->assertEquals('SendTestNotificationToDestination', $o['Action']);
        $this->assertArrayNotHasKey('Subscription.Destination.DeliveryChannel', $o);
        $this->assertArrayNotHasKey('Subscription.Destination.AttributeList.member.1.Key', $o);
        $this->assertArrayNotHasKey('Subscription.IsEnabled', $o);
        $this->assertArrayNotHasKey('Subscription.NotificationType', $o);
        $this->assertArrayNotHasKey('NotificationType', $o);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: pingDestination.xml',$check[1]);
        $this->assertEquals('Delivery channel must be set in order to test a subscription destination!',$check[2]);
        $this->assertEquals('Attributes must be set in order to test a subscription destination!',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/pingDestination.xml',$check[4]);
    }

    public function testCreateSubscription() {
        resetLog();
        $this->object->setMock(true, 'createSubscription.xml');
        $this->assertFalse($this->object->createSubscription()); //no channel yet
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $this->assertFalse($this->object->createSubscription()); //no attributes yet
        $this->assertNull($this->object->setAttributes(array('url'=>'google')));
        $this->assertFalse($this->object->createSubscription()); //no notification type yet
        $this->assertNull($this->object->setNotificationType('special'));
        $this->assertFalse($this->object->createSubscription()); //no enabled yet
        $this->assertNull($this->object->setIsEnabled());
        $this->assertNull($this->object->createSubscription());
        $o = $this->object->getOptions();
        $this->assertEquals('CreateSubscription', $o['Action']);
        $this->assertArrayNotHasKey('Destination.DeliveryChannel', $o);
        $this->assertArrayNotHasKey('Destination.AttributeList.member.1.Key', $o);
        $this->assertArrayNotHasKey('NotificationType', $o);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: createSubscription.xml',$check[1]);
        $this->assertEquals('Delivery channel must be set in order to create a subscription!',$check[2]);
        $this->assertEquals('Attributes must be set in order to create a subscription!',$check[3]);
        $this->assertEquals('Notification type must be set in order to create a subscription!',$check[4]);
        $this->assertEquals('Enabled status must be set in order to create a subscription!',$check[5]);
        $this->assertEquals('Fetched Mock File: mock/createSubscription.xml',$check[6]);
    }

    public function testFetchSubscription() {
        resetLog();
        $this->object->setMock(true, 'fetchSubscription.xml');
        $this->object->setIsEnabled();
        $this->assertFalse($this->object->fetchSubscription()); //no channel yet
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $this->assertFalse($this->object->fetchSubscription()); //no attributes yet
        $this->assertNull($this->object->setAttributes(array('url'=>'google')));
        $this->assertFalse($this->object->fetchSubscription()); //no notification type yet
        $this->assertNull($this->object->setNotificationType('special'));
        $this->assertNull($this->object->fetchSubscription());
        $o = $this->object->getOptions();
        $this->assertEquals('GetSubscription', $o['Action']);
        $this->assertArrayNotHasKey('Subscription.Destination.DeliveryChannel', $o);
        $this->assertArrayNotHasKey('Subscription.Destination.AttributeList.member.1.Key', $o);
        $this->assertArrayNotHasKey('Subscription.IsEnabled', $o);
        $this->assertArrayNotHasKey('Subscription.NotificationType', $o);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchSubscription.xml',$check[1]);
        $this->assertEquals('Delivery channel must be set in order to fetch a subscription!',$check[2]);
        $this->assertEquals('Attributes must be set in order to fetch a subscription!',$check[3]);
        $this->assertEquals('Notification type must be set in order to fetch a subscription!',$check[4]);
        $this->assertEquals('Fetched Mock File: mock/fetchSubscription.xml',$check[5]);

        return $this->object;
    }

    public function testUpdateSubscription() {
        resetLog();
        $this->object->setMock(true, 'updateSubscription.xml');
        $this->assertFalse($this->object->updateSubscription()); //no channel yet
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $this->assertFalse($this->object->updateSubscription()); //no attributes yet
        $this->assertNull($this->object->setAttributes(array('url'=>'google')));
        $this->assertFalse($this->object->updateSubscription()); //no notification type yet
        $this->assertNull($this->object->setNotificationType('special'));
        $this->assertFalse($this->object->updateSubscription()); //no enabled yet
        $this->assertNull($this->object->setIsEnabled());
        $this->assertNull($this->object->updateSubscription());
        $o = $this->object->getOptions();
        $this->assertEquals('UpdateSubscription', $o['Action']);
        $this->assertArrayNotHasKey('Destination.DeliveryChannel', $o);
        $this->assertArrayNotHasKey('Destination.AttributeList.member.1.Key', $o);
        $this->assertArrayNotHasKey('NotificationType', $o);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: updateSubscription.xml',$check[1]);
        $this->assertEquals('Delivery channel must be set in order to update a subscription!',$check[2]);
        $this->assertEquals('Attributes must be set in order to update a subscription!',$check[3]);
        $this->assertEquals('Notification type must be set in order to update a subscription!',$check[4]);
        $this->assertEquals('Enabled status must be set in order to update a subscription!',$check[5]);
        $this->assertEquals('Fetched Mock File: mock/updateSubscription.xml',$check[6]);
    }

    public function testDeleteSubscription() {
        resetLog();
        $this->object->setMock(true, 'deleteSubscription.xml');
        $this->object->setIsEnabled();
        $this->assertFalse($this->object->deleteSubscription()); //no channel yet
        $this->assertNull($this->object->setDeliveryChannel('SQS'));
        $this->assertFalse($this->object->deleteSubscription()); //no attributes yet
        $this->assertNull($this->object->setAttributes(array('url'=>'google')));
        $this->assertFalse($this->object->deleteSubscription()); //no notification type yet
        $this->assertNull($this->object->setNotificationType('special'));
        $this->assertNull($this->object->deleteSubscription());
        $o = $this->object->getOptions();
        $this->assertEquals('DeleteSubscription', $o['Action']);
        $this->assertArrayNotHasKey('Subscription.Destination.DeliveryChannel', $o);
        $this->assertArrayNotHasKey('Subscription.Destination.AttributeList.member.1.Key', $o);
        $this->assertArrayNotHasKey('Subscription.IsEnabled', $o);
        $this->assertArrayNotHasKey('Subscription.NotificationType', $o);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: deleteSubscription.xml',$check[1]);
        $this->assertEquals('Delivery channel must be set in order to delete a subscription!',$check[2]);
        $this->assertEquals('Attributes must be set in order to delete a subscription!',$check[3]);
        $this->assertEquals('Notification type must be set in order to delete a subscription!',$check[4]);
        $this->assertEquals('Fetched Mock File: mock/deleteSubscription.xml',$check[5]);
    }

    /**
     * @param AmazonSubscription $o
     * @depends testFetchSubscription
     */
    public function testGetSubscription($o) {
        $data = array();
        $data['NotificationType'] = $o->getNotificationType();
        $data['IsEnabled'] = $o->getIsEnabled();
        $data['Destination']['DeliveryChannel'] = $o->getDeliveryChannel();
        $data['Destination']['AttributeList'] = $o->getAttributes();
        $this->assertEquals($data, $o->getSubscription());
        //not fetched yet for this object
        $this->assertFalse($this->object->getSubscription());
    }

    /**
     * @param AmazonSubscription $o
     * @depends testFetchSubscription
     */
    public function testGetNotificationType($o) {
        $this->assertEquals('AnyOfferChanged', $o->getNotificationType());
        //not fetched yet for this object
        $this->assertFalse($this->object->getNotificationType());
    }

    /**
     * @param AmazonSubscription $o
     * @depends testFetchSubscription
     */
    public function testGetIsEnabled($o) {
        $this->assertEquals('true', $o->getIsEnabled());
        //not fetched yet for this object
        $this->assertFalse($this->object->getIsEnabled());
    }

    /**
     * @param AmazonSubscription $o
     * @depends testFetchSubscription
     */
    public function testGetDeliveryChannel($o) {
        $this->assertEquals('SQS', $o->getDeliveryChannel());
        //not fetched yet for this object
        $this->assertFalse($this->object->getDeliveryChannel());
    }

    /**
     * @param AmazonSubscription $o
     * @depends testFetchSubscription
     */
    public function testGetAttributes($o) {
        $data = array(
            'sqsQueueUrl' => 'https://sqs.us-east-1.amazonaws.com/51471EXAMPLE/mws_notifications',
        );
        $this->assertEquals($data, $o->getAttributes());
        //not fetched yet for this object
        $this->assertFalse($this->object->getAttributes());
    }

}

require_once('helperFunctions.php');
