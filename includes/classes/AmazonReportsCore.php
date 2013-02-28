<?php
/**
 * Core class for Amazon Reports API.
 * 
 * This is the core class for all objects in the Amazon Reports section.
 * It contains no methods in itself other than the constructor.
 */
abstract class AmazonReportsCore extends AmazonCore{
    /**
     * AmazonReportsCore constructor sets up key information used in all Amazon Reports Core requests
     * 
     * This constructor is called when initializing all objects in the Amazon Reports Core.
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
        
        $this->urlbranch = '';
        $this->options['Version'] = $AMAZON_VERSION_REPORTS;
    }
    
    /**
     * Checks for a token and changes the proper options
     * @param SimpleXMLObject $xml <p>response data</p>
     * @return boolean <p><b>FALSE</b> if no XML data</p>
     */
    protected function checkToken($xml){
        if (!$xml){
            return false;
        }
        if ((string)$xml->HasNext == 'true'){
            $this->tokenFlag = true;
            $this->options['NextToken'] = (string)$xml->NextToken;
        } else {
            unset($this->options['NextToken']);
            $this->tokenFlag = false;
        }
    }
}
?>
