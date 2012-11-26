<?php
include('/var/www/athena/includes/header.php');
        
include('includes/classes.php');

//$a = new AmazonOrderList('BigKitchen');
//$a->setFetchItems();
//$a->setUseItemToken();
////$a->setFulfillmentChannelFilter('AFN');
//$a->setLimits('Modified','-1 hours');
//$a->fetchOrders();

$a = new AmazonShipmentList('BigKitchen',true,'shiplist.xml');
$a->setTimeLimits();
$a->setIdFilter('Order #5');
$a->fetchShipments();
$b = new AmazonShipmentItemList('BigKitchen','5',true,'shipitems.xml');
$b->fetchItems();
var_dump($b);

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