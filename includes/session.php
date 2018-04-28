<?php
require($PathPrefix . 'config.php');
require_once('includes/DefineCartItemClass.php');
include('includes/Functions.php'); 
if (!isset($RootPath)){
	$RootPath = dirname(htmlspecialchars($_SERVER['PHP_SELF']));
	if ($RootPath == '/' OR $RootPath == "\\") {
		$RootPath = '';
	}
}

if (isset($SessionSavePath)){
	session_save_path($SessionSavePath);
}
ini_set('session.gc_maxlifetime',$SessionLifeTime);

if( !ini_get('safe_mode') ){
	set_time_limit($MaximumExecutionTime);
	ini_set('max_execution_time',$MaximumExecutionTime);
}
session_write_close(); //in case a previous session is not closed
session_name('webERPStoreSESSID');
session_start();

if (isset($_GET['LoggOff'])){
	ResetForNewOrder($LogOff=true);
}

if (!isset($_SESSION['SessionName'])){
	session_name(CreateRandomHash(15));
	$_SESSION['SessionName'] = session_name();
	$_SESSION['FormID'] = sha1(uniqid(mt_rand(), true));
}

require('includes/DatabaseFunctions.php'); //had to go with a local copy due to error messages/logging in webERP file
require($PathPrefix . 'includes/DateFunctions.inc');
include($PathPrefix . 'includes/LanguageSetup.php');

/*Sanitise $_POST and $_GET data */
foreach ($_POST as $PostVariableName => $PostVariableValue) {
	if (gettype($PostVariableValue) != 'array') {
		if(get_magic_quotes_gpc()) {
			$_POST['name'] = stripslashes($_POST['name']);
		}
		$_POST[$PostVariableName] = DB_escape_string($PostVariableValue);
	} else {
		foreach ($PostVariableValue as $PostArrayKey => $PostArrayValue) {
			if(get_magic_quotes_gpc()) {
				$PostVariableValue[$PostArrayKey] = stripslashes($value[$PostArrayKey]);
			}
			$PostVariableValue[$PostArrayKey] = DB_escape_string($PostArrayValue);
		}
	}
}

/* iterate through all elements of the $_GET array and DB_escape_string them
to limit possibility for SQL injection attacks and cross scripting attacks
*/
foreach ($_GET as $GetKey => $GetValue) {
	if (gettype($GetValue) != 'array') {
		$_GET[$GetKey] = DB_escape_string($GetValue);
	}
}

if (!isset($_SESSION['CompanyDefaultsLoaded'])) {

	//echo '<Br />LOADED NEW DEFAULTS';
	
	$sql = "SELECT confname, confvalue FROM config";
	$ErrMsg = _('Could not get the configuration parameters from the database because');
	$ConfigResult = DB_query($sql,$db,$ErrMsg);
	while( $myrow = DB_fetch_array($ConfigResult) ) {
		if (is_numeric($myrow['confvalue']) AND $myrow['confname']!='DefaultPriceList' AND $myrow['confname']!='VersionNumber'){
			//the variable name is given by $myrow[0]
			$_SESSION[$myrow['confname']] = (double) $myrow['confvalue'];
		} else {
			$_SESSION[$myrow['confname']] =  $myrow['confvalue'];
		}
	} //end loop through all config variables
	
	DB_free_result($ConfigResult); // no longer needed

	$sql=	"SELECT	coyname,
					gstno,
					regoffice1,
					regoffice2,
					regoffice3,
					regoffice4,
					regoffice5,
					regoffice6,
					telephone,
					fax,
					email,
					currencydefault,
					debtorsact,
					pytdiscountact,
					creditorsact,
					payrollact,
					grnact,
					exchangediffact,
					purchasesexchangediffact,
					retainedearnings,
					freightact,
					gllink_debtors,
					gllink_creditors,
					gllink_stock,
					decimalplaces
				FROM companies
				INNER JOIN currencies ON companies.currencydefault=currencies.currabrev
				WHERE coycode=1";

	$ErrMsg = _('An error occurred accessing the database to retrieve the company information');
	$ReadCoyResult = DB_query($sql,$db,$ErrMsg);

	if (DB_num_rows($ReadCoyResult)==0) {
		echo '<br /><b>';
		prnMsg( _('The company record has not yet been set up') . '</b><br />' . _('From the system setup tab select company maintenance to enter the company information and system preferences'),'error',_('CRITICAL PROBLEM'));
		exit;
	} else {
		$_SESSION['CompanyRecord'] = DB_fetch_array($ReadCoyResult);
	}
	if (isset($ShopDebtorNo) AND $ShopDebtorNo!='' AND isset($ShopBranchCode) AND $ShopBranchCode!='') { //$ShopDebtorNo set in includes/config.php is not empty
		//then use $ShopDebtorNo for this shop - this allows multiple webSHOPs for a single webERP installation
		$_SESSION['ShopDebtorNo']=$ShopDebtorNo;
		$_SESSION['ShopBranchCode']=$ShopBranchCode;
	}
	include('includes/GetCustomerDetails.php'); // also used when a customer logs in

	if (isset($ShopName) AND $ShopName!='') { //$ShopName set in includes/config.php is not empty
		//then use $ShopName for this shop - this allows multiple webSHOPs for a single webERP installation
		$_SESSION['ShopName'] = $ShopName;
	}
	if (isset($ShopAboutUs) AND $ShopAboutUs!='') { //$ShopAboutUs set in includes/config.php is not empty
		//then use $ShopAboutUs for this shop - this allows multiple webSHOPs for a single webERP installation
		$_SESSION['ShopAboutUs'] = $ShopAboutUs;
	}
	if (isset($ShopFreightPolicy) AND $ShopFreightPolicy!='') { //$ShopFreightPolicy set in includes/config.php is not empty
		//then use $ShopName for this shop - this allows multiple webSHOPs for a single webERP installation
		$_SESSION['ShopFreightPolicy'] = $ShopFreightPolicy;
	}
	if (isset($ShopContactUs) AND $ShopContactUs!='') { //$ShopContactUs set in includes/config.php is not empty
		//then use $ShopContactUs for this shop - this allows multiple webSHOPs for a single webERP installation
		$_SESSION['ShopContactUs'] = $ShopContactUs;
	}
	
	
	$_SESSION['CompanyDefaultsLoaded'] = true; //so we don't do this with every page
}

if (!isset($_SESSION['ShoppingCart'])){
	$_SESSION['ShoppingCart']=array(); //of  CartItem objects from DefineCartItemClass.php above
}

//set up the PaymentMethods array based on shop config paramteres
$PaymentMethods = array();
if ($_SESSION['ShopAllowPayPal'] == '1') {
	$PaymentMethods['PayPal']=array('MethodName'=>_('Pay Pal'), 'Surcharge'=>$_SESSION['ShopPayPalSurcharge']);
}
if ($_SESSION['ShopAllowCreditCards']==1){
	$PaymentMethods[$_SESSION['ShopCreditCardGateway']]=array('MethodName'=>_('Credit Card'), 'Surcharge'=>$_SESSION['ShopCreditCardSurcharge']);
}
if ($_SESSION['ShopAllowBankTransfer'] == '1') {
	$PaymentMethods['BankTransfer']=array('MethodName'=>_('Bank Transfer'), 'Surcharge'=>$_SESSION['ShopBankTransferSurcharge']);
}

if ($_SESSION['CustomerDetails']['creditcustomer']==true) { //set up additional system CreditAccount PaymentMethod
	$PaymentMethods['CreditAccount']['MethodName'] = 'Credit Account';
	$PaymentMethods['CreditAccount']['Surcharge']= 0;
}
if  (sizeof($_POST) > 0) {
	/*Security check to ensure that the form submitted is originally sourced from webERP with the FormID = $_SESSION['FormID'] - which is set before the first login*/
	if (!isset($_POST['FormID']) OR ($_POST['FormID'] != $_SESSION['FormID'])) {
		$Title = _('Error in form verification');
		include('includes/header.php');
		echo '<br />
			<br />';
		prnMsg(_('This form was not submitted with a correct ID') , 'error');
		include('includes/footer.php');
		exit;
	}
}

function CreateRandomHash($Length){
	$Characters = 'ABCDEFGHIJKLMOPQRSTUVXWYZ0123456789';
	$SizeofCharArray = strlen($Characters);
	$SizeofCharArray--;
	
	$Hash='';
	for($i=1;$i<=$Length;$i++){
		$Position = rand(0,$SizeofCharArray);
		$Hash .= substr($Characters,$Position,1);
	}
	
	return $Hash;
}
?>
