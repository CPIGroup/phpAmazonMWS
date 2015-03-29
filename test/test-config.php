<?php

//Merchant ID for this store
$store['testStore']['merchantId'] = 'T_M_GOOD_83835495';
//Marketplace ID for this store
$store['testStore']['marketplaceId'] = 'ATVPDKIKX0DER';
//Access Key ID
$store['testStore']['keyId'] = 'key';
//Secret Accress Key for this store
$store['testStore']['secretKey'] = 'secret';

//Fake store
$store['bad']['no'] = 'no';

//Service URL Base
//Current setting is United States
$AMAZON_SERVICE_URL = 'https://mws.amazonservices.com/';

//for User-Agent header(?)
$AMAZON_APPLICATION = 'phpAmazonMWS';
$AMAZON_APPVERSION = '1.0';


//Version numbers for cores
$AMAZON_VERSION_FEEDS       = '2009-01-01';
$AMAZON_VERSION_INBOUND     = '2010-10-01';
$AMAZON_VERSION_INVENTORY   = '2010-10-01';
$AMAZON_VERSION_ORDERS      = '2011-01-01';
$AMAZON_VERSION_OUTBOUND    = '2010-10-01';
$AMAZON_VERSION_PRODUCTS    = '2011-10-01';
$AMAZON_VERSION_REPORTS     = '2009-01-01';
$AMAZON_VERSION_SELLERS     = '2011-07-01';

//Location of log file to use
$logpath = __DIR__.'/log.txt';

//Name of custom log function to use
$logfunction = '';

//Turn off normal logging
$muteLog = false;

//Amazon Throttle Values in seconds
//Do not modify unless Amazon changes the values
//Fetching Orders
$THROTTLE_LIMIT_ORDER = 6;
$THROTTLE_TIME_ORDER = 60;
//Fetching Order Lists
$THROTTLE_LIMIT_ORDERLIST = 6;
$THROTTLE_TIME_ORDERLIST = 60;
//Fetching Items
$THROTTLE_LIMIT_ITEM = 30;
$THROTTLE_TIME_ITEM = 2;
//Fetching Service Status
$THROTTLE_LIMIT_STATUS = 2;
$THROTTLE_TIME_STATUS = 300;
//Fetching Sellers Participation
$THROTTLE_LIMIT_SELLERS = 15;
$THROTTLE_TIME_SELLERS = 60;
//Anything in Inbound/Inventory/Outbound
$THROTTLE_TIME_INVENTORY = 30;
$THROTTLE_TIME_INVENTORY = 2;
//Products
$THROTTLE_LIMIT_PRODUCT = 20;
$THROTTLE_TIME_PRODUCTLIST = 5;
$THROTTLE_TIME_PRODUCTMATCH = 1;
$THROTTLE_TIME_PRODUCTID = 4;
$THROTTLE_TIME_PRODUCTPRICE = 2;
//Requesting a Report
$THROTTLE_LIMIT_REPORTREQUEST = 15;
$THROTTLE_TIME_REPORTREQUEST = 60;
//Fetching a Report Request List
$THROTTLE_LIMIT_REPORTREQUESTLIST = 10;
$THROTTLE_TIME_REPORTREQUESTLIST = 45;
//Using a token with a report request
$THROTTLE_LIMIT_REPORTTOKEN = 30;
$THROTTLE_TIME_REPORTTOKEN = 2;
//Fetching a Report List
$THROTTLE_LIMIT_REPORTLIST = 10;
$THROTTLE_TIME_REPORTLIST = 60;
//Fetching a Report
$THROTTLE_LIMIT_REPORT = 15;
$THROTTLE_TIME_REPORT = 60;
//Fetching a Report Request List
$THROTTLE_LIMIT_REPORTSCHEDULE = 10;
$THROTTLE_TIME_REPORTSCHEDULE = 45;
//Submitting a Feed
$THROTTLE_TIME_FEEDSUBMIT = 15;
$THROTTLE_TIME_FEEDSUBMIT = 120;
//Getting a Feed
$THROTTLE_LIMIT_FEEDLIST = 10;
$THROTTLE_TIME_FEEDLIST = 45;
//Getting a Feed
$THROTTLE_LIMIT_FEEDRESULT = 15;
$THROTTLE_TIME_FEEDRESULT = 60;

?>
