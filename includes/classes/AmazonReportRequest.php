<?php

class AmazonReportRequest extends AmazonReportsCore{
    private $response;
    
    /**
     * Sends a report request to Amazon.
     * @param string $s name of store as seen in config file
     * @param boolean $mock true to enable mock mode
     * @param array|string $m list of mock files to use
     */
    public function __construct($s, $mock = false, $m = null) {
        parent::__construct($s, $mock, $m);
        if (file_exists($this->config)){
            include($this->config);
        } else {
            throw new Exception('Config file does not exist!');
        }
        
        $this->options['Action'] = 'RequestReport';
        
        $this->throttleLimit = $throttleLimitReportRequest;
        $this->throttleTime = $throttleTimeReportRequest;
        $this->throttleGroup = 'RequestReport';
    }
    
    /**
     * set the report type to be used in the next request
     * @param string $s value from specific list, see comment inside
     */
    public function setReportType($s){
        if (is_string($s) && $s){
            $this->options['ReportType'] = $s;
        }
        /*
         * List of valid Report Types:
         * Listings Reports:
         *      Open Listings Report ~ _GET_FLAT_FILE_OPEN_LISTINGS_DATA_
         *      Open Listings Report ~ _GET_MERCHANT_LISTINGS_DATA_BACK_COMPAT_
         *      Merchant Listings Report ~ _GET_MERCHANT_LISTINGS_DATA_
         *      Merchant Listings Lite Report ~ _GET_MERCHANT_LISTINGS_DATA_LITE_
         *      Merchant Listings Liter Report ~ _GET_MERCHANT_LISTINGS_DATA_LITER_
         *      Canceled Listings Report ~ _GET_MERCHANT_CANCELLED_LISTINGS_DATA_
         *      Quality Listing Report ~ _GET_MERCHANT_LISTINGS_DEFECT_DATA_
         * Order Reports:
         *      Unshipped Orders Report ~ _GET_FLAT_FILE_ACTIONABLE_ORDER_DATA_
         *      Flat File Order Report ~ _GET_FLAT_FILE_ORDER_REPORT_DATA_
         *      Requested Flat File Order Report ~ _GET_FLAT_FILE_ORDERS_DATA_
         *      Flat File Order Report ~ _GET_CONVERGED_FLAT_FILE_ORDER_REPORT_DATA_
         * Order Tracking Reports:
         *      Flat File Orders By Last Update Report ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      Flat File Orders By Order Date Report ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_
         *      XML Orders By Last Update Report ~ _GET_XML_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      XML Orders By Order Date Report ~ _GET_XML_ALL_ORDERS_DATA_BY_ORDER_DATE_
         * Pending Order Reports:
         *      Flat File Pending Orders Report ~ _GET_FLAT_FILE_PENDING_ORDERS_DATA_
         *      XML Pending Orders Report ~ _GET_PENDING_ORDERS_DATA_
         *      Converged Flat File Pending Orders Report ~ GET_CONVERGED_FLAT_FILE_PENDING_ORDERS_DATA_
         * Performance Reports:
         *      Flat File Feedback Report ~ _GET_SELLER_FEEDBACK_DATA_
         * FBA Reports:
         *      Flat File All Orders Report by Last Update ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      Flat File All Orders Report by Order Date ~ _GET_FLAT_FILE_ALL_ORDERS_DATA_BY_ORDER_DATE_
         *      XML All Orders Report by Last Update ~ _GET_XML_ALL_ORDERS_DATA_BY_LAST_UPDATE_
         *      XML All Orders Report by Order Date ~ _GET_XML_ALL_ORDERS_DATA_BY_ORDER_DATE_
         *      FBA Inventory Report ~ _GET_AFN_INVENTORY_DATA_
         *      FBA Fulfilled Shipments Report ~ _GET_AMAZON_FULFILLED_SHIPMENTS_DATA_
         *      FBA Returns Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_
         *      FBA Customer Shipment Sales Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_SALES_DATA_
         *      Customer Taxes ~ _GET_FBA_FULFILLMENT_CUSTOMER_TAXES_DATA_
         *      FBA Promotions Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_PROMOTION_DATA_
         *      FBA Inbound Compliance Report ~ _GET_FBA_FULFILLMENT_INBOUND_NONCOMPLIANCE_DATA_
         *      FBA Daily Inventory History Report ~ _GET_FBA_FULFILLMENT_CURRENT_INVENTORY_DATA_
         *      FBA Monthly Inventory History Repoty ~ _GET_FBA_FULFILLMENT_MONTHLY_INVENTORY_DATA_
         *      FBA Received Inventory Report ~ _GET_FBA_FULFILLMENT_INVENTORY_RECEIPTS_DATA_
         *      FBA Inventory Event Detail Report ~ _GET_FBA_FULFILLMENT_INVENTORY_SUMMARY_DATA_
         *      FBA Inventory Adjustments Report ~ _GET_FBA_FULFILLMENT_INVENTORY_ADJUSTMENTS_DATA_
         *      FBA Inventory Health Report ~ _GET_FBA_FULFILLMENT_INVENTORY_HEALTH_DATA_
         *      FBA Manage Inventory ~ _GET_FBA_MYI_UNSUPPRESSED_INVENTORY_DATA_
         *      FBA Manage Inventory - Archived ~ _GET_FBA_MYI_ALL_INVENTORY_DATA_
         *      FBA Replacements Report ~ _GET_FBA_FULFILLMENT_CUSTOMER_SHIPMENT_REPLACEMENT_DATA_
         *      FBA Cross-Border Inventory Movement Report ~ _GET_FBA_FULFILLMENT_CROSS_BORDER_INVENTORY_MOVEMENT_DATA_
         *      FBA Recommended Removal Report ~ _GET_FBA_RECOMMENDED_REMOVAL_DATA_
         * Amazon Product Ads Report:
         *      Product Ads Listings Report ~ _GET_NEMO_MERCHANT_LISTINGS_DATA_
         *      Product Ads Daily Performance by SKU Report, flat file ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_DAILY_DATA_TSV_
         *      Product Ads Daily Performance by SKU Report, XML ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_DAILY_DATA_XML_
         *      Product Ads Weekly Performance by SKU Report, flat file ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_WEEKLY_DATA_TSV_
         *      Product Ads Weekly Performance by SKU Report, XML ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_WEEKLY_DATA_XML_
         *      Product Ads Monthly Performance by SKU Report, flat file ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_MONTHLY_DATA_TSV_
         *      Product Ads Monthly Performance by SKU Report, XML ~ _GET_PADS_PRODUCT_PERFORMANCE_OVER_TIME_MONTHLY_DATA_XML_
         */
    }
    
    /**
     * Sets the Start Time and End Time for the report
     * @param string $s passed through strtotime, set to null to ignore
     * @param string $e passed through strtotime
     */
    public function setTimeLimits($s = null,$e = null){
        if ($s && is_string($s)){
            $times = $this->genTime($s);
            $this->options['StartDate'] = $times;
        }
        if ($e && is_string($e)){
            $timee = $this->genTime($e);
            $this->options['EndDate'] = $timee;
        }
    }
    
    /**
     * removes time frame limits
     */
    public function resetTimeLimits(){
        unset($this->options['StartDate']);
        unset($this->options['EndDate']);
    }
    
    /**
     * set whether or not the report should return the Sales Channel column
     * @param string $s "true" or "false"
     */
    public function setShowSalesChannel($s){
        if (is_string($s) && $s){
            $this->options['ReportOptions=ShowSalesChannel'] = $s;
        }
    }
    
    /**
     * sets the Marketplace ID(s) to be used in the next request
     * @param array|string $s array of Marketplace IDs or single ID
     * @return boolean false if failure
     */
    public function setMarketplaces($s){
        if (is_string($s)){
            $this->resetMarketplaces();
            $this->options['MarketplaceIdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetMarketplaces();
            $i = 1;
            foreach ($s as $x){
                $this->options['MarketplaceIdList.Id.'.$i] = $x;
            }
        } else {
            return false;
        }
    }
    
    /**
     * removes speed options
     */
    public function resetMarketplaces(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#MarketplaceIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * sends a report request to Amazon
     */
    public function requestReport(){
        if (!array_key_exists('ReportType',$this->options)){
            $this->log("Report Type must be set in order to request a report!",'Warning');
            return false;
        }
        
        $this->options['Timestamp'] = $this->genTime();
        
        $url = $this->urlbase.$this->urlbranch;
        
        $this->options['Signature'] = $this->_signParameters($this->options, $this->secretKey);
        $query = $this->_getParametersAsString($this->options);
        
        $path = $this->options['Action'].'Result';
        if ($this->mockMode){
           $xml = $this->fetchMockFile()->$path;
        } else {
            $this->throttle();
            $this->log("Making request to Amazon");
            $response = fetchURL($url,array('Post'=>$query));
            $this->logRequest();
            
            if (!$this->checkResponse($response)){
                return false;
            }
            
            $xml = simplexml_load_string($response['body'])->$path;
        }
        
        $this->parseXML($xml);
        
    }
    
    /**
     * loads XML response into array
     * @param SimpleXMLObject $xml XML from response
     * @return boolean false on failure
     */
    protected function parseXML($xml){
        if (!$xml){
            return false;
        }
        
        $this->response = array();
        $this->response['ReportRequestId'] = (string)$xml->ReportRequestId;
        $this->response['ReportType'] = (string)$xml->ReportType;
        $this->response['StartDate'] = (string)$xml->StartDate;
        $this->response['EndDate'] = (string)$xml->EndDate;
        $this->response['Scheduled'] = (string)$xml->Scheduled;
        $this->response['SubmittedDate'] = (string)$xml->SubmittedDate;
        $this->response['ReportProcessingStatus'] = (string)$xml->ReportProcessingStatus;
        
    }
    
    /**
     * gets the response array, if it exists
     * @return array|boolean Response array, or false on failure
     */
    public function getResponse(){
        if (!isset($this->response) || !isset($this->response['ReportRequestId'])){
            return false;
        } else {
            return $this->response;
        }
    }
    
}
?>