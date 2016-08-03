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
 * Fetches list of products from Amazon
 * 
 * This Amazon Products Core object retrieves a list of products from Amazon
 * that match the given product IDs. In order to do this, both the ID type
 * and product ID(s) must be given.
 */
class AmazonProductList extends AmazonProductsCore implements Iterator{
    protected $i = 0;
    
    /**
     * AmazonProductList fetches a list of products from Amazon.
     * 
     * The parameters are passed to the parent constructor, which are
     * in turn passed to the AmazonCore constructor. See it for more information
     * on these parameters and common methods.
     * @param string $s [optional] <p>Name for the store you want to use.
     * This parameter is optional if only one store is defined in the config file.</p>
     * @param boolean $mock [optional] <p>This is a flag for enabling Mock Mode.
     * This defaults to <b>FALSE</b>.</p>
     * @param array|string $m [optional] <p>The files (or file) to use in Mock Mode.</p>
     * @param string $config [optional] <p>An alternate config file to set. Used for testing.</p>
     */
    public function __construct($s = null, $mock = false, $m = null, $config = null){
        parent::__construct($s, $mock, $m, $config);
        include($this->env);
        
        $this->options['Action'] = 'GetMatchingProductForId';
        
        if(isset($THROTTLE_TIME_PRODUCTLIST)) {
            $this->throttleTime = $THROTTLE_TIME_PRODUCTLIST;
        }
        $this->throttleGroup = 'GetMatchingProductForId';
    }
    
    /**
     * Sets the ID type. (Required)
     * 
     * @param string $s <p>"ASIN", "GCID", "SellerSKU", "UPC", "EAN", "ISBN", or "JAN"</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setIdType($s){
        if (is_string($s)){
            $this->options['IdType'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the request ID(s). (Required)
     * 
     * This method sets the list of product IDs to be sent in the next request.
     * @param array|string $s <p>A list of product IDs, or a single type string. (max: 5)</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setProductIds($s){
        if (is_string($s)){
            $this->resetProductIds();
            $this->options['IdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetProductIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['IdList.Id.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Resets the product ID options.
     * 
     * Since product ID is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    protected function resetProductIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#IdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Fetches a list of products from Amazon.
     * 
     * Submits a <i>GetMatchingProductForId</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getProduct</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchProductList(){
        if (!array_key_exists('IdList.Id.1',$this->options)){
            $this->log("Product IDs must be set in order to fetch them!",'Warning');
            return false;
        }
        if (!array_key_exists('IdType',$this->options)){
            $this->log("ID Type must be set in order to use the given IDs!",'Warning');
            return false;
        }
        
        $url = $this->urlbase.$this->urlbranch;
        
        $query = $this->genQuery();
        
        if ($this->mockMode){
           $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body']);
        }
        
        $this->parseXML($xml);
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->productList[$this->i]; 
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
        return isset($this->productList[$this->i]);
    }
    
}
?>