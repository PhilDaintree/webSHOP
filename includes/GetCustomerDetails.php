<?php

/*Get the Customer default details */
$sql = "SELECT name,
				salestype,
				currcode,
				address1,
				address2,
				address3,
				address4,
				address5,
				address6,
				language_id,
				taxref,
				typeid,
				creditlimit,
				area,
				salesman,
				holdreason,
				paymentterms,
				defaultlocation,
				taxprovinceid,
				taxgroupid,
				defaultshipvia,
				decimalplaces as currdecimalplaces,
				rate,
				contactname,
				braddress1,
				braddress2,
				braddress3,
				braddress4,
				braddress5,
				braddress6,
				phoneno,
				daysbeforedue,
				dayinfollowingmonth,
				deladd5 as from_postal_code,
				deladd6 as dispatch_country
		FROM debtorsmaster INNER JOIN custbranch
		ON debtorsmaster.debtorno=custbranch.debtorno
		INNER JOIN locations ON custbranch.defaultlocation=locations.loccode
		INNER JOIN currencies ON debtorsmaster.currcode=currencies.currabrev
		INNER JOIN paymentterms ON debtorsmaster.paymentterms=paymentterms.termsindicator
		WHERE debtorsmaster.debtorno='" . $_SESSION['ShopDebtorNo'] . "'
		AND custbranch.branchcode='" . $_SESSION['ShopBranchCode'] . "'";
$ErrMsg = _('An error occurred accessing the default customer configuration');
$ReadCustomerDefaultsResult = DB_query($sql,$ErrMsg);

if (DB_num_rows($ReadCustomerDefaultsResult)==0) {
	print_r($_SESSION);
	echo '<br /><b>';
	prnMsg( _('The customer defaults have not yet been set up') . '</b>
			<br />' . _('From the webERP system setup tab select shop maintenance to enter the default customer and customer branch information and other shop set up preferences'),'error',_('CRITICAL PROBLEM'));
	exit;
} else {
	$_SESSION['CustomerDetails'] = DB_fetch_array($ReadCustomerDefaultsResult);
	if ($_SESSION['CustomerDetails']['dayinfollowingmonth'] >= 1 OR $_SESSION['CustomerDetails']['daysbeforedue'] > 1){
		$_SESSION['CustomerDetails']['creditcustomer'] = true;
		$_SESSION['SelectedPaymentMethod'] = 'CreditAccount';
	} else {
		$_SESSION['CustomerDetails']['creditcustomer'] = false;
	}
}
$_SESSION['Language'] = $_SESSION['CustomerDetails']['language_id'];
include($PathPrefix . 'includes/LanguageSetup.php');

/*Now get the tax details for sales to this customer */

$SQL = "SELECT taxgrouptaxes.calculationorder,
					taxgrouptaxes.taxontax,
					taxauthrates.taxcatid,
					taxauthrates.taxrate
			FROM taxauthrates INNER JOIN taxgrouptaxes ON
				taxauthrates.taxauthority=taxgrouptaxes.taxauthid
			WHERE taxgrouptaxes.taxgroupid='" . $_SESSION['CustomerDetails']['taxgroupid'] . "'
			AND taxauthrates.dispatchtaxprovince='" . $_SESSION['CustomerDetails']['taxprovinceid'] . "'
			ORDER BY taxauthrates.taxcatid, taxgrouptaxes.calculationorder";

/*Figure out effective total tax rate for each tax category */
$TaxesResult = DB_query($SQL);
$_SESSION['TaxRates'] = array();
while ($TaxRow = DB_fetch_array($TaxesResult)){
	if (!isset($_SESSION['TaxRates'][$TaxRow['taxcatid']])){
		$_SESSION['TaxRates'][$TaxRow['taxcatid']] = 0;
	}
	if ($TaxRow['taxontax']==1) { //if tax on tax add taxrate x current total of taxes
		$_SESSION['TaxRates'][$TaxRow['taxcatid']] += ($TaxRow['taxrate']*$_SESSION['TaxRates'][$TaxRow['taxcatid']]);
	}
	$_SESSION['TaxRates'][$TaxRow['taxcatid']] += $TaxRow['taxrate'];  // add all taxes together for this taxgroup
}
//We should now have an array of $TaxRates that has the total effective tax rate with the index being the tax category id of the item
?>