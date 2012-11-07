<?php

//Service URL Base
//Current setting is United States
$serviceURL = 'https://mws.amazonservices.com/';

//for User-Agent header
$applicationName = 'Athena Amazon Plugin';
$applicationVersion = '0.1';


//Full name of store, for reporting purposes
$store['BigKitchen']['name'] = 'Big Kitchen';
//Merchant ID for this store
$store['BigKitchen']['merchantId'] = 'MERCHANTID';
//Marketplace ID for this store
$store['BigKitchen']['marketplaceId'] = 'MARKETPLACEID';
//Access Key ID
$store['BigKitchen']['keyId'] = 'KEYGOESHERE';
//Secret Accress Key for this store
$store['BigKitchen']['secretKey'] = 'SECRET';


//Amazon Throttle Values
//Do not modify unless Amazon changes the values
//Fetching Orders
$throttleLimitOrder = 6;
$throttleTimeOrder = 60;
//Fetching Items
$throttleLimitItem = 30;
$throttleTimeItem = 2;

//Safe Throttle Mode
//Adds extra second onto throttle times to ensure service does not fail
//due to errors in timing
$throttleSafe = false;

?>
