<?php
/**
 * Core class for Amazon Sellers API.
 * 
 * This is the core class for the only object in the Amazon Sellers section.
 * It contains no methods in itself other than the constructor.
 */
abstract class AmazonSellersCore extends AmazonCore{
    /**
     * AmazonSellersCore constructor sets up key information used in all Amazon Sellers Core requests
     * 
     * This constructor is called when initializing all objects in the Amazon Sellers Core.
     * The parameters are passed by the child objects' constructors, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     */
    public function __construct($s, $mock = false, $m = null){
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->urlbranch = 'Sellers/'.AMAZON_VERSION_SELLERS;
        $this->options['Version'] = AMAZON_VERSION_SELLERS;
    }
}
?>
