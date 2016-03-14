# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## 1.2.0 - 2016-03-10
### Added
- The store name can now be omitted when initiating objects if there is only one store is set in the config file.
- Added support for four relatively-new fields returned for Orders: `CbaDisplayableShippingLabel`, `ShippedByAmazonTFM`, `TFMShipmentStatus`, and `OrderType`.
- Each store in the config file can have its own `serviceUrl`, which will override the normal service URL.

### Changed
- Log messages now display time using the 24 hour format, rather than 12 hour.

### Fixed
- Inventory lists no longer cause an error if detailed information is not given.
- Logging function no longer gives an error when PHP is in strict mode.
- Removed bad include paths from NetBeans project settings.
- Product class no longer gives an error when PHP is in strict mode.
- Product Info class now uses the correct identifier parameters when fetching categories.
- Product Info class now correctly gets child relationship data.

### Removed
- Removed the obsolete `checkResponse` method from the Feed class.

## 1.1.0 - 2014-05-06
### Added
- Raw responses are stored for debugging purposes and can be accessed with getLastResponse() or getRawResponses()
- Created a folder for example scripts and added some examples to the readme
- Updated the Orders API to the 2013-09-01 version, which adds Earliest/Latest Ship Date and Delivery Date to returned order data

### Changed
- Feeds now allow for direct string input rather than relying entirely on files
- Amazon-defined constants, such as API version numbers and throttle times, have been moved to a separate file since users shouldn't have to worry about them. Users who already have a config file should redo them.

### Fixed
- HTTP 100 Continue responses are properly handled

## 1.0.0 - 2014-02-12
### Added
- Core class
- Classes for eight APIs: Feeds, Fulfillment Inbound, Fulfillment Inventory, Fulfillment Outbound, Orders, Products, Reports, and Sellers
