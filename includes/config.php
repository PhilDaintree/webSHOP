<?php
$PathPrefix = '../webERP/'; //path to main webERP installation

$DatabaseName = 'weberpdemo';

$Theme = 'default';
/*The StockID code for the Freight charge */
$FreightStockID ='FREIGHT';
/* If you wish to use Google Analytics to monitor the traffic to your shop you need to register with google analytics to get a code */
$GoogleAnalyticsID = 'UA-50867101-1';
$GoogleAnalyticsDomain = 'yourdomain.com';

$AusPostAPIKey = '28744ed5982391881611cca6cf5c2409'; //this is the test key need your own registered key for Aus Post if you are using the AusPost API to get freight quotes

/* Choosing a root sales category will allow the shop to only display a subset of products you sell - maybe multiple webSHOPs for a single webERP installation
*/
$RootSalesCategory ='';

/* Choosing a ShopDebtorNo and ShopBranchCode will use these as the default webSHOP customer in preference to the entries made in the webERP interface
 both a ShopDebtorNo and a ShopBranchCode must be !='' for this config to over-ride webERP - this allows for multiple webSHOPs per webERP installation
*/
$ShopDebtorNo='';
$ShopBranchCode='';
$ShopName='';
$ShopAboutUs='';
$ShopContactUs='';
$ShopTermsAndConditions='';

$StrictXHTML=False;
error_reporting (E_ALL & ~E_NOTICE);
//error_reporting (-1);
$debug=0;

?>