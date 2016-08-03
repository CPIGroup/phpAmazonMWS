<?php

//Merchant ID for this store
$store['testStore']['merchantId'] = 'T_M_GOOD_83835495';
//Marketplace ID for this store
$store['testStore']['marketplaceId'] = 'ATVPDKIKX0DER';
//Access Key ID
$store['testStore']['keyId'] = 'key';
//Secret Accress Key for this store
$store['testStore']['secretKey'] = 'secret';
//token needed for web apps and third-party developers
$store['testStore']['MWSAuthToken'] = 'secret';

//Fake store
$store['bad']['no'] = 'no';

//Service URL Base
//Current setting is United States
$AMAZON_SERVICE_URL = 'https://mws.amazonservices.com/';

//Location of log file to use
$logpath = __DIR__.'/log.txt';

//Name of custom log function to use
$logfunction = '';

//Turn off normal logging
$muteLog = false;

?>
