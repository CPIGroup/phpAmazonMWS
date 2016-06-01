<?php

class AmazonTransportTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonTransport
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonTransport('testStore', null, true, null, __DIR__.'/../../test-config.php');
    }

    public function testSetUp() {
        $obj = new AmazonTransport('testStore', '77', true, null, __DIR__.'/../../test-config.php');

        $o = $obj->getOptions();
        $this->assertArrayHasKey('ShipmentId', $o);
        $this->assertEquals('77', $o['ShipmentId']);
    }

    public function testSetShipmentId() {
        $this->assertNull($this->object->setShipmentId('777'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('ShipmentId', $o);
        $this->assertEquals('777', $o['ShipmentId']);
        $this->assertFalse($this->object->setShipmentId(77)); //won't work for numbers
        $this->assertFalse($this->object->setShipmentId(array())); //won't work for this
        $this->assertFalse($this->object->setShipmentId(null)); //won't work for other things
    }

    public function testSetIsPartnered() {
        $o1 = $this->object->getOptions();
        $this->assertArrayNotHasKey('IsPartnered', $o1);
        $this->assertNull($this->object->setIsPartnered(true));
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey('IsPartnered', $o2);
        $this->assertEquals('true', $o2['IsPartnered']);
        $this->assertNull($this->object->setIsPartnered(false));
        $o3 = $this->object->getOptions();
        $this->assertArrayHasKey('IsPartnered', $o3);
        $this->assertEquals('false', $o3['IsPartnered']);
    }

    public function testSetShipmentType(){
        $this->assertFalse($this->object->setShipmentType(null)); //can't be nothing
        $this->assertFalse($this->object->setShipmentType(5)); //can't be an int
        $this->assertFalse($this->object->setShipmentType('wrong')); //not a valid value
        $this->assertNull($this->object->setShipmentType('SP'));
        $this->assertNull($this->object->setShipmentType('LTL'));
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('ShipmentType', $o);
        $this->assertEquals('LTL', $o['ShipmentType']);
    }

    /**
     * @return array
     */
    public function comboProvider() {
        return array(
            array('SP', true),
            array('SP', false),
            array('LTL', true),
            array('LTL', false),
        );
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetCarrier($type, $partnered) {
        resetLog();

        $this->assertFalse($this->object->setCarrier('truck')); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setCarrier('truck')); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setCarrier('truck'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.CarrierName';
        $this->assertArrayHasKey($op, $o);
        $this->assertEquals('truck', $o[$op]);

        //invalid values
        $this->assertFalse($this->object->setCarrier(''));
        $this->assertFalse($this->object->setCarrier(null));
        $this->assertFalse($this->object->setCarrier(6));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set carrier name because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetPackages($type, $partnered) {
        $op = $this->findOp($type, $partnered);
        resetLog();

        $data1 = array(
            array(
                'Length' => '6',
                'Width' => '7',
                'Height' => '8',
                'Weight' => '9.8',
            ),
            array(
                'Length' => '6',
                'Width' => '7',
                'TrackingId' => 'Z123',
            ),
            array(
                'TrackingId' => 'Z456',
            ),
        );

        $this->assertFalse($this->object->setPackages($data1)); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setPackages($data1)); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setPackages($data1));
        $o = $this->object->getOptions();
        //package 1/3
        $this->assertArrayHasKey($op.'.PackageList.member.1.Dimensions.Length', $o);
        $this->assertEquals('6', $o[$op.'.PackageList.member.1.Dimensions.Length']);
        $this->assertArrayHasKey($op.'.PackageList.member.1.Dimensions.Width', $o);
        $this->assertEquals('7', $o[$op.'.PackageList.member.1.Dimensions.Width']);
        $this->assertArrayHasKey($op.'.PackageList.member.1.Dimensions.Height', $o);
        $this->assertEquals('8', $o[$op.'.PackageList.member.1.Dimensions.Height']);
        $this->assertArrayHasKey($op.'.PackageList.member.1.Dimensions.Unit', $o);
        $this->assertEquals('centimeters', $o[$op.'.PackageList.member.1.Dimensions.Unit']);
        $this->assertArrayHasKey($op.'.PackageList.member.1.Weight.Value', $o);
        $this->assertEquals('9.8', $o[$op.'.PackageList.member.1.Weight.Value']);
        $this->assertArrayHasKey($op.'.PackageList.member.1.Weight.Unit', $o);
        $this->assertEquals('kilograms', $o[$op.'.PackageList.member.1.Weight.Unit']);
        $this->assertArrayNotHasKey($op.'.PackageList.member.1.TrackingId', $o);
        //package 2/3
        $this->assertArrayHasKey($op.'.PackageList.member.2.TrackingId', $o);
        $this->assertEquals('Z123', $o[$op.'.PackageList.member.2.TrackingId']);
        $this->assertArrayNotHasKey($op.'.PackageList.member.2.Dimensions.Length', $o);
        $this->assertArrayNotHasKey($op.'.PackageList.member.2.Dimensions.Width', $o);
        $this->assertArrayNotHasKey($op.'.PackageList.member.2.Dimensions.Height', $o);
        $this->assertArrayNotHasKey($op.'.PackageList.member.2.Dimensions.Unit', $o);
        $this->assertArrayNotHasKey($op.'.PackageList.member.2.Weight.Value', $o);
        $this->assertArrayNotHasKey($op.'.PackageList.member.2.Weight.Unit', $o);
        //package 3/3
        $this->assertArrayHasKey($op.'.PackageList.member.3.TrackingId', $o);
        $this->assertEquals('Z456', $o[$op.'.PackageList.member.3.TrackingId']);

        //setting again will cause a reset
        $data2 = array(
            array(
                'TrackingId' => 'Z789',
            ),
            array(
                'Length' => '10',
                'Width' => '11',
                'Height' => '12',
                'Weight' => '20',
            ),
        );

        $this->assertNull($this->object->setPackages($data2, 'inches', 'pounds'));
        $o2 = $this->object->getOptions();
        //package 1/2
        $this->assertArrayHasKey($op.'.PackageList.member.1.TrackingId', $o2);
        $this->assertEquals('Z789', $o2[$op.'.PackageList.member.1.TrackingId']);
        $this->assertArrayNotHasKey($op.'.PackageList.member.1.Dimensions.Length', $o2);
        $this->assertArrayNotHasKey($op.'.PackageList.member.1.Dimensions.Width', $o2);
        $this->assertArrayNotHasKey($op.'.PackageList.member.1.Dimensions.Height', $o2);
        $this->assertArrayNotHasKey($op.'.PackageList.member.1.Dimensions.Unit', $o2);
        $this->assertArrayNotHasKey($op.'.PackageList.member.1.Weight.Value', $o2);
        $this->assertArrayNotHasKey($op.'.PackageList.member.1.Weight.Unit', $o2);
        //package 2/2
        $this->assertArrayHasKey($op.'.PackageList.member.2.Dimensions.Length', $o2);
        $this->assertEquals('10', $o2[$op.'.PackageList.member.2.Dimensions.Length']);
        $this->assertArrayHasKey($op.'.PackageList.member.2.Dimensions.Width', $o2);
        $this->assertEquals('11', $o2[$op.'.PackageList.member.2.Dimensions.Width']);
        $this->assertArrayHasKey($op.'.PackageList.member.2.Dimensions.Height', $o2);
        $this->assertEquals('12', $o2[$op.'.PackageList.member.2.Dimensions.Height']);
        $this->assertArrayHasKey($op.'.PackageList.member.2.Dimensions.Unit', $o2);
        $this->assertEquals('inches', $o2[$op.'.PackageList.member.2.Dimensions.Unit']);
        $this->assertArrayHasKey($op.'.PackageList.member.2.Weight.Value', $o2);
        $this->assertEquals('20', $o2[$op.'.PackageList.member.2.Weight.Value']);
        $this->assertArrayHasKey($op.'.PackageList.member.2.Weight.Unit', $o2);
        $this->assertEquals('pounds', $o2[$op.'.PackageList.member.2.Weight.Unit']);
        $this->assertArrayNotHasKey($op.'.PackageList.member.2.TrackingId', $o2);
        //no package 3
        $this->assertArrayNotHasKey($op.'.PackageList.member.3.TrackingId', $o2);


        //invalid values
        $this->assertFalse($this->object->setPackages(array('banana')));
        $this->assertFalse($this->object->setPackages(array()));
        $this->assertFalse($this->object->setPackages('banana'));
        $this->assertFalse($this->object->setPackages(6));
        $this->assertFalse($this->object->setPackages(null));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set packages because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
        $this->assertEquals('Tried to set packages with invalid array', $check[4]);
        $this->assertEquals('Tried to set package list to invalid values', $check[5]);
        $this->assertEquals($check[5], $check[6]);
        $this->assertEquals($check[5], $check[7]);
        $this->assertEquals($check[5], $check[8]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetProNumber($type, $partnered) {
        resetLog();

        $this->assertFalse($this->object->setProNumber('123ABC7')); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setProNumber('123ABC7')); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setProNumber('123ABC7'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.ProNumber';
        $this->assertArrayHasKey($op, $o);
        $this->assertEquals('123ABC7', $o[$op]);

        //invalid values
        $this->assertFalse($this->object->setProNumber(''));
        $this->assertFalse($this->object->setProNumber(null));
        $this->assertFalse($this->object->setProNumber(6));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set PRO number because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testContact($type, $partnered) {
        resetLog();

        //missing partnered and type
        $this->assertFalse($this->object->setContact('Bob', '555-1234', 'test@email.com', '555-6789'));
        $this->assertNull($this->object->setIsPartnered($partnered));
        //missing shipment type
        $this->assertFalse($this->object->setContact('Bob', '555-1234', 'test@email.com', '555-6789'));
        $this->assertNull($this->object->setShipmentType($type));
        //good now
        $this->assertNull($this->object->setContact('Bob', '555-1234', 'test@email.com', '555-6789'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.Contact';
        $this->assertArrayHasKey($op.'.Name', $o);
        $this->assertEquals('Bob', $o[$op.'.Name']);
        $this->assertArrayHasKey($op.'.Phone', $o);
        $this->assertEquals('555-1234', $o[$op.'.Phone']);
        $this->assertArrayHasKey($op.'.Email', $o);
        $this->assertEquals('test@email.com', $o[$op.'.Email']);
        $this->assertArrayHasKey($op.'.Fax', $o);
        $this->assertEquals('555-6789', $o[$op.'.Fax']);

        //invalid values
        $this->assertFalse($this->object->setContact(false, '555-1234', 'test@email.com', '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', false, 'test@email.com', '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', '555-1234', false, '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', '555-1234', 'test@email.com', false));
        $this->assertFalse($this->object->setContact(array(), '555-1234', 'test@email.com', '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', array(), 'test@email.com', '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', '555-1234', array(), '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', '555-1234', 'test@email.com', array()));
        $this->assertFalse($this->object->setContact(5, '555-1234', 'test@email.com', '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', 5.2, 'test@email.com', '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', '555-1234', 5, '555-6789'));
        $this->assertFalse($this->object->setContact('Bob', '555-1234', 'test@email.com', 5.2));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set contact info because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetBoxCount($type, $partnered) {
        resetLog();

        $this->assertFalse($this->object->setBoxCount('12')); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setBoxCount('12')); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setBoxCount('12'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.BoxCount';
        $this->assertArrayHasKey($op, $o);
        $this->assertEquals('12', $o[$op]);

        //invalid values
        $this->assertFalse($this->object->setBoxCount(0));
        $this->assertFalse($this->object->setBoxCount('0'));
        $this->assertFalse($this->object->setBoxCount('-3'));
        $this->assertFalse($this->object->setBoxCount(null));
        $this->assertFalse($this->object->setBoxCount('banana'));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set box count because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetFreightClass($type, $partnered) {
        resetLog();

        $this->assertFalse($this->object->setFreightClass('12')); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setFreightClass('12')); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setFreightClass('12'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.SellerFreightClass';
        $this->assertArrayHasKey($op, $o);
        $this->assertEquals('12', $o[$op]);

        //invalid values
        $this->assertFalse($this->object->setFreightClass(0));
        $this->assertFalse($this->object->setFreightClass(null));
        $this->assertFalse($this->object->setFreightClass('banana'));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set freight class because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetReadyDate($type, $partnered) {
        resetLog();

        $this->assertFalse($this->object->setReadyDate('+50 min')); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setReadyDate('+50 min')); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setReadyDate('+50 min'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.FreightReadyDate';
        $this->assertArrayHasKey($op, $o);
        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $o[$op]);

        //invalid values
        $this->assertFalse($this->object->setReadyDate(array(5)));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set ready date because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
        $this->assertEquals('Error: Invalid time input given', $check[4]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetPallets($type, $partnered) {
        $op = $this->findOp($type, $partnered);
        resetLog();

        $data1 = array(
            array(
                'Length' => '6',
                'Width' => '7',
                'Height' => '8',
                'Weight' => '9.8',
            ),
            array(
                'Length' => '6',
                'Width' => '7',
                'IsStacked' => true,
            ),
            array(
                'IsStacked' => false,
            ),
        );

        $this->assertFalse($this->object->setPallets($data1)); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setPallets($data1)); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setPallets($data1));
        $o = $this->object->getOptions();
        //package 1/3
        $this->assertArrayHasKey($op.'.PalletList.member.1.Dimensions.Length', $o);
        $this->assertEquals('6', $o[$op.'.PalletList.member.1.Dimensions.Length']);
        $this->assertArrayHasKey($op.'.PalletList.member.1.Dimensions.Width', $o);
        $this->assertEquals('7', $o[$op.'.PalletList.member.1.Dimensions.Width']);
        $this->assertArrayHasKey($op.'.PalletList.member.1.Dimensions.Height', $o);
        $this->assertEquals('8', $o[$op.'.PalletList.member.1.Dimensions.Height']);
        $this->assertArrayHasKey($op.'.PalletList.member.1.Dimensions.Unit', $o);
        $this->assertEquals('centimeters', $o[$op.'.PalletList.member.1.Dimensions.Unit']);
        $this->assertArrayHasKey($op.'.PalletList.member.1.Weight.Value', $o);
        $this->assertEquals('9.8', $o[$op.'.PalletList.member.1.Weight.Value']);
        $this->assertArrayHasKey($op.'.PalletList.member.1.Weight.Unit', $o);
        $this->assertEquals('kilograms', $o[$op.'.PalletList.member.1.Weight.Unit']);
        $this->assertArrayNotHasKey($op.'.PalletList.member.1.IsStacked', $o);
        //package 2/3
        $this->assertArrayHasKey($op.'.PalletList.member.2.IsStacked', $o);
        $this->assertEquals('true', $o[$op.'.PalletList.member.2.IsStacked']);
        $this->assertArrayNotHasKey($op.'.PalletList.member.2.Dimensions.Length', $o);
        $this->assertArrayNotHasKey($op.'.PalletList.member.2.Dimensions.Width', $o);
        $this->assertArrayNotHasKey($op.'.PalletList.member.2.Dimensions.Height', $o);
        $this->assertArrayNotHasKey($op.'.PalletList.member.2.Dimensions.Unit', $o);
        $this->assertArrayNotHasKey($op.'.PalletList.member.2.Weight.Value', $o);
        $this->assertArrayNotHasKey($op.'.PalletList.member.2.Weight.Unit', $o);
        //package 3/3
        $this->assertArrayHasKey($op.'.PalletList.member.3.IsStacked', $o);
        $this->assertEquals('false', $o[$op.'.PalletList.member.3.IsStacked']);

        //setting again will cause a reset
        $data2 = array(
            array(
                'IsStacked' => 0,
            ),
            array(
                'Length' => '10',
                'Width' => '11',
                'Height' => '12',
                'Weight' => '20',
            ),
        );

        $this->assertNull($this->object->setPallets($data2, 'inches', 'pounds'));
        $o2 = $this->object->getOptions();
        //package 1/2
        $this->assertArrayHasKey($op.'.PalletList.member.1.IsStacked', $o2);
        $this->assertEquals('false', $o2[$op.'.PalletList.member.1.IsStacked']);
        $this->assertArrayNotHasKey($op.'.PalletList.member.1.Dimensions.Length', $o2);
        $this->assertArrayNotHasKey($op.'.PalletList.member.1.Dimensions.Width', $o2);
        $this->assertArrayNotHasKey($op.'.PalletList.member.1.Dimensions.Height', $o2);
        $this->assertArrayNotHasKey($op.'.PalletList.member.1.Dimensions.Unit', $o2);
        $this->assertArrayNotHasKey($op.'.PalletList.member.1.Weight.Value', $o2);
        $this->assertArrayNotHasKey($op.'.PalletList.member.1.Weight.Unit', $o2);
        //package 2/2
        $this->assertArrayHasKey($op.'.PalletList.member.2.Dimensions.Length', $o2);
        $this->assertEquals('10', $o2[$op.'.PalletList.member.2.Dimensions.Length']);
        $this->assertArrayHasKey($op.'.PalletList.member.2.Dimensions.Width', $o2);
        $this->assertEquals('11', $o2[$op.'.PalletList.member.2.Dimensions.Width']);
        $this->assertArrayHasKey($op.'.PalletList.member.2.Dimensions.Height', $o2);
        $this->assertEquals('12', $o2[$op.'.PalletList.member.2.Dimensions.Height']);
        $this->assertArrayHasKey($op.'.PalletList.member.2.Dimensions.Unit', $o2);
        $this->assertEquals('inches', $o2[$op.'.PalletList.member.2.Dimensions.Unit']);
        $this->assertArrayHasKey($op.'.PalletList.member.2.Weight.Value', $o2);
        $this->assertEquals('20', $o2[$op.'.PalletList.member.2.Weight.Value']);
        $this->assertArrayHasKey($op.'.PalletList.member.2.Weight.Unit', $o2);
        $this->assertEquals('pounds', $o2[$op.'.PalletList.member.2.Weight.Unit']);
        $this->assertArrayNotHasKey($op.'.PalletList.member.2.IsStacked', $o2);
        //no package 3
        $this->assertArrayNotHasKey($op.'.PalletList.member.3.IsStacked', $o2);


        //invalid values
        $this->assertFalse($this->object->setPallets(array('banana')));
        $this->assertFalse($this->object->setPallets(array()));
        $this->assertFalse($this->object->setPallets('banana'));
        $this->assertFalse($this->object->setPallets(6));
        $this->assertFalse($this->object->setPallets(null));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set pallets because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
        $this->assertEquals('Tried to set pallets with invalid array', $check[4]);
        $this->assertEquals('Tried to set pallet list to invalid values', $check[5]);
        $this->assertEquals($check[5], $check[6]);
        $this->assertEquals($check[5], $check[7]);
        $this->assertEquals($check[5], $check[8]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetTotalWeight($type, $partnered) {
        resetLog();

        $this->assertFalse($this->object->setTotalWeight('123')); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setTotalWeight('123')); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setTotalWeight('123'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.TotalWeight';
        $this->assertArrayHasKey($op.'.Value', $o);
        $this->assertEquals('123', $o[$op.'.Value']);
        $this->assertArrayHasKey($op.'.Unit', $o);
        $this->assertEquals('kilograms', $o[$op.'.Unit']);

        //invalid values
        $this->assertFalse($this->object->setTotalWeight(null));
        $this->assertFalse($this->object->setTotalWeight('banana'));
        $this->assertFalse($this->object->setTotalWeight(''));
        $this->assertFalse($this->object->setTotalWeight(0));
        $this->assertFalse($this->object->setTotalWeight(6, 'bad unit'));
        $this->assertFalse($this->object->setTotalWeight(6, ''));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set total weight because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
    }

    /**
     * @param string $type "SP" or "LTL"
     * @param boolean $partnered partnered or not
     * @dataProvider comboProvider
     */
    public function testSetDeclaredValue($type, $partnered) {
        resetLog();

        $this->assertFalse($this->object->setDeclaredValue('1.23', 'USD')); //missing partnered and type
        $this->assertNull($this->object->setIsPartnered($partnered));
        $this->assertFalse($this->object->setDeclaredValue('1.23', 'USD')); //missing shipment type
        $this->assertNull($this->object->setShipmentType($type));
        $this->assertNull($this->object->setDeclaredValue('1.23', 'USD'));
        $o = $this->object->getOptions();
        $op = $this->findOp($type, $partnered).'.SellerDeclaredValue';
        $this->assertArrayHasKey($op.'.Value', $o);
        $this->assertEquals('1.23', $o[$op.'.Value']);
        $this->assertArrayHasKey($op.'.CurrencyCode', $o);
        $this->assertEquals('USD', $o[$op.'.CurrencyCode']);

        //invalid values
        $this->assertFalse($this->object->setDeclaredValue(null, 'USD'));
        $this->assertFalse($this->object->setDeclaredValue('banana', 'USD'));
        $this->assertFalse($this->object->setDeclaredValue('', 'USD'));
        $this->assertFalse($this->object->setDeclaredValue(0, 'USD'));
        $this->assertFalse($this->object->setDeclaredValue(6, 6));
        $this->assertFalse($this->object->setDeclaredValue(6, ''));

        $check = parseLog();
        $this->assertEquals($this->getOpError(), $check[0]);
        $this->assertEquals('Cannot set declared value because of the shipment type and partnered parameters.', $check[1]);
        $this->assertEquals($check[0], $check[2]);
        $this->assertEquals($check[1], $check[3]);
    }

    public function testSendTransportContentsWithPartneredSp() {
        resetLog();
        $this->object->setMock(true, 'sendTransportContents.xml');

        $this->assertFalse($this->object->sendTransportContents()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertFalse($this->object->sendTransportContents()); //no partnered status yet
        $this->assertNull($this->object->setIsPartnered(true));
        $this->assertFalse($this->object->sendTransportContents()); //no shipment type yet
        $this->assertNull($this->object->setShipmentType('SP'));
        $this->assertFalse($this->object->sendTransportContents()); //no packages yet
        $packages = array(array(
            'Length' => '5',
            'Width' => '6',
            'Height' => '7',
            'Weight' => '8',
        ));
        $this->assertNull($this->object->setPackages($packages));
        //all good now
        $this->assertNull($this->object->sendTransportContents());

        $this->assertFalse($this->object->getContentDetails());
        $this->assertEquals('WORKING', $this->object->getStatus());

        $check = parseLog();
        $m = ' must be set in order to send transport content!';
        $this->assertEquals('Single Mock File set: sendTransportContents.xml', $check[1]);
        $this->assertEquals('Shipment ID'.$m, $check[2]);
        $this->assertEquals('IsPartnered'.$m, $check[3]);
        $this->assertEquals('Shipment type'.$m, $check[4]);
        $this->assertEquals('Packages'.$m, $check[5]);
        $this->assertEquals('Fetched Mock File: mock/sendTransportContents.xml', $check[6]);
    }

    public function testSendTransportContentsWithNonPartneredSp() {
        resetLog();
        $this->object->setMock(true, 'sendTransportContents.xml');

        $this->assertFalse($this->object->sendTransportContents()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertFalse($this->object->sendTransportContents()); //no partnered status yet
        $this->assertNull($this->object->setIsPartnered(false));
        $this->assertFalse($this->object->sendTransportContents()); //no shipment type yet
        $this->assertNull($this->object->setShipmentType('SP'));
        $this->assertFalse($this->object->sendTransportContents()); //no carrier yet
        $this->assertNull($this->object->setCarrier('truck'));
        $this->assertFalse($this->object->sendTransportContents()); //no packages yet
        $packages = array(array(
            'Length' => '5',
            'Width' => '6',
            'Height' => '7',
            'Weight' => '8',
        ));
        $this->assertNull($this->object->setPackages($packages));
        //all good now
        $this->assertNull($this->object->sendTransportContents());

        $this->assertFalse($this->object->getContentDetails());
        $this->assertEquals('WORKING', $this->object->getStatus());

        $check = parseLog();
        $m = ' must be set in order to send transport content!';
        $this->assertEquals('Single Mock File set: sendTransportContents.xml', $check[1]);
        $this->assertEquals('Shipment ID'.$m, $check[2]);
        $this->assertEquals('IsPartnered'.$m, $check[3]);
        $this->assertEquals('Shipment type'.$m, $check[4]);
        $this->assertEquals('Carrier'.$m, $check[5]);
        $this->assertEquals('Packages'.$m, $check[6]);
        $this->assertEquals('Fetched Mock File: mock/sendTransportContents.xml', $check[7]);
    }

    public function testSendTransportContentsWithPartneredLtl() {
        resetLog();
        $this->object->setMock(true, 'sendTransportContents.xml');

        $this->assertFalse($this->object->sendTransportContents()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertFalse($this->object->sendTransportContents()); //no partnered status yet
        $this->assertNull($this->object->setIsPartnered(true));
        $this->assertFalse($this->object->sendTransportContents()); //no shipment type yet
        $this->assertNull($this->object->setShipmentType('LTL'));
        $this->assertFalse($this->object->sendTransportContents()); //no contact
        $this->assertNull($this->object->setContact('Bob', '555-1234', 'test@email.com', '555-6789'));
        $this->assertFalse($this->object->sendTransportContents()); //no box count yet
        $this->assertNull($this->object->setBoxCount(3));
        $this->assertFalse($this->object->sendTransportContents()); //no ready date yet
        $this->assertNull($this->object->setReadyDate('+3 days'));
        //all good now
        $this->assertNull($this->object->sendTransportContents());

        $this->assertFalse($this->object->getContentDetails());
        $this->assertEquals('WORKING', $this->object->getStatus());

        $check = parseLog();
        $m = ' must be set in order to send transport content!';
        $this->assertEquals('Single Mock File set: sendTransportContents.xml', $check[1]);
        $this->assertEquals('Shipment ID'.$m, $check[2]);
        $this->assertEquals('IsPartnered'.$m, $check[3]);
        $this->assertEquals('Shipment type'.$m, $check[4]);
        $this->assertEquals('Contact info'.$m, $check[5]);
        $this->assertEquals('Box count'.$m, $check[6]);
        $this->assertEquals('Ready date'.$m, $check[7]);
        $this->assertEquals('Fetched Mock File: mock/sendTransportContents.xml', $check[8]);
    }

    public function testSendTransportContentsWithNonPartneredLtl() {
        resetLog();
        $this->object->setMock(true, 'sendTransportContents.xml');

        $this->assertFalse($this->object->sendTransportContents()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertFalse($this->object->sendTransportContents()); //no partnered status yet
        $this->assertNull($this->object->setIsPartnered(false));
        $this->assertFalse($this->object->sendTransportContents()); //no shipment type yet
        $this->assertNull($this->object->setShipmentType('LTL'));
        $this->assertFalse($this->object->sendTransportContents()); //no carrier yet
        $this->assertNull($this->object->setCarrier('truck'));
        $this->assertFalse($this->object->sendTransportContents()); //no PRO number
        $this->assertNull($this->object->setProNumber('123ABC7'));
        //all good now
        $this->assertNull($this->object->sendTransportContents());

        $this->assertFalse($this->object->getContentDetails());
        $this->assertEquals('WORKING', $this->object->getStatus());

        $check = parseLog();
        $m = ' must be set in order to send transport content!';
        $this->assertEquals('Single Mock File set: sendTransportContents.xml', $check[1]);
        $this->assertEquals('Shipment ID'.$m, $check[2]);
        $this->assertEquals('IsPartnered'.$m, $check[3]);
        $this->assertEquals('Shipment type'.$m, $check[4]);
        $this->assertEquals('Carrier'.$m, $check[5]);
        $this->assertEquals('PRO number'.$m, $check[6]);
        $this->assertEquals('Fetched Mock File: mock/sendTransportContents.xml', $check[7]);
    }

    public function testEstimateTransport() {
        resetLog();
        $this->object->setMock(true, 'estimateTransport.xml');

        $this->assertFalse($this->object->estimateTransport()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertNull($this->object->estimateTransport());

        $this->assertFalse($this->object->getContentDetails());
        $this->assertEquals('ESTIMATING', $this->object->getStatus());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: estimateTransport.xml', $check[1]);
        $this->assertEquals('Shipment ID must be set in order to estimate the transport request!', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/estimateTransport.xml', $check[3]);
    }

    public function testConfirmTransport() {
        resetLog();
        $this->object->setMock(true, 'confirmTransport.xml');

        $this->assertFalse($this->object->confirmTransport()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertNull($this->object->confirmTransport());

        $this->assertFalse($this->object->getContentDetails());
        $this->assertEquals('CONFIRMING', $this->object->getStatus());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: confirmTransport.xml', $check[1]);
        $this->assertEquals('Shipment ID must be set in order to confirm the transport request!', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/confirmTransport.xml', $check[3]);
    }

    public function testVoidTransport() {
        resetLog();
        $this->object->setMock(true, 'voidTransport.xml');

        $this->assertFalse($this->object->voidTransport()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertNull($this->object->voidTransport());

        $this->assertFalse($this->object->getContentDetails());
        $this->assertEquals('VOIDING', $this->object->getStatus());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: voidTransport.xml', $check[1]);
        $this->assertEquals('Shipment ID must be set in order to void the transport request!', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/voidTransport.xml', $check[3]);
    }

    public function testFetchTransportSpPartnered() {
        resetLog();
        $this->object->setMock(true, 'fetchTransportContentSpPartnered.xml');

        $this->assertFalse($this->object->fetchTransportContent()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertNull($this->object->fetchTransportContent());
        $this->assertEquals('CONFIRMED', $this->object->getStatus());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchTransportContentSpPartnered.xml', $check[1]);
        $this->assertEquals('Shipment ID must be set in order to get transport contents!', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchTransportContentSpPartnered.xml', $check[3]);

        return $this->object;
    }

    public function testFetchTransportSpNonPartnered() {
        resetLog();
        $this->object->setMock(true, 'fetchTransportContentSpNonPartnered.xml');

        $this->assertFalse($this->object->fetchTransportContent()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertNull($this->object->fetchTransportContent());
        $this->assertEquals('ESTIMATED', $this->object->getStatus());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchTransportContentSpNonPartnered.xml', $check[1]);
        $this->assertEquals('Shipment ID must be set in order to get transport contents!', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchTransportContentSpNonPartnered.xml', $check[3]);

        return $this->object;
    }

    public function testFetchTransportLtlPartnered() {
        resetLog();
        $this->object->setMock(true, 'fetchTransportContentLtlPartnered.xml');

        $this->assertFalse($this->object->fetchTransportContent()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertNull($this->object->fetchTransportContent());
        $this->assertEquals('ERROR_ON_ESTIMATING', $this->object->getStatus());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchTransportContentLtlPartnered.xml', $check[1]);
        $this->assertEquals('Shipment ID must be set in order to get transport contents!', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchTransportContentLtlPartnered.xml', $check[3]);

        return $this->object;
    }

    public function testFetchTransportLtlNonPartnered() {
        resetLog();
        $this->object->setMock(true, 'fetchTransportContentLtlNonPartnered.xml');

        $this->assertFalse($this->object->fetchTransportContent()); //no shipment ID yet
        $this->assertNull($this->object->setShipmentId('77'));
        $this->assertNull($this->object->fetchTransportContent());
        $this->assertEquals('WORKING', $this->object->getStatus());

        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchTransportContentLtlNonPartnered.xml', $check[1]);
        $this->assertEquals('Shipment ID must be set in order to get transport contents!', $check[2]);
        $this->assertEquals('Fetched Mock File: mock/fetchTransportContentLtlNonPartnered.xml', $check[3]);

        return $this->object;
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetContentInfo($psp, $nsp, $pltl, $nltl) {
        foreach (array($psp, $nsp, $pltl, $nltl) as $o) {
            $info = $o->getContentInfo();
            $this->assertInternalType('array', $info);
            $this->assertNotEmpty($info);
            $this->assertArrayHasKey('SellerId', $info);
            $this->assertEquals($o->getSellerId(), $info['SellerId']);
            $this->assertArrayHasKey('ShipmentId', $info);
            $this->assertEquals($o->getShipmentId(), $info['ShipmentId']);
            $this->assertArrayHasKey('IsPartnered', $info);
            $this->assertEquals($o->getIsPartnered(), $info['IsPartnered']);
            $this->assertArrayHasKey('ShipmentType', $info);
            $this->assertEquals($o->getShipmentType(), $info['ShipmentType']);
            $this->assertArrayHasKey('Details', $info);
            $this->assertEquals($o->getContentDetails(), $info['Details']);
        }

        $this->assertFalse($this->object->getContentInfo()); //not fetched yet for this object
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetContentDetails($psp, $nsp, $pltl, $nltl) {
        $pspDetails = $psp->getContentDetails();
        $nspDetails = $nsp->getContentDetails();
        $pltlDetails = $pltl->getContentDetails();
        $nltlDetails = $nltl->getContentDetails();
        $this->assertInternalType('array', $pspDetails);
        $this->assertInternalType('array', $nspDetails);
        $this->assertInternalType('array', $pltlDetails);
        $this->assertInternalType('array', $nltlDetails);
        $this->assertNotEmpty($pspDetails);
        $this->assertNotEmpty($nspDetails);
        $this->assertNotEmpty($pltlDetails);
        $this->assertNotEmpty($nltlDetails);

        //partnered SP
        $this->assertArrayHasKey('PartneredEstimate', $pspDetails);
        $this->assertEquals($psp->getPartneredEstimate(), $pspDetails['PartneredEstimate']);
        $this->assertArrayHasKey('PackageList', $pspDetails);
        $this->assertEquals($psp->getPackageList(), $pspDetails['PackageList']);

        //non-partnered SP
        $this->assertArrayHasKey('PackageList', $nspDetails);
        $this->assertEquals($nsp->getPackageList(), $nspDetails['PackageList']);

        //partnered LTL
        $this->assertArrayHasKey('Contact', $pltlDetails);
        $this->assertEquals($pltl->getContact(), $pltlDetails['Contact']);
        $this->assertArrayHasKey('BoxCount', $pltlDetails);
        $this->assertEquals($pltl->getBoxCount(), $pltlDetails['BoxCount']);
        $this->assertArrayHasKey('SellerFreightClass', $pltlDetails);
        $this->assertEquals('55', $pltlDetails['SellerFreightClass']);
        $this->assertArrayHasKey('PreviewFreightClass', $pltlDetails);
        $this->assertEquals($pltl->getFreightClass(), $pltlDetails['PreviewFreightClass']);
        $this->assertArrayHasKey('FreightReadyDate', $pltlDetails);
        $this->assertEquals($pltl->getReadyDate(), $pltlDetails['FreightReadyDate']);
        $this->assertArrayHasKey('PalletList', $pltlDetails);
        $this->assertEquals($pltl->getPalletList(), $pltlDetails['PalletList']);
        $this->assertArrayHasKey('TotalWeight', $pltlDetails);
        $this->assertEquals($pltl->getTotalWeight(), $pltlDetails['TotalWeight']);
        $this->assertArrayHasKey('SellerDeclaredValue', $pltlDetails);
        $this->assertEquals($pltl->getDeclaredValue(), $pltlDetails['SellerDeclaredValue']);
        $this->assertArrayHasKey('AmazonCalculatedValue', $pltlDetails);
        $this->assertEquals($pltl->getCalculatedValue(), $pltlDetails['AmazonCalculatedValue']);
        $this->assertArrayHasKey('PreviewPickupDate', $pltlDetails);
        $this->assertEquals($pltl->getPickupDate(), $pltlDetails['PreviewPickupDate']);
        $this->assertArrayHasKey('PreviewDeliveryDate', $pltlDetails);
        $this->assertEquals($pltl->getDeliveryDate(), $pltlDetails['PreviewDeliveryDate']);
        $this->assertArrayHasKey('AmazonReferenceId', $pltlDetails);
        $this->assertEquals($pltl->getReferenceId(), $pltlDetails['AmazonReferenceId']);
        $this->assertArrayHasKey('IsBillOfLadingAvailable', $pltlDetails);
        $this->assertEquals($pltl->getIsBillOfLadingAvailable(), $pltlDetails['IsBillOfLadingAvailable']);
        $this->assertArrayHasKey('CarrierName', $pltlDetails);
        $this->assertEquals($pltl->getCarrier(), $pltlDetails['CarrierName']);

        //non-partnered LTL
        $this->assertArrayHasKey('CarrierName', $nltlDetails);
        $this->assertEquals($nltl->getCarrier(), $nltlDetails['CarrierName']);
        $this->assertArrayHasKey('ProNumber', $nltlDetails);
        $this->assertEquals($nltl->getProNumber(), $nltlDetails['ProNumber']);

        $this->assertFalse($this->object->getContentDetails()); //not fetched yet for this object
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetSellerId($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('A135KKEKWF1J56', $psp->getSellerId());
        $this->assertEquals('A995KKEKWF1J56', $nsp->getSellerId());
        $this->assertEquals('A123KKEKWF1J56', $pltl->getSellerId());
        $this->assertEquals('A170GGEKWF1J56', $nltl->getSellerId());

        $this->assertFalse($this->object->getSellerId()); //not fetched yet for this object
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetShipmentId($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('FBAQF72K', $psp->getShipmentId());
        $this->assertEquals('FBAQ6QBP', $nsp->getShipmentId());
        $this->assertEquals('FBAQFCQC', $pltl->getShipmentId());
        $this->assertEquals('FBAQFGQZ', $nltl->getShipmentId());

        $this->assertFalse($this->object->getShipmentId()); //not fetched yet for this object
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetIsPartnered($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('true', $psp->getIsPartnered());
        $this->assertEquals('false', $nsp->getIsPartnered());
        $this->assertEquals('true', $pltl->getIsPartnered());
        $this->assertEquals('false', $nltl->getIsPartnered());

        $this->assertFalse($this->object->getIsPartnered()); //not fetched yet for this object
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetShipmentType($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('SP', $psp->getShipmentType());
        $this->assertEquals('SP', $nsp->getShipmentType());
        $this->assertEquals('LTL', $pltl->getShipmentType());
        $this->assertEquals('LTL', $nltl->getShipmentType());

        $this->assertFalse($this->object->getShipmentType()); //not fetched yet for this object
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetPackageList($psp, $nsp, $pltl, $nltl) {
        $plist = $psp->getPackageList();
        $nlist = $nsp->getPackageList();
        $this->assertInternalType('array', $plist);
        $this->assertInternalType('array', $nlist);
        $this->assertCount(3, $plist);
        $this->assertCount(1, $nlist);

        //replicas
        $x = array();
        $x[0]['Weight']['Value'] = '5.5';
        $x[0]['Weight']['Unit'] = 'pounds';
        $x[0]['TrackingId'] = '1Z8V016A0377769652';
        $x[0]['CarrierName'] = 'UNITED_PARCEL_SERVICE_INC';
        $x[0]['Dimensions']['Height'] = '15';
        $x[0]['Dimensions']['Width'] = '14';
        $x[0]['Dimensions']['Length'] = '13';
        $x[0]['Dimensions']['Unit'] = 'inches';
        $x[0]['PackageStatus'] = 'SHIPPED';
        $x[1]['Weight']['Value'] = '5.6';
        $x[1]['Weight']['Unit'] = 'pounds';
        $x[1]['TrackingId'] = '1Z8V016A0371928464';
        $x[1]['CarrierName'] = 'UNITED_PARCEL_SERVICE_INC';
        $x[1]['PackageStatus'] = 'SHIPPED';
        $x[2]['Weight']['Value'] = '5.7';
        $x[2]['Weight']['Unit'] = 'pounds';
        $x[2]['TrackingId'] = '1Z8V016A0360430477';
        $x[2]['CarrierName'] = 'UNITED_PARCEL_SERVICE_INC';
        $x[2]['PackageStatus'] = 'SHIPPED';
        $z = array();
        $z[0]['TrackingId'] = '1Z6Y68W00342402864';
        $z[0]['CarrierName'] = 'UNITED_PARCEL_SERVICE_INC';
        $z[0]['PackageStatus'] = 'SHIPPED';

        $this->assertEquals($x, $plist);
        $this->assertEquals($z, $nlist);

        //not set for these objects
        $this->assertFalse($pltl->getPackageList());
        $this->assertFalse($nltl->getPackageList());
        $this->assertFalse($this->object->getPackageList());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetPartneredEstimate($psp, $nsp, $pltl, $nltl) {
        $list = $psp->getPartneredEstimate();
        $this->assertInternalType('array', $list);

        //replica
        $x = array();
        $x['Amount']['Value'] = '38.22';
        $x['Amount']['CurrencyCode'] = 'USD';
        $x['ConfirmDeadline'] = '2013-08-09T00:25:05.650Z';
        $x['VoidDeadline'] = '2013-08-10T00:25:05.650Z';

        $this->assertEquals($x, $list);

        //not set for these objects
        $this->assertFalse($nsp->getPartneredEstimate());
        $this->assertFalse($pltl->getPartneredEstimate());
        $this->assertFalse($nltl->getPartneredEstimate());
        $this->assertFalse($this->object->getPartneredEstimate());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetCarrier($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('ABF FREIGHT SYSTEM INC', $pltl->getCarrier());
        $this->assertEquals('ABF FREIGHT SYSTEM INC', $nltl->getCarrier());

        //not set for these objects
        $this->assertFalse($psp->getCarrier());
        $this->assertFalse($nsp->getCarrier());
        $this->assertFalse($this->object->getCarrier());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetProNumber($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('123456', $nltl->getProNumber());

        //not set for these objects
        $this->assertFalse($psp->getProNumber());
        $this->assertFalse($nsp->getProNumber());
        $this->assertFalse($pltl->getProNumber());
        $this->assertFalse($this->object->getProNumber());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetContact($psp, $nsp, $pltl, $nltl) {
        $list = $pltl->getContact();
        $this->assertInternalType('array', $list);

        //replicas
        $x = array();
        $x['Name'] = 'Bob';
        $x['Phone'] = '555-1234';
        $x['Email'] = 'test@email.com';
        $x['Fax'] = '555-6789';

        $this->assertEquals($x, $list);

        //not set for these objects
        $this->assertFalse($psp->getContact());
        $this->assertFalse($nsp->getContact());
        $this->assertFalse($nltl->getContact());
        $this->assertFalse($this->object->getContact());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetBoxCount($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('12', $pltl->getBoxCount());

        //not set for these objects
        $this->assertFalse($psp->getBoxCount());
        $this->assertFalse($nsp->getBoxCount());
        $this->assertFalse($nltl->getBoxCount());
        $this->assertFalse($this->object->getBoxCount());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetFreightClass($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('50', $pltl->getFreightClass());

        //not set for these objects
        $this->assertFalse($psp->getFreightClass());
        $this->assertFalse($nsp->getFreightClass());
        $this->assertFalse($nltl->getFreightClass());
        $this->assertFalse($this->object->getFreightClass());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetReadyDate($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('2012-12-21', $pltl->getReadyDate());

        //not set for these objects
        $this->assertFalse($psp->getReadyDate());
        $this->assertFalse($nsp->getReadyDate());
        $this->assertFalse($nltl->getReadyDate());
        $this->assertFalse($this->object->getReadyDate());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetPalletList($psp, $nsp, $pltl, $nltl) {
        $list = $pltl->getPalletList();
        $this->assertInternalType('array', $list);
        $this->assertCount(2, $list);

        //replica
        $x = array();
        $x[0]['IsStacked'] = 'true';
        $x[0]['Weight']['Value'] = '500';
        $x[0]['Weight']['Unit'] = 'pounds';
        $x[0]['Dimensions']['Length'] = '40';
        $x[0]['Dimensions']['Width'] = '30';
        $x[0]['Dimensions']['Height'] = '25';
        $x[0]['Dimensions']['Unit'] = 'inches';
        $x[1]['IsStacked'] = 'false';
        $x[1]['Dimensions']['Length'] = '15';
        $x[1]['Dimensions']['Width'] = '12';
        $x[1]['Dimensions']['Height'] = '10';
        $x[1]['Dimensions']['Unit'] = 'inches';

        $this->assertEquals($x, $list);

        //not set for these objects
        $this->assertFalse($psp->getPalletList());
        $this->assertFalse($nsp->getPalletList());
        $this->assertFalse($nltl->getPalletList());
        $this->assertFalse($this->object->getPalletList());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetTotalWeight($psp, $nsp, $pltl, $nltl) {
        $weight = $pltl->getTotalWeight();
        $this->assertInternalType('array', $weight);

        //replica
        $x = array();
        $x['Value'] = '2000';
        $x['Unit'] = 'pounds';

        $this->assertEquals($x, $weight);
        $this->assertEquals($x['Value'], $pltl->getTotalWeight(true));

        //not set for these objects
        $this->assertFalse($psp->getTotalWeight());
        $this->assertFalse($nsp->getTotalWeight());
        $this->assertFalse($nltl->getTotalWeight());
        $this->assertFalse($this->object->getTotalWeight());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetDeclaredValue($psp, $nsp, $pltl, $nltl) {
        $amount = $pltl->getDeclaredValue();
        $this->assertInternalType('array', $amount);

        //replica
        $x = array();
        $x['Value'] = '200';
        $x['CurrencyCode'] = 'USD';

        $this->assertEquals($x, $amount);
        $this->assertEquals($x['Value'], $pltl->getDeclaredValue(true));

        //not set for these objects
        $this->assertFalse($psp->getDeclaredValue());
        $this->assertFalse($nsp->getDeclaredValue());
        $this->assertFalse($nltl->getDeclaredValue());
        $this->assertFalse($this->object->getDeclaredValue());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetCalculatedValue($psp, $nsp, $pltl, $nltl) {
        $amount = $pltl->getCalculatedValue();
        $this->assertInternalType('array', $amount);

        //replica
        $x = array();
        $x['Value'] = '40';
        $x['CurrencyCode'] = 'USD';

        $this->assertEquals($x, $amount);
        $this->assertEquals($x['Value'], $pltl->getCalculatedValue(true));

        //not set for these objects
        $this->assertFalse($psp->getCalculatedValue());
        $this->assertFalse($nsp->getCalculatedValue());
        $this->assertFalse($nltl->getCalculatedValue());
        $this->assertFalse($this->object->getCalculatedValue());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetPickupDate($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('2013-08-10T00:25:05.650Z', $pltl->getPickupDate());

        //not set for these objects
        $this->assertFalse($psp->getPickupDate());
        $this->assertFalse($nsp->getPickupDate());
        $this->assertFalse($nltl->getPickupDate());
        $this->assertFalse($this->object->getPickupDate());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetDeliveryDate($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('2013-08-15T00:25:05.650Z', $pltl->getDeliveryDate());

        //not set for these objects
        $this->assertFalse($psp->getDeliveryDate());
        $this->assertFalse($nsp->getDeliveryDate());
        $this->assertFalse($nltl->getDeliveryDate());
        $this->assertFalse($this->object->getDeliveryDate());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetReferenceId($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('123ABC789', $pltl->getReferenceId());

        //not set for these objects
        $this->assertFalse($psp->getReferenceId());
        $this->assertFalse($nsp->getReferenceId());
        $this->assertFalse($nltl->getReferenceId());
        $this->assertFalse($this->object->getReferenceId());
    }

    /**
     * @depends testFetchTransportSpPartnered
     * @depends testFetchTransportSpNonPartnered
     * @depends testFetchTransportLtlPartnered
     * @depends testFetchTransportLtlNonPartnered
     * @param AmazonTransport $psp partnered SP
     * @param AmazonTransport $nsp non-partnered SP
     * @param AmazonTransport $pltl partnered LTL
     * @param AmazonTransport $nltl non-partnered LTL
     */
    public function testGetIsBillOfLadingAvailable($psp, $nsp, $pltl, $nltl) {
        $this->assertEquals('false', $pltl->getIsBillOfLadingAvailable());

        //not set for these objects
        $this->assertFalse($psp->getIsBillOfLadingAvailable());
        $this->assertFalse($nsp->getIsBillOfLadingAvailable());
        $this->assertFalse($nltl->getIsBillOfLadingAvailable());
        $this->assertFalse($this->object->getIsBillOfLadingAvailable());
    }

    /**
     * Simplified copy of method <i>determineDetailOption</i> in class
     * @param string $t <p>shipment type ("SP" or "LTL")</p>
     * @param boolean $p <p>partnered or not</p>
     * @return string|boolean parameter prefix or <b>FALSE</b> if it could not be determined
     */
    private function findOp($t, $p) {
        if (!isset($p) || !isset($t)) {
            return false;
        }
        $op = 'TransportDetails.';
        if ($t == 'SP') {
            if ($p) {
                return $op . 'PartneredSmallParcelData';
            } else {
                return $op . 'NonPartneredSmallParcelData';
            }
        } else if ($t == 'LTL') {
            if ($p) {
                return $op . 'PartneredLtlData';
            } else {
                return $op . 'NonPartneredLtlData';
            }
        }
        return false;
    }

    /**
     * Gives the error message that appears for all parameters that rely on carrier type and partnered.
     * @return string
     */
    private function getOpError() {
        return 'Cannot set transport details without shipment type and partner parameters!';
    }

}

require_once('helperFunctions.php');
