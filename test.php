<?php
include('/var/www/athena/includes/header.php');
        
include('includes/classes.php');

$a = new AmazonOrderList('BigKitchen');
$a->setFetchItems();
$a->setUseItemToken();
//$a->setFulfillmentChannelFilter('AFN');
$a->setLimits('Modified','-1 hours');
$a->fetchOrders();

//$a = new AmazonOrder('BigKitchen');
//$a->setOrderId('106-2655952-6625846');
//$a->setFetchItems();
//$a->fetchOrder();

include('/var/www/athena/includes/footer.php');

?>