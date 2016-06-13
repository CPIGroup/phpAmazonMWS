<?php

class AmazonFinancialEventListTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonFinancialEventList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonFinancialEventList('testStore', true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetUseToken(){
        $this->assertNull($this->object->setUseToken());
        $this->assertNull($this->object->setUseToken(true));
        $this->assertNull($this->object->setUseToken(false));
        $this->assertFalse($this->object->setUseToken('wrong'));
    }

    public function testSetMaxResultsPerPage(){
        $this->assertFalse($this->object->setMaxResultsPerPage(null)); //can't be nothing
        $this->assertFalse($this->object->setMaxResultsPerPage(-5)); //too low
        $this->assertFalse($this->object->setMaxResultsPerPage(150)); //too high
        $this->assertFalse($this->object->setMaxResultsPerPage(array(5, 7))); //not a valid value
        $this->assertFalse($this->object->setMaxResultsPerPage('banana')); //what are you even doing
        $this->assertNull($this->object->setMaxResultsPerPage(77));
        $this->assertNull($this->object->setMaxResultsPerPage('75'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('MaxResultsPerPage', $o);
        $this->assertEquals('75', $o['MaxResultsPerPage']);
    }

    /**
    * @return array
    */
    public function timeProvider() {
        return array(
            array(null, null, false, false), //nothing given, so no change
            array(time(), time(), true, true), //timestamps
            array('', '', false, false), //strings, but empty
            array('-1 min', null, true, false), //one set
            array(null, '-1 min', false, false), //other set
            array('-1 min', '-1 min', true, true), //both set
        );
    }

    /**
     * @dataProvider timeProvider
     */
    public function testSetTimeLimits($a, $b, $c, $d){
        $this->object->setOrderFilter('123-1234567-1234567');
        $try = $this->object->setTimeLimits($a, $b);
        $o = $this->object->getOptions();
        if ($c) {
            $this->assertNull($try);
            $this->assertArrayHasKey('PostedAfter', $o);
            $this->assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i', $o['PostedAfter']);
            $this->assertArrayNotHasKey('AmazonOrderId', $o);
        } else {
            $this->assertFalse($try);
            $this->assertArrayNotHasKey('PostedAfter', $o);
            $this->assertArrayHasKey('AmazonOrderId', $o);
        }

        if ($c && $d) {
            $this->assertArrayHasKey('PostedBefore', $o);
            $this->assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i', $o['PostedBefore']);
            //setting only first date resets second one
            $this->assertNull($this->object->setTimeLimits($a));
            $o2 = $this->object->getOptions();
            $this->assertArrayNotHasKey('PostedBefore', $o2);
        } else {
            $this->assertArrayNotHasKey('PostedBefore', $o);
        }
    }

    public function testFetchEventList() {
        resetLog();
        $this->object->setMock(true, 'fetchFinancialEvents.xml'); //no token
        $this->assertNull($this->object->fetchEventList());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchFinancialEvents.xml', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialEvents.xml', $check[2]);

        $o = $this->object->getOptions();
        $this->assertEquals('ListFinancialEvents', $o['Action']);

        $this->assertFalse($this->object->hasToken());

        return $this->object;
    }

    public function testFetchEventListToken1() {
        resetLog();
        $this->object->setMock(true, 'fetchFinancialEventsToken.xml');
        //without using token
        $this->assertNull($this->object->fetchEventList());
        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchFinancialEventsToken.xml', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialEventsToken.xml', $check[2]);

        $this->assertTrue($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListFinancialEvents', $o['Action']);
        $r = $this->object->getEvents();
        $this->assertInternalType('array', $r);
        foreach ($r as $x) {
            $this->assertCount(1, $x);
        }
    }

    public function testFetchEventListToken2() {
        resetLog();
        $this->object->setMock(true, array('fetchFinancialEventsToken.xml', 'fetchFinancialEventsToken2.xml'));

        //with using token
        $this->object->setUseToken();
        $this->object->setMaxResultsPerPage(5);
        $this->assertNull($this->object->fetchEventList());
        $check = parseLog();
        $this->assertEquals('Mock files array set.', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialEventsToken.xml', $check[2]);
        $this->assertEquals('Recursively fetching more Financial Events', $check[3]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialEventsToken2.xml', $check[4]);
        $this->assertFalse($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListFinancialEventsByNextToken', $o['Action']);
        $this->assertArrayNotHasKey('FinancialEventGroupStartedAfter', $o);
        $r = $this->object->getEvents();
        $this->assertInternalType('array', $r);
        foreach ($r as $x) {
            $this->assertCount(2, $x);
        }
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetEvents($o) {
        $list = $o->getEvents();
        $this->assertInternalType('array', $list);
        $this->assertArrayHasKey('Shipment', $list);
        $this->assertEquals($o->getShipmentEvents(), $list['Shipment']);
        $this->assertArrayHasKey('Refund', $list);
        $this->assertEquals($o->getRefundEvents(), $list['Refund']);
        $this->assertArrayHasKey('GuaranteeClaim', $list);
        $this->assertEquals($o->getGuaranteeClaimEvents(), $list['GuaranteeClaim']);
        $this->assertArrayHasKey('Chargeback', $list);
        $this->assertEquals($o->getChargebackEvents(), $list['Chargeback']);
        $this->assertArrayHasKey('PayWithAmazon', $list);
        $this->assertEquals($o->getPayWithAmazonEvents(), $list['PayWithAmazon']);
        $this->assertArrayHasKey('ServiceProviderCredit', $list);
        $this->assertEquals($o->getServiceProviderCreditEvents(), $list['ServiceProviderCredit']);
        $this->assertArrayHasKey('Retrocharge', $list);
        $this->assertEquals($o->getRetrochargeEvents(), $list['Retrocharge']);
        $this->assertArrayHasKey('RentalTransaction', $list);
        $this->assertEquals($o->getRentalTransactionEvents(), $list['RentalTransaction']);
        $this->assertArrayHasKey('PerformanceBondRefund', $list);
        $this->assertEquals($o->getPerformanceBondRefundEvents(), $list['PerformanceBondRefund']);
        $this->assertArrayHasKey('ServiceFee', $list);
        $this->assertEquals($o->getServiceFeeEvents(), $list['ServiceFee']);
        $this->assertArrayHasKey('DebtRecovery', $list);
        $this->assertEquals($o->getDebtRecoveryEvents(), $list['DebtRecovery']);
        $this->assertArrayHasKey('LoanServicing', $list);
        $this->assertEquals($o->getLoanServicingEvents(), $list['LoanServicing']);
        $this->assertArrayHasKey('Adjustment', $list);
        $this->assertEquals($o->getAdjustmentEvents(), $list['Adjustment']);
        //not fetched yet for this object
        $this->assertFalse($this->object->getEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetShipmentEvents($o) {
        $x = array();
        $x[0]['AmazonOrderId'] = '333-1234567-1234567';
        $x[0]['SellerOrderId'] = '333-1234567-7654321';
        $x[0]['MarketplaceName'] = 'amazon.com';
        $x[0]['OrderChargeList'][0]['ChargeType'] = 'Principal';
        $x[0]['OrderChargeList'][0]['Amount'] = '10.00';
        $x[0]['OrderChargeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['OrderChargeList'][1]['ChargeType'] = 'Tax';
        $x[0]['OrderChargeList'][1]['Amount'] = '1.00';
        $x[0]['OrderChargeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentFeeList'][0]['FeeType'] = 'FBAStorageFee';
        $x[0]['ShipmentFeeList'][0]['Amount'] = '-1.50';
        $x[0]['ShipmentFeeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentFeeList'][1]['FeeType'] = 'FBAStorageTax';
        $x[0]['ShipmentFeeList'][1]['Amount'] = '-5.00';
        $x[0]['ShipmentFeeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['OrderFeeList'][0]['FeeType'] = 'LabellingFee';
        $x[0]['OrderFeeList'][0]['Amount'] = '-1.00';
        $x[0]['OrderFeeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['OrderFeeList'][1]['FeeType'] = 'LabellingTax';
        $x[0]['OrderFeeList'][1]['Amount'] = '-3.00';
        $x[0]['OrderFeeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['DirectPaymentList'][0]['DirectPaymentType'] = 'StoredValuedCardRevenue';
        $x[0]['DirectPaymentList'][0]['Amount'] = '1.20';
        $x[0]['DirectPaymentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['DirectPaymentList'][1]['DirectPaymentType'] = 'Money';
        $x[0]['DirectPaymentList'][1]['Amount'] = '5.50';
        $x[0]['DirectPaymentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['PostedDate'] = '2012-07-18T00:00:00Z';
        $x[0]['ShipmentItemList'][0]['SellerSKU'] = 'CBA_OTF_1';
        $x[0]['ShipmentItemList'][0]['OrderItemId'] = '6882857EXAMPLE';
        $x[0]['ShipmentItemList'][0]['QuantityShipped'] = '2';
        $x[0]['ShipmentItemList'][0]['ItemChargeList'][0]['ChargeType'] = 'Discount';
        $x[0]['ShipmentItemList'][0]['ItemChargeList'][0]['Amount'] = '1.99';
        $x[0]['ShipmentItemList'][0]['ItemChargeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][0]['ItemChargeList'][1]['ChargeType'] = 'Tax';
        $x[0]['ShipmentItemList'][0]['ItemChargeList'][1]['Amount'] = '0.50';
        $x[0]['ShipmentItemList'][0]['ItemChargeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][0]['ItemFeeList'][0]['FeeType'] = 'FBAStorageFee';
        $x[0]['ShipmentItemList'][0]['ItemFeeList'][0]['Amount'] = '-1.99';
        $x[0]['ShipmentItemList'][0]['ItemFeeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][0]['ItemFeeList'][1]['FeeType'] = 'ItemFeeTax';
        $x[0]['ShipmentItemList'][0]['ItemFeeList'][1]['Amount'] = '-0.99';
        $x[0]['ShipmentItemList'][0]['ItemFeeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][0]['PromotionList'][0]['PromotionType'] = 'Shipping';
        $x[0]['ShipmentItemList'][0]['PromotionList'][0]['PromotionId'] = 'SummerEXAMPLE';
        $x[0]['ShipmentItemList'][0]['PromotionList'][0]['Amount'] = '-15.99';
        $x[0]['ShipmentItemList'][0]['PromotionList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][0]['PromotionList'][1]['PromotionType'] = 'Shipping2';
        $x[0]['ShipmentItemList'][0]['PromotionList'][1]['PromotionId'] = 'WinterEXAMPLE';
        $x[0]['ShipmentItemList'][0]['PromotionList'][1]['Amount'] = '-3.99';
        $x[0]['ShipmentItemList'][0]['PromotionList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][0]['CostOfPointsGranted']['Amount'] = '-5.99';
        $x[0]['ShipmentItemList'][0]['CostOfPointsGranted']['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][1]['SellerSKU'] = 'CBA_OTF_2';
        $x[0]['ShipmentItemList'][1]['OrderItemId'] = '9992857EXAMPLE';
        $x[0]['ShipmentItemList'][1]['QuantityShipped'] = '1';
        $x[0]['ShipmentItemList'][1]['ItemChargeList'][0]['ChargeType'] = 'Tax';
        $x[0]['ShipmentItemList'][1]['ItemChargeList'][0]['Amount'] = '0.40';
        $x[0]['ShipmentItemList'][1]['ItemChargeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][1]['ItemFeeList'][0]['FeeType'] = 'ItemFeeTax';
        $x[0]['ShipmentItemList'][1]['ItemFeeList'][0]['Amount'] = '-0.30';
        $x[0]['ShipmentItemList'][1]['ItemFeeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][1]['PromotionList'][0]['PromotionType'] = 'Shipping3';
        $x[0]['ShipmentItemList'][1]['PromotionList'][0]['PromotionId'] = 'SpringEXAMPLE';
        $x[0]['ShipmentItemList'][1]['PromotionList'][0]['Amount'] = '-5.99';
        $x[0]['ShipmentItemList'][1]['PromotionList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemList'][1]['CostOfPointsGranted']['Amount'] = '-5.99';
        $x[0]['ShipmentItemList'][1]['CostOfPointsGranted']['CurrencyCode'] = 'USD';
        $x[1]['AmazonOrderId'] = '999-1234567-1234567';
        $x[1]['SellerOrderId'] = '999-1234567-7654321';
        $x[1]['MarketplaceName'] = 'amazon2.com';
        $x[1]['OrderChargeList'][0]['ChargeType'] = 'Main';
        $x[1]['OrderChargeList'][0]['Amount'] = '12.00';
        $x[1]['OrderChargeList'][0]['CurrencyCode'] = 'USD';
        $x[1]['ShipmentFeeList'][0]['FeeType'] = 'BigFee';
        $x[1]['ShipmentFeeList'][0]['Amount'] = '-10.50';
        $x[1]['ShipmentFeeList'][0]['CurrencyCode'] = 'USD';
        $x[1]['OrderFeeList'][0]['FeeType'] = 'LabellingFee2';
        $x[1]['OrderFeeList'][0]['Amount'] = '-2.00';
        $x[1]['OrderFeeList'][0]['CurrencyCode'] = 'USD';
        $x[1]['DirectPaymentList'][0]['DirectPaymentType'] = 'Money';
        $x[1]['DirectPaymentList'][0]['Amount'] = '2.50';
        $x[1]['DirectPaymentList'][0]['CurrencyCode'] = 'USD';
        $x[1]['PostedDate'] = '2012-07-19T00:00:00Z';
        $x[1]['ShipmentItemList'][0]['SellerSKU'] = 'CBA_OTF_3';
        $x[1]['ShipmentItemList'][0]['OrderItemId'] = '3212857EXAMPLE';
        $x[1]['ShipmentItemList'][0]['QuantityShipped'] = '3';
        $x[1]['ShipmentItemList'][0]['CostOfPointsGranted']['Amount'] = '-1.99';
        $x[1]['ShipmentItemList'][0]['CostOfPointsGranted']['CurrencyCode'] = 'USD';

        $list = $o->getShipmentEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getShipmentEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetRefundEvents($o) {
        $x = array();
        $x[0]['AmazonOrderId'] = '333-7654321-7654321';
        $x[0]['SellerOrderId'] = '333-7654321-1234567';
        $x[0]['MarketplaceName'] = 'amazon.com';
        $x[0]['OrderChargeAdjustmentList'][0]['ChargeType'] = 'ShippingCharge';
        $x[0]['OrderChargeAdjustmentList'][0]['Amount'] = '-1.99';
        $x[0]['OrderChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['OrderChargeAdjustmentList'][1]['ChargeType'] = 'Giftwrap';
        $x[0]['OrderChargeAdjustmentList'][1]['Amount'] = '-0.99';
        $x[0]['OrderChargeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentFeeAdjustmentList'][0]['FeeType'] = 'FBADeliveryServicesFee';
        $x[0]['ShipmentFeeAdjustmentList'][0]['Amount'] = '1.99';
        $x[0]['ShipmentFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentFeeAdjustmentList'][1]['FeeType'] = 'FBAPlacementServiceFee';
        $x[0]['ShipmentFeeAdjustmentList'][1]['Amount'] = '0.99';
        $x[0]['ShipmentFeeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['OrderFeeAdjustmentList'][0]['FeeType'] = 'FBAInventoryReturnFee';
        $x[0]['OrderFeeAdjustmentList'][0]['Amount'] = '1.99';
        $x[0]['OrderFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['OrderFeeAdjustmentList'][1]['FeeType'] = 'FBAInventoryReturnFee2';
        $x[0]['OrderFeeAdjustmentList'][1]['Amount'] = '2.99';
        $x[0]['OrderFeeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['PostedDate'] = '2012-07-18T00:00:00Z';
        $x[0]['ShipmentItemAdjustmentList'][0]['SellerSKU'] = 'CBA_OTF_1';
        $x[0]['ShipmentItemAdjustmentList'][0]['OrderItemId'] = '999EXAMPLE123';
        $x[0]['ShipmentItemAdjustmentList'][0]['OrderAdjustmentItemId'] = '6882857EXAMPLE';
        $x[0]['ShipmentItemAdjustmentList'][0]['QuantityShipped'] = '4';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][0]['ChargeType'] = 'ReturnShipping';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][0]['Amount'] = '-1.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][1]['ChargeType'] = 'Tax';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][1]['Amount'] = '-0.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemFeeAdjustmentList'][0]['FeeType'] = 'ShippingChargeback';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemFeeAdjustmentList'][0]['Amount'] = '2.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemFeeAdjustmentList'][1]['FeeType'] = 'ShippingTax';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemFeeAdjustmentList'][1]['Amount'] = '0.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemFeeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][0]['PromotionType'] = 'Shipping';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][0]['PromotionId'] = 'Summer099018';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][0]['Amount'] = '22.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][1]['PromotionType'] = 'Shipping2';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][1]['PromotionId'] = 'Winter099018';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][1]['Amount'] = '11.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['PromotionAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][0]['CostOfPointsReturned']['Amount'] = '5.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['CostOfPointsReturned']['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][1]['SellerSKU'] = 'CBA_OTF_2';
        $x[0]['ShipmentItemAdjustmentList'][1]['OrderItemId'] = '999EXAMPLE456';
        $x[0]['ShipmentItemAdjustmentList'][1]['OrderAdjustmentItemId'] = '9992857EXAMPLE';
        $x[0]['ShipmentItemAdjustmentList'][1]['QuantityShipped'] = '3';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemChargeAdjustmentList'][0]['ChargeType'] = 'Tax';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemChargeAdjustmentList'][0]['Amount'] = '-3.99';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemFeeAdjustmentList'][0]['FeeType'] = 'ShippingTax';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemFeeAdjustmentList'][0]['Amount'] = '2.99';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][1]['PromotionAdjustmentList'][0]['PromotionType'] = 'Shipping3';
        $x[0]['ShipmentItemAdjustmentList'][1]['PromotionAdjustmentList'][0]['PromotionId'] = 'Spring099018';
        $x[0]['ShipmentItemAdjustmentList'][1]['PromotionAdjustmentList'][0]['Amount'] = '5.99';
        $x[0]['ShipmentItemAdjustmentList'][1]['PromotionAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][1]['CostOfPointsReturned']['Amount'] = '2.99';
        $x[0]['ShipmentItemAdjustmentList'][1]['CostOfPointsReturned']['CurrencyCode'] = 'USD';
        $x[1]['AmazonOrderId'] = '999-7654321-7654321';
        $x[1]['SellerOrderId'] = '999-7654321-1234567';
        $x[1]['MarketplaceName'] = 'amazon2.com';
        $x[1]['OrderChargeAdjustmentList'][0]['ChargeType'] = 'Giftwrap2';
        $x[1]['OrderChargeAdjustmentList'][0]['Amount'] = '-0.99';
        $x[1]['OrderChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[1]['ShipmentFeeAdjustmentList'][0]['FeeType'] = 'FBAPlacementServiceFee2';
        $x[1]['ShipmentFeeAdjustmentList'][0]['Amount'] = '0.99';
        $x[1]['ShipmentFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[1]['OrderFeeAdjustmentList'][0]['FeeType'] = 'FBAInventoryReturnFee3';
        $x[1]['OrderFeeAdjustmentList'][0]['Amount'] = '2.99';
        $x[1]['OrderFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[1]['PostedDate'] = '2012-07-19T00:00:00Z';
        $x[1]['ShipmentItemAdjustmentList'][0]['SellerSKU'] = 'CBA_OTF_3';
        $x[1]['ShipmentItemAdjustmentList'][0]['OrderItemId'] = '999EXAMPLE789';
        $x[1]['ShipmentItemAdjustmentList'][0]['OrderAdjustmentItemId'] = '9992857EXAMPLE2';
        $x[1]['ShipmentItemAdjustmentList'][0]['QuantityShipped'] = '5';
        $x[1]['ShipmentItemAdjustmentList'][0]['CostOfPointsReturned']['Amount'] = '1.99';
        $x[1]['ShipmentItemAdjustmentList'][0]['CostOfPointsReturned']['CurrencyCode'] = 'USD';

        $list = $o->getRefundEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getRefundEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetGuaranteeClaimEvents($o) {
        $x = array();
        $x[0]['AmazonOrderId'] = '333-5551234-7654321';
        $x[0]['SellerOrderId'] = '333-5551234-1234567';
        $x[0]['MarketplaceName'] = 'amazon.com';
        $x[0]['OrderChargeAdjustmentList'][0]['ChargeType'] = 'ShippingCharge';
        $x[0]['OrderChargeAdjustmentList'][0]['Amount'] = '-5.99';
        $x[0]['OrderChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['PostedDate'] = '2012-07-20T00:00:00Z';
        $x[0]['ShipmentItemAdjustmentList'][0]['SellerSKU'] = 'CBA_OTF_1';
        $x[0]['ShipmentItemAdjustmentList'][0]['OrderItemId'] = '6992857EXAMPLE';
        $x[0]['ShipmentItemAdjustmentList'][0]['OrderAdjustmentItemId'] = '6992859EXAMPLE';
        $x[0]['ShipmentItemAdjustmentList'][0]['QuantityShipped'] = '3';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][0]['ChargeType'] = 'Tax';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][0]['Amount'] = '-0.99';
        $x[0]['ShipmentItemAdjustmentList'][0]['ItemChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ShipmentItemAdjustmentList'][1]['SellerSKU'] = 'CBA_OTF_2';
        $x[0]['ShipmentItemAdjustmentList'][1]['OrderItemId'] = '9992857EXAMPLE';
        $x[0]['ShipmentItemAdjustmentList'][1]['OrderAdjustmentItemId'] = '9992897EXAMPLE';
        $x[0]['ShipmentItemAdjustmentList'][1]['QuantityShipped'] = '3';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemChargeAdjustmentList'][0]['ChargeType'] = 'Tax';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemChargeAdjustmentList'][0]['Amount'] = '-3.50';
        $x[0]['ShipmentItemAdjustmentList'][1]['ItemChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[1]['AmazonOrderId'] = '999-5554321-7654321';
        $x[1]['SellerOrderId'] = '999-5554321-1234567';
        $x[1]['MarketplaceName'] = 'amazon2.com';
        $x[1]['ShipmentFeeAdjustmentList'][0]['FeeType'] = 'FBAPlacementServiceFee';
        $x[1]['ShipmentFeeAdjustmentList'][0]['Amount'] = '7.99';
        $x[1]['ShipmentFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[1]['ShipmentFeeAdjustmentList'][1]['FeeType'] = 'FBAPlacementServiceFee2';
        $x[1]['ShipmentFeeAdjustmentList'][1]['Amount'] = '5.99';
        $x[1]['ShipmentFeeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[1]['PostedDate'] = '2012-07-21T00:00:00Z';

        $list = $o->getGuaranteeClaimEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getGuaranteeClaimEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetChargebackEvents($o) {
        $x = array();
        $x[0]['AmazonOrderId'] = '555-7654321-7654321';
        $x[0]['SellerOrderId'] = '555-7654321-1234567';
        $x[0]['MarketplaceName'] = 'amazon.com';
        $x[0]['OrderChargeAdjustmentList'][0]['ChargeType'] = 'ShippingCharge';
        $x[0]['OrderChargeAdjustmentList'][0]['Amount'] = '17.99';
        $x[0]['OrderChargeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['OrderChargeAdjustmentList'][1]['ChargeType'] = 'Giftwrap';
        $x[0]['OrderChargeAdjustmentList'][1]['Amount'] = '18.99';
        $x[0]['OrderChargeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[0]['PostedDate'] = '2012-07-25T00:00:00Z';
        $x[1]['AmazonOrderId'] = '999-5554321-7654321';
        $x[1]['SellerOrderId'] = '999-5554321-1234567';
        $x[1]['MarketplaceName'] = 'amazon2.com';
        $x[1]['ShipmentFeeAdjustmentList'][0]['FeeType'] = 'FeeTax';
        $x[1]['ShipmentFeeAdjustmentList'][0]['Amount'] = '2.75';
        $x[1]['ShipmentFeeAdjustmentList'][0]['CurrencyCode'] = 'USD';
        $x[1]['ShipmentFeeAdjustmentList'][1]['FeeType'] = 'BigFee';
        $x[1]['ShipmentFeeAdjustmentList'][1]['Amount'] = '5.75';
        $x[1]['ShipmentFeeAdjustmentList'][1]['CurrencyCode'] = 'USD';
        $x[1]['PostedDate'] = '2012-07-26T00:00:00Z';

        $list = $o->getChargebackEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getChargebackEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetPayWithAmazonEvents($o) {
        $x = array();
        $x[0]['SellerOrderId'] = '333-7654321-7654321';
        $x[0]['TransactionPostedDate'] = '2013-09-071T02:00:00.000-06:00';
        $x[0]['BusinessObjectType'] = 'PaymentContract';
        $x[0]['SalesChannel'] = 'www.merchantsite.com';
        $x[0]['Charge']['ChargeType'] = 'Principal';
        $x[0]['Charge']['Amount'] = '2.99';
        $x[0]['Charge']['CurrencyCode'] = 'USD';
        $x[0]['FeeList'][0]['FeeType'] = 'VariableClosingFee';
        $x[0]['FeeList'][0]['Amount'] = '-0.99';
        $x[0]['FeeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['FeeList'][1]['FeeType'] = 'GiftWrapFee';
        $x[0]['FeeList'][1]['Amount'] = '-3.99';
        $x[0]['FeeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['PaymentAmountType'] = 'Sales';
        $x[0]['AmountDescription'] = 'Pay with amazon transaction';
        $x[0]['FulfillmentChannel'] = 'MFN';
        $x[0]['StoreName'] = 'TestStoreName';
        $x[1]['SellerOrderId'] = '555-7654321-7654321';
        $x[1]['TransactionPostedDate'] = '2013-09-091T02:00:00.000-06:00';
        $x[1]['BusinessObjectType'] = 'PaymentContract2';
        $x[1]['SalesChannel'] = 'www.merchantsite2.com';
        $x[1]['Charge']['ChargeType'] = 'VicePrincipal';
        $x[1]['Charge']['Amount'] = '5.99';
        $x[1]['Charge']['CurrencyCode'] = 'USD';
        $x[1]['FeeList'][0]['FeeType'] = 'GiftWrapFee';
        $x[1]['FeeList'][0]['Amount'] = '-1.99';
        $x[1]['FeeList'][0]['CurrencyCode'] = 'USD';
        $x[1]['PaymentAmountType'] = 'Sales2';
        $x[1]['AmountDescription'] = 'Pay more with amazon transaction';
        $x[1]['FulfillmentChannel'] = 'AFN';
        $x[1]['StoreName'] = 'TestStoreName2';

        $list = $o->getPayWithAmazonEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getPayWithAmazonEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetServiceProviderCreditEvents($o) {
        $x = array();
        $x[0]['ProviderTransactionType'] = 'SolutionProviderCredit';
        $x[0]['SellerOrderId'] = '333-7654321-7654321';
        $x[0]['MarketplaceId'] = '12';
        $x[0]['MarketplaceCountryCode'] = 'US';
        $x[0]['SellerId'] = '987918809';
        $x[0]['SellerStoreName'] = 'TestSellerStoreName';
        $x[0]['ProviderId'] = '6798769889';
        $x[0]['ProviderStoreName'] = 'TestProviderStoreName';
        $x[1]['ProviderTransactionType'] = 'SolutionProviderCredit2';
        $x[1]['SellerOrderId'] = '555-7654321-7654321';
        $x[1]['MarketplaceId'] = '13';
        $x[1]['MarketplaceCountryCode'] = 'US';
        $x[1]['SellerId'] = '999918809';
        $x[1]['SellerStoreName'] = 'TestSellerStoreName2';
        $x[1]['ProviderId'] = '6798769999';
        $x[1]['ProviderStoreName'] = 'TestProviderStoreName2';

        $list = $o->getServiceProviderCreditEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getServiceProviderCreditEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetRetrochargeEvents($o) {
        $x = array();
        $x[0]['RetrochargeEventType'] = 'Retrocharge';
        $x[0]['AmazonOrderId'] = '333-1234567-1234567';
        $x[0]['PostedDate'] = '2013-09-071T02:00:00.000-06:00';
        $x[0]['BaseTax']['Amount'] = '1.99';
        $x[0]['BaseTax']['CurrencyCode'] = 'USD';
        $x[0]['ShippingTax']['Amount'] = '2.99';
        $x[0]['ShippingTax']['CurrencyCode'] = 'USD';
        $x[0]['MarketplaceName'] = 'amazon.com';
        $x[1]['RetrochargeEventType'] = 'Retrocharge2';
        $x[1]['AmazonOrderId'] = '999-1234567-1234567';
        $x[1]['PostedDate'] = '2013-09-081T02:00:00.000-06:00';
        $x[1]['BaseTax']['Amount'] = '3.99';
        $x[1]['BaseTax']['CurrencyCode'] = 'USD';
        $x[1]['ShippingTax']['Amount'] = '4.99';
        $x[1]['ShippingTax']['CurrencyCode'] = 'USD';
        $x[1]['MarketplaceName'] = 'amazon2.com';

        $list = $o->getRetrochargeEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getRetrochargeEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetRentalTransactionEvents($o) {
        $x = array();
        $x[0]['AmazonOrderId'] = '333-1234567-1234567';
        $x[0]['RentalEventType'] = 'RentalCustomerPayment-Buyout';
        $x[0]['ExtensionLength'] = '12';
        $x[0]['PostedDate'] = '2013-09-071T02:00:00.000-06:00';
        $x[0]['RentalChargeList'][0]['ChargeType'] = 'Tax';
        $x[0]['RentalChargeList'][0]['Amount'] = '0.99';
        $x[0]['RentalChargeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['RentalChargeList'][1]['ChargeType'] = 'TaxTax';
        $x[0]['RentalChargeList'][1]['Amount'] = '0.50';
        $x[0]['RentalChargeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['RentalFeeList'][0]['FeeType'] = 'SalesTaxServiceFee';
        $x[0]['RentalFeeList'][0]['Amount'] = '-1.99';
        $x[0]['RentalFeeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['RentalFeeList'][1]['FeeType'] = 'SalesTaxServiceFeeTax';
        $x[0]['RentalFeeList'][1]['Amount'] = '-0.99';
        $x[0]['RentalFeeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['MarketplaceName'] = 'amazon.com';
        $x[0]['RentalInitialValue']['Amount'] = '3.99';
        $x[0]['RentalInitialValue']['CurrencyCode'] = 'USD';
        $x[0]['RentalReimbursement']['Amount'] = '1.99';
        $x[0]['RentalReimbursement']['CurrencyCode'] = 'USD';
        $x[1]['AmazonOrderId'] = '555-1234567-1234567';
        $x[1]['RentalEventType'] = 'RentalCustomerPayment-Buyout2';
        $x[1]['ExtensionLength'] = '11';
        $x[1]['PostedDate'] = '2013-09-081T02:00:00.000-06:00';
        $x[1]['RentalChargeList'][0]['ChargeType'] = 'RentalTax';
        $x[1]['RentalChargeList'][0]['Amount'] = '0.70';
        $x[1]['RentalChargeList'][0]['CurrencyCode'] = 'USD';
        $x[1]['RentalFeeList'][0]['FeeType'] = 'SalesTaxTax';
        $x[1]['RentalFeeList'][0]['Amount'] = '-0.70';
        $x[1]['RentalFeeList'][0]['CurrencyCode'] = 'USD';
        $x[1]['MarketplaceName'] = 'amazon2.com';
        $x[1]['RentalInitialValue']['Amount'] = '5.99';
        $x[1]['RentalInitialValue']['CurrencyCode'] = 'USD';
        $x[1]['RentalReimbursement']['Amount'] = '4.99';
        $x[1]['RentalReimbursement']['CurrencyCode'] = 'USD';

        $list = $o->getRentalTransactionEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getRentalTransactionEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetPerformanceBondRefundEvents($o) {
        $x = array();
        $x[0]['MarketplaceCountryCode'] = 'US';
        $x[0]['Amount'] = '1.99';
        $x[0]['CurrencyCode'] = 'USD';
        $x[0]['ProductGroupList'][0] = 'gl_books';
        $x[0]['ProductGroupList'][1] = 'gl_magazines';
        $x[1]['MarketplaceCountryCode'] = 'UK';
        $x[1]['Amount'] = '2.99';
        $x[1]['CurrencyCode'] = 'EUR';
        $x[1]['ProductGroupList'][0] = 'gl_boxes';

        $list = $o->getPerformanceBondRefundEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getPerformanceBondRefundEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetServiceFeeEvents($o) {
        $x = array();
        $x[0]['AmazonOrderId'] = '333-1234567-1234567';
        $x[0]['FeeReason'] = 'fba inbound defect fee';
        $x[0]['FeeList'][0]['FeeType'] = 'FBAOrderHandlingFee';
        $x[0]['FeeList'][0]['Amount'] = '-0.99';
        $x[0]['FeeList'][0]['CurrencyCode'] = 'USD';
        $x[0]['FeeList'][1]['FeeType'] = 'FBAOrderHandlingFeeTax';
        $x[0]['FeeList'][1]['Amount'] = '-1.99';
        $x[0]['FeeList'][1]['CurrencyCode'] = 'USD';
        $x[0]['SellerSKU'] = 'CBA_OF_1';
        $x[0]['FnSKU'] = 'AKSJD12';
        $x[0]['FeeDescription'] = 'Test Fee description';
        $x[0]['ASIN'] = 'BT0093TELA';
        $x[1]['AmazonOrderId'] = '555-1234567-1234567';
        $x[1]['FeeReason'] = 'fba inbound defect again';
        $x[1]['FeeList'][0]['FeeType'] = 'HandlingFee';
        $x[1]['FeeList'][0]['Amount'] = '-3.99';
        $x[1]['FeeList'][0]['CurrencyCode'] = 'USD';
        $x[1]['SellerSKU'] = 'CBA_OF_2';
        $x[1]['FnSKU'] = 'ASDFJ12';
        $x[1]['FeeDescription'] = 'Test Fee more';
        $x[1]['ASIN'] = 'BT0093F0N3';

        $list = $o->getServiceFeeEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getServiceFeeEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetDebtRecoveryEvents($o) {
        $x = array();
        $x[0]['DebtRecoveryType'] = 'DebtAdjustment';
        $x[0]['RecoveryAmount']['Amount'] = '10.99';
        $x[0]['RecoveryAmount']['CurrencyCode'] = 'USD';
        $x[0]['OverPaymentCredit']['Amount'] = '8.99';
        $x[0]['OverPaymentCredit']['CurrencyCode'] = 'USD';
        $x[0]['DebtRecoveryItemList'][0]['RecoveryAmount']['Amount'] = '5.99';
        $x[0]['DebtRecoveryItemList'][0]['RecoveryAmount']['CurrencyCode'] = 'USD';
        $x[0]['DebtRecoveryItemList'][0]['OriginalAmount']['Amount'] = '4.99';
        $x[0]['DebtRecoveryItemList'][0]['OriginalAmount']['CurrencyCode'] = 'USD';
        $x[0]['DebtRecoveryItemList'][0]['GroupBeginDate'] = '2013-09-09T01:30:00.000-06:00';
        $x[0]['DebtRecoveryItemList'][0]['GroupEndDate'] = '2013-09-23T01:30:00.000-06:00';
        $x[0]['DebtRecoveryItemList'][1]['RecoveryAmount']['Amount'] = '3.99';
        $x[0]['DebtRecoveryItemList'][1]['RecoveryAmount']['CurrencyCode'] = 'USD';
        $x[0]['DebtRecoveryItemList'][1]['OriginalAmount']['Amount'] = '2.99';
        $x[0]['DebtRecoveryItemList'][1]['OriginalAmount']['CurrencyCode'] = 'USD';
        $x[0]['DebtRecoveryItemList'][1]['GroupBeginDate'] = '2013-09-10T01:30:00.000-06:00';
        $x[0]['DebtRecoveryItemList'][1]['GroupEndDate'] = '2013-09-24T01:30:00.000-06:00';
        $x[0]['ChargeInstrumentList'][0]['Description'] = 'Credit card';
        $x[0]['ChargeInstrumentList'][0]['Tail'] = '9887';
        $x[0]['ChargeInstrumentList'][0]['Amount'] = '9.99';
        $x[0]['ChargeInstrumentList'][0]['CurrencyCode'] = 'USD';
        $x[0]['ChargeInstrumentList'][1]['Description'] = 'Debit card';
        $x[0]['ChargeInstrumentList'][1]['Tail'] = '9889';
        $x[0]['ChargeInstrumentList'][1]['Amount'] = '10.99';
        $x[0]['ChargeInstrumentList'][1]['CurrencyCode'] = 'USD';
        $x[1]['DebtRecoveryType'] = 'DebtAdjustment2';
        $x[1]['RecoveryAmount']['Amount'] = '11.99';
        $x[1]['RecoveryAmount']['CurrencyCode'] = 'USD';
        $x[1]['OverPaymentCredit']['Amount'] = '9.99';
        $x[1]['OverPaymentCredit']['CurrencyCode'] = 'USD';
        $x[1]['DebtRecoveryItemList'][0]['RecoveryAmount']['Amount'] = '2.99';
        $x[1]['DebtRecoveryItemList'][0]['RecoveryAmount']['CurrencyCode'] = 'USD';
        $x[1]['DebtRecoveryItemList'][0]['OriginalAmount']['Amount'] = '1.99';
        $x[1]['DebtRecoveryItemList'][0]['OriginalAmount']['CurrencyCode'] = 'USD';
        $x[1]['DebtRecoveryItemList'][0]['GroupBeginDate'] = '2013-09-11T01:30:00.000-06:00';
        $x[1]['DebtRecoveryItemList'][0]['GroupEndDate'] = '2013-09-25T01:30:00.000-06:00';
        $x[1]['ChargeInstrumentList'][0]['Description'] = 'Debit card';
        $x[1]['ChargeInstrumentList'][0]['Tail'] = '1234';
        $x[1]['ChargeInstrumentList'][0]['Amount'] = '5.99';
        $x[1]['ChargeInstrumentList'][0]['CurrencyCode'] = 'USD';

        $list = $o->getDebtRecoveryEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getDebtRecoveryEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetLoanServicingEvents($o) {
        $x = array();
        $x[0]['Amount'] = '13.99';
        $x[0]['CurrencyCode'] = 'USD';
        $x[0]['SourceBusinessEventType'] = 'LoanAdvance';
        $x[1]['Amount'] = '15.99';
        $x[1]['CurrencyCode'] = 'USD';
        $x[1]['SourceBusinessEventType'] = 'LoanAdvance2';

        $list = $o->getLoanServicingEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getLoanServicingEvents());
    }

    /**
     * @param AmazonFinancialEventList $o
     * @depends testFetchEventList
     */
    public function testGetAdjustmentEvents($o) {
        $x = array();
        $x[0]['AdjustmentType'] = 'PostageBilling';
        $x[0]['Amount'] = '-5.99';
        $x[0]['CurrencyCode'] = 'USD';
        $x[0]['AdjustmentItemList'][0]['Quantity'] = '2';
        $x[0]['AdjustmentItemList'][0]['PerUnitAmount']['Amount'] = '-1.99';
        $x[0]['AdjustmentItemList'][0]['PerUnitAmount']['CurrencyCode'] = 'USD';
        $x[0]['AdjustmentItemList'][0]['TotalAmount']['Amount'] = '-5.99';
        $x[0]['AdjustmentItemList'][0]['TotalAmount']['CurrencyCode'] = 'USD';
        $x[0]['AdjustmentItemList'][0]['SellerSKU'] = 'ASK_AS_1';
        $x[0]['AdjustmentItemList'][0]['FnSKU'] = 'ASLKLDS12';
        $x[0]['AdjustmentItemList'][0]['ProductDescription'] = 'Test Product';
        $x[0]['AdjustmentItemList'][0]['ASIN'] = 'BT0093TELA';
        $x[0]['AdjustmentItemList'][1]['Quantity'] = '1';
        $x[0]['AdjustmentItemList'][1]['PerUnitAmount']['Amount'] = '-2.99';
        $x[0]['AdjustmentItemList'][1]['PerUnitAmount']['CurrencyCode'] = 'USD';
        $x[0]['AdjustmentItemList'][1]['TotalAmount']['Amount'] = '-6.99';
        $x[0]['AdjustmentItemList'][1]['TotalAmount']['CurrencyCode'] = 'USD';
        $x[0]['AdjustmentItemList'][1]['SellerSKU'] = 'ASK_AS_2';
        $x[0]['AdjustmentItemList'][1]['FnSKU'] = 'ASDFJDS12';
        $x[0]['AdjustmentItemList'][1]['ProductDescription'] = 'Test Product 2';
        $x[0]['AdjustmentItemList'][1]['ASIN'] = 'BT0093F0N3';
        $x[1]['AdjustmentType'] = 'PostageBilling2';
        $x[1]['Amount'] = '-8.99';
        $x[1]['CurrencyCode'] = 'USD';
        $x[1]['AdjustmentItemList'][0]['Quantity'] = '3';
        $x[1]['AdjustmentItemList'][0]['PerUnitAmount']['Amount'] = '-4.99';
        $x[1]['AdjustmentItemList'][0]['PerUnitAmount']['CurrencyCode'] = 'USD';
        $x[1]['AdjustmentItemList'][0]['TotalAmount']['Amount'] = '-7.99';
        $x[1]['AdjustmentItemList'][0]['TotalAmount']['CurrencyCode'] = 'USD';
        $x[1]['AdjustmentItemList'][0]['SellerSKU'] = 'ASK_AS_3';
        $x[1]['AdjustmentItemList'][0]['FnSKU'] = 'ASDFJDS99';
        $x[1]['AdjustmentItemList'][0]['ProductDescription'] = 'Test Product 3';
        $x[1]['AdjustmentItemList'][0]['ASIN'] = 'BT0093BNNA';

        $list = $o->getAdjustmentEvents();
        $this->assertInternalType('array', $list);
        $this->assertEquals($x, $list);
        //not fetched yet for this object
        $this->assertFalse($this->object->getAdjustmentEvents());
    }

}

require_once('helperFunctions.php');
