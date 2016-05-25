<?php
/**
 * Copyright 2013 CPI Group, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Fetches the status of the a specific service from Amazon.
 * 
 * This Amazon Core object retrieves the status of a selected Amazon service.
 * Please note that it has a 5 minute throttle time.
 */
class AmazonServiceStatus extends AmazonCore{
    protected $lastTimestamp;
    protected $status;
    protected $messageId;
    protected $messageList;
    protected $ready = false;
    
    /**
     * AmazonServiceStatus is a simple object that fetches the status of given Amazon service.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param string $service [optional] <p>The service to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $service = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        
        if ($service){
            $this->setService($service);
        }
        
        $this->options['Action'] = 'GetServiceStatus';
        
        if(isset($THROTTLE_LIMIT_STATUS)) {
            $this->throttleLimit = $THROTTLE_LIMIT_STATUS;
        }
        if(isset($THROTTLE_TIME_STATUS)) {
            $this->throttleTime = $THROTTLE_TIME_STATUS;
        }
        $this->throttleGroup = 'GetServiceStatus';
    }
    
    /**
     * Set the service to fetch the status of. (Required)
     * 
     * This method sets the service for the object to check in the next request.
     * This parameter is required for fetching the service status from Amazon.
     * The list of valid services to check is as follows:
     * <ul>
     * <li>Inbound</li>
     * <li>Inventory</li>
     * <li>Orders</li>
     * <li>Outbound</li>
     * <li>Products</li>
     * <li>Sellers</li>
     * </ul>
     * @param string $s <p>See list.</p>
     * @return boolean <b>TRUE</b> if valid input, <b>FALSE</b> if improper input
     */
    public function setService($s){
        if (file_exists($this->env)){
            include($this->env);
        } else {
            return false;
        }
        
        if (is_null($s)){
            $this->log("Service cannot be null",'Warning');
            return false;
        }
        
        if (is_bool($s)){
            $this->log("A boolean is not a service",'Warning');
            return false;
        }
        
        switch($s){
            case 'Inbound':
                if(isset($AMAZON_VERSION_INBOUND)){
                    $this->urlbranch = 'FulfillmentInboundShipment/'.$AMAZON_VERSION_INBOUND;
                    $this->options['Version'] = $AMAZON_VERSION_INBOUND;
                    $this->ready = true;
                }
                return true;
            case 'Inventory':
                if(isset($AMAZON_VERSION_INVENTORY)){
                    $this->urlbranch = 'FulfillmentInventory/'.$AMAZON_VERSION_INVENTORY;
                    $this->options['Version'] = $AMAZON_VERSION_INVENTORY;
                    $this->ready = true;
                }
                return true;
            case 'Orders':
                if(isset($AMAZON_VERSION_ORDERS)){
                    $this->urlbranch = 'Orders/'.$AMAZON_VERSION_ORDERS;
                    $this->options['Version'] = $AMAZON_VERSION_ORDERS;
                    $this->ready = true;
                }
                return true;
            case 'Outbound':
                if(isset($AMAZON_VERSION_OUTBOUND)){
                    $this->urlbranch = 'FulfillmentOutboundShipment/'.$AMAZON_VERSION_OUTBOUND;
                    $this->options['Version'] = $AMAZON_VERSION_OUTBOUND;
                    $this->ready = true;
                }
                return true;
            case 'Products':
                if(isset($AMAZON_VERSION_PRODUCTS)){
                    $this->urlbranch = 'Products/'.$AMAZON_VERSION_PRODUCTS;
                    $this->options['Version'] = $AMAZON_VERSION_PRODUCTS;
                    $this->ready = true;
                }
                return true;
            case 'Sellers':
                if(isset($AMAZON_VERSION_SELLERS)){
                    $this->urlbranch = 'Sellers/'.$AMAZON_VERSION_SELLERS;
                    $this->options['Version'] = $AMAZON_VERSION_SELLERS;
                    $this->ready = true;
                }
                return true;
            default:
                $this->log("$s is not a valid service",'Warning');
                return false;
        }
        
    }
    
    /**
     * Fetches the status of the service from Amazon.
     * 
     * Submits a <i>GetServiceStatus</i> request to Amazon. In order to do this,
     * an service is required. Use <i>isReady</i> to see if you are ready to
     * retrieve the service status. Amazon will send data back as a response,
     * which can be retrieved using various methods.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchServiceStatus(){
        if (!$this->ready){
            $this->log("Service must be set in order to retrieve status",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
            $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $this->parseXML($xml);
    }
    
    /**
     * Parses XML response into array.
     * 
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        $this->lastTimestamp = (string)$xml->Timestamp;
        $this->status = (string)$xml->Status;
        
        if ($this->status == 'GREEN_I'){
            $this->messageId = (string)$xml->MessageId;
            $i = 0;
            foreach ($xml->Messages->children() as $x){
                $this->messageList[$i] = (string)$x->Text;
                $i++;
            }
        }
    }
    
    /**
     * Returns whether or not the object is ready to retrieve the status.
     * @return boolean
     */
    public function isReady(){
        return $this->ready;
    }
    
    /**
     * Returns the service status.
     * 
     * This method will return <b>FALSE</b> if the service status has not been checked yet.
     * @return string|boolean single value, or <b>FALSE</b> if status not checked yet
     */
    public function getStatus(){
        if (isset($this->status)){
            return $this->status;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the timestamp of the last response.
     * 
     * This method will return <b>FALSE</b> if the service status has not been checked yet.
     * @return string|boolean single value, or <b>FALSE</b> if status not checked yet
     */
    public function getTimestamp(){
        if (isset($this->lastTimestamp)){
            return $this->lastTimestamp;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the info message ID, if it exists.
     * 
     * This method will return <b>FALSE</b> if the service status has not been checked yet.
     * @return string|boolean single value, or <b>FALSE</b> if status not checked yet
     */
    public function getMessageId(){
        if (isset($this->messageId)){
            return $this->messageId;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the list of info messages.
     * 
     * This method will return <b>FALSE</b> if the service status has not been checked yet.
     * @return array|boolean single value, or <b>FALSE</b> if status not checked yet
     */
    public function getMessageList(){
        if (isset($this->messageList)){
            return $this->messageList;
        } else {
            return false;
        }
    }
    
}

?>
