<?php

class AmazonFinancialGroupListTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonFinancialGroupList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonFinancialGroupList('testStore', true, null, __DIR__.'/../../test-config.php');
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
        $try = $this->object->setTimeLimits($a, $b);
        $o = $this->object->getOptions();
        if ($c) {
            $this->assertNull($try);
            $this->assertArrayHasKey('FinancialEventGroupStartedAfter', $o);
            $this->assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i', $o['FinancialEventGroupStartedAfter']);
        } else {
            $this->assertFalse($try);
            $this->assertArrayNotHasKey('FinancialEventGroupStartedAfter', $o);
        }

        if ($c && $d) {
            $this->assertArrayHasKey('FinancialEventGroupStartedBefore', $o);
            $this->assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i', $o['FinancialEventGroupStartedBefore']);
            //setting only first date resets second one
            $this->assertNull($this->object->setTimeLimits($a));
            $o2 = $this->object->getOptions();
            $this->assertArrayNotHasKey('FinancialEventGroupStartedBefore', $o2);
        } else {
            $this->assertArrayNotHasKey('FinancialEventGroupStartedBefore', $o);
        }
    }

    public function testFetchGroupList() {
        resetLog();
        $this->object->setMock(true, 'fetchFinancialGroups.xml'); //no token
        $this->assertFalse($this->object->fetchGroupList()); //no date yet
        $this->object->setTimeLimits('-1 day');
        $this->assertNull($this->object->fetchGroupList());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchFinancialGroups.xml', $check[1]);
        $this->assertEquals('Start date must be set in order to fetch financial event groups', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialGroups.xml', $check[3]);

        $o = $this->object->getOptions();
        $this->assertEquals('ListFinancialEventGroups', $o['Action']);

        $this->assertFalse($this->object->hasToken());

        return $this->object;
    }

    public function testFetchGroupListToken1() {
        resetLog();
        $this->object->setMock(true, 'fetchFinancialGroupsToken.xml');
        //without using token
        $this->object->setTimeLimits('-1 day');
        $this->assertNull($this->object->fetchGroupList());
        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchFinancialGroupsToken.xml', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialGroupsToken.xml', $check[2]);

        $this->assertTrue($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListFinancialEventGroups', $o['Action']);
        $r = $this->object->getGroups();
        $this->assertInternalType('array', $r);
        $this->assertCount(1, $r);
    }

    public function testFetchGroupListToken2() {
        resetLog();
        $this->object->setMock(true, array('fetchFinancialGroupsToken.xml', 'fetchFinancialGroupsToken2.xml'));

        //with using token
        $this->object->setUseToken();
        $this->object->setTimeLimits('-1 day');
        $this->assertNull($this->object->fetchGroupList());
        $check = parseLog();
        $this->assertEquals('Mock files array set.', $check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialGroupsToken.xml', $check[2]);
        $this->assertEquals('Recursively fetching more Financial Event Groups', $check[3]);
        $this->assertEquals('Fetched Mock File: mock/fetchFinancialGroupsToken2.xml', $check[4]);
        $this->assertFalse($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('ListFinancialEventGroupsByNextToken', $o['Action']);
        $this->assertArrayNotHasKey('FinancialEventGroupStartedAfter', $o);
        $r = $this->object->getGroups();
        $this->assertInternalType('array', $r);
        $this->assertCount(2, $r);
        $this->assertNotEquals($r[0], $r[1]);
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetGroups($o) {
        $list = $o->getGroups();
        $this->assertInternalType('array', $list);
        $this->assertCount(2, $list);
        $this->assertArrayHasKey(0, $list);
        $this->assertArrayHasKey(1, $list);
        $this->assertArrayHasKey('FinancialEventGroupId', $list[0]);
        $this->assertEquals($o->getGroupId(0), $list[0]['FinancialEventGroupId']);
        $this->assertArrayHasKey('ProcessingStatus', $list[0]);
        $this->assertEquals($o->getProcessingStatus(0), $list[0]['ProcessingStatus']);
        $this->assertArrayHasKey('FundTransferStatus', $list[0]);
        $this->assertEquals($o->getTransferStatus(0), $list[0]['FundTransferStatus']);
        $this->assertArrayHasKey('OriginalTotal', $list[0]);
        $this->assertEquals($o->getOriginalTotal(0), $list[0]['OriginalTotal']);
        $this->assertArrayHasKey('ConvertedTotal', $list[0]);
        $this->assertEquals($o->getConvertedTotal(0), $list[0]['ConvertedTotal']);
        $this->assertArrayHasKey('FundTransferDate', $list[0]);
        $this->assertEquals($o->getTransferDate(0), $list[0]['FundTransferDate']);
        $this->assertArrayHasKey('TraceId', $list[0]);
        $this->assertEquals($o->getTraceId(0), $list[0]['TraceId']);
        $this->assertArrayHasKey('AccountTail', $list[0]);
        $this->assertEquals($o->getAccountTail(0), $list[0]['AccountTail']);
        $this->assertArrayHasKey('BeginningBalance', $list[0]);
        $this->assertEquals($o->getBeginningBalance(0), $list[0]['BeginningBalance']);
        $this->assertArrayHasKey('FinancialEventGroupStart', $list[0]);
        $this->assertEquals($o->getStartDate(0), $list[0]['FinancialEventGroupStart']);
        $this->assertArrayHasKey('FinancialEventGroupEnd', $list[0]);
        $this->assertEquals($o->getEndDate(0), $list[0]['FinancialEventGroupEnd']);
        //not fetched yet for this object
        $this->assertFalse($this->object->getGroups());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetGroupId($o) {
        $this->assertEquals('22YgYW55IGNhcm5hbCBwbGVhEXAMPLE', $o->getGroupId(0));
        $this->assertEquals('22Y99995IGNhcm5hbANOTHEREXAMPLE', $o->getGroupId(1));
        $this->assertEquals($o->getGroupId(0), $o->getGroupId());
        //not fetched yet for this object
        $this->assertFalse($this->object->getGroupId());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetProcessingStatus($o) {
        $this->assertEquals('Closed', $o->getProcessingStatus(0));
        $this->assertEquals('Closed2', $o->getProcessingStatus(1));
        $this->assertEquals($o->getProcessingStatus(0), $o->getProcessingStatus());
        //not fetched yet for this object
        $this->assertFalse($this->object->getProcessingStatus());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetTransferStatus($o) {
        $this->assertEquals('Successful', $o->getTransferStatus(0));
        $this->assertEquals('Successful2', $o->getTransferStatus(1));
        $this->assertEquals($o->getTransferStatus(0), $o->getTransferStatus());
        //not fetched yet for this object
        $this->assertFalse($this->object->getTransferStatus());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetOriginalTotal($o) {
        $x0 = array();
        $x0['Amount'] = '19.00';
        $x0['CurrencyCode'] = 'USD';
        $x1 = array();
        $x1['Amount'] = '42.00';
        $x1['CurrencyCode'] = 'USD';
        $this->assertEquals($x0, $o->getOriginalTotal(0));
        $this->assertEquals($x0['Amount'], $o->getOriginalTotal(0, true));
        $this->assertEquals($x1, $o->getOriginalTotal(1));
        $this->assertEquals($x1['Amount'], $o->getOriginalTotal(1, true));
        $this->assertEquals($o->getOriginalTotal(0), $o->getOriginalTotal());
        //not fetched yet for this object
        $this->assertFalse($this->object->getOriginalTotal());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetConvertedTotal($o) {
        $x0 = array();
        $x0['Amount'] = '19.50';
        $x0['CurrencyCode'] = 'USD';
        $x1 = array();
        $x1['Amount'] = '42.50';
        $x1['CurrencyCode'] = 'USD';
        $this->assertEquals($x0, $o->getConvertedTotal(0));
        $this->assertEquals($x0['Amount'], $o->getConvertedTotal(0, true));
        $this->assertEquals($x1, $o->getConvertedTotal(1));
        $this->assertEquals($x1['Amount'], $o->getConvertedTotal(1, true));
        $this->assertEquals($o->getConvertedTotal(0), $o->getConvertedTotal());
        //not fetched yet for this object
        $this->assertFalse($this->object->getConvertedTotal());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetTransferDate($o) {
        $this->assertEquals('2014-09-09T01:30:00.000-06:00', $o->getTransferDate(0));
        $this->assertEquals('2014-10-09T01:30:00.000-06:00', $o->getTransferDate(1));
        $this->assertEquals($o->getTransferDate(0), $o->getTransferDate());
        //not fetched yet for this object
        $this->assertFalse($this->object->getTransferDate());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetTraceId($o) {
        $this->assertEquals('128311029381HSADJEXAMPLE', $o->getTraceId(0));
        $this->assertEquals('128999929381HADJEXAMPLE2', $o->getTraceId(1));
        $this->assertEquals($o->getTraceId(0), $o->getTraceId());
        //not fetched yet for this object
        $this->assertFalse($this->object->getTraceId());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetAccountTail($o) {
        $this->assertEquals('1212', $o->getAccountTail(0));
        $this->assertEquals('1313', $o->getAccountTail(1));
        $this->assertEquals($o->getAccountTail(0), $o->getAccountTail());
        //not fetched yet for this object
        $this->assertFalse($this->object->getAccountTail());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetBeginningBalance($o) {
        $x0 = array();
        $x0['Amount'] = '0.00';
        $x0['CurrencyCode'] = 'USD';
        $x1 = array();
        $x1['Amount'] = '20.00';
        $x1['CurrencyCode'] = 'USD';
        $this->assertEquals($x0, $o->getBeginningBalance(0));
        $this->assertEquals($x0['Amount'], $o->getBeginningBalance(0, true));
        $this->assertEquals($x1, $o->getBeginningBalance(1));
        $this->assertEquals($x1['Amount'], $o->getBeginningBalance(1, true));
        $this->assertEquals($o->getBeginningBalance(0), $o->getBeginningBalance());
        //not fetched yet for this object
        $this->assertFalse($this->object->getBeginningBalance());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetStartDate($o) {
        $this->assertEquals('2014-09-01T01:30:00.000-06:00', $o->getStartDate(0));
        $this->assertEquals('2014-10-01T01:30:00.000-06:00', $o->getStartDate(1));
        $this->assertEquals($o->getStartDate(0), $o->getStartDate());
        //not fetched yet for this object
        $this->assertFalse($this->object->getStartDate());
    }

    /**
     * @param AmazonFinancialGroupList $o
     * @depends testFetchGroupList
     */
    public function testGetEndDate($o) {
        $this->assertEquals('2014-09-09T01:30:00.000-06:00', $o->getEndDate(0));
        $this->assertEquals('2014-10-09T01:30:00.000-06:00', $o->getEndDate(1));
        $this->assertEquals($o->getEndDate(0), $o->getEndDate());
        //not fetched yet for this object
        $this->assertFalse($this->object->getEndDate());
    }

}

require_once('helperFunctions.php');
