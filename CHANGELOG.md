# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## 1.3.0 - 2016-08-03
### Added
- Travis support
- PHPUnit XML configuration file
- Change log file
- Contribution guidelines file
- Credits file
- Added support for MWS Auth Token, which can be set for each store in the config file.
- Added methods for getting the last response code, error message, and error response.
- Added support for the Merchant Fulfillment API with three new classes.
- Added support for the Recommendations API with one new class.
- Added support for the Subscriptions API with three new classes.
- Added support for the Finance API two new classes.
- Added four new Inbound classes relating to preorders, transport, and item prep instructions.
- The marketplace can now be specified in the Order List class, Fulfillment Order class, and all Product classes.
- New response fields in the Feed class: `StartedProcessingDate` and `CompletedProcessingDate`
- New response fields in the Order class: `IsBusinessOrder`, `PurchaseOrderNumber`, `IsPrime`, and `IsPremiumOrder`
- New response fields in the Order Item List class: `BuyerCustomizedInfo`, `PointsGranted`, and `PriceDesignation`
- Added a method for getting the order ID from the Order Item List class.
- New parameter in the Order List class: `TFMShipmentStatus`
- Added a method for getting the raw report data from the Report class.
- New response field in the Report List class: `AcknowledgedDate`
- New response fields in the Fulfillment Order class: `MarketplaceId`, `DeliveryWindow`, `FulfillmentAction`, `CODSettings`, `PerUnitPrice`, and `PerUnitTax`.
- New parameters in the Fulfillment Order Creator class: `MarketplaceId`, `FulfillmentAction`, `CODSettings`, and `DeliveryWindow`. The method for setting items now also has `PerUnitPrice` and `PerUnitTax`.
- New method in the Fulfillment Order Creator class for updating orders.
- New response fields in the Fulfillment Order List class: `MarketplaceId`, `DeliveryWindow`, `FulfillmentAction`, and `CODSettings`.
- New options in the Fulfillment Preview class: `IncludeCODFulfillmentPreview` and `IncludeDeliveryWindows`.
- New response fields in the Fulfillment Preview class: `ShippingSpeedCategory`, `IsFulfillable`, `IsCODCapable`, `MarketplaceId`, and `ScheduledDeliveryInfo`.
- New method in the Product Info class for getting lowest-priced offers.
- The Product class now keeps the identifier used to search for an item under the key `Request`.
- New methods in the Shipment class for setting parameters previously restricted to use of `usePlan`.
- New parameter in the Shipment class: `ShipmentName`. The method for setting items now also supports `PrepDetailsList` and `ReleaseDate` for each item.
- New response fields in the Shipment Item List class: `PrepDetailsList` and `ReleaseDate`
- New response field in the Shipment List class: `ConfirmedNeedByDate`
- New parameters in the Shipment Planner class: `ShipToCountryCode` and `ShipToCountrySubdivisionCode`. The method for setting items now also supports `ASIN` and `PrepDetailsList` for each item.
- New response field in the Shipment Planner class: `PrepDetailsList`
- Added support for new styles of token responses used by some classes.

### Changed
- Some tests that tried to load the normal configuration file now properly load test configuration.
- Corrected many small mistakes in documentation.
- The `setShowSalesChannel` method in the Report class now properly sets the parameter.
- The Service URL setting now works with or without a slash at the end.
- Changed all private methods and properties to protected.
- Updated the Composer file to allow for newer PHP versions.
- Fixed the spelling of `StateOrProvinceCode` throughout the Outbound and Inbound classes.
- The `genTime` method now supports Unix timestamps, though some methods that use `genTime` still do not.
- Updated the name of the `CompletedProcessingDate` field in the Report Request List class to `CompletedDate`.
- Deprecated `getDateProcessingCompleted` in favor of `getDateCompleted`.
- Updated the name of the `ShipServiceLevelCategory` field in the Order class to `ShipmentServiceLevelCategory`.
- Deprecated `getShipServiceLevelCategory` in favor of `getShipmentServiceLevelCategory`.
- Deprecated `setFulfillmentMethod` in the Fulfillment Order Creator class.
- Deprecated `setMethodFilter` in the Fulfillment Order List class.
- The Product Info class now properly gets all relationships.
- The Shipment class no longer sets the address when using `usePlan` and correctly sets other parameters.
- Fixed a loop caused by an empty response to actions that use tokens.

### Removed
- Removed all of the old leftover test XML files from the mock folder
- Removed old environment config lines from the test config file

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
