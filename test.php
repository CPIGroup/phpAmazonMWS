<?php
include('/var/www/athena/includes/header.php');
        
include('includes/classes.php');

//$a = new AmazonOrderList('BigKitchen');
//$a->setFetchItems();
//$a->setUseItemToken();
////$a->setFulfillmentChannelFilter('AFN');
//$a->setLimits('Modified','-1 hours');
//$a->fetchOrders();

$a = new AmazonReportRequestCounter('BigKitchen',true,'requestcount.xml');
$a->fetchCount();
myPrint($a);

//$a = new AmazonReportRequestList('BigKitchen',true,array('requestlist.xml','requestlist2.xml'));
//$a->setUseToken();
//$a->fetchReportList();
//myPrint($a);

//$a = new AmazonPackageTracker('BigKitchen','5',true,'package.xml');
//$a->fetchTrackingDetails();
//myPrint($a);

//$a = new AmazonFulfillmentOrderList('BigKitchen',true,'fulfillmentlist.xml');
//$a->fetchOrderList();
//myPrint($a);

//$a = new AmazonFulfillmentOrder('BigKitchen',5,true,array('fulfillmentorder.xml',200));
//$a->fetchOrder();
//$a->cancelOrder();
//myPrint($a);

//$a = new AmazonFulfillmentOrderCreator('BigKitchen',true,'200');
//$a->setFulfillmentOrderId('test');
//$a->setDisplayableOrderId('two');
//$a->setDate('-5 minutes');
//$a->setComment('comment');
//$a->setShippingSpeed('Standard');
//$a->setAddress(array('Name'=>'bob'));
//$a->setItems(array(array('SellerSKU'=>'123','Quantity'=>'99','SellerFulfillmentOrderItemId'=>5)));
//$a->createOrder();
//myPrint($a);

//$a = new AmazonFulfillmentPreview('BigKitchen',true,'preview.xml');
//$a->setAddress(array());
//$a->setItems(array(array('SellerSKU'=>0,'Quantity'=>0)));
//$a->fetchPreview();
//myPrint($a);

//$a = new AmazonShipmentList('BigKitchen',true,'shiplist.xml');
//$a->setTimeLimits();
//$a->setIdFilter('Order #5');
//$a->fetchShipments();
//$b = new AmazonShipmentItemList('BigKitchen','5',true,'shipitems.xml');
//$b->fetchItems();
//var_dump($b);

//$a = new AmazonShipmentPlanner('BigKitchen',true,'plan.xml');
//$address = array('Name'=>'Nameo','AddressLine1'=>'Nameworld');
//$a->setAddress($address);
//$things = array(array('SellerSKU' => '123', 'Quantity' => '4'));
//$a->setItems($things);
//$a->fetchPlan();
//var_dump($a->getShipmentId());
//$b = new AmazonShipment('BigKitchen',true,'createship.xml');
//$b->usePlan($a->getPlan(0));
//$b->setShipmentId('FBA63JX44');
//$b->createShipment();
//var_dump($a);
//var_dump($b);

////$a = new AmazonParticipationList('BigKitchen');
//$a = new AmazonParticipationList('BigKitchen',true,array('mocky.xml','mocky2.xml'));
//$a->setUseToken();
//$a->fetchParticipationList();
////var_dump ($a);
//echo 'First reply: ';
//var_dump($a->getSellerId(0));
//echo 'Token reply: ';
//var_dump($a->getSellerId(1));

//$a = new AmazonInventoryList('BigKitchen');
//$a->setResponseGroup('Detailed');
//$a->fetchInventoryList();

//$a = new AmazonOrder('BigKitchen');
//$a->setOrderId('106-2655952-6625846');
//$a->setFetchItems();
//$a->fetchOrder();

include('/var/www/athena/includes/footer.php');

?>