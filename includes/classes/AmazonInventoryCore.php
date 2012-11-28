<?php

abstract class AmazonInventoryCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        $this->urlbranch = 'FulfillmentInventory/2010-10-01';
        $this->options['Version'] = '2010-10-01';
        $this->throttleGroup = 'Inventory';
    }
}
?>
