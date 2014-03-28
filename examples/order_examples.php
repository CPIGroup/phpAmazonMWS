<?php
die('This is just an example and will not work without proper store credentials.');

/*
 * This script retrieves a list of orders from the store "myStore" and displays various bits of their info.
 */
$list=getAmazonOrders();
if ($list) {
    echo 'My Store Orders<hr>';
    foreach ($list as $order) {
        //these are AmazonOrder objects
        echo '<b>Order Number:</b> '.$order->getAmazonOrderId();
        echo '<br><b>Purchase Date:</b> '.$order->getPurchaseDate();
        echo '<br><b>Status:</b> '.$order->getOrderStatus();
        echo '<br><b>Customer:</b> '.$order->getBuyerName();
        $address=$order->getShippingAddress(); //address is an array
        echo '<br><b>City:</b> '.$address['City'];
        echo '<br><br>';
    }
}

/**
 * This function will retrieve a list of all unshipped MFN orders made within the past 24 hours.
 * The entire list of orders is returned, with each order contained in an AmazonOrder object.
 * Note that the items in the order are not included in the data.
 * To get the order's items, the "fetchItems" method must be used by the specific order object.
 */
function getAmazonOrders() {
    require('../includes/classes.php'); //autoload classes, not needed if composer is being used
    try {
        $amz = new AmazonOrderList("myStore"); //store name matches the array key in the config file
        $amz->setLimits('Modified', "- 24 hours"); //accepts either specific timestamps or relative times 
        $amz->setFulfillmentChannelFilter("MFN"); //no Amazon-fulfilled orders
        $amz->setOrderStatusFilter(
            array("Unshipped", "PartiallyShipped", "Canceled", "Unfulfillable")
            ); //no shipped or pending orders
        $amz->setUseToken(); //tells the object to automatically use tokens right away
        $amz->fetchOrders(); //this is what actually sends the request
        return $amz->getList();
    } catch (Exception $ex) {
        echo 'There was a problem with the Amazon library. Error: '.$ex->getMessage();
    }
}

?>
