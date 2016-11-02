<?php
die('This is just an example and will not work without proper store credentials.');

$amazonConfig = array(
    'stores' =>
        array('myStoreName' =>
            array(
                'merchantId'    => 'AAAAAAAAAAAA',
                'marketplaceId' => 'AAAAAAAAAAAAAA',
                'keyId'         => 'AAAAAAAAAAAAAAAAAAAA',
                'secretKey'     => 'BABABABABABABABABABABABABABABABABABABABA',
                'serviceUrl'    => '',
                'MWSAuthToken'  => '',
            )
        ),
    'AMAZON_SERVICE_URL'        => 'https://mws-eu.amazonservices.com', // eu store
    'logpath'                   => __DIR__ . './logs/amazon_mws.log',
    'logfunction'               => '',
    'muteLog'                   => false
);

/**
 * This function will retrieve a list of all items with quantity that was adjusted within the past 24 hours.
 * The entire list of items is returned, with each item contained in an array.
 * Note that this does not relay whether or not the feed had any errors.
 * To get this information, the feed's results must be retrieved.
 */
function getAmazonFeedStatusA(){
    global $amazonConfig; // only for example purposes, please don't use globals!


    try {
        $amz=new AmazonFeedList($amazonConfig);
        $amz->setStore('myStoreName'); // Not strictly needed as there is only 1 store in the array and its automatically activated
        $amz->setTimeLimits('- 24 hours'); //limit time frame for feeds to any updated since the given time
        $amz->setFeedStatuses(array("_SUBMITTED_", "_IN_PROGRESS_", "_DONE_")); //exclude cancelled feeds
        $amz->fetchFeedSubmissions(); //this is what actually sends the request
        return $amz->getFeedList();
    } catch (Exception $ex) {
        echo 'There was a problem with the Amazon library. Error: '.$ex->getMessage();
    }
}

/**
 * As above but with an alternative method of creating the config object.
 */
function getAmazonFeedStatusB(){
    global $amazonConfig; // only for example purposes, please don't use globals!

    $configObject = new \AmazonMWSConfig($amazonConfig);

    try {
        // using the getConfigFor method creates another instance of AmazonMWSConfig containing just that store's data
        // If the method in getAmazonFeedStatusA() has more than 1 store setup in the array, they all are available to
        // the Amazon MWS library and you can switch between them using setStore(). However, should you want to
        // have clear seperation between the stores forwhatever reason, you can use getConfigFor to ensure that only
        // one store is available to the library. They're all still available in the configObject for later use,
        // calling getConfigFor does not affect the store list within the $configObject

        $amz=new AmazonFeedList($configObject->getConfigFor('myStoreName'));
        $amz->setTimeLimits('- 24 hours'); //limit time frame for feeds to any updated since the given time
        $amz->setFeedStatuses(array("_SUBMITTED_", "_IN_PROGRESS_", "_DONE_")); //exclude cancelled feeds
        $amz->fetchFeedSubmissions(); //this is what actually sends the request
        return $amz->getFeedList();
    } catch (Exception $ex) {
        echo 'There was a problem with the Amazon library. Error: '.$ex->getMessage();
    }
}?>
