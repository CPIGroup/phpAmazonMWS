<?php
die('This is just an example and will not work without proper store credentials.');

/*
 * This script retrieves a list of recently changed item supply info for the store "myStore" and display some of it.
 */
$list=getAmazonSupply();
if ($list) {
    echo 'Amazon Inventory<hr>';
    foreach ($list as $item) {
        //these are arrays
        echo '<b>Item SKU:</b> '.$item['SellerSKU'];
        echo '<br><b>Condition:</b> '.$item['Condition'];
        echo '<br><b>In Stock:</b> '.$item['InStockSupplyQuantity'];
        echo '<br><br>';
    }
}

/**
 * This function will retrieve a list of all items with quantity that was adjusted within the past 24 hours.
 * The entire list of items is returned, with each item contained in an array.
 */
function getAmazonSupply(){
    require('../includes/classes.php'); //autoload classes, not needed if composer is being used
    try {
        $obj = new AmazonInventoryList("myStore"); //store name matches the array key in the config file
        $obj->setUseToken(); //tells the object to automatically use tokens right away
        $obj->setStartTime("- 24 hours");
        $obj->fetchInventoryList(); //this is what actually sends the request
        return $obj->getSupply();
    } catch (Exception $ex) {
        echo 'There was a problem with the Amazon library. Error: '.$ex->getMessage();
    }
}


?>
