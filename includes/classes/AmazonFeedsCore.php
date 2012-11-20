<?php

abstract class AmazonFeedsCore extends AmazonCore{
    /**
     * For organization's sake
     * @param type $s
     * @param type $mock
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        $this->urlbranch = '';
        $this->options['Version'] = '2009-01-01';
    }
}
?>
