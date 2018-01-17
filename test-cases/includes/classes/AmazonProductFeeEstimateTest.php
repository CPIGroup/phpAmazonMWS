<?php

class AmazonProductFeeEstimateTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonProductFeeEstimate
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonProductFeeEstimate('testStore', true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetRequest() {
        //bad input
        $this->assertFalse($this->object->setRequests(null));
        $this->assertFalse($this->object->setRequests(123));
        $this->assertFalse($this->object->setRequests('word'));
        $this->assertFalse($this->object->setRequests(array()));
        $this->assertFalse($this->object->setRequests(array(123)));
        $this->assertFalse($this->object->setRequests(array('word')));

        $op = array();
        $this->assertFalse($this->object->setRequests(array($op))); //missing keys

        $op['MarketplaceId'] = 'Marketplace';
        $this->assertFalse($this->object->setRequests(array($op))); //still missing keys
        $op['IdType'] = 'ASIN';
        $this->assertFalse($this->object->setRequests(array($op))); //still missing keys
        $op['IdValue'] = 'B00123ASIN';
        $this->assertFalse($this->object->setRequests(array($op))); //still missing keys
        $op['ListingPrice'] = array();
        $this->assertFalse($this->object->setRequests(array($op))); //still missing keys
        $op['ListingPrice']['CurrencyCode'] = 'USD';
        $this->assertFalse($this->object->setRequests(array($op))); //still missing keys
        $op['ListingPrice']['Value'] = '123';
        $this->assertFalse($this->object->setRequests(array($op))); //still missing keys
        $op['Identifier'] = 'TEST123';
        $this->assertFalse($this->object->setRequests(array($op))); //still missing keys
        $op['IsAmazonFulfilled'] = 'false';
        $this->assertNull($this->object->setRequests(array($op))); //finally good

        //test doubles
        $op2 = $op;
        $op2['MarketplaceId'] = 'Mark II';
        $op2['Shipping']['CurrencyCode'] = 'USD';
        $op2['Shipping']['Value'] = '1.23';
        $op2['Points'] = '3';
        $this->assertNull($this->object->setRequests(array($op, $op2)));

        //verify options were set correctly
        $o = $this->object->getOptions();
        $pre = 'FeesEstimateRequestList.FeesEstimateRequest.';
        $this->assertArrayHasKey($pre.'1.MarketplaceId', $o);
        $this->assertEquals('Marketplace', $o[$pre.'1.MarketplaceId']);
        $this->assertArrayHasKey($pre.'1.IdType', $o);
        $this->assertEquals('ASIN', $o[$pre.'1.IdType']);
        $this->assertArrayHasKey($pre.'1.IdValue', $o);
        $this->assertEquals('B00123ASIN', $o[$pre.'1.IdValue']);
        $this->assertEquals('USD', $o[$pre.'1.PriceToEstimateFees.ListingPrice.CurrencyCode']);
        $this->assertArrayHasKey($pre.'1.PriceToEstimateFees.ListingPrice.Amount', $o);
        $this->assertArrayHasKey($pre.'1.ListingPrice.Value', $o);
        $this->assertEquals('123', $o[$pre.'1.ListingPrice.Value']);
        $this->assertArrayHasKey($pre.'1.Identifier', $o);
        $this->assertEquals('TEST123', $o[$pre.'1.Identifier']);
        $this->assertArrayHasKey($pre.'1.IsAmazonFulfilled', $o);
        $this->assertEquals('false', $o[$pre.'1.IsAmazonFulfilled']);
        $this->assertArrayHasKey($pre.'2.MarketplaceId', $o);
        $this->assertEquals('Mark II', $o[$pre.'2.MarketplaceId']);
        $this->assertArrayHasKey($pre.'2.Shipping.CurrencyCode', $o);
        $this->assertEquals('USD', $o[$pre.'2.Shipping.CurrencyCode']);
        $this->assertArrayHasKey($pre.'2.Shipping.Value', $o);
        $this->assertEquals('1.23', $o[$pre.'2.Shipping.Value']);
        $this->assertArrayHasKey($pre.'2.Points.PointsNumber', $o);
        $this->assertEquals('3', $o[$pre.'2.Points.PointsNumber']);

        //setting again should reset
        $this->assertNull($this->object->setRequests(array($op)));
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey($pre.'1.MarketplaceId', $o2);
        $this->assertArrayNotHasKey($pre.'2.MarketplaceId', $o2);
        $this->assertArrayNotHasKey($pre.'3.MarketplaceId', $o2);

        //check logs
        $check = parseLog();
        $err1 = 'Tried to set Fee Estimate Requests to invalid values';
        $err2 = 'Tried to set Fee Estimate Requests with invalid array';
        $this->assertEquals($err1, $check[1]);
        $this->assertEquals($err1, $check[2]);
        $this->assertEquals($err1, $check[3]);
        $this->assertEquals($err1, $check[4]);
        $this->assertEquals($err2, $check[5]);
        $this->assertEquals($err2, $check[6]);
        $this->assertEquals($err2, $check[7]);
        $this->assertEquals($err2, $check[8]);
        $this->assertEquals($err2, $check[9]);
        $this->assertEquals($err2, $check[10]);
        $this->assertEquals($err2, $check[11]);
        $this->assertEquals($err2, $check[12]);
        $this->assertEquals($err2, $check[13]);
        $this->assertEquals($err2, $check[14]);

        return $this->object;
    }

    /**
     * @depends testSetRequest
     * @param AmazonProductFeeEstimate $o
     */
    public function testFetchEstimates($o) {
        resetLog();
        $this->object->setMock(true, 'fetchEstimates.xml');
        $this->assertFalse($this->object->getEstimates()); //no data yet
        $this->assertFalse($this->object->fetchEstimates()); //no requests yet
        $o->setMock(true, 'fetchEstimates.xml');
        $this->assertNull($o->fetchEstimates()); //good, request already set

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchEstimates.xml', $check[1]);
        $this->assertEquals('Fee Requests must be set in order to fetch estimates!', $check[2]);

        return $o;
    }

    /**
     * @depends testFetchEstimates
     * @param AmazonProductFeeEstimate $o
     */
    public function testGetEstimates($o) {
        $get = $o->getEstimates();
        $this->assertInternalType('array', $get);
        $x = array();
        $x[0]['MarketplaceId'] = 'ATVPDKIKX0DER';
        $x[0]['IdType'] = 'ASIN';
        $x[0]['IdValue'] = 'B0002GTTRC';
        $x[0]['ListingPrice']['Amount'] = '58.00';
        $x[0]['ListingPrice']['CurrencyCode'] = 'USD';
        $x[0]['Shipping']['Amount'] = '0.01';
        $x[0]['Shipping']['CurrencyCode'] = 'USD';
        $x[0]['Points'] = '1';
        $x[0]['IsAmazonFulfilled'] = 'True';
        $x[0]['SellerInputIdentifier'] = 'IDDDDDDDD';
        $x[0]['TimeOfFeesEstimation'] = '2015-07-19T23:15:11.859Z';
        $x[0]['Status'] = 'Success';
        $x[0]['TotalFeesEstimate']['Amount'] = '10.00';
        $x[0]['TotalFeesEstimate']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][0]['FeeType'] = 'AmazonReferralFee';
        $x[0]['FeeDetailList'][0]['FeeAmount']['Amount'] = '8.70';
        $x[0]['FeeDetailList'][0]['FeeAmount']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][0]['FeePromotion']['Amount'] = '1.00';
        $x[0]['FeeDetailList'][0]['FeePromotion']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][0]['FinalFee']['Amount'] = '7.70';
        $x[0]['FeeDetailList'][0]['FinalFee']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][1]['FeeType'] = 'VariableClosingFee';
        $x[0]['FeeDetailList'][1]['FeeAmount']['Amount'] = '0.01';
        $x[0]['FeeDetailList'][1]['FeeAmount']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][1]['FinalFee']['Amount'] = '0.02';
        $x[0]['FeeDetailList'][1]['FinalFee']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['FeeType'] = 'FulfillmentFees';
        $x[0]['FeeDetailList'][2]['FeeAmount']['Amount'] = '2.30';
        $x[0]['FeeDetailList'][2]['FeeAmount']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['FinalFee']['Amount'] = '2.31';
        $x[0]['FeeDetailList'][2]['FinalFee']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][0]['FeeType'] = 'OrderHandlingFee';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][0]['FeeAmount']['Amount'] = '1.00';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][0]['FeeAmount']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][0]['FinalFee']['Amount'] = '1.01';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][0]['FinalFee']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][1]['FeeType'] = 'PickAndPackFee';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][1]['FeeAmount']['Amount'] = '0.30';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][1]['FeeAmount']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][1]['FinalFee']['Amount'] = '0.31';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][1]['FinalFee']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][2]['FeeType'] = 'WeightHandlingFee';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][2]['FeeAmount']['Amount'] = '1.00';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][2]['FeeAmount']['CurrencyCode'] = 'USD';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][2]['FinalFee']['Amount'] = '1.01';
        $x[0]['FeeDetailList'][2]['IncludedFeeDetailList'][2]['FinalFee']['CurrencyCode'] = 'USD';
        $x[1]['MarketplaceId'] = 'ATVPDKIKX0DER';
        $x[1]['IdType'] = 'ASIN';
        $x[1]['IdValue'] = 'B00032ASIN';
        $x[1]['ListingPrice']['Amount'] = '58.00';
        $x[1]['ListingPrice']['CurrencyCode'] = 'USD';
        $x[1]['Shipping']['Amount'] = '0.00';
        $x[1]['Shipping']['CurrencyCode'] = 'USD';
        $x[1]['Points'] = '100';
        $x[1]['IsAmazonFulfilled'] = 'True';
        $x[1]['SellerInputIdentifier'] = 'IDDDDDDDD2';
        $x[1]['TimeOfFeesEstimation'] = '2015-07-20T12:13:14.000Z';
        $x[1]['Status'] = 'Success';
        $x[1]['TotalFeesEstimate']['Amount'] = '10.00';
        $x[1]['TotalFeesEstimate']['CurrencyCode'] = 'USD';
        $x[1]['FeeDetailList'][0]['FeeType'] = 'AmazonReferralFee';
        $x[1]['FeeDetailList'][0]['FeeAmount']['Amount'] = '2.24';
        $x[1]['FeeDetailList'][0]['FeeAmount']['CurrencyCode'] = 'USD';
        $x[1]['FeeDetailList'][0]['FeePromotion']['Amount'] = '1.01';
        $x[1]['FeeDetailList'][0]['FeePromotion']['CurrencyCode'] = 'USD';
        $x[1]['FeeDetailList'][0]['FinalFee']['Amount'] = '1.23';
        $x[1]['FeeDetailList'][0]['FinalFee']['CurrencyCode'] = 'USD';
        $this->assertEquals($x, $get);
        $this->assertFalse($this->object->getEstimates()); //not fetched yet for this object
    }

}
