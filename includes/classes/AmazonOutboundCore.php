<?php

abstract class AmazonOutboundCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        $this->urlbranch = 'FulfillmentOutboundShipment/'.$versionOutbound;
        $this->options['Version'] = $versionOutbound;
        $this->throttleGroup = 'Inventory';
    }
}
?>
