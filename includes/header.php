<?php
/* $Id: header.php 5785 2012-12-29 04:47:42Z daintree $ */

// Titles and screen header
// Needs the file config.php loaded where the variables are defined for
//  $RootPath
//  $Title - should be defined in the page this file is included with

if (!headers_sent()){
	if ($StrictXHTML) {
		header('Content-type: application/xhtml+xml; charset=utf-8');
	} else {
		header('Content-type: text/html; charset=utf-8');
	}
}
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
		"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml"><head><title>' . $Title . '</title>
		<link rel="shortcut icon" href="' . $PathPrefix . 'favicon.ico" />
		<link rel="icon" href="' . $PathPrefix . 'favicon.ico" />';
if ($StrictXHTML) {
	echo '<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />';
} else {
	echo '<meta http-equiv="Content-Type" content="application/html; charset=utf-8" />';
}

echo'	<link href="' . $RootPath . '/css/'. $Theme .'.css" rel="stylesheet" type="text/css" />
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
	<script type="text/javascript" src ="' . $RootPath . '/javascripts/jquery.validate.min.js"></script>
	<script type="text/javascript" src ="' . $PathPrefix . '/javascripts/MiscFunctions.js"></script>
	';

/* Google Analytics tracking code only if not in test, as test distorts statistics*/
if($_SESSION['ShopMode']!='test'){
	echo "<script type='text/javascript'>

	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', '". $GoogleAnalyticsID ."']);
	  _gaq.push(['_trackPageview']);

	  (function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();
	</script>";
}

echo'</head>
<body>

<div id="header_block">
	<div id="shop_logo">
		<img src="' . $RootPath . '/css/webshop_logo.jpg">
	</div>
	<div id="shop_title">
		<h1>&nbsp;' . $_SESSION['ShopName'] . '</h1>
	</div>';


if (isset($_POST['CurrCode'])){
	$CurrCode = $_POST['CurrCode'];
}elseif (isset($_GET['CurrCode'])){
	$CurrCode = $_GET['CurrCode'];
}
if (isset($CurrCode) AND $CurrCode!=$_SESSION['CustomerDetails']['currcode']){
	$_SESSION['CustomerDetails']['currcode'] = $CurrCode;
	// change as well the decimal places to show
	$SQL = "SELECT decimalplaces FROM currencies WHERE currabrev ='" . $CurrCode . "'";
	$result = DB_query($SQL,$db);
	if (DB_num_rows($result)==1){
		$NewCurrencyRow = DB_fetch_array($result);
		$_SESSION['CustomerDetails']['currdecimalplaces'] = $NewCurrencyRow['decimalplaces'];
	}
	update_currency_prices($CurrCode);
}

$Errors= array();
$CarriageReturnOrLineFeed = array("\r\n",'\r\n',"\r","\n");

if (isset($_POST['Login']) AND (!isset($_SESSION['LoggedIn']) OR $_SESSION['LoggedIn']==false)){
	//This could be run either from the header form or from the Checkout form - the checkout form now has two possible login forms
	include('includes/Login.php');
}

if (isset($_POST['AddToCart'])){ //as it could be from the ItemDetails.php page
	$_GET['AddToCart'] = $_POST['StockID'];
}

/*Process any cart additions */
if (isset($_GET['AddToCart'])){

	$_GET['Page'] = 'ShoppingCart'; //display the shopping cart with the new item in it
	if (isset($_SESSION['ShoppingCart'][$_GET['AddToCart']])){
		if (isset($_POST['Quantity'])){  //as it could be from the ItemDetails.php script
			$_SESSION['ShoppingCart'][$_GET['AddToCart']]->Quantity += filter_number_format($_POST['Quantity']);
		} else {
			//Just increment the quantity if the item is already in the shopping cart
			$_SESSION['ShoppingCart'][$_GET['AddToCart']]->Quantity++;
		}
	} else {
		$SQL = "SELECT stockid,
						description,
						longdescription,
						taxcatid,
						discountcategory,
						decimalplaces,
						mbflag,
						grossweight,
						volume
				FROM stockmaster
				WHERE stockmaster.stockid ='" . $_GET['AddToCart'] . "'";
		$result = DB_query($SQL,$db);
		if (DB_num_rows($result)==1){
			$NewItemRow = DB_fetch_array($result);
			$Description = $NewItemRow['description'];
			//need to get description translation (if any) and price grossed up for tax
			$TranslatedDescription = get_item_description_translation($_GET['AddToCart']);
			if ($TranslatedDescription!=false) {
				$Description = $TranslatedDescription;
			}
			$Price = GetPrice($_GET['AddToCart'],
								$_SESSION['ShopDebtorNo'],
								$_SESSION['ShopBranchCode'],
								$_SESSION['CustomerDetails']['currcode']);

			$Discount = GetDiscount($NewItemRow['discountcategory'],1, $db);
			$Price = $Price * (1- $Discount);
			if (isset($_POST['Quantity'])){  //as it could be from the ItemDetails.php script
				$Quantity = filter_number_format($_POST['Quantity']);
			} else {
				$Quantity =1;
			}
			$_SESSION['ShoppingCart'][$_GET['AddToCart']] = new CartItem($_GET['AddToCart'],
																			$Description,
																			$NewItemRow['longdescription'],
																			$Price,
																			$Quantity,
																			$NewItemRow['decimalplaces'],
																			$NewItemRow['taxcatid'],
																			$NewItemRow['discountcategory'],
																			$NewItemRow['grossweight'],
																			$NewItemRow['volume'],
																			$NewItemRow['mbflag'],
																			$Discount);

		} // no rows returned from query to get the item to add details
	} // its a new item not already in the shopping cart
} // end of adding an item to the shopping cart

if (isset($_GET['Delete'])) {
	unset($_SESSION['ShoppingCart'][$_GET['Delete']]);
	$_GET['Page']='ShoppingCart';
}
include('includes/RecalculateCartTotals.php');

echo '<div id="cart_summary" title="' . _('Click to show the detail of what is in the shopping cart') . '">
		<form id="CartSummaryForm" method="post" action="' . $RootPath . '/index.php">
		<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
		<table class="cart_summary">
				<tr>
					<th colspan="3">' . _('Cart Summary') . '</th>
				</tr>
				<tr>
					<td>' . ('Item count') . ':&nbsp;&nbsp;' . locale_number_format($CountItems,0) . '</td>
					<td>' . ('Total Cost') . '&nbsp;';
if (!isset($_SESSION['LoggedIn']) OR $_SESSION['LoggedIn']==false){ //if not logged in then allow the user to change her currency
	echo '<select id="Currency" class="cart_summary_currency" name="CurrCode">';
	$CurrenciesResult = DB_query("SELECT currabrev FROM currencies WHERE webcart=1",$db);
	while ($CurrRow = DB_fetch_array($CurrenciesResult)){
		echo '<option ';
		if ($_SESSION['CustomerDetails']['currcode']==$CurrRow['currabrev']){
			echo 'selected="selected" ';
		}
		echo 'value="' . $CurrRow['currabrev'] . '">' . $CurrRow['currabrev'] . '</option>';
	}
	echo '</select>';
} else { // if logged in then stuck with the currency of the account
	echo $_SESSION['CustomerDetails']['currcode'];
}

echo '</td>
	<td class="number">' . locale_number_format($TotalCartValue,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</td>
	</tr>
	</form>
	</table>
	</div>
			</div>';//end header_block
echo '<div class="navbar">
		<div class="top-links">
			<ul>
			<li><a href="index.php">' . _('Home') . '</a></li>
			<li><a href="index.php?Page=ShoppingCart">' . _('View Order') . '</a></li>
			<li><a href="Checkout.php">' . _('Checkout') . '</a></li>';

if (isset($_SESSION['LoggedIn']) AND $_SESSION['LoggedIn']==true){ // need to get the user details for modification
	echo '<li class="header_login">' . _('Logged in as') . ' ' . $_SESSION['UsersRealName'] . '</li>
		<li><a href="Register.php">' . _('Update Account Details') . '</a></li>
		<li><a href="index.php?LoggOff=1" onclick="return confirm(\''._('Are you sure you wish to logout?').'\');">' . _('Logout') . '</a>';
} else {
	echo '<li><a href="Register.php">' . _('Register') . '</a></li>
		<li class="header_login"><form id="LoginForm" method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">
			<input type="hidden" name="FormID"
				value="' . $_SESSION['FormID'] . '" />
			<input type="email" placeholder="' . _('Enter Email') . '" class="required username ' . (in_array('UserEmail',$Errors) ?  'error' : '' ) . '" required="required" name="UserEmail" size="20" maxlength="30" value="" />
			<input type="password" placeholder="' . _('Password') . '" required="required" class="required password ' . (in_array('Password',$Errors) ?  'error' : '' ) . '" name="Password" size="15" maxlength="15" />
			<input class="button" type="submit" name="Login" value="' . _('Login') . '" />
		</form>';
}
echo '			</li>
			</ul>
		</div>';
echo '<div id="search_description">
		<form id="SearchForm" method="post" action="' . $RootPath . '/index.php">
		<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />

	<img src="css/button-search.png">
	<input type="search" placeholder="' . _('Search') . '" name="SearchDescription" value="" onclick="this.value = \'\';" onkeydown="this.style.color = \'#000000\';" />
	</form>
	</div>
</div>';//end header_block

?>
