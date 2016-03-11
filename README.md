phpAmazonMWS
============

[![Build Status](https://travis-ci.org/CPIGroup/phpAmazonMWS.svg?branch=stable)](https://travis-ci.org/CPIGroup/phpAmazonMWS)

A library to connect to Amazon's Merchant Web Services (MWS) in an object-oriented manner, with a focus on intuitive usage.  

This is __NOT__ for Amazon Web Services (AWS) - Cloud Computing Services.


## Example Usage
Here are a couple of examples of the library in use.
All of the technical details required by the API are handled behind the scenes,
so users can easily build code for sending requests to Amazon
without having to jump hurdles such as parameter URL formatting and token management. 

Here is an example of a function used to get all warehouse-fulfilled orders from Amazon updated in the past 24 hours:
```php
function getAmazonOrders() {
    $amz = new AmazonOrderList("myStore"); //store name matches the array key in the config file
    $amz->setLimits('Modified', "- 24 hours");
    $amz->setFulfillmentChannelFilter("MFN"); //no Amazon-fulfilled orders
    $amz->setOrderStatusFilter(
        array("Unshipped", "PartiallyShipped", "Canceled", "Unfulfillable")
        ); //no shipped or pending
    $amz->setUseToken(); //Amazon sends orders 100 at a time, but we want them all
    $amz->fetchOrders();
    return $amz->getList();
}
```
This example shows a function used to send a previously-created XML feed to Amazon to update Inventory numbers:
```php
function sendInventoryFeed($feed) {
    $amz=new AmazonFeed(); //if there is only one store in config, it can be omitted
    $amz->setFeedType("_POST_INVENTORY_AVAILABILITY_DATA_"); //feed types listed in documentation
    $amz->setFeedContent($feed);
    $amz->submitFeed();
    return $amz->getResponse();
}
```
