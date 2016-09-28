<?php
/**
 * Copyright 2016 CPI Group, LLC
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
 * Class AmazonMWSConfig
 *
 * @Author Steve Childs (stevechilds76 at gmail.com)
 *
 * This class handles the configuration of for the AmazonMWS library. For examples of usage see the file in the examples
 *
 */
class AmazonMWSConfig {

    const DEFAULT_ENDPOINT = 'https://mws.amazonservices.com/';

    /** @var array the array of store data  */
    private $stores = [];
    /** @var  string - Path to logging file, if it doesn't exist when run, its created */
    private $logFile;
    /** @var  callable */
    private $logCallback;
    /** @var  boolean */
    private $loggingDisabled;

    /**
     * AmazonMWSConfig constructor.
     *
     * If a string is passed in, then it defaults to reading the variables out of the file, as in the original
     * configuration handling.
     *
     * @param null|string $configFN
     * @throws Exception
     */
    public function __construct($configFN = null) {
        if (empty($configFN) === false) {

            // Set Defaults
            $this->logFile = '';

            // Yes I know this is a change from the previous release which defaulted to always logging,
            // but to better handle log files not existing, this is required as validation is performed when logging
            // is enabled
            $this->loggingDisabled = true;

            $this->logCallback = null;
            $this->stores = array();
            $this->endpoint = self::DEFAULT_ENDPOINT;

            if ($configFN instanceof self) {
                // Config can also be another instance of this class! If so, simply grab the config from it.
                $this->_getConfigFrom($configFN,true);

            } elseif (is_array($configFN)) {
                // Get the config out of the array instead.
                if (array_key_exists('stores',$configFN) === false OR empty($configFN['stores'])) {
                    throw new Exception("Config array does not contain any store data!");
                }

                if ((array_key_exists('AMAZON_SERVICE_URL',$configFN)) AND (empty($configFN['AMAZON_SERVICE_URL']) === false)) {
                    $this->setEndPoint($configFN['AMAZON_SERVICE_URL']);
                }

                // Add the stores to the internal array.
                foreach ($configFN['stores'] as $storeName => $rec) {
                    $this->addStore($storeName,$rec);
                }

                if ((array_key_exists('logpath',$configFN)) AND (empty($configFN['logpath']) === false)) {
                    $this->setLogFile($configFN['logpath']);
                }

                if ((array_key_exists('logfunction',$configFN)) AND (empty($configFN['logfunction']) === false)) {
                    $this->setLogCallback($configFN['logfunction']);
                }

                if (array_key_exists('muteLog',$configFN)) {
                    $this->setLoggingDisabled($configFN['muteLog']);
                }
            } else {
                // Presume its a string (filename)
                if (file_exists($configFN) AND is_readable($configFN)){
                    include $configFN;
                } else {
                    throw new Exception("Config file does not exist or cannot be read! ($configFN)");
                }

                if (empty($store)) {
                    throw new Exception("Config file does not contain any store data! ($configFN)");
                }

                if (empty($AMAZON_SERVICE_URL) === false) {
                    $this->setEndPoint($AMAZON_SERVICE_URL);
                }

                // Add the stores to the internal array.
                foreach ($store as $storeName => $rec) {
                    $this->addStore($storeName,$rec);
                }

                if (isset($logpath)) {
                    $this->setLogFile($logpath);
                }

                if (isset($logfunction)) {
                    $this->setLogCallback($logfunction);
                }

                if (isset($muteLog)) {
                    $this->setLoggingDisabled($muteLog);
                }
            }
        } else {
            // No Config is also valid if you want to set it up manually.
        }
    }

    /**
     * Copies the config out of the passed source object, optionally copying the store data over.
     *
     * @param $source
     * @param bool $copyStoreData
     */
    protected function _getConfigFrom($source,$copyStoreData = false) {
        // Copy the config over and yes I purposefully didn't call the setters, all the property values should
        // be valid as they're validated when set.
        $this->endpoint = $source->endpoint;
        $this->logCallback = $source->logCallback;
        $this->logFile = $source->logFile;
        $this->loggingDisabled = $source->loggingDisabled;

        if ($copyStoreData) {
            $this->stores = array_merge($source->stores,array()); // Ensure we get a duplicate of the array, not a reference.
        }
    }

    /// Getter / Setters

    /**
     * @return mixed
     */
    public function getStores()
    {
        return $this->stores;
    }

    /**
     * Does this store exists within the configuration?
     *
     * @param $storeName
     * @return bool
     */
    public function storeExists($storeName) {
        return array_key_exists($storeName,$this->stores);
    }

    /**
     * Adds a new store to the Array
     *
     * @param $storename
     * @param array $storeData
     */
    public function addStore($storename,array $storeData)
    {
        $this->stores[$storename] = $storeData;
    }

    /**
     * Returns a config class to pass into the constructor of a library call. This enables you to have a master configuration
     * object and then only pass the config for a particular store into the Amazon calls.
     *
     * @param $aStoreName
     * @return AmazonMWSConfig
     * @throws Exception
     */
    public function getConfigFor($aStoreName) {
        /** @var AmazonMWSConfig $result */

        if ($this->storeExists($aStoreName) === false) {
            throw new Exception("The requested store does not exist!");
        }

        $result = new self;
        $result->_getConfigFrom($this);
        $result->addStore($aStoreName,$this->stores[$aStoreName]);

        return $result;
     }

    /**
     * @return string
     */
    public function getLogFile()
    {
        return $this->logFile;
    }

    /**
     * @param string $logFile
     */
    public function setLogFile($logFile)
    {
        $this->logFile = $logFile;
        // If we set a log file, assume we want to enable logging!
        $this->setLoggingDisabled(false);
    }

    /**
     * @return boolean
     */
    public function isLoggingDisabled()
    {
        return $this->loggingDisabled;
    }

    /**
     * Ok, the point at which the logging is enabled, we do some checks. A Blank filename is permitted if a callback
     * is configured, but must be configured prior to logging being enabled.
     *
     * @param boolean $loggingDisabled
     */
    public function setLoggingDisabled($loggingDisabled)
    {
        // Cast it to a bool
        $bValue = (bool) $loggingDisabled;

        if ($bValue === false) {
            // Ok, we don't want to disable logging, inwhich case we need to ensure we can write to the file.
            if ((empty($this->logFile)) AND (empty($this->logCallback))) {
                $this->loggingDisabled = true;
                return;
            }

            if (empty($this->logCallback) === false) {
                // The callback is validated upon setting, so if its not empty, it must be valid.
                $this->loggingDisabled = false;
                return;
            }

            if (is_dir($this->logFile)) {
                // doh!
                $this->loggingDisabled = true;
                return;
            }

            /** If we get here, then we have configured a log file... **/
            if ((empty($this->logFile) === false) AND (file_exists($this->logFile) === false)) {
                // Ok, file doesn't exist, try and create it.
                $hFile = @fopen($this->logFile,'a+b');
                if ($hFile !== false) {
                    // File created ok, close it.
                    fclose($hFile);
                }
            }

            if ((file_exists($this->logFile) === false) OR (is_writable($this->logFile) === false)){
                // Ok, either we have no logfile or we do, but it can't be written to.
                // We won't die, but we'll simply disable logging.
                $this->loggingDisabled = true;
            } else {
                $this->loggingDisabled = false;
            }
        }
        else {
            // We don't care if logging is disabled, no checks to do!
            $this->loggingDisabled = true;
        }
    }

    /**
     * Sets the endpoint and ensures we have a trailing path separator.
     * @param string $endPoint
     */
    public function setEndPoint($endPoint) {
        $this->endpoint = rtrim($endPoint, '/') . '/';
    }

    public function getEndPoint() {
        return $this->endpoint;
    }

    /**
     * Returns the requested store configuration, or a blank array if the key is invalid.
     *
     * @param $aStoreName
     * @return array|mixed
     */
    public function getStore($aStoreName) {
        if (array_key_exists($aStoreName,$this->stores)) {
            return $this->stores[$aStoreName];
        }

        // Invalid key, simply return an empty array
        return [];
    }

    /**
     * Returns the length of the stores array
     *
     * @return int
     */
    public function getStoreCount() {
        return count($this->stores);
    }

    /**
     * @return callable
     */
    public function getLogCallback()
    {
        return $this->logCallback;
    }

    /**
     * @param callable $logCallback
     */
    public function setLogCallback($logCallback)
    {
        $this->logCallback = null;
        if (empty($logCallback) === false AND is_callable($logCallback)) {
            $this->logCallback = $logCallback;
        }
    }

    /**
     * Gets the marketplace for the store from the configuration data
     *
     * @param $storeName
     * @return mixed|string
     */
    public function getStoreMarketPlace($storeName) {
        $store = $this->getStore($storeName);
        if(array_key_exists('marketplaceId', $store)){
            return $store['marketplaceId'];
        }
        return '';
    }

}