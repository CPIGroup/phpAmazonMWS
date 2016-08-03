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
 * Receives a list of feeds from Amazon.
 * 
 * This Amazon Feeds Core object can receive a list of feed submissions
 * that were previously sent to Amazon. It can also fetch a count of
 * said feed submissions, or even cancel them. While no parameters are
 * required for these functions, filters such as feed ID, feed type, and
 * time frame can be set to narrow the scope of the list. This object
 * can use tokens when retrieving the list.
 */
class AmazonFeedList extends AmazonFeedsCore implements Iterator{
    protected $tokenFlag = false;
    protected $tokenUseFlag = false;
    protected $feedList;
    protected $index = 0;
    protected $i = 0;
    protected $count;
    
    /**
     * AmazonFeedList fetches a list of Feeds from Amazon.
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
        
        if(isset($THROTTLE_LIMIT_FEEDLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_FEEDLIST;
        }
        if(isset($THROTTLE_TIME_FEEDLIST)) {
            $this->throttleTime = $THROTTLE_TIME_FEEDLIST;
        }
    }
    
    /**
     * Returns whether or not a token is available.
     * @return boolean
     */
    public function hasToken(){
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
    public function setUseToken($b = true){
        if (is_bool($b)){
            $this->tokenUseFlag = $b;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the feed submission ID(s). (Optional)
     * 
     * This method sets the list of Feed Submission IDs to be sent in the next request.
     * Setting this parameter tells Amazon to only return Feed Submissions that match
     * the IDs in the list. If this parameter is set, all other parameters will be ignored.
     * @param array|string $s <p>A list of Feed Submission IDs, or a single ID string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setFeedIds($s){
        if (is_string($s)){
            $this->resetFeedIds();
            $this->options['FeedSubmissionIdList.Id.1'] = $s;
        } else if (is_array($s)){
            $this->resetFeedIds();
            $i = 1;
            foreach ($s as $x){
                $this->options['FeedSubmissionIdList.Id.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Removes feed ID options.
     * 
     * Use this in case you change your mind and want to remove the Submission Feed ID
     * parameters you previously set.
     */
    public function resetFeedIds(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#FeedSubmissionIdList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the feed type(s). (Optional)
     * 
     * This method sets the list of Feed Types to be sent in the next request.
     * Setting this parameter tells Amazon to only return Feed Submissions that match
     * the types in the list. If this parameter is not set, Amazon will return
     * Feed Submissions of any type.
     * @param array|string $s <p>A list of Feed Types, or a single type string.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setFeedTypes($s){
        if (is_string($s)){
            $this->resetFeedTypes();
            $this->options['FeedTypeList.Type.1'] = $s;
        } else if (is_array($s)){
            $this->resetFeedTypes();
            $i = 1;
            foreach ($s as $x){
                $this->options['FeedTypeList.Type.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Removes feed type options.
     * 
     * Use this in case you change your mind and want to remove the Feed Type
     * parameters you previously set.
     */
    public function resetFeedTypes(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#FeedTypeList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the feed status(es). (Optional)
     * 
     * This method sets the list of Feed Processing Statuses to be sent in the next request.
     * Setting this parameter tells Amazon to only return Feed Submissions that match
     * the statuses in the list. If this parameter is not set, Amazon will return
     * Feed Submissions with any status.
     * @param array|string $s <p>A list of Feed Statuses, or a single status string.<br />
     * Valid values are "_UNCONFIRMED_", "_SUBMITTED_", "_IN_PROGRESS_", "_IN_SAFETY_NET_",
     * "_AWAITING_ASYNCHRONOUS_REPLY_", "_CANCELLED_", and "_DONE_".</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setFeedStatuses($s){
        if (is_string($s)){
            $this->resetFeedStatuses();
            $this->options['FeedProcessingStatusList.Status.1'] = $s;
        } else if (is_array($s)){
            $this->resetFeedStatuses();
            $i = 1;
            foreach ($s as $x){
                $this->options['FeedProcessingStatusList.Status.'.$i] = $x;
                $i++;
            }
        } else {
            return false;
        }
    }
    
    /**
     * Removes feed status options.
     * 
     * Use this in case you change your mind and want to remove the Feed Status
     * parameters you previously set.
     */
    public function resetFeedStatuses(){
        foreach($this->options as $op=>$junk){
            if(preg_match("#FeedProcessingStatusList#",$op)){
                unset($this->options[$op]);
            }
        }
    }
    
    /**
     * Sets the maximum response count. (Optional)
     * 
     * This method sets the maximum number of Feed Submissions for Amazon to return.
     * If this parameter is not set, Amazon will only send ten.
     * @param array|string $s <p>Positive integer from 1 to 100.</p>
     * @return boolean <b>FALSE</b> if improper input
     */
    public function setMaxCount($s){
        if (is_numeric($s) && $s >= 1 && $s <= 100){
            $this->options['MaxCount'] = $s;
        } else {
            return false;
        }
    }
    
    /**
     * Sets the time frame options. (Optional)
     * 
     * This method sets the start and end times for the next request. If this
     * parameter is set, Amazon will only return Feed Submissions that were submitted
     * between the two times given. If these parameters are not set, Amazon will
     * only return Feed Submissions that were submitted within the past 180 days.
     * The parameters are passed through <i>strtotime</i>, so values such as "-1 hour" are fine.
     * @param string $s [optional] <p>A time string for the earliest time.</p>
     * @param string $e [optional] <p>A time string for the latest time.</p>
     */
    public function setTimeLimits($s = null,$e = null){
        if ($s && is_string($s)){
            $times = $this->genTime($s);
            $this->options['SubmittedFromDate'] = $times;
        }
        if ($e && is_string($e)){
            $timee = $this->genTime($e);
            $this->options['SubmittedToDate'] = $timee;
        }
    }
    
    /**
     * Removes time limit options.
     * 
     * Use this in case you change your mind and want to remove the time limit
     * parameters you previously set.
     */
    public function resetTimeLimits(){
        unset($this->options['SubmittedFromDate']);
        unset($this->options['SubmittedToDate']);
    }
    
    /**
     * Fetches a list of Feed Submissions from Amazon.
     * 
     * Submits a <i>GetFeedSubmissionList</i> request to Amazon. Amazon will send
     * the list back as a response, which can be retrieved using <i>getFeedList</i>.
     * Other methods are available for fetching specific values from the list.
     * This operation can potentially involve tokens.
     * @param boolean $r [optional] <p>When set to <b>FALSE</b>, the function will not recurse, defaults to <b>TRUE</b></p>
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function fetchFeedSubmissions($r = true){
        $this->prepareToken();
        
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
        
        $this->checkToken($xml);
        
        if ($this->tokenFlag && $this->tokenUseFlag && $r === true){
            while ($this->tokenFlag){
                $this->log("Recursively fetching more Feeds");
                $this->fetchFeedSubmissions(false);
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
    protected function prepareToken(){
        include($this->env);
        if ($this->tokenFlag && $this->tokenUseFlag){
            $this->options['Action'] = 'GetFeedSubmissionListByNextToken';
            if(isset($THROTTLE_LIMIT_REPORTTOKEN)) {
                $this->throttleLimit = $THROTTLE_LIMIT_REPORTTOKEN;
            }
            if(isset($THROTTLE_TIME_REPORTTOKEN)) {
                $this->throttleTime = $THROTTLE_TIME_REPORTTOKEN;
            }
            $this->throttleGroup = 'GetFeedSubmissionListByNextToken';
            $this->resetFeedTypes();
            $this->resetFeedStatuses();
            $this->resetFeedIds();
            $this->resetTimeLimits();
            unset($this->options['MaxCount']);
        } else {
            $this->options['Action'] = 'GetFeedSubmissionList';
            if(isset($THROTTLE_LIMIT_FEEDLIST)) {
                $this->throttleLimit = $THROTTLE_LIMIT_FEEDLIST;
            }
            if(isset($THROTTLE_TIME_FEEDLIST)) {
                $this->throttleTime = $THROTTLE_TIME_FEEDLIST;
            }
            $this->throttleGroup = 'GetFeedSubmissionList';
            unset($this->options['NextToken']);
            $this->feedList = array();
            $this->index = 0;
        }
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
        foreach($xml->children() as $key=>$x){
            $i = $this->index;
            if ($key == 'Count'){
                $this->count = (string)$x;
                $this->log("Successfully cancelled $this->count report requests.");
            }
            if ($key != 'FeedSubmissionInfo'){
                continue;
            }
            
            $this->feedList[$i]['FeedSubmissionId'] = (string)$x->FeedSubmissionId;
            $this->feedList[$i]['FeedType'] = (string)$x->FeedType;
            $this->feedList[$i]['SubmittedDate'] = (string)$x->SubmittedDate;
            $this->feedList[$i]['FeedProcessingStatus'] = (string)$x->FeedProcessingStatus;
            //this fields are not always returned
            if (isset($x->StartedProcessingDate)) {
                $this->feedList[$i]['StartedProcessingDate'] = (string)$x->StartedProcessingDate;
            }
            if (isset($x->CompletedProcessingDate)) {
                $this->feedList[$i]['CompletedProcessingDate'] = (string)$x->CompletedProcessingDate;
            }
            
            $this->index++;
        }
    }
    
    /**
     * Fetches a count of Feed Submissions from Amazon.
     * 
     * Submits a <i>GetFeedSubmissionCount</i> request to Amazon. Amazon will send
     * the number back as a response, which can be retrieved using <i>getCount</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function countFeeds(){
        $this->prepareCount();
        
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
        
        $this->count = (string)$xml->Count;
        
    }
    
    /**
     * Sets up options for using <i>countFeeds</i>.
     * 
     * This changes key options for using <i>countFeeds</i>. Please note: because the
     * operation for counting feeds does not use all of the parameters, some of the
     * parameters will be removed. The following parameters are removed:
     * feed IDs, max count, and token.
     */
    protected function prepareCount(){
        $this->options['Action'] = 'GetFeedSubmissionCount';
        if(isset($THROTTLE_LIMIT_FEEDLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_FEEDLIST;
        }
        if(isset($THROTTLE_TIME_FEEDLIST)) {
            $this->throttleTime = $THROTTLE_TIME_FEEDLIST;
        }
        $this->throttleGroup = 'GetFeedSubmissionCount';
        $this->resetFeedIds();
        unset($this->options['MaxCount']);
        unset($this->options['NextToken']);
    }
    
    /**
     * Cancels the feed submissions that match the given parameters. Careful!
     * 
     * Submits a <i>CancelFeedSubmissions</i> request to Amazon. Amazon will send
     * as a response the list of feeds that were cancelled, along with the count
     * of the number of affected feeds. This data can be retrieved using the same
     * methods as with <i>fetchFeedSubmissions</i> and <i>countFeeds</i>.
     * @return boolean <b>FALSE</b> if something goes wrong
     */
    public function cancelFeeds(){
        $this->prepareCancel();
        
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
     * Sets up options for using <i>cancelFeeds</i>.
     * 
     * This changes key options for using <i>cancelFeeds</i>. Please note: because the
     * operation for cancelling feeds does not use all of the parameters, some of the
     * parameters will be removed. The following parameters are removed:
     * feed statuses, max count, and token.
     */
    protected function prepareCancel(){
        include($this->env);
        $this->options['Action'] = 'CancelFeedSubmissions';
        if(isset($THROTTLE_LIMIT_FEEDLIST)) {
            $this->throttleLimit = $THROTTLE_LIMIT_FEEDLIST;
        }
        if(isset($THROTTLE_TIME_FEEDLIST)) {
            $this->throttleTime = $THROTTLE_TIME_FEEDLIST;
        }
        $this->throttleGroup = 'CancelFeedSubmissions';
        unset($this->options['MaxCount']);
        unset($this->options['NextToken']);
        $this->resetFeedStatuses();
    }
    
    /**
     * Returns the feed submission ID for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getFeedId($i = 0){
        if (is_numeric($i) && isset($this->feedList) && is_array($this->feedList)){
            return $this->feedList[$i]['FeedSubmissionId'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the feed type for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getFeedType($i = 0){
        if (is_numeric($i) && isset($this->feedList) && is_array($this->feedList)){
            return $this->feedList[$i]['FeedType'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date submitted for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The time will be in the ISO8601 date format.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getDateSubmitted($i = 0){
        if (is_numeric($i) && isset($this->feedList) && is_array($this->feedList)){
            return $this->feedList[$i]['SubmittedDate'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the feed processing status for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * See <i>setFeedStatuses</i> for a list of possible values.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getFeedStatus($i = 0){
        if (is_numeric($i) && isset($this->feedList) && is_array($this->feedList)){
            return $this->feedList[$i]['FeedProcessingStatus'];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the date that the specified entry started being processed.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The time will be in the ISO8601 date format.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getDateStarted($i = 0){
        if (is_numeric($i) && isset($this->feedList) && is_array($this->feedList) && isset($this->feedList[$i]['StartedProcessingDate'])){
            return $this->feedList[$i]['StartedProcessingDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the date that the specified entry finished being processed.
     *
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The time will be in the ISO8601 date format.
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return string|boolean single value, or <b>FALSE</b> if Non-numeric index
     */
    public function getDateCompleted($i = 0){
        if (is_numeric($i) && isset($this->feedList) && is_array($this->feedList) && isset($this->feedList[$i]['CompletedProcessingDate'])){
            return $this->feedList[$i]['CompletedProcessingDate'];
        } else {
            return false;
        }
    }

    /**
     * Returns the full info for the specified entry.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * The array returned will have the following fields:
     * <ul>
     * <li><b>FeedSubmissionId</b> - unique ID for the feed submission</li>
     * <li><b>FeedType</b> - feed type for the feed submission</li>
     * <li><b>SubmittedDate</b> - time in ISO8601 date format</li>
     * <li><b>FeedProcessingStatus</b> - see <i>setFeedStatuses</i> for a list of possible values</li>
     * <li><b>StartedProcessingDate</b> - time in ISO8601 date format</li>
     * <li><b>CompletedProcessingDate</b> - time in ISO8601 date format</li>
     * </ul>
     * @param int $i [optional] <p>List index to retrieve the value from. Defaults to 0.</p>
     * @return array|boolean array of values, or <b>FALSE</b> if Non-numeric index
     */
    public function getFeedInfo($i = 0){
        if (is_numeric($i) && isset($this->feedList) && is_array($this->feedList)){
            return $this->feedList[$i];
        } else {
            return false;
        }
    }
    
    /**
     * Returns the full list.
     * 
     * This method will return <b>FALSE</b> if the list has not yet been filled.
     * @return array|boolean multi-dimensional array, or <b>FALSE</b> if list not filled yet
     */
    public function getFeedList(){
        if (isset($this->feedList)){
            return $this->feedList;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the feed count from either countFeeds or cancelFeeds.
     * 
     * This method will return <b>FALSE</b> if the count has not been set yet.
     * @return number|boolean number, or <b>FALSE</b> if count not set yet
     */
    public function getFeedCount(){
        if (isset($this->count)){
            return $this->count;
        } else {
            return false;
        }
    }
    
    /**
     * Iterator function
     * @return type
     */
    public function current(){
       return $this->feedList[$this->i]; 
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
        return isset($this->feedList[$this->i]);
    }
    
}
?>