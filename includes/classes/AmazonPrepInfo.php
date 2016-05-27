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
 * Fetches preparation instructions for items.
 *
 * This Amazon Inbound Core object retrieves instructions from Amazon on how
 * to prepare various items for transport in an inbound fulfillment shipment.
 * In order to fetch this information, a list of item identifiers is required.
 */
class AmazonPrepInfo extends AmazonInboundCore implements Iterator {
    protected $prepList;
    protected $invalidList;
    protected $i = 0;

    /**
     * Sets the seller SKU(s). (Required*)
     *
     * This method sets the list of seller SKUs to be sent in the next request.
     * If this parameter is set, ASINs cannot be set.
     * @param array|string $s <p>A list of Seller SKUs, or a single SKU string. (max: 20)</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setSkus($s){
        if (is_string($s)){
            $s = array($s);
        }
        if (is_array($s)){
            $this->resetASINs();
            $this->resetSKUs();
            $i = 1;
            foreach ($s as $x){
                $this->options['SellerSKUList.Id.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Resets the seller SKU options.
     *
     * Since seller SKU is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    private function resetSkus(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#SellerSKUList#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Sets the ASIN(s). (Required*)
     *
     * This method sets the list of ASINs to be sent in the next request.
     * If this parameter is set, Seller SKUs cannot be set.
     * @param array|string $s <p>A list of ASINs, or a single ASIN string. (max: 20)</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setAsins($s){
        if (is_string($s)){
            $s = array($s);
        }
        if (is_array($s)){
            $this->resetSKUs();
            $this->resetASINs();
            $i = 1;
            foreach ($s as $x){
                $this->options['ASINList.Id.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }

    /**
     * Resets the ASIN options.
     *
     * Since ASIN is a required parameter, these options should not be removed
     * without replacing them, so this method is not public.
     */
    private function resetAsins(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#ASINList#",$op)){
                unset($this->options[$op]);
            }
        }
    }

    /**
     * Fetches item preperation instructions from Amazon.
     *
     * Submits a <i>GetPrepInstructionsForASIN</i> or
     * <i>GetPrepInstructionsForSKU</i> request to Amazon. In order to do this,
     * a list of SKUs or ASINs is required. Amazon will send the data back as a
     * response, which can be retrieved using <i>getPrepList</i>.
     * Other methods are available for fetching specific values from the list.
     * A list of items that were deemed invalid can also be retrieved using
     * <i>getInvalidItemList</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchPrepInstructions(){
        if (!array_key_exists('SellerSKUList.Id.1',$this->options) &&
                !array_key_exists('ASINList.Id.1',$this->options)){
            $this->log("Product IDs must be set in order to get prep instructions!",'Warning');
            return false;
        }

        $this->preparePrep();

        $url = $this->urlbase.$this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
            $xml = $this->fetchMockFile();
        } else {
            $response = $this->sendRequest($url, array('Post'=>$query));

            if (!$this->checkResponse($response)){
                return false;
            }

            $xml = simplexml_load_string($response['body']);
        }

        $this->parseXml($xml->$path);
    }

    /**
     * Sets up options for using <i>fetchPrepInstructions</i>.
     *
     * This changes key options for using <i>fetchPrepInstructions</i>.
     */
    protected function preparePrep(){
        $this->prepList = array();
        $this->invalidList = array();
        $this->rewind();
        if (array_key_exists('SellerSKUList.Id.1',$this->options)){
            $this->options['Action'] = 'GetPrepInstructionsForSKU';
            $this->resetASINs();
        } else if (array_key_exists('ASINList.Id.1',$this->options)){
            $this->options['Action'] = 'GetPrepInstructionsForASIN';
            $this->resetSKUs();
        }
    }

    /**
     * Parses XML response into array.
     *
     * This is what reads the response XML and converts it into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return boolean <b>FALSE</b> if no XML data is found
     */
    protected function parseXml($xml) {
        if (!$xml){
            return false;
        }

        //SKU or ASIN list
        $list = false;
        if (isset($xml->SKUPrepInstructionsList)) {
            $list = $xml->SKUPrepInstructionsList;
        }
        if (isset($xml->ASINPrepInstructionsList)) {
            $list = $xml->ASINPrepInstructionsList;
        }
        if ($list) {
            foreach ($list->children() as $x) {
                $temp = array();
                if (isset($x->SellerSKU)) {
                    $temp['SellerSKU'] = (string)$x->SellerSKU;
                }
                $temp['ASIN'] = (string)$x->ASIN;
                $temp['BarcodeInstruction'] = (string)$x->BarcodeInstruction;
                $temp['PrepGuidance'] = (string)$x->PrepGuidance;
                foreach ($x->PrepInstructionList->children() as $z) {
                    $temp['PrepInstructionList'][] = (string)$z;
                }
                if (isset($x->AmazonPrepFeesDetailsList)) {
                    foreach ($x->AmazonPrepFeesDetailsList->children() as $z) {
                        $fee = array();
                        $fee['PrepInstruction'] = (string)$z->PrepInstruction;
                        $fee['Amount']['Value'] = (string)$z->Amount->Value;
                        $fee['Amount']['CurrencyCode'] = (string)$z->Amount->CurrencyCode;
                        $temp['AmazonPrepFees'][] = $fee;
                    }
                }
                $this->prepList[] = $temp;
            }
        }

        //invalid item list
        $invList = false;
        if (isset($xml->InvalidSKUList)) {
            $invList = $xml->InvalidSKUList;
        }
        if (isset($xml->InvalidASINList)) {
            $invList = $xml->InvalidASINList;
        }
        if ($invList) {
            foreach ($invList->children() as $x) {
                $temp = array();
                $temp['ErrorReason'] = (string)$x->ErrorReason;
                if (isset($x->SellerSKU)) {
                    $temp['SellerSKU'] = (string)$x->SellerSKU;
                }
                if (isset($x->ASIN)) {
                    $temp['ASIN'] = (string)$x->ASIN;
                }
                $this->invalidList[] = $temp;
            }
        }
    }

    /**
     * Returns the Seller SKU for the specified item preperation instruction.
     *
     * Prep instructions will only include this data if SKUs were sent when
     * retrieving the list of prep instructions.
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getSku($i = 0){
        if (isset($this->prepList[$i]['SellerSKU'])){
            return $this->prepList[$i]['SellerSKU'];
        } else {
            return false;
        }
    }

    /**
     * Returns the ASIN for the specified item preperation instruction.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getAsin($i = 0){
        if (isset($this->prepList[$i]['ASIN'])){
            return $this->prepList[$i]['ASIN'];
        } else {
            return false;
        }
    }

    /**
     * Returns the barcode instruction for the specified item preperation instruction.
     *
     * Possible values are "RequiresFNSKULabel" and "CanUseOriginalBarcode".
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getBarcodeInstruction($i = 0){
        if (isset($this->prepList[$i]['BarcodeInstruction'])){
            return $this->prepList[$i]['BarcodeInstruction'];
        } else {
            return false;
        }
    }

    /**
     * Returns the prep guidance message for the specified item preperation instruction.
     *
     * Possible values are "ConsultHelpDocuments", "NoAdditionalPrepRequired",
     * and "SeePrepInstructionsList".
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getPrepGuidance($i = 0){
        if (isset($this->prepList[$i]['PrepGuidance'])){
            return $this->prepList[$i]['PrepGuidance'];
        } else {
            return false;
        }
    }

    /**
     * Returns the list of instructions for the specified item preperation instruction.
     *
     * Possible values are "Polybagging", "BubbleWrapping", "Taping", "BlackShrinkWrapping",
     * "Labeling", and "HangGarment".
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return array|boolean simple array, or <b>FALSE</b> if Non-numeric index
     */
    public function getPrepInstructions($i = 0){
        if (isset($this->prepList[$i]['PrepInstructionList'])){
            return $this->prepList[$i]['PrepInstructionList'];
        } else {
            return false;
        }
    }

    /**
     * Returns the list of instructions for the specified item preperation instruction.
     *
     * The array for a single fee will have the following fields:
     * <ul>
     * <li><b>PrepInstruction</b> - see getPrepInstructions for possible values</li>
     * <li><b>FeePerUnit</b> - array</li>
     * <ul>
     * <li><b>Value</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * </ul>
     * Prep instructions will only include this data if SKUs were sent when
     * retrieving the list of prep instructions.
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if Non-numeric index
     * @see getPrepInstructions
     */
    public function getAmazonPrepFees($i = 0){
        if (isset($this->prepList[$i]['AmazonPrepFees'])){
            return $this->prepList[$i]['AmazonPrepFees'];
        } else {
            return false;
        }
    }

    /**
     * Returns the full list of preperation instructions.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single instruction will have the following fields:
     * <ul>
     * <li><b>SellerSKU</b></li>
     * <li><b>ASIN</b></li>
     * <li><b>BarcodeInstruction</b></li>
     * <li><b>PrepGuidance</b></li>
     * <li><b>PrepInstructionList</b> (see <i>getPrepInstructions</i> for details)</li>
     * <li><b>AmazonPrepFees</b> (see <i>getAmazonPrepFees</i> for details)</li>
     * </ul>
     * @param int $i [optional] <p>List index of the instruction to return. Defaults to NULL.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     * @see getPrepInstructionList
     * @see getAmazonPrepFees
     */
    public function getPrepList($i = null){
        if (!isset($this->prepList)){
            return false;
        }
        if (is_int($i)){
            return $this->prepList[$i];
        } else {
            return $this->prepList;
        }
    }

    /**
     * Returns the full list of invalid items.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array for a single item will have the following fields:
     * <ul>
     * <li><b>SellerSKU</b> or <b>ASIN</b></li>
     * <li><b>ErrorReason</b></li>
     * </ul>
     * @param int $i [optional] <p>List index of the item to return. Defaults to NULL.</p>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getInvalidItemList($i = null){
        if (!isset($this->invalidList)){
            return false;
        }
        if (is_int($i)){
            return $this->invalidList[$i];
        } else {
            return $this->invalidList;
        }
    }

    /**
     * Iterator function
     * @return array
     */
    public function current(){
       return $this->prepList[$this->i];
    }

    /**
     * Iterator function
     */
    public function rewind(){
        $this->i = 0;
    }

    /**
     * Iterator function
     * @return int
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
     * @return boolean
     */
    public function valid() {
        return isset($this->prepList[$this->i]);
    }
}

