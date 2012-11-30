<?php

//Service URL Base
//Current setting is United States
$serviceURL = 'https://mws.amazonservices.com/';
//$serviceURL = 'http://localhost/';

//for User-Agent header
$applicationName = 'Athena Amazon Plugin';
$applicationVersion = '0.1';


//Merchant ID for this store
$store['BigKitchen']['merchantId'] = 'AYBHI2AQPIRDU';
//Marketplace ID for this store
$store['BigKitchen']['marketplaceId'] = 'ATVPDKIKX0DER';
//Access Key ID
$store['BigKitchen']['keyId'] = 'AKIAJBQH4G6FKKUAPU6Q';
//Secret Accress Key for this store
$store['BigKitchen']['secretKey'] = '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';


//Location of log file to use
$logpath = 'log.txt';

//Amazon Throttle Values in seconds
//Do not modify unless Amazon changes the values
//Fetching Orders
$throttleLimitOrder = 6;
$throttleTimeOrder = 60;
//Fetching Order Lists
$throttleLimitOrderList = 6;
$throttleTimeOrderList = 60;
//Fetching Items
$throttleLimitItem = 30;
$throttleTimeItem = 2;
//Fetching Service Status
$throttleLimitStatus = 2;
$throttleTimeStatus = 300;
//Fetching Sellers Participation
$throttleLimitSellers = 15;
$throttleTimeSellers = 60;
//Anything in Inbound/Inventory/Outbound
$throttleLimitInventory = 30;
$throttleTimeInventory = 2;
//Products
$throttleLimitProduct = 20;
$throttleTimeProductList = 5;
$throttleTimeProductMatch = 1;
$throttleTimeProductId = 4;
$throttleTimeProductPrice = 2;
//Requesting a Report
$throttleLimitReportRequest = 15;
$throttleTimeReportRequest = 60;
//Fetching a Report Request List
$throttleLimitReportRequestList = 10;
$throttleTimeReportRequestList = 45;
//Using a token with a report request
$throttleLimitReportToken = 30;
$throttleTimeReportToken = 2;
//Fetching a Report List
$throttleLimitReportList = 10;
$throttleTimeReportList = 60;
//Requesting a Report
$throttleLimitReport = 15;
$throttleTimeReport = 60;
//Fetching a Report Request List
$throttleLimitReportSchedule = 10;
$throttleTimeReportSchedule = 45;
//Submitting a Feed
$throttleLimitFeedSubmit = 15;
$throttleTimeFeedSubmit = 120;
//Getting a Feed
$throttleLimitFeedList = 10;
$throttleTimeFeedList = 45;
//Getting a Feed
$throttleLimitFeedResult = 15;
$throttleTimeFeedResult = 60;

//Safe Throttle Mode
//Adds extra second onto throttle times to ensure service does not fail
//due to errors in timing
$throttleSafe = false;

?>
