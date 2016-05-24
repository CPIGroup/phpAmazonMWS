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
