<?php
include('/var/www/athena/includes/header.php');
        
include('includes/classes.php');

//$a = new AmazonOrderList('BigKitchen');
//$a->setFetchItems();
//$a->setUseItemToken();
////$a->setFulfillmentChannelFilter('AFN');
//$a->setLimits('Modified','-1 hours');
//$a->fetchOrders();

//$a = new AmazonParticipationList('BigKitchen');
$a = new AmazonParticipationList('BigKitchen',true,array('mocky.xml','mocky2.xml'));
$a->setUseToken();
$a->fetchParticipationList();
//var_dump ($a);
echo 'First reply: ';
var_dump($a->getSellerId(0));
echo 'Token reply: ';
var_dump($a->getSellerId(1));

//$a = new AmazonOrder('BigKitchen');
//$a->setOrderId('106-2655952-6625846');
//$a->setFetchItems();
//$a->fetchOrder();

include('/var/www/athena/includes/footer.php');

?>