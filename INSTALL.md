## Installing
To install, simply add the library to your project. Composer is the default installation tool for this library.
If you do not use Composer for your project, you can still auto-load classes by  including the file **includes/classes.php** in the page or function.

Before you use any commands,  you need to create an **amazon-config.php** file with your account credentials. Start by copying the template provided (*amazon-config.default.php*) and renaming the file.

If you are operating outside of the United States, be sure to change the Amazon Service URL to the one matching your region.

You can also link the built-in logging system to your own system by putting the logging function's name in the *$logfunction* parameter.

The default location for the built-in log file is in the library's main directory. In the event that PHP does not have the correct permissions to create a file in there, you will have to create the log file as "log.txt" and give PHP permission to edit it.

## Usage
All of the technical details required by the API are handled behind the scenes,
so users can easily build code for sending requests to Amazon
without having to jump hurdles such as parameter URL formatting and token management. 
The general work flow for using one of the objects is this:

1. Create an object for the task you need to perform.
2. Load it up with parameters, depending on the object, using *set____* methods.
3. Submit the request to Amazon. The methods to do this are usually named *fetch____* or *submit____* and have no parameters.
4. Reference the returned data, whether as single values or in bulk, using *get____* methods.
5. Monitor the performance of the library using the built-in logging system.

Note that if you want to act on more than one Amazon store, you will need a separate object for each store.

Also note that the objects perform best when they are not treated as reusable. Otherwise, you may end up grabbing old response data if a new request fails.

## Examples
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
