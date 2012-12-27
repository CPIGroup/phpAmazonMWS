<?php

//Service URL Base
//Current setting is United States
define('AMAZON_SERVICE_URL','https://mws.amazonservices.com/');

//for User-Agent header(?)
define('AMAZON_APPLICATION','Athena Amazon Plugin');
define('AMAZON_APPVERSION','0.1');


//Merchant ID for this store
$store['BigKitchen']['merchantId'] = 'AYBHI2AQPIRDU';
//Marketplace ID for this store
$store['BigKitchen']['marketplaceId'] = 'ATVPDKIKX0DER';
//Access Key ID
$store['BigKitchen']['keyId'] = 'AKIAJBQH4G6FKKUAPU6Q';
//Secret Accress Key for this store
$store['BigKitchen']['secretKey'] = 'Ccr8G3kGNxmVi+extfVRrZU9X8+QjLmBJkSraNsC';

//Fake store
$store['bad']['no'] = 'no';


//Version numbers for cores
define('AMAZON_VERSION_FEEDS',     '2009-01-01');
define('AMAZON_VERSION_INBOUND',   '2010-10-01');
define('AMAZON_VERSION_INVENTORY', '2010-10-01');
define('AMAZON_VERSION_ORDERS',    '2011-01-01');
define('AMAZON_VERSION_OUTBOUND',  '2010-10-01');
define('AMAZON_VERSION_PRODUCTS',  '2011-10-01');
define('AMAZON_VERSION_REPORTS',   '2009-01-01');
define('AMAZON_VERSION_SELLERS',   '2011-07-01');


//Location of log file to use
define('AMAZON_LOG','/var/www/athena/plugins/newAmazon/test-cases/log.txt');

//Amazon Throttle Values in seconds
//Do not modify unless Amazon changes the values
//Fetching Orders
define('THROTTLE_LIMIT_ORDER',6);
define('THROTTLE_TIME_ORDER',60);
//Fetching Order Lists
define('THROTTLE_LIMIT_ORDERLIST',6);
define('THROTTLE_TIME_ORDERLIST',60);
//Fetching Items
define('THROTTLE_LIMIT_ITEM',30);
define('THROTTLE_TIME_ITEM',2);
//Fetching Service Status
define('THROTTLE_LIMIT_STATUS',2);
define('THROTTLE_TIME_STATUS',300);
//Fetching Sellers Participation
define('THROTTLE_LIMIT_SELLERS',15);
define('THROTTLE_TIME_SELLERS',60);
//Anything in Inbound/Inventory/Outbound
define('THROTTLE_LIMIT_INVENTORY',30);
define('THROTTLE_TIME_INVENTORY',2);
//Products
define('THROTTLE_LIMIT_PRODUCT',20);
define('THROTTLE_TIME_PRODUCTLIST',5);
define('THROTTLE_TIME_PRODUCTMATCH',1);
define('THROTTLE_TIME_PRODUCTID',4);
define('THROTTLE_TIME_PRODUCTPRICE',2);
//Requesting a Report
define('THROTTLE_LIMIT_REPORTREQUEST',15);
define('THROTTLE_TIME_REPORTREQUEST',60);
//Fetching a Report Request List
define('THROTTLE_LIMIT_REPORTREQUESTLIST',10);
define('THROTTLE_TIME_REPORTREQUESTLIST',45);
//Using a token with a report request
define('THROTTLE_LIMIT_REPORTTOKEN',30);
define('THROTTLE_TIME_REPORTTOKEN',2);
//Fetching a Report List
define('THROTTLE_LIMIT_REPORTLIST',10);
define('THROTTLE_TIME_REPORTLIST',60);
//Fetching a Report
define('THROTTLE_LIMIT_REPORT',15);
define('THROTTLE_TIME_REPORT',60);
//Fetching a Report Request List
define('THROTTLE_LIMIT_REPORTSCHEDULE',10);
define('THROTTLE_TIME_REPORTSCHEDULE',45);
//Submitting a Feed
define('THROTTLE_LIMIT_FEEDSUBMIT',15);
define('THROTTLE_TIME_FEEDSUBMIT',120);
//Getting a Feed
define('THROTTLE_LIMIT_FEEDLIST',10);
define('THROTTLE_TIME_FEEDLIST',45);
//Getting a Feed
define('THROTTLE_LIMIT_FEEDRESULT',15);
define('THROTTLE_TIME_FEEDRESULT',60);

//Safe Throttle Mode
//Automatically throttles every time
define('AMAZON_THROTTLE_SAFE',true);

?>
