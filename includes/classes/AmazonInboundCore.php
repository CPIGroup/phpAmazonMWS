<?php

abstract class AmazonInboundCore extends AmazonCore{
    /**
     * For organization's sake @todo Mock Mode stuck on
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false, $m = null){
        $mock = true; //Mock Mode is stuck on while developing this core
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->urlbranch = 'FulfillmentInboundShipment/'.$versionInbound;
        $this->options['Version'] = $versionInbound;
        $this->throttleGroup = 'Inventory';
    }
}
?>
