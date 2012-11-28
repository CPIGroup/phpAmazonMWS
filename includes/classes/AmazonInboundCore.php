<?php

abstract class AmazonInboundCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false, $m = null){
        $mock = true; //Mock Mode is stuck on while developing this core
        parent::__construct($s, $mock, $m);
        $this->urlbranch = 'FulfillmentInboundShipment/2010-10-01';
        $this->options['Version'] = '2010-10-01';
        $this->throttleGroup = 'Inventory';
    }
}
?>
