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
 * Pulls a list of financial event groups from Amazon.
 *
 * This Amazon Finance Core object retrieves a list of financial event groups
 * from Amazon. In order to do this, a start date is required. This
 * object can use tokens when retrieving the list.
 */
class AmazonFinancialGroupList extends AmazonFinanceCore implements Iterator {
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $list;
    protected $index = 0;
    protected $i = 0;

    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken() {
        return $this->tokenFlag;
    }

    /**
     * Sets whether or not the object should automatically use tokens if it receives one.
     *
     * If this option is set to <b>TRUE</b>, the object will automatically perform
     * the necessary operations to retrieve the rest of the list using tokens. If
     * this option is off, the object will only ever retrieve the first section of
     * the list.
     * @param boolean $b [optional] <p>Defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setUseToken($b = true) {
        if (is_bool($b)) {
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }

    /**
     * Sets the maximum number of responses per page. (Optional)
     *
     * This method sets the maximum number of Financial Event Groups for Amazon to return per page.
     * If this parameter is not set, Amazon will send 100 at a time.
     * @param int $num <p>Positive integer from 1 to 100.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setMaxResultsPerPage($num){
        if (is_numeric($num) && $num <= 100 && $num >= 1){
            $this->options['MaxResultsPerPage'] = $num;
        } else {
            return false;
        }
    }

    /**
     * Sets the time frame options. (Required*)
     *
     * This method sets the start and end times for the next request. If this
     * parameter is set, Amazon will only return Financial Event Groups that occurred
     * between the two times given. Only the starting time is required to fetch financial event groups.
     * The parameters are passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * @param string $s <p>A time string for the earliest time.</p>
     * @param string $e [optional] <p>A time string for the latest time.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setTimeLimits($s, $e = null) {
        if (empty($s)) {
            return FALSE;
        }
        $times = $this->genTime($s);
        $this->options['FinancialEventGroupStartedAfter'] = $times;
        if (!empty($e)) {
            $timee = $this->genTime($e);
            $this->options['FinancialEventGroupStartedBefore'] = $timee;
        } else {
            unset($this->options['FinancialEventGroupStartedBefore']);
        }
    }

    /**
     * Fetches a list of financial event groups from Amazon.
     *
     * Submits a <i>ListFinancialEventGroups</i> request to Amazon. In order to do this,
     * a start date must be set. Amazon will send the list back as a response,
     * which can be retrieved using <i>getGroups</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchGroupList($r = true) {
        if (!array_key_exists('FinancialEventGroupStartedAfter', $this->options)) {
            $this->log("Start date must be set in order to fetch financial event groups", 'Warning');
            return false;
        }

        $this->prepareToken();

        $url = $this->urlbase.$this->urlbranch;

        $query = $this->genQuery();

        $path = $this->options['Action'].'Result';

        if ($this->mockMode) {
            $xml = $this->fetchMockFile()->$path;
        } else {
            $response = $this->sendRequest($url, array('Post' => $query));

            if (!$this->checkResponse($response)) {
                return false;
            }

            $xml = simplexml_load_string($response['body'])->$path;
        }

        $this->parseXml($xml);

        $this->checkToken($xml);

        if ($this->tokenFlag && $this->tokenUseFlag && $r === true) {
            while ($this->tokenFlag) {
                $this->log("Recursively fetching more Financial Event Groups");
                $this->fetchGroupList(false);
            }
        }
    }

    /**
     * Sets up options for using tokens.
     *
     * This changes key options for switching between simply fetching a list and
     * fetching the rest of a list using a token. Please note: because the
     * operation for using tokens does not use any other parameters, all other
     * parameters will be removed.
     */
    protected function prepareToken() {
        if ($this->tokenFlag && $this->tokenUseFlag) {
            $this->options['Action'] = 'ListFinancialEventGroupsByNextToken';
            unset($this->options['MaxResultsPerPage']);
            unset($this->options['FinancialEventGroupStartedAfter']);
            unset($this->options['FinancialEventGroupStartedBefore']);
        } else {
            $this->options['Action'] = 'ListFinancialEventGroups';
            unset($this->options['NextToken']);
            $this->index = 0;
            $this->list = array();
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
        if (!$xml || !$xml->FinancialEventGroupList) {
            return false;
        }
        foreach($xml->FinancialEventGroupList->children() as $x) {
            $temp = array();
            $temp['FinancialEventGroupId'] = (string)$x->FinancialEventGroupId;
            $temp['ProcessingStatus'] = (string)$x->ProcessingStatus;
            $temp['FundTransferStatus'] = (string)$x->FundTransferStatus;
            $temp['OriginalTotal']['Amount'] = (string)$x->OriginalTotal->CurrencyAmount;
            $temp['OriginalTotal']['CurrencyCode'] = (string)$x->OriginalTotal->CurrencyCode;
            $temp['ConvertedTotal']['Amount'] = (string)$x->ConvertedTotal->CurrencyAmount;
            $temp['ConvertedTotal']['CurrencyCode'] = (string)$x->ConvertedTotal->CurrencyCode;
            if (isset($x->FundTransferDate)) {
                $temp['FundTransferDate'] = (string)$x->FundTransferDate;
            }
            $temp['TraceId'] = (string)$x->TraceId;
            $temp['AccountTail'] = (string)$x->AccountTail;
            $temp['BeginningBalance']['Amount'] = (string)$x->BeginningBalance->CurrencyAmount;
            $temp['BeginningBalance']['CurrencyCode'] = (string)$x->BeginningBalance->CurrencyCode;
            $temp['FinancialEventGroupStart'] = (string)$x->FinancialEventGroupStart;
            $temp['FinancialEventGroupEnd'] = (string)$x->FinancialEventGroupEnd;
            $this->list[$this->index] = $temp;
            $this->index++;
        }
    }

    /**
     * Returns all financial event groups.
     *
     * Each financial event group array will have the following keys:
     * <ul>
     * <li><b>FinancialEventGroupId</b></li>
     * <li><b>ProcessingStatus</b> - "Open" or "Closed"</li>
     * <li><b>FundTransferStatus</b></li>
     * <li><b>OriginalTotal</b> - array</li>
     * <ul>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>ConvertedTotal</b> - array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>FundTransferDate</b> - ISO 8601 date format</li>
     * <li><b>TraceId</b></li>
     * <li><b>AccountTail</b></li>
     * <li><b>BeginningBalance</b> - array with the fields <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>FinancialEventGroupStart</b> - ISO 8601 date format</li>
     * <li><b>FinancialEventGroupEnd</b> - ISO 8601 date format</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getGroups(){
        if (isset($this->list)){
            return $this->list;
        } else {
            return false;
        }
    }

    /**
     * Iterator function
     * @return type
     */
    public function current() {
       return $this->list[$this->i];
    }

    /**
     * Iterator function
     */
    public function rewind() {
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
        return isset($this->list[$this->i]);
    }

}
