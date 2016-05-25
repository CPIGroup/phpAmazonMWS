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
 * Gets the details for a set of orders from Amazon.
 * 
 * This Amazon Order Core object retrieves the data from a set of orders on Amazon.
 * In order to fetch this data, a list of Amazon Order IDs is required. If you
 * wish to retrieve information for only one order, please use the <i>AmazonOrder</i>
 * class instead.
 */
class AmazonOrderSet extends AmazonOrderCore implements Iterator{
    protected $i = 0;
    protected $index = 0;
    protected $orderList;
    
    /**
     * AmazonOrderSet is a variation of <i>AmazonOrder</i> that pulls multiple specified orders.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * Please note that an extra parameter comes before the usual Mock Mode parameters,
     * so be careful when setting up the object.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param string $o [optional] <p>The Order IDs to set for the object.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $o = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        $this->i = 0;
        include($this->env);
        
        if($o){
            $this->setOrderIds($o);
        }
        
        $this->options['Action'] = 'GetOrder';
        if(isset($THROTTLE_LIMIT_ORDER)) {
            $this->throttleLimit = $THROTTLE_LIMIT_ORDER;
        }
        if(isset($THROTTLE_TIME_ORDER)) {
            $this->throttleTime = $THROTTLE_TIME_ORDER;
        }
        $this->throttleGroup = 'GetOrder';
    }
    
    /**
     * Sets the order ID(s). (Optional)
     * 
     * This method sets the list of Order IDs to be sent in the next request.
     * If you wish to retrieve information for only one order, please use the 
     * <i>AmazonOrder</i> class instead.
     * @param array|string $o <p>A list of Amazon Order IDs, or a single ID string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setOrderIds($o){
        if($o){
            $this->resetOrderIds();
            if(is_string($o)){
                $this->options['AmazonOrderId.Id.1'] = $o;
            } else if(is_array($o)){
                $k = 1;
                foreach ($o as $id){
                    $this->options['AmazonOrderId.Id.'.$k] = $id;
                    $k++;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Resets the order ID options.
     * 
     * Since order ID is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetOrderIds(){
        foreach($this->options as $op=>$junk){
                if(preg_match("#AmazonOrderId.Id.#",$op)){
                    unset($this->options[$op]);
                }
            }
    }
    
    /**
     * Fetches the specified order from Amazon.
     * 
     * Submits a <i>GetOrder</i> request to Amazon. In order to do this,
     * a list of Amazon order IDs is required. Amazon will send
     * the data back as a response, which can be retrieved using <i>getOrders</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchOrders(){
        if (!array_key_exists('AmazonOrderId.Id.1',$this->options)){
            $this->log("Order IDs must be set in order to fetch them!",'Warning');
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
        foreach($xml->Orders->children() as $key => $order){
            if ($key != 'Order'){
                break;
            }
            $this->orderList[$this->index] = new AmazonOrder($this->storeName,null,$order,$this->mockMode,$this->mockFiles,$this->config);
            $this->orderList[$this->index]->mockIndex = $this->mockIndex;
            $this->index++;
        }
    }
    
    /**
     * Returns array of item lists or a single item list.
     * 
     * If <i>$i</i> is not specified, the method will fetch the items for every
     * order in the list. Please note that for lists with a high number of orders,
     * this operation could take a while due to throttling. (Two seconds per order when throttled.)
     * @param boolean $token [optional] <p>whether or not to automatically use tokens when fetching items.</p>
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to null.</p>
     * @return array|AmazonOrderItemList <i>AmazonOrderItemList</i> object or array of objects, or <b>FALSE</b> if non-numeric index
     */
    public function fetchItems($token = false, $i = null){
        if (!isset($this->orderList)){
            return false;
        }
        if (!is_bool($token)){
            $token = false;
        }
         if (is_int($i)) {
            return $this->orderList[$i]->fetchItems($token);
        } else {
            $a = array();
            foreach($this->orderList as $x){
                $a[] = $x->fetchItems($token);
            }
            return $a;
        }
    }
    /**
     * Returns the list of orders.
     * @return array|boolean array of <i>AmazonOrder</i> objects, or <b>FALSE</b> if list not filled yet
     */
    public function getOrders(){
        if (isset($this->orderList) && $this->orderList){
            return $this->orderList;
        } else {
            return false;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->orderList[$this->i]; 
    }

    /**
     * Iterator function
     */
    public function rewind(){
        $this->i = 0;
    }

    /**
     * Iterator function
     * @return type
     */
    public function key() {
        return $this->i;
    }

    /**
     * Iterator function
     */
    public function next() {
        $this->i++;
    }

    /**
     * Iterator function
     * @return type
     */
    public function valid() {
        return isset($this->orderList[$this->i]);
    }
    
}

?>
