<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * These variables pertain to the inner workings of the Amazon API.
 * The values come from Amazon and should not be modified.
 * Otherwise, the library may not be able to connect to Amazon.
 */

//for User-Agent header(?)
$AMAZON_APPLICATION = 'phpAmazonMWS';
$AMAZON_APPVERSION = '1.0';

//Version numbers for cores
$AMAZON_VERSION_FEEDS       = '2009-01-01';
$AMAZON_VERSION_FINANCE     = '2015-05-01';
$AMAZON_VERSION_INBOUND     = '2010-10-01';
$AMAZON_VERSION_INVENTORY   = '2010-10-01';
$AMAZON_VERSION_MERCHANT    = '2015-06-01';
$AMAZON_VERSION_ORDERS      = '2013-09-01';
$AMAZON_VERSION_OUTBOUND    = '2010-10-01';
$AMAZON_VERSION_PRODUCTS    = '2011-10-01';
$AMAZON_VERSION_RECOMMEND   = '2013-04-01';
$AMAZON_VERSION_REPORTS     = '2009-01-01';
$AMAZON_VERSION_SELLERS     = '2011-07-01';
$AMAZON_VERSION_SUBSCRIBE   = '2013-07-01';

//Amazon Throttle Values in seconds
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
$THROTTLE_LIMIT_INVENTORY = 30;
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
$THROTTLE_LIMIT_FEEDSUBMIT = 15;
$THROTTLE_TIME_FEEDSUBMIT = 120;
//Fetching a Feed List
$THROTTLE_LIMIT_FEEDLIST = 10;
$THROTTLE_TIME_FEEDLIST = 45;
//Getting a Feed
$THROTTLE_LIMIT_FEEDRESULT = 15;
$THROTTLE_TIME_FEEDRESULT = 60;
//Merchant Fulfillments
$THROTTLE_LIMIT_MERCHANT = 10;
$THROTTLE_TIME_MERCHANT = 1;
//Subscriptions
$THROTTLE_LIMIT_SUBSCRIBE = 25;
$THROTTLE_TIME_SUBSCRIBE = 1;
//Recommendations
$THROTTLE_LIMIT_RECOMMEND = 8;
$THROTTLE_TIME_RECOMMEND = 2;
//Recommendations
$THROTTLE_LIMIT_FINANCE = 30;
$THROTTLE_TIME_FINANCE = 2;

?>
