<?php

abstract class AmazonOrderCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false){
        parent::__construct($s, $mock);
        $this->urlbranch = 'Orders/2011-01-01';
        $this->options['Version'] = '2011-01-01';
    }
}
?>
