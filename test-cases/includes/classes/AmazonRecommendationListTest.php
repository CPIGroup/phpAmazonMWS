<?php

class AmazonRecommendationListTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonRecommendationList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonRecommendationList('testStore', true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetUseToken(){
        $this->assertNull($this->object->setUseToken());
        $this->assertNull($this->object->setUseToken(true));
        $this->assertNull($this->object->setUseToken(false));
        $this->assertFalse($this->object->setUseToken('wrong'));
    }

    public function testSetCategory() {
        $this->assertNull($this->object->setCategory('Inventory'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('RecommendationCategory', $o);
        $this->assertEquals('Inventory', $o['RecommendationCategory']);
        $this->assertFalse($this->object->setCategory(77)); //won't work for numbers
        $this->assertFalse($this->object->setCategory(array())); //won't work for this
        $this->assertFalse($this->object->setCategory(null)); //won't work for other things
    }

    public function testSetFilters() {
        $this->assertFalse($this->object->setFilter(null)); //can't be nothing
        $this->assertFalse($this->object->setFilter(5)); //can't be an int
        $this->assertFalse($this->object->setFilter('banana')); //can't be an string

        $list = array();
        $list['Inventory'] = array('first' => 'worst');
        $list['Pricing'] = array('second' => 'best', 'third' => 'other');
        $this->assertNull($this->object->setFilter($list));

        $pre = 'CategoryQueryList.CategoryQuery.';
        $cat = '.RecommendationCategory';
        $fil = '.FilterOptions.FilterOption.';

        $o = $this->object->getOptions();
        $this->assertArrayHasKey($pre.'1'.$cat, $o);
        $this->assertEquals('Inventory', $o[$pre.'1'.$cat]);
        $this->assertArrayHasKey($pre.'1'.$fil.'1', $o);
        $this->assertEquals('first=worst', $o[$pre.'1'.$fil.'1']);
        $this->assertArrayHasKey($pre.'2'.$cat, $o);
        $this->assertEquals('Pricing', $o[$pre.'2'.$cat]);
        $this->assertArrayHasKey($pre.'2'.$fil.'1', $o);
        $this->assertEquals('second=best', $o[$pre.'2'.$fil.'1']);
        $this->assertArrayHasKey($pre.'2'.$fil.'2', $o);
        $this->assertEquals('third=other', $o[$pre.'2'.$fil.'2']);

        $list2 = array();
        $list2['Advertising'] = array('different' => 'good');
        $this->assertNull($this->object->setFilter($list2)); //will cause reset
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey($pre.'1'.$cat, $o2);
        $this->assertEquals('Advertising', $o2[$pre.'1'.$cat]);
        $this->assertArrayHasKey($pre.'1'.$fil.'1', $o2);
        $this->assertEquals('different=good', $o2[$pre.'1'.$fil.'1']);
        $this->assertArrayNotHasKey($pre.'2'.$cat, $o2);
        $this->assertArrayNotHasKey($pre.'2'.$fil.'1', $o2);
        $this->assertArrayNotHasKey($pre.'2'.$fil.'2', $o2);

        $this->object->resetFilters();
        $o3 = $this->object->getOptions();
        $this->assertArrayNotHasKey($pre.'1'.$cat, $o3);
        $this->assertArrayNotHasKey($pre.'1'.$fil.'1', $o3);
    }

    public function testFetchRecommendations() {
        resetLog();
        $this->object->setMock(true, 'fetchRecommendations.xml'); //no token
        $this->assertNull($this->object->fetchRecommendations());

        $o = $this->object->getOptions();
        $this->assertEquals('ListRecommendations', $o['Action']);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchRecommendations.xml', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchRecommendations.xml', $check[2]);

        $this->assertFalse($this->object->hasToken());

        $this->assertFalse($this->object->getLastUpdateTimes());
        return $this->object;
    }

    public function testFetchRecommendationsToken1() {
        resetLog();
        $this->object->setMock(true, 'fetchRecommendationsToken.xml'); //no token
        //without using token
        $this->assertNull($this->object->fetchRecommendations());
        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchRecommendationsToken.xml', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchRecommendationsToken.xml', $check[2]);

        $this->assertTrue($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListRecommendations', $o['Action']);
        $r = $this->object->getLists();
        $this->assertInternalType('array', $r);
        $this->assertArrayHasKey('ListingQuality', $r);
        $this->assertArrayNotHasKey('Selection', $r);
        $this->assertEquals(1, count($r));
        $this->assertArrayHasKey(0, $r['ListingQuality']);
        $this->assertArrayHasKey(1, $r['ListingQuality']);
        $this->assertEquals(2, count($r['ListingQuality']));
    }

    public function testFetchRecommendationsToken2() {
        resetLog();
        $this->object->setMock(true, array('fetchRecommendationsToken.xml', 'fetchRecommendationsToken2.xml'));

        //with using token
        $this->object->setUseToken();
        $this->object->setCategory('Selection');
        $this->assertNull($this->object->fetchRecommendations());
        $check = parseLog();
        $this->assertEquals('Mock files array set.', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchRecommendationsToken.xml', $check[2]);
        $this->assertEquals('Recursively fetching more recommendations', $check[3]);
        $this->assertEquals('Fetched Mock File: mock/fetchRecommendationsToken2.xml', $check[4]);
        $this->assertFalse($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListRecommendationsByNextToken', $o['Action']);
        $this->assertArrayNotHasKey('RecommendationCategory', $o);
        $r = $this->object->getLists();
        $this->assertInternalType('array', $r);
        $this->assertArrayHasKey('ListingQuality', $r);
        $this->assertArrayHasKey('Selection', $r);
        $this->assertEquals(2, count($r));
        $this->assertArrayHasKey(0, $r['ListingQuality']);
        $this->assertArrayHasKey(1, $r['ListingQuality']);
        $this->assertEquals(2, count($r['ListingQuality']));
        $this->assertArrayHasKey(0, $r['Selection']);
        $this->assertArrayHasKey(1, $r['Selection']);
        $this->assertEquals(2, count($r['Selection']));
        $this->assertNotEquals($r['ListingQuality'][0], $r['ListingQuality'][1]);
        $this->assertNotEquals($r['Selection'][0], $r['Selection'][1]);
        $this->assertNotEquals($r['ListingQuality'][0], $r['Selection'][0]);
        $this->assertNotEquals($r['ListingQuality'][1], $r['Selection'][1]);
    }

    public function testFetchLastUpdateTimes() {
        resetLog();
        $this->object->setMock(true, 'fetchRecommendationTimes.xml');
        $this->assertNull($this->object->fetchLastUpdateTimes());
        $o = $this->object->getOptions();
        $this->assertEquals('GetLastUpdatedTimeForRecommendations', $o['Action']);

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchRecommendationTimes.xml',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchRecommendationTimes.xml',$check[2]);

        $this->assertFalse($this->object->getLists());
        return $this->object;
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetLists($o) {
        $list = $o->getLists();
        $this->assertInternalType('array', $list);
        $this->assertArrayHasKey('Inventory', $list);
        $this->assertEquals($o->getInventoryList(), $list['Inventory']);
        $this->assertArrayHasKey('Selection', $list);
        $this->assertEquals($o->getSelectionList(), $list['Selection']);
        $this->assertArrayHasKey('Pricing', $list);
        $this->assertEquals($o->getPricingList(), $list['Pricing']);
        $this->assertArrayHasKey('Fulfillment', $list);
        $this->assertEquals($o->getFulfillmentList(), $list['Fulfillment']);
        $this->assertArrayHasKey('ListingQuality', $list);
        $this->assertEquals($o->getListingList(), $list['ListingQuality']);
        $this->assertArrayHasKey('GlobalSelling', $list);
        $this->assertEquals($o->getGlobalSellingList(), $list['GlobalSelling']);
        $this->assertArrayHasKey('Advertising', $list);
        $this->assertEquals($o->getAdvertisingList(), $list['Advertising']);
        //not fetched yet for this object
        $this->assertFalse($this->object->getLists());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetInventoryList($o) {
        $x = array();
        $x[0]['RecommendationReason'] = 'OutOfStock';
        $x[0]['SalesRank'] = '186020';
        $x[0]['ItemName'] = 'Water Bottle';
        $x[0]['ProductCategory'] = 'Sports';
        $x[0]['LastUpdated'] = '2013-03-05T00:00:00Z';
        $x[0]['ItemIdentifier']['ASIN'] = 'B008YFV5P2';
        $x[0]['ItemIdentifier']['SKU'] = 'EZ';
        $x[0]['ItemIdentifier']['UPC'] = '885911242721';
        $x[0]['BrandName'] = 'DEWALT';
        $x[0]['RecommendationId'] = '123-inv1';
        $x[0]['AvailableQuantity'] = '1';
        $x[0]['DaysUntilStockRunsOut'] = '0';
        $x[1]['RecommendationReason'] = 'TooMuchStock';
        $x[1]['ItemName'] = 'DEWALT Ratcheting Screwdriv';
        $x[1]['ProductCategory'] = 'Home Improvement';
        $x[1]['LastUpdated'] = '2013-03-07T00:00:00Z';
        $x[1]['ItemIdentifier']['ASIN'] = 'B006TVOX98';
        $x[1]['ItemIdentifier']['SKU'] = 'ER';
        $x[1]['ItemIdentifier']['UPC'] = '076174692334';
        $x[1]['BrandName'] = 'DEWALT';
        $x[1]['RecommendationId'] = '123-inv2';
        $x[1]['AvailableQuantity'] = '7853';
        $x[1]['DaysUntilStockRunsOut'] = '500';
        $x[1]['InboundQuantity'] = '300';

        $list = $o->getInventoryList();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getInventoryList());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetSelectionList($o) {
        $x = array();
        $x[0]['RecommendationReason'] = 'PopularOutOfStock';
        $x[0]['SalesRank'] = '186020';
        $x[0]['ItemName'] = 'Factory-Reconditioned';
        $x[0]['ProductCategory'] = 'Home Improvement';
        $x[0]['LastUpdated'] = '2013-03-06T00:00:00Z';
        $x[0]['NumberOfCustomerReviews'] = '0';
        $x[0]['ItemIdentifier']['ASIN'] = 'B008YFV5P2';
        $x[0]['ItemIdentifier']['SKU'] = 'EZ';
        $x[0]['ItemIdentifier']['UPC'] = '885911242721';
        $x[0]['BrandName'] = 'DEWALT';
        $x[0]['BuyboxPrice']['Amount'] = '245.5';
        $x[0]['BuyboxPrice']['CurrencyCode'] = 'USD';
        $x[0]['RecommendationId'] = '123-select1';
        $x[0]['NumberOfOffers'] = '2';
        $x[1]['RecommendationReason'] = 'UnpopularOutOfStock';
        $x[1]['ItemName'] = 'DEWALT Ratcheting Screwdriv';
        $x[1]['ProductCategory'] = 'Home Improvement';
        $x[1]['LastUpdated'] = '2013-03-08T00:00:00Z';
        $x[1]['ItemIdentifier']['ASIN'] = 'B006TVOX98';
        $x[1]['ItemIdentifier']['SKU'] = 'ER';
        $x[1]['ItemIdentifier']['UPC'] = '076174692334';
        $x[1]['BrandName'] = 'DEWALT';
        $x[1]['BuyboxPrice']['Amount'] = '15.99';
        $x[1]['BuyboxPrice']['CurrencyCode'] = 'USD';
        $x[1]['NumberOfCustomerReviews'] = '0';
        $x[1]['AverageCustomerReview'] = '0.0';
        $x[1]['RecommendationId'] = '123-select2';
        $x[1]['NumberOfOffers'] = '2';

        $list = $o->getSelectionList();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getSelectionList());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetPricingList($o) {
        $x = array();
        $x[0]['RecommendationReason'] = 'TooExpensive';
        $x[0]['SalesRank'] = '186020';
        $x[0]['ItemName'] = 'Factory-Reconditioned';
        $x[0]['ProductCategory'] = 'Home Improvement';
        $x[0]['LastUpdated'] = '2013-03-05T00:00:00Z';
        $x[0]['ItemIdentifier']['ASIN'] = 'B008YFV5P2';
        $x[0]['ItemIdentifier']['SKU'] = 'EZ';
        $x[0]['ItemIdentifier']['UPC'] = '885911242721';
        $x[0]['BrandName'] = 'DEWALT';
        $x[0]['Condition'] = 'Newish';
        $x[0]['YourPricePlusShipping']['Amount'] = '245.5';
        $x[0]['YourPricePlusShipping']['CurrencyCode'] = 'USD';
        $x[0]['RecommendationId'] = '123-price1';
        $x[1]['RecommendationReason'] = 'TooCheap';
        $x[1]['ItemName'] = 'DEWALT Ratcheting Screwdriv';
        $x[1]['ProductCategory'] = 'Home Improvement';
        $x[1]['LastUpdated'] = '2013-03-06T00:00:00Z';
        $x[1]['ItemIdentifier']['ASIN'] = 'B006TVOX98';
        $x[1]['ItemIdentifier']['SKU'] = 'ER';
        $x[1]['ItemIdentifier']['UPC'] = '076174692334';
        $x[1]['BrandName'] = 'DEWALT';
        $x[1]['Condition'] = 'New';
        $x[1]['SubCondition'] = 'Newish';
        $x[1]['MedianPricePlusShipping']['Amount'] = '15.99';
        $x[1]['MedianPricePlusShipping']['CurrencyCode'] = 'USD';
        $x[1]['RecommendationId'] = '123-price2';
        $x[1]['NumberOfOffers'] = '5';

        $list = $o->getPricingList();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getPricingList());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetFulfillmentList($o) {
        $x = array();
        $x[0]['RecommendationReason'] = 'PopularOutOfStock';
        $x[0]['SalesRank'] = '186020';
        $x[0]['ItemName'] = 'Factory-Reconditioned';
        $x[0]['ProductCategory'] = 'Home Improvement';
        $x[0]['LastUpdated'] = '2013-03-05T00:00:00Z';
        $x[0]['ItemIdentifier']['ASIN'] = 'B008YFV5P2';
        $x[0]['ItemIdentifier']['SKU'] = 'EZ';
        $x[0]['ItemIdentifier']['UPC'] = '885911242721';
        $x[0]['BrandName'] = 'DEWALT';
        $x[0]['BuyboxPrice']['Amount'] = '245.5';
        $x[0]['BuyboxPrice']['CurrencyCode'] = 'USD';
        $x[0]['RecommendationId'] = '123-fulfil1';
        $x[0]['NumberOfCustomerReviews'] = '4';
        $x[1]['RecommendationReason'] = 'UnpopularOutOfStock';
        $x[1]['ItemName'] = 'DEWALT Ratcheting Screwdriv';
        $x[1]['ProductCategory'] = 'Home Improvement';
        $x[1]['LastUpdated'] = '2013-03-10T00:00:00Z';
        $x[1]['ItemIdentifier']['ASIN'] = 'B006TVOX98';
        $x[1]['ItemIdentifier']['SKU'] = 'ER';
        $x[1]['ItemIdentifier']['UPC'] = '076174692334';
        $x[1]['BrandName'] = 'DEWALT';
        $x[1]['BuyboxPrice']['Amount'] = '15.99';
        $x[1]['BuyboxPrice']['CurrencyCode'] = 'USD';
        $x[1]['ItemDimensions']['Height']['Value'] = '7';
        $x[1]['ItemDimensions']['Height']['Unit'] = 'cm';
        $x[1]['ItemDimensions']['Width']['Value'] = '6';
        $x[1]['ItemDimensions']['Width']['Unit'] = 'cm';
        $x[1]['ItemDimensions']['Length']['Value'] = '5';
        $x[1]['ItemDimensions']['Length']['Unit'] = 'cm';
        $x[1]['ItemDimensions']['Weight']['Value'] = '40';
        $x[1]['ItemDimensions']['Weight']['Unit'] = 'kg';
        $x[1]['RecommendationId'] = '123-fulfil2';
        $x[1]['NumberOfCustomerReviews'] = '5';

        $list = $o->getFulfillmentList();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getFulfillmentList());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetListingList($o) {
        $x = array();
        $x[0]['RecommendationReason'] = 'Description is important.';
        $x[0]['ItemName'] = 'Nike II';
        $x[0]['DefectAttribute'] = 'product_description';
        $x[0]['QualitySet'] = 'DEFECT';
        $x[0]['RecommendationId'] = '123-quality1';
        $x[0]['DefectGroup'] = 'Missing Description and Bullets';
        $x[0]['ItemIdentifier']['ASIN'] = 'BT00I3X7F0';
        $x[0]['ItemIdentifier']['SKU'] = 'ldr-core';
        $x[0]['ItemIdentifier']['UPC'] = '';
        $x[1]['RecommendationReason'] = 'Images can make your product more attractive.';
        $x[1]['ItemName'] = 'Pants Apparel';
        $x[1]['DefectAttribute'] = 'image';
        $x[1]['QualitySet'] = 'QUARANTINE';
        $x[1]['RecommendationId'] = '123-quality2';
        $x[1]['DefectGroup'] = 'Image';
        $x[1]['ItemIdentifier']['ASIN'] = 'BT00HKTWE4';
        $x[1]['ItemIdentifier']['SKU'] = 'ldr-quarantine';
        $x[1]['ItemIdentifier']['UPC'] = '';

        $list = $o->getListingList();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getListingList());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetGlobalSellingList($o) {
        $x = array();
        $x[0]['RecommendationReason'] = 'PopularOutOfStock';
        $x[0]['SalesRank'] = '186020';
        $x[0]['ItemName'] = 'Factory-Reconditioned';
        $x[0]['ProductCategory'] = 'Home Improvement';
        $x[0]['LastUpdated'] = '2013-03-05T00:00:00Z';
        $x[0]['ItemIdentifier']['ASIN'] = 'B008YFV5P2';
        $x[0]['ItemIdentifier']['SKU'] = 'EZ';
        $x[0]['ItemIdentifier']['UPC'] = '885911242721';
        $x[0]['BrandName'] = 'DEWALT';
        $x[0]['BuyboxPrice']['Amount'] = '245.5';
        $x[0]['BuyboxPrice']['CurrencyCode'] = 'USD';
        $x[0]['RecommendationId'] = '123-global1';
        $x[0]['ItemDimensions']['Height']['Value'] = '7';
        $x[0]['ItemDimensions']['Height']['Unit'] = 'cm';
        $x[0]['ItemDimensions']['Width']['Value'] = '8';
        $x[0]['ItemDimensions']['Width']['Unit'] = 'cm';
        $x[0]['ItemDimensions']['Length']['Value'] = '9';
        $x[0]['ItemDimensions']['Length']['Unit'] = 'cm';
        $x[0]['ItemDimensions']['Weight']['Value'] = '7';
        $x[0]['ItemDimensions']['Weight']['Unit'] = 'lb';
        $x[1]['RecommendationReason'] = 'UnpopularOutOfStock';
        $x[1]['ItemName'] = 'DEWALT Ratcheting Screwdriv';
        $x[1]['ProductCategory'] = 'Home Improvement';
        $x[1]['LastUpdated'] = '2013-03-12T00:00:00Z';
        $x[1]['ItemIdentifier']['ASIN'] = 'B006TVOX98';
        $x[1]['ItemIdentifier']['SKU'] = 'ER';
        $x[1]['ItemIdentifier']['UPC'] = '076174692334';
        $x[1]['BrandName'] = 'DEWALT';
        $x[1]['BuyboxPrice']['Amount'] = '15.99';
        $x[1]['BuyboxPrice']['CurrencyCode'] = 'USD';
        $x[1]['RecommendationId'] = '123-global2';

        $list = $o->getGlobalSellingList();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getGlobalSellingList());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testGetAdvertisingList($o) {
        $x = array();
        $x[0]['RecommendationReason'] = 'NotEnoughText';
        $x[0]['SalesRank'] = '186020';
        $x[0]['ItemName'] = 'Factory-Reconditioned';
        $x[0]['ProductCategory'] = 'Home Improvement';
        $x[0]['LastUpdated'] = '2013-03-08T00:00:00Z';
        $x[0]['ItemIdentifier']['ASIN'] = 'B008YFV5P2';
        $x[0]['ItemIdentifier']['SKU'] = 'EZ';
        $x[0]['ItemIdentifier']['UPC'] = '885911242721';
        $x[0]['BrandName'] = 'DEWALT';
        $x[0]['YourPricePlusShipping']['Amount'] = '245.5';
        $x[0]['YourPricePlusShipping']['CurrencyCode'] = 'USD';
        $x[0]['RecommendationId'] = '123-ad1';
        $x[0]['SalesForTheLast30Days'] = '4';
        $x[1]['RecommendationReason'] = 'TooBoring';
        $x[1]['ItemName'] = 'Box';
        $x[1]['ProductCategory'] = 'Boxes';
        $x[1]['LastUpdated'] = '2013-03-09T00:00:00Z';
        $x[1]['ItemIdentifier']['ASIN'] = 'B006TVOX98';
        $x[1]['ItemIdentifier']['SKU'] = 'ER';
        $x[1]['ItemIdentifier']['UPC'] = '076174692334';
        $x[1]['BrandName'] = 'DEWALT';
        $x[1]['YourPricePlusShipping']['Amount'] = '15.99';
        $x[1]['YourPricePlusShipping']['CurrencyCode'] = 'USD';
        $x[1]['RecommendationId'] = '123-ad2';
        $x[1]['SalesForTheLast30Days'] = '5';

        $list = $o->getAdvertisingList();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getAdvertisingList());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchLastUpdateTimes
     */
    public function testGetLastUpdateTimes($o) {
        $list = $o->getLastUpdateTimes();
        $this->assertInternalType('array', $list);
        $this->assertArrayHasKey('Inventory', $list);
        $this->assertEquals($o->getInventoryLastUpdateTime(), $list['Inventory']);
        $this->assertArrayHasKey('Selection', $list);
        $this->assertEquals($o->getSelectionLastUpdateTime(), $list['Selection']);
        $this->assertArrayHasKey('Pricing', $list);
        $this->assertEquals($o->getPricingLastUpdateTime(), $list['Pricing']);
        $this->assertArrayHasKey('Fulfillment', $list);
        $this->assertEquals($o->getFulfillmentLastUpdateTime(), $list['Fulfillment']);
        $this->assertArrayHasKey('GlobalSelling', $list);
        $this->assertEquals($o->getGlobalSellingLastUpdateTime(), $list['GlobalSelling']);
        $this->assertArrayHasKey('Advertising', $list);
        $this->assertEquals($o->getAdvertisingLastUpdateTime(), $list['Advertising']);
        $this->assertArrayNotHasKey('ListingQuality', $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getLastUpdateTimes());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchLastUpdateTimes
     */
    public function testGetInventoryLastUpdateTime($o) {
        $this->assertEquals('2013-03-04T02:10:32+00:00', $o->getInventoryLastUpdateTime());
        //not fetched yet for this object
        $this->assertFalse($this->object->getInventoryLastUpdateTime());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchLastUpdateTimes
     */
    public function testGetSelectionLastUpdateTime($o) {
        $this->assertEquals('2013-03-03T03:11:34+00:00', $o->getSelectionLastUpdateTime());
        //not fetched yet for this object
        $this->assertFalse($this->object->getSelectionLastUpdateTime());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchLastUpdateTimes
     */
    public function testGetPricingLastUpdateTime($o) {
        $this->assertEquals('2013-03-05T03:11:33+00:00', $o->getPricingLastUpdateTime());
        //not fetched yet for this object
        $this->assertFalse($this->object->getPricingLastUpdateTime());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchLastUpdateTimes
     */
    public function testGetFulfillmentLastUpdateTime($o) {
        $this->assertEquals('2013-03-02T03:11:32+00:00', $o->getFulfillmentLastUpdateTime());
        //not fetched yet for this object
        $this->assertFalse($this->object->getFulfillmentLastUpdateTime());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchLastUpdateTimes
     */
    public function testGetGlobalSellingLastUpdateTime($o) {
        $this->assertEquals('2013-03-02T04:31:32+00:00', $o->getGlobalSellingLastUpdateTime());
        //not fetched yet for this object
        $this->assertFalse($this->object->getGlobalSellingLastUpdateTime());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchLastUpdateTimes
     */
    public function testGetAdvertisingLastUpdateTime($o) {
        $this->assertEquals('2013-03-03T17:45:11+00:00', $o->getAdvertisingLastUpdateTime());
        //not fetched yet for this object
        $this->assertFalse($this->object->getAdvertisingLastUpdateTime());
    }

    /**
     * @param AmazonRecommendationList $o
     * @depends testFetchRecommendations
     */
    public function testIterator($o) {
        //fetched, but not using the category param
        foreach ($o as $x) {
            $this->fail('Should not have iterated');
        }
        //not fetched yet for this object
        foreach ($this->object as $x) {
            $this->fail('Should be nothing to iterate through');
        }
        //set category and get list
        $this->object->setMock(true, 'fetchRecommendations.xml');
        $this->object->setCategory('Pricing');
        $this->assertNull($this->object->fetchRecommendations());
        $c = 0;
        $list = $this->object->getPricingList();
        foreach ($this->object as $i => $x) {
            $this->assertEquals($list[$i], $x);
            $c++;
        }
        $this->assertCount($c, $list);
    }

}

require_once('helperFunctions.php');

