<?php

abstract class AmazonFeedsCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false){
        parent::__construct($s, $mock);
        $this->urlbranch = '';
        $this->options['Version'] = '2009-01-01';
    }
}
?>
