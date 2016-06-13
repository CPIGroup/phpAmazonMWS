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
 * Pulls a list of financial events from Amazon.
 *
 * This Amazon Finance Core object retrieves a list of financial events
 * from Amazon. Because the object has separate lists for each event type,
 * the object cannot be iterated over.
 */
class AmazonFinancialEventList extends AmazonFinanceCore {
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $list;

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
     * This method sets the maximum number of Financial Events for Amazon to return per page.
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
     * Sets the order ID filter. (Required*)
     *
     * If this parameter is set, Amazon will only return Financial Events that
     * relate to the given order. This parameter is required if none of the
     * other filter options are set.
     * If this parameter is set, the group ID and time range options will be removed.
     * @param string $s <p>Amazon Order ID in 3-7-7 format</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setOrderFilter($s){
        if ($s && is_string($s)) {
            $this->resetFilters();
            $this->options['AmazonOrderId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the financial event group ID filter. (Required*)
     *
     * If this parameter is set, Amazon will only return Financial Events that
     * belong to the given financial event group. This parameter is required if
     * none of the other filter options are set.
     * If this parameter is set, the order ID and time range options will be removed.
     * @param string $s <p>Financial Event Group ID</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setGroupFilter($s){
        if ($s && is_string($s)) {
            $this->resetFilters();
            $this->options['FinancialEventGroupId'] = $s;
        } else {
            return false;
        }
    }

    /**
     * Sets the time frame options. (Required*)
     *
     * This method sets the start and end times for the next request. If this
     * parameter is set, Amazon will only return Financial Events posted
     * between the two times given. This parameter is required if none of the
     * other filter options are set.
     * The parameters are passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * If this parameter is set, the order ID and group ID options will be removed.
     * @param string $s <p>A time string for the earliest time.</p>
     * @param string $e [optional] <p>A time string for the latest time.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setTimeLimits($s, $e = null) {
        if (empty($s)) {
            return FALSE;
        }
        $this->resetFilters();

        $times = $this->genTime($s);
        $this->options['PostedAfter'] = $times;
        if (!empty($e)) {
            $timee = $this->genTime($e);
            $this->options['PostedBefore'] = $timee;
        }
    }

    /**
     * Removes time limit options.
     *
     * Use this in case you change your mind and want to remove the time limit
     * parameters you previously set.
     */
    public function resetTimeLimits(){
        unset($this->options['PostedAfter']);
        unset($this->options['PostedBefore']);
    }

    /**
     * Removes all filter options.
     *
     * Use this in case you change your mind and want to remove all filter
     * parameters you previously set.
     */
    public function resetFilters(){
        unset($this->options['AmazonOrderId']);
        unset($this->options['FinancialEventGroupId']);
        $this->resetTimeLimits();
    }

    /**
     * Fetches the inventory supply list from Amazon.
     *
     * Submits a <i>ListFinancialEvents</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getEvents</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchEventList($r = true) {
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

        $this->parseXml($xml->FinancialEvents);

        $this->checkToken($xml);

        if ($this->tokenFlag && $this->tokenUseFlag && $r === true) {
            while ($this->tokenFlag) {
                $this->log("Recursively fetching more Financial Events");
                $this->fetchEventList(false);
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
            $this->options['Action'] = 'ListFinancialEventsByNextToken';
            unset($this->options['MaxResultsPerPage']);
            $this->resetFilters();
        } else {
            $this->options['Action'] = 'ListFinancialEvents';
            unset($this->options['NextToken']);
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
        if (!$xml) {
            return false;
        }
        if (isset($xml->ShipmentEventList)) {
            foreach($xml->ShipmentEventList->children() as $x) {
                $this->list['Shipment'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->RefundEventList)) {
            foreach($xml->RefundEventList->children() as $x) {
                $this->list['Refund'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->GuaranteeClaimEventList)) {
            foreach($xml->GuaranteeClaimEventList->children() as $x) {
                $this->list['GuaranteeClaim'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->ChargebackEventList)) {
            foreach($xml->ChargebackEventList->children() as $x) {
                $this->list['Chargeback'][] = $this->parseShipmentEvent($x);
            }
        }
        if (isset($xml->PayWithAmazonEventList)) {
            foreach($xml->PayWithAmazonEventList->children() as $x) {
                $temp = array();
                $temp['SellerOrderId'] = (string)$x->SellerOrderId;
                $temp['TransactionPostedDate'] = (string)$x->TransactionPostedDate;
                $temp['BusinessObjectType'] = (string)$x->BusinessObjectType;
                $temp['SalesChannel'] = (string)$x->SalesChannel;
                $temp['Charge'] = $this->parseCharge($x->Charge);
                if (isset($x->FeeList)) {
                    foreach($x->FeeList->children() as $z) {
                        $temp['FeeList'][] = $this->parseFee($z);
                    }
                }
                $temp['PaymentAmountType'] = (string)$x->PaymentAmountType;
                $temp['AmountDescription'] = (string)$x->AmountDescription;
                $temp['FulfillmentChannel'] = (string)$x->FulfillmentChannel;
                $temp['StoreName'] = (string)$x->StoreName;
                $this->list['PayWithAmazon'][] = $temp;
            }
        }
        if (isset($xml->ServiceProviderCreditEventList)) {
            foreach($xml->ServiceProviderCreditEventList->children() as $x) {
                $temp = array();
                $temp['ProviderTransactionType'] = (string)$x->ProviderTransactionType;
                $temp['SellerOrderId'] = (string)$x->SellerOrderId;
                $temp['MarketplaceId'] = (string)$x->MarketplaceId;
                $temp['MarketplaceCountryCode'] = (string)$x->MarketplaceCountryCode;
                $temp['SellerId'] = (string)$x->SellerId;
                $temp['SellerStoreName'] = (string)$x->SellerStoreName;
                $temp['ProviderId'] = (string)$x->ProviderId;
                $temp['ProviderStoreName'] = (string)$x->ProviderStoreName;
                $this->list['ServiceProviderCredit'][] = $temp;
            }
        }
        if (isset($xml->RetrochargeEventList)) {
            foreach($xml->RetrochargeEventList->children() as $x) {
                $temp = array();
                $temp['RetrochargeEventType'] = (string)$x->RetrochargeEventType;
                $temp['AmazonOrderId'] = (string)$x->AmazonOrderId;
                $temp['PostedDate'] = (string)$x->PostedDate;
                $temp['BaseTax']['Amount'] = (string)$x->BaseTax->CurrencyAmount;
                $temp['BaseTax']['CurrencyCode'] = (string)$x->BaseTax->CurrencyCode;
                $temp['ShippingTax']['Amount'] = (string)$x->ShippingTax->CurrencyAmount;
                $temp['ShippingTax']['CurrencyCode'] = (string)$x->ShippingTax->CurrencyCode;
                $temp['MarketplaceName'] = (string)$x->MarketplaceName;
                $this->list['Retrocharge'][] = $temp;
            }
        }
        if (isset($xml->RentalTransactionEventList)) {
            foreach($xml->RentalTransactionEventList->children() as $x) {
                $temp = array();
                $temp['AmazonOrderId'] = (string)$x->AmazonOrderId;
                $temp['RentalEventType'] = (string)$x->RentalEventType;
                $temp['ExtensionLength'] = (string)$x->ExtensionLength;
                $temp['PostedDate'] = (string)$x->PostedDate;
                if (isset($x->RentalChargeList)) {
                    foreach($x->RentalChargeList->children() as $z) {
                        $temp['RentalChargeList'][] = $this->parseCharge($z);
                    }
                }
                if (isset($x->RentalFeeList)) {
                    foreach($x->RentalFeeList->children() as $z) {
                        $temp['RentalFeeList'][] = $this->parseFee($z);
                    }
                }
                $temp['MarketplaceName'] = (string)$x->MarketplaceName;
                if (isset($x->RentalInitialValue)) {
                    $temp['RentalInitialValue']['Amount'] = (string)$x->RentalInitialValue->CurrencyAmount;
                    $temp['RentalInitialValue']['CurrencyCode'] = (string)$x->RentalInitialValue->CurrencyCode;
                }
                if (isset($x->RentalReimbursement)) {
                    $temp['RentalReimbursement']['Amount'] = (string)$x->RentalReimbursement->CurrencyAmount;
                    $temp['RentalReimbursement']['CurrencyCode'] = (string)$x->RentalReimbursement->CurrencyCode;
                }
                $this->list['RentalTransaction'][] = $temp;
            }
        }
        if (isset($xml->PerformanceBondRefundEventList)) {
            foreach($xml->PerformanceBondRefundEventList->children() as $x) {
                $temp = array();
                $temp['MarketplaceCountryCode'] = (string)$x->MarketplaceCountryCode;
                $temp['Amount'] = (string)$x->Amount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->Amount->CurrencyCode;
                if (isset($x->ProductGroupList)) {
                    foreach($x->ProductGroupList->children() as $z) {
                        $temp['ProductGroupList'][] = (string)$z;
                    }
                }
                $this->list['PerformanceBondRefund'][] = $temp;
            }
        }
        if (isset($xml->ServiceFeeEventList)) {
            foreach($xml->ServiceFeeEventList->children() as $x) {
                $temp = array();
                $temp['AmazonOrderId'] = (string)$x->AmazonOrderId;
                $temp['FeeReason'] = (string)$x->FeeReason;
                if (isset($x->FeeList)) {
                    foreach($x->FeeList->children() as $z) {
                        $temp['FeeList'][] = $this->parseFee($z);
                    }
                }
                $temp['SellerSKU'] = (string)$x->SellerSKU;
                $temp['FnSKU'] = (string)$x->FnSKU;
                $temp['FeeDescription'] = (string)$x->FeeDescription;
                $temp['ASIN'] = (string)$x->ASIN;
                $this->list['ServiceFee'][] = $temp;
            }
        }
        if (isset($xml->DebtRecoveryEventList)) {
            foreach($xml->DebtRecoveryEventList->children() as $x) {
                $temp = array();
                $temp['DebtRecoveryType'] = (string)$x->DebtRecoveryType;
                $temp['RecoveryAmount']['Amount'] = (string)$x->RecoveryAmount->CurrencyAmount;
                $temp['RecoveryAmount']['CurrencyCode'] = (string)$x->RecoveryAmount->CurrencyCode;
                $temp['OverPaymentCredit']['Amount'] = (string)$x->OverPaymentCredit->CurrencyAmount;
                $temp['OverPaymentCredit']['CurrencyCode'] = (string)$x->OverPaymentCredit->CurrencyCode;
                if (isset($x->DebtRecoveryItemList)) {
                    foreach($x->DebtRecoveryItemList->children() as $z) {
                        $ztemp = array();
                        $ztemp['RecoveryAmount']['Amount'] = (string)$z->RecoveryAmount->CurrencyAmount;
                        $ztemp['RecoveryAmount']['CurrencyCode'] = (string)$z->RecoveryAmount->CurrencyCode;
                        $ztemp['OriginalAmount']['Amount'] = (string)$z->OriginalAmount->CurrencyAmount;
                        $ztemp['OriginalAmount']['CurrencyCode'] = (string)$z->OriginalAmount->CurrencyCode;
                        $ztemp['GroupBeginDate'] = (string)$z->GroupBeginDate;
                        $ztemp['GroupEndDate'] = (string)$z->GroupEndDate;
                        $temp['DebtRecoveryItemList'][] = $ztemp;
                    }
                }
                if (isset($x->ChargeInstrumentList)) {
                    foreach($x->ChargeInstrumentList->children() as $z) {
                        $ztemp = array();
                        $ztemp['Description'] = (string)$z->Description;
                        $ztemp['Tail'] = (string)$z->Tail;
                        $ztemp['Amount'] = (string)$z->Amount->CurrencyAmount;
                        $ztemp['CurrencyCode'] = (string)$z->Amount->CurrencyCode;
                        $temp['ChargeInstrumentList'][] = $ztemp;
                    }
                }
                $this->list['DebtRecovery'][] = $temp;
            }
        }
        if (isset($xml->LoanServicingEventList)) {
            foreach($xml->LoanServicingEventList->children() as $x) {
                $temp = array();
                $temp['Amount'] = (string)$x->LoanAmount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->LoanAmount->CurrencyCode;
                $temp['SourceBusinessEventType'] = (string)$x->SourceBusinessEventType;
                $this->list['LoanServicing'][] = $temp;
            }
        }
        if (isset($xml->AdjustmentEventList)) {
            foreach($xml->AdjustmentEventList->children() as $x) {
                $temp = array();
                $temp['AdjustmentType'] = (string)$x->AdjustmentType;
                $temp['Amount'] = (string)$x->AdjustmentAmount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->AdjustmentAmount->CurrencyCode;
                if (isset($x->AdjustmentItemList)) {
                    foreach($x->AdjustmentItemList->children() as $z) {
                        $ztemp = array();
                        $ztemp['Quantity'] = (string)$z->Quantity;
                        $ztemp['PerUnitAmount']['Amount'] = (string)$z->PerUnitAmount->CurrencyAmount;
                        $ztemp['PerUnitAmount']['CurrencyCode'] = (string)$z->PerUnitAmount->CurrencyCode;
                        $ztemp['TotalAmount']['Amount'] = (string)$z->TotalAmount->CurrencyAmount;
                        $ztemp['TotalAmount']['CurrencyCode'] = (string)$z->TotalAmount->CurrencyCode;
                        $ztemp['SellerSKU'] = (string)$z->SellerSKU;
                        $ztemp['FnSKU'] = (string)$z->FnSKU;
                        $ztemp['ProductDescription'] = (string)$z->ProductDescription;
                        $ztemp['ASIN'] = (string)$z->ASIN;
                        $temp['AdjustmentItemList'][] = $ztemp;
                    }
                }
                $this->list['Adjustment'][] = $temp;
            }
        }
    }

    /**
     * Parses XML for a single shipment event into an array.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return array parsed structure from XML
     */
    protected function parseShipmentEvent($xml) {
        $r = array();
        $r['AmazonOrderId'] = (string)$xml->AmazonOrderId;
        $r['SellerOrderId'] = (string)$xml->SellerOrderId;
        $r['MarketplaceName'] = (string)$xml->MarketplaceName;
        $chargeLists = array(
            'OrderChargeList',
            'OrderChargeAdjustmentList',
        );
        foreach ($chargeLists as $key) {
            if (isset($xml->$key)) {
                foreach($xml->$key->children() as $x) {
                    $r[$key][] = $this->parseCharge($x);
                }
            }
        }
        $feelists = array(
            'ShipmentFeeList',
            'ShipmentFeeAdjustmentList',
            'OrderFeeList',
            'OrderFeeAdjustmentList',
        );
        foreach ($feelists as $key) {
            if (isset($xml->$key)) {
                foreach($xml->$key->children() as $x) {
                    $r[$key][] = $this->parseFee($x);
                }
            }
        }
        if (isset($xml->DirectPaymentList)) {
            foreach($xml->DirectPaymentList->children() as $x){
                $temp = array();
                $temp['DirectPaymentType'] = (string)$x->DirectPaymentType;
                $temp['Amount'] = (string)$x->DirectPaymentAmount->CurrencyAmount;
                $temp['CurrencyCode'] = (string)$x->DirectPaymentAmount->CurrencyCode;
                $r['DirectPaymentList'][] = $temp;
            }
        }
        $r['PostedDate'] = (string)$xml->PostedDate;
        $itemLists = array(
            'ShipmentItemList',
            'ShipmentItemAdjustmentList',
        );
        $itemChargeLists = array(
            'ItemChargeList',
            'ItemChargeAdjustmentList',
        );
        $itemFeeLists = array(
            'ItemFeeList',
            'ItemFeeAdjustmentList',
        );
        $itemPromoLists = array(
            'PromotionList',
            'PromotionAdjustmentList',
        );
        foreach ($itemLists as $key) {
            if (isset($xml->$key)) {
                foreach($xml->$key->children() as $x) {
                    $temp = array();
                    $temp['SellerSKU'] = (string)$x->SellerSKU;
                    $temp['OrderItemId'] = (string)$x->OrderItemId;
                    if (isset($x->OrderAdjustmentItemId)) {
                        $temp['OrderAdjustmentItemId'] = (string)$x->OrderAdjustmentItemId;
                    }
                    $temp['QuantityShipped'] = (string)$x->QuantityShipped;
                    foreach ($itemChargeLists as $zkey) {
                        if (isset($x->$zkey)) {
                            foreach($x->$zkey->children() as $z) {
                                $temp[$zkey][] = $this->parseCharge($z);
                            }
                        }
                    }
                    foreach ($itemFeeLists as $zkey) {
                        if (isset($x->$zkey)) {
                            foreach($x->$zkey->children() as $z) {
                                $temp[$zkey][] = $this->parseFee($z);
                            }
                        }
                    }
                    foreach ($itemPromoLists as $zkey) {
                        if (isset($x->$zkey)) {
                            foreach($x->$zkey->children() as $z) {
                                $ztemp = array();
                                $ztemp['PromotionType'] = (string)$z->PromotionType;
                                $ztemp['PromotionId'] = (string)$z->PromotionId;
                                $ztemp['Amount'] = (string)$z->PromotionAmount->CurrencyAmount;
                                $ztemp['CurrencyCode'] = (string)$z->PromotionAmount->CurrencyCode;
                                $temp[$zkey][] = $ztemp;
                            }
                        }
                    }
                    if (isset($x->CostOfPointsGranted)) {
                        $temp['CostOfPointsGranted']['Amount'] = (string)$x->CostOfPointsGranted->CurrencyAmount;
                        $temp['CostOfPointsGranted']['CurrencyCode'] = (string)$x->CostOfPointsGranted->CurrencyCode;
                    }
                    if (isset($x->CostOfPointsReturned)) {
                        $temp['CostOfPointsReturned']['Amount'] = (string)$x->CostOfPointsReturned->CurrencyAmount;
                        $temp['CostOfPointsReturned']['CurrencyCode'] = (string)$x->CostOfPointsReturned->CurrencyCode;
                    }
                    $r[$key][] = $temp;
                }
            }
        }
        return $r;
    }

    /**
     * Parses XML for a single charge into an array.
     * This structure is used many times throughout shipment events.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return array parsed structure from XML
     */
    protected function parseCharge($xml) {
        $r = array();
        $r['ChargeType'] = (string)$xml->ChargeType;
        $r['Amount'] = (string)$xml->ChargeAmount->CurrencyAmount;
        $r['CurrencyCode'] = (string)$xml->ChargeAmount->CurrencyCode;
        return $r;
    }

    /**
     * Parses XML for a single charge into an array.
     * This structure is used many times throughout shipment events.
     * @param SimpleXMLElement $xml <p>The XML response from Amazon.</p>
     * @return array parsed structure from XML
     */
    protected function parseFee($xml) {
        $r = array();
        $r['FeeType'] = (string)$xml->FeeType;
        $r['Amount'] = (string)$xml->FeeAmount->CurrencyAmount;
        $r['CurrencyCode'] = (string)$xml->FeeAmount->CurrencyCode;
        return $r;
    }

    /**
     * Returns all financial events.
     *
     * The array will have the following keys:
     * <ul>
     * <li><b>Shipment</b> - see <i>getShipmentEvents</i></li>
     * <li><b>Refund</b> - see <i>getRefundEvents</i></li>
     * <li><b>GuaranteeClaim</b> - see <i>getGuaranteeClaimEvents</i></li>
     * <li><b>Chargeback</b> - see <i>getChargebackEvents</i></li>
     * <li><b>PayWithAmazon</b> - see <i>getPayWithAmazonEvents</i></li>
     * <li><b>ServiceProviderCredit</b> - see <i>getServiceProviderCreditEvents</i></li>
     * <li><b>Retrocharge</b> - see <i>getRetrochargeEvents</i></li>
     * <li><b>RentalTransaction</b> - see <i>getRentalTransactionEvents</i></li>
     * <li><b>PerformanceBondRefund</b> - see <i>getPerformanceBondRefundEvents</i></li>
     * <li><b>ServiceFee</b> - see <i>getServiceFeeEvents</i></li>
     * <li><b>DebtRecovery</b> - see <i>getDebtRecoveryEvents</i></li>
     * <li><b>LoanServicing</b> - see <i>getLoanServicingEvents</i></li>
     * <li><b>Adjustment</b> - see <i>getAdjustmentEvents</i></li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     * @see getShipmentEvents
     * @see getRefundEvents
     * @see getGuaranteeClaimEvents
     * @see getChargebackEvents
     * @see getPayWithAmazonEvents
     * @see getServiceProviderCreditEvents
     * @see getRetrochargeEvents
     * @see getRentalTransactionEvents
     * @see getPerformanceBondRefundEvents
     * @see getServiceFeeEvents
     * @see getDebtRecoveryEvents
     * @see getLoanServicingEvents
     * @see getAdjustmentEvents
     */
    public function getEvents(){
        if (isset($this->list)){
            return $this->list;
        } else {
            return false;
        }
    }

    /**
     * Returns all shipment events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>AmazonOrderId</b></li>
     * <li><b>SellerOrderId</b></li>
     * <li><b>MarketplaceName</b></li>
     * <li><b>OrderChargeList</b> (optional) - list of charges, only for MCF COD orders</li>
     * <li><b>ShipmentFeeList</b> - list of fees</li>
     * <li><b>OrderFeeList</b> (optional) - list of fees, only for MCF orders</li>
     * <li><b>DirectPaymentList</b> (optional) - multi-dimensional array, only for COD orders.
     * Each array in the list has the following keys:</li>
     * <ul>
     * <li><b>DirectPaymentType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>PostedDate</b> - ISO 8601 date format</li>
     * </ul>
     *
     * Each "charge" array has the following keys:
     * <ul>
     * <li><b>ChargeType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * Each "fee" array has the following keys:
     * <ul>
     * <li><b>FeeType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * Each "item" array has the following keys:
     * <ul>
     * <li><b>SellerSKU</b></li>
     * <li><b>OrderItemId</b></li>
     * <li><b>QuantityShipped</b></li>
     * <li><b>ItemChargeList</b> - list of charges</li>
     * <li><b>ItemFeeList</b> - list of fees</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * <li><b>PromotionList</b> - list of promotions</li>
     * <li><b>CostOfPointsGranted</b> (optional) - array</li>
     * <ul>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * </ul>
     * Each "promotion" array has the following keys:
     * <ul>
     * <li><b>PromotionType</b></li>
     * <li><b>PromotionId</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getShipmentEvents(){
        if (isset($this->list['Shipment'])){
            return $this->list['Shipment'];
        } else {
            return false;
        }
    }

    /**
     * Returns all refund events.
     *
     * The structure for each event array is the same as in <i>getShipmentEvents</i>,
     * but with the following additional keys in each "item" array:
     * <ul>
     * <li><b>OrderChargeAdjustmentList</b> (optional) - list of charges, only for MCF COD orders</li>
     * <li><b>ShipmentFeeAdjustmentList</b> - list of fees</li>
     * <li><b>OrderFeeAdjustmentList</b> (optional) - list of fees, only for MCF orders</li>
     * </ul>
     * Each "item" array will have the following additional keys:
     * <ul>
     * <li><b>OrderAdjustmentItemId</b></li>
     * <li><b>ItemChargeAdjustmentList</b> - list of charges</li>
     * <li><b>ItemFeeAdjustmentList</b> - list of fees</li>
     * <li><b>PromotionAdjustmentList</b> - list of promotions</li>
     * <li><b>CostOfPointsReturned</b> (optional) - array</li>
     * <ul>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     * @see getShipmentEvents
     */
    public function getRefundEvents(){
        if (isset($this->list['Refund'])){
            return $this->list['Refund'];
        } else {
            return false;
        }
    }

    /**
     * Returns all guarantee claim events.
     *
     * The structure for each event array is the same as in <i>getRefundEvents</i>.
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     * @see getRefundEvents
     */
    public function getGuaranteeClaimEvents(){
        if (isset($this->list['GuaranteeClaim'])){
            return $this->list['GuaranteeClaim'];
        } else {
            return false;
        }
    }

    /**
     * Returns all chargeback events.
     *
     * The structure for each event array is the same as in <i>getRefundEvents</i>.
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     * @see getRefundEvents
     */
    public function getChargebackEvents(){
        if (isset($this->list['Chargeback'])){
            return $this->list['Chargeback'];
        } else {
            return false;
        }
    }

    /**
     * Returns all pay with Amazon events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>SellerOrderId</b></li>
     * <li><b>TransactionPostedDate</b> - ISO 8601 date format</li>
     * <li><b>BusinessObjectType</b> - "PaymentContract"</li>
     * <li><b>SalesChannel</b></li>
     * <li><b>Charge</b> - array</li>
     * <ul>
     * <li><b>ChargeType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>FeeList</b> - multi-dimensional array, each array has the following keys:</li>
     * <ul>
     * <li><b>FeeType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>PaymentAmountType</b> - "Sales"</li>
     * <li><b>AmountDescription</b></li>
     * <li><b>FulfillmentChannel</b> - "MFN" or "AFN"</li>
     * <li><b>StoreName</b></li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getPayWithAmazonEvents(){
        if (isset($this->list['PayWithAmazon'])){
            return $this->list['PayWithAmazon'];
        } else {
            return false;
        }
    }

    /**
     * Returns all service provider credit events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>ProviderTransactionType</b> - "ProviderCredit" or "ProviderCreditReversal"</li>
     * <li><b>SellerOrderId</b></li>
     * <li><b>MarketplaceId</b></li>
     * <li><b>MarketplaceCountryCode</b> - two-letter country code in ISO 3166-1 alpha-2 format</li>
     * <li><b>SellerId</b></li>
     * <li><b>SellerStoreName</b></li>
     * <li><b>ProviderId</b></li>
     * <li><b>ProviderStoreName</b></li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getServiceProviderCreditEvents(){
        if (isset($this->list['ServiceProviderCredit'])){
            return $this->list['ServiceProviderCredit'];
        } else {
            return false;
        }
    }

    /**
     * Returns all retrocharge events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>RetrochargeEventType</b> -"Retrocharge" or "RetrochargeReversal"</li>
     * <li><b>AmazonOrderId</b></li>
     * <li><b>PostedDate</b> - ISO 8601 date format</li>
     * <li><b>BaseTax</b> - array</li>
     * <ul>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>ShippingTax</b> - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>MarketplaceName</b></li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getRetrochargeEvents(){
        if (isset($this->list['Retrocharge'])){
            return $this->list['Retrocharge'];
        } else {
            return false;
        }
    }

    /**
     * Returns all rental transaction events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>AmazonOrderId</b></li>
     * <li><b>RentalEventType</b></li>
     * <li><b>ExtensionLength</b> (optional)</li>
     * <li><b>PostedDate</b> - ISO 8601 date format</li>
     * <li><b>RentalChargeList</b> - multi-dimensional array, each with the following keys:</li>
     * <ul>
     * <li><b>ChargeType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>RentalFeeList</b> - multi-dimensional array, each array has the following keys:</li>
     * <ul>
     * <li><b>FeeType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>MarketplaceName</b></li>
     * <li><b>RentalInitialValue</b> (optional) - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>RentalReimbursement</b> (optional) - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getRentalTransactionEvents(){
        if (isset($this->list['RentalTransaction'])){
            return $this->list['RentalTransaction'];
        } else {
            return false;
        }
    }

    /**
     * Returns all performance bond refund events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>MarketplaceCountryCode</b> - two-letter country code in ISO 3166-1 alpha-2 format</li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * <li><b>ProductGroupList</b> - simple array of category names</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getPerformanceBondRefundEvents(){
        if (isset($this->list['PerformanceBondRefund'])){
            return $this->list['PerformanceBondRefund'];
        } else {
            return false;
        }
    }

    /**
     * Returns all service fee events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>AmazonOrderId</b></li>
     * <li><b>FeeReason</b></li>
     * <li><b>FeeList</b> - multi-dimensional array, each array has the following keys:</li>
     * <ul>
     * <li><b>FeeType</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>SellerSKU</b></li>
     * <li><b>FnSKU</b></li>
     * <li><b>FeeDescription</b></li>
     * <li><b>ASIN</b></li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getServiceFeeEvents(){
        if (isset($this->list['ServiceFee'])){
            return $this->list['ServiceFee'];
        } else {
            return false;
        }
    }

    /**
     * Returns all debt recovery events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>DebtRecoveryType</b> - "DebtPayment", "DebtPaymentFailure", or "DebtAdjustment"</li>
     * <li><b>RecoveryAmount</b> - array</li>
     * <ul>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * <li><b>OverPaymentCredit</b> (optional) - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>DebtRecoveryItemList</b> - multi-dimensional array, each array has the following keys:</li>
     * <ul>
     * <li><b>RecoveryAmount</b> - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>OriginalAmount</b> - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>GroupBeginDate</b> - ISO 8601 date format</li>
     * <li><b>GroupEndDate</b> - ISO 8601 date format</li>
     * </ul>
     * <li><b>ChargeInstrumentList</b> - multi-dimensional array, each array has the following keys:</li>
     * <ul>
     * <li><b>Description</b></li>
     * <li><b>Tail</b></li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * </ul>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getDebtRecoveryEvents(){
        if (isset($this->list['DebtRecovery'])){
            return $this->list['DebtRecovery'];
        } else {
            return false;
        }
    }

    /**
     * Returns all loan servicing events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * <li><b>SourceBusinessEventType</b> - "LoanAdvance", "LoanPayment", or "LoanRefund"</li>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getLoanServicingEvents(){
        if (isset($this->list['LoanServicing'])){
            return $this->list['LoanServicing'];
        } else {
            return false;
        }
    }

    /**
     * Returns all adjustment events.
     *
     * Each event array will have the following keys:
     * <ul>
     * <li><b>AdjustmentType</b> "FBAInventoryReimbursement", "ReserveEvent", "PostageBilling", or "PostageRefund"</li>
     * <li><b>Amount</b> - number</li>
     * <li><b>CurrencyCode</b> - ISO 4217 currency code</li>
     * <li><b>AdjustmentItemList</b> - multi-dimensional array, each array has the following keys:</li>
     * <ul>
     * <li><b>Quantity</b></li>
     * <li><b>PerUnitAmount</b> - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>TotalAmount</b> - array with <b>Amount</b> and <b>CurrencyCode</b></li>
     * <li><b>SellerSKU</b></li>
     * <li><b>FnSKU</b></li>
     * <li><b>ProductDescription</b></li>
     * <li><b>ASIN</b></li>
     * </ul>
     * </ul>
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getAdjustmentEvents(){
        if (isset($this->list['Adjustment'])){
            return $this->list['Adjustment'];
        } else {
            return false;
        }
    }

}
