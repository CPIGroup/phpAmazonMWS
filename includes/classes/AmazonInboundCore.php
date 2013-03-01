<?php
/**
 * Core class for Amazon Inbound Shipment API.
 * 
 * This is the core class for all objects in the Amazon Inbound section.
 * It contains no methods in itself other than the constructor.
 */
abstract class AmazonInboundCore extends AmazonCore{
    /**
     * AmazonInboundCore constructor sets up key information used in all Amazon Inbound Core requests
     * 
     * This constructor is called when initializing all objects in the Amazon Inbound Core.
     * The parameters are passed by the child objects' constructors, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s <p>Name for the store you want to use.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        if(isset($AMAZON_VERSION_INBOUND)){
            $this->urlbranch = 'FulfillmentInboundShipment/'.$AMAZON_VERSION_INBOUND;
            $this->options['Version'] = $AMAZON_VERSION_INBOUND;
        }
        
        
        if(isset($THROTTLE_LIMIT_INVENTORY))
        $this->throttleLimit = $THROTTLE_LIMIT_INVENTORY;
        if(isset($THROTTLE_TIME_INVENTORY))
        $this->throttleTime = $THROTTLE_TIME_INVENTORY;
        $this->throttleGroup = 'Inventory';
    }
}
?>
