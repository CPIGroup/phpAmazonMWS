<?php

abstract class AmazonInventoryCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false){
        parent::__construct($s, $mock);
        $this->urlbranch = 'FulfillmentInventory/2010-10-01';
    }
}
?>
