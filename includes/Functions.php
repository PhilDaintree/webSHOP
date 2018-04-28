<?php
function display_item($StockID, $Description, $LongDescription, $TaxCatID, $DiscountCategory, $Quantity, $DecimalPlaces, $MBFlag, $UOM) {
	global $db;
	global $PathPrefix;
	global $RoothPath;

	//need to get description translation (if any) and price grossed up for tax
	$TranslatedDescription = get_item_description_translation($StockID);
	if ($TranslatedDescription!=false) {
		$Description = $TranslatedDescription;
	}
	$Price = GetPrice($StockID, $_SESSION['ShopDebtorNo'],$_SESSION['ShopBranchCode'],$_SESSION['CustomerDetails']['currcode']);
	if ($Price ==0){
		return '0';
	}

	$Discount = GetDiscount($DiscountCategory, $Quantity, $db);

	$GrossPrice = $Price * (1 - $Discount) * (1 + $_SESSION['TaxRates'][$TaxCatID]);

	$DisplayItemRowHTML = '<div class="prodbox">
							<div class="code_column">' .  $StockID . '</div>
							<div class="image_column">';

	if( isset($StockID) AND file_exists($PathPrefix . $_SESSION['part_pics_dir'] . '/' .$StockID.'.jpg') ) {
		$StockImgLink = '<img src="GetStockImage.php?automake=1&amp;textcolor=FFFFFF&amp;bgcolor=CCCCCC&amp;StockID=' .$StockID . '&amp;text=&amp;width=175&amp;height=175" title="' . _('Click for more information') . '" />';
		$DisplayItemRowHTML .= '<a href="ItemDetails.php?StockID=' . $StockID . '">' . $StockImgLink . '</a>';
 	} else {
		$DisplayItemRowHTML .= '<a href="ItemDetails.php?StockID=' . $StockID . '"><img src="css/no_image.png" height="175" width="175" title="' . _('Click for more information') . '" /></a>';
 	}

	$DisplayItemRowHTML .= '</div><div class="description_column">';
	if (mb_strlen($LongDescription) >3){
		$DisplayItemRowHTML .= ('<div class="info"><h2>' . $Description .'</h2> </div>');
	}
	$DisplayItemRowHTML .= '</div>';

	if ($_SESSION['ShopShowQOHColumn'] == 1){
		if ($MBFlag=='A' OR $MBFlag=='D' OR $MBFlag=='K') {
			$DisplayOnHand = _('N/A');
		} elseif ($Quantity <= 0) {
			$DisplayOnHand = _('Arriving Soon');
		} elseif ($Quantity > 20) {
			$DisplayOnHand = '20+';
		} else {
			$DisplayOnHand = locale_number_format($Quantity,$DecimalPlaces);
		}
		$DisplayItemRowHTML .= '<div class="qoh_column">
									<div class="qoh_column_label">' ._('Stock QTY') . ':&nbsp;</div>
									<div class="qoh_column_value">' . $DisplayOnHand . '</div>';
		if ($DisplayOnHand != _('Arriving Soon')){
			$DisplayItemRowHTML .= '<div class="price_column_label_excl">' . $UOM . '</div>';
		}
		$DisplayItemRowHTML .= '</div>';
	}

	$DisplayItemRowHTML .= '<div class="price_column">
								<div class="price_column_label">' . _('Price') . ' ' .  $_SESSION['CustomerDetails']['currcode'] . ':&nbsp;</div>
								<div class="price_column_value">' . locale_number_format($GrossPrice, $_SESSION['CustomerDetails']['currdecimalplaces']) . ' </div>
								<div class="price_column_label_excl">&nbsp;' . _('incl tax') . '</div>
							</div>
							<div class="row center"><a class="link_button" href="index.php?AddToCart=' . $StockID . '">' . _('Add to Order') . '</a></div>
								<a href="index.php?Page=ShoppingCart"><div class="view_order_label">' . _('View Order') . '</div></a>
							</div>';
// end-divs
	return $DisplayItemRowHTML;
} //end display_item function

function list_sales_categories($ParentCatID) {
	global $db;
	$SalesCatList ='';
	$SalesCategoriesResult = DB_query("SELECT salescatid
										FROM salescat
										WHERE parentcatid='" . $ParentCatID . "'
										AND active=1 ", $db);
	if (DB_num_rows($SalesCategoriesResult)>0){
		while ($SalesCatRow = DB_fetch_array($SalesCategoriesResult)){
			if ($ParentCatID =='' AND $SalesCatList=='') {
				$SalesCatList = $SalesCatRow['salescatid'];
			} else {
				$SalesCatList .= ',' .$SalesCatRow['salescatid'];
			}
			$SalesCatList .= list_sales_categories($SalesCatRow['salescatid']);
		}
	}
	return $SalesCatList;
}

function display_sub_categories ($ParentCatID, $HtmlString) {
	global $db;
	global $RootSalesCategory;

	$SalesCategoriesResult = DB_query("SELECT salescatid, salescatname
										FROM salescat
										WHERE parentcatid='" . $ParentCatID . "'
											AND active = 1 ", $db);
	if (DB_num_rows($SalesCategoriesResult)>0){
		if ($ParentCatID==$RootSalesCategory){
			$HtmlString .= '<ul class="dropdown dropdown-vertical">';
		} else {
			$HtmlString .= '<ul class="sublevel">';
		}
		while ($SaleCatRow = DB_fetch_array($SalesCategoriesResult)){
			$SQL = "SELECT salescattranslation
					FROM salescattranslations
					WHERE salescatid='" .$SaleCatRow['salescatid'] . "'
					AND language_id='" . $_SESSION['Language'] . "'";
			$SaleCatTranslationResult = DB_query($SQL,$db);
			if (DB_num_rows($SaleCatTranslationResult)>0){
				$SaleCatTranslationRow = DB_fetch_array($SaleCatTranslationResult);
				$HtmlString .= '<li><a class="sales_category" href="index.php?SalesCategory=' . urlencode($SaleCatRow['salescatid']) . '">' . $SaleCatTranslationRow['salescattranslation'] . '</a>';
			} else {
				$HtmlString .= '<li><a class="sales_category" href="index.php?SalesCategory=' . urlencode($SaleCatRow['salescatid']) . '">' . $SaleCatRow['salescatname'] . '</a>';
			}
			$HtmlString = display_sub_categories($SaleCatRow['salescatid'], $HtmlString);
			$HtmlString .= '</li>';
		}
		$HtmlString .= '</ul>';
	}
	return $HtmlString;
}

function ShowSalesCategoriesMenu() {

	global $RootSalesCategory;

	$MenuLinksHtml = display_sub_categories($RootSalesCategory,'');//recursive function to display through all levels of categories defined

	echo '<div id="menu_block"></div>
			<div id="content_block">
				<div id="content_inner">
					<div id="column_left">
						<div id="column_heading">' . _('Categories') . '</div>'
						 . $MenuLinksHtml .
						'</div>';
}


function get_sales_category_name ($SalesCategoryID) {

	global $db;

	$SaleCatTranslationResult = DB_query("SELECT salescattranslation
											FROM salescattranslations
											WHERE salescatid='" . $SalesCategoryID . "'
											AND language_id='" . $_SESSION['Language'] . "'",$db);
	if (DB_num_rows($SaleCatTranslationResult)>0){
		$SaleCatTranslationRow = DB_fetch_row($SaleCatTranslationResult);
		$SalesCategoryDescription =  $SaleCatTranslationRow[0];
	} else {
		$SaleCatResult = DB_query("SELECT salescatname FROM salescat WHERE salescatid='" . $SalesCategoryID . "' AND active = 1",$db);
		$SaleCatRow = DB_fetch_row($SaleCatResult);
		$SalesCategoryDescription =  $SaleCatRow[0];
	}
	return $SalesCategoryDescription;
}

function get_item_description_translation($StockID) {
	global $db;
	$TranslationResult = DB_query("SELECT descriptiontranslation FROM stockdescriptiontranslations WHERE stockid='" . $StockID . "' AND language_id='" . $_SESSION['Language'] ."'",$db);
	if (DB_num_rows($TranslationResult)>0){
		$TranslationRow = DB_fetch_row($TranslationResult);
		if ($TranslationROw[0]!='') {
			return $TranslationRow[0];
		} else {
			return false;
		}
	} else {
		return false;
	}
}

Function GetNextSequenceNo ($SequenceType){

	global $db;
/* SQL to get the next transaction number these are maintained in the table SysTypes - Transaction Types
Also updates the transaction number

10 sales invoice
11 sales credit note
12 sales receipt
etc
*
*/

	DB_query("LOCK TABLES systypes WRITE",$db);

	$SQL = "SELECT typeno FROM systypes WHERE typeid = '" . $SequenceType . "'";

	$ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ': <BR>' . _('The next transaction number could not be retrieved from the database because');
	$DbgMsg =  _('The following SQL to retrieve the transaction number was used');
	$GetTransNoResult = DB_query($SQL,$db,$ErrMsg,$DbgMsg);

	$myrow = DB_fetch_row($GetTransNoResult);

	$SQL = "UPDATE systypes SET typeno = '" . ($myrow[0] + 1) . "' WHERE typeid = '" . $SequenceType . "'";
	$ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ': ' . _('The transaction number could not be incremented');
	$DbgMsg =  _('The following SQL to increment the transaction number was used');
	$UpdTransNoResult = DB_query($SQL,$db,$ErrMsg,$DbgMsg);

	DB_query("UNLOCK TABLES",$db);

	return $myrow[0] + 1;
}

function EnsureGLEntriesBalance ($TransType, $TransTypeNo) {
	/*Ensures general ledger entries balance for a given transaction */
	global $db;

	$result = DB_query("SELECT SUM(amount)
						FROM gltrans
						WHERE type = '" . $TransType . "'
						AND typeno = '" . $TransTypeNo . "'",
						$db);
	$myrow = DB_fetch_row($result);
	$Difference = $myrow[0];
	if (abs($Difference)!=0){
		if (abs($Difference)>0.1){
			message_log(_('The general ledger entries created do not balance. See your system administrator'),'error');
		} else {
			$result = DB_query("SELECT counterindex,
										MAX(amount)
								FROM gltrans
								WHERE type = '" . $TransType . "'
								AND typeno = '" . $TransTypeNo . "'
								GROUP BY counterindex",
								$db);
			$myrow = DB_fetch_array($result);
			$TransToAmend = $myrow['counterindex'];
			$result = DB_query("UPDATE gltrans SET amount = amount - " . $Difference . "
								WHERE counterindex = '" . $TransToAmend . "'",
								$db);

		}
	}
}

function message_log ($Message, $Level) {
	if (!isset($_SESSION['MessageLog'])){
		$_SESSION['MessageLog'] = array();
	}
	$_SESSION['MessageLog'][] = new Message($Message, $Level);
}

function display_messages () {
	if (!isset($_SESSION['MessageLog'])){
		return;
	}

	foreach($_SESSION['MessageLog'] as $Msg) {
		echo '<div class="' . $Msg->Severity . '">' . $Msg->MessageText . '</div>';
	}
	unset($_SESSION['MessageLog']);
}

function pay_pal_request($MethodName, $NVPString, $User, $Password, $Signature) {
//Classic API - for PayPal Pro credit cards and also PayPal Express Checkout
	global $debug;

	if($_SESSION['ShopMode']=='test'){
		$API_Endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
	} else {
		$API_Endpoint = 'https://api-3t.paypal.com/nvp';
	}
	$Version = urlencode('93.0');

	// Set the curl parameters.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);

	// Set the API operation, version, and API signature in the request.
	$NVPRequest = 'METHOD='  . $MethodName .
				  '&USER=' . urlencode($User) .
				  '&PWD=' . urlencode($Password) .
				  '&SIGNATURE=' . urlencode($Signature) .
				  '&VERSION=' . $Version .
				  $NVPString;

	if ($debug==1) {
		message_log(_('Sending the PayPal Request') . '<br />' . $NVPRequest,'info');
	}

	// Set the request as a POST FIELD for curl.
	curl_setopt($ch, CURLOPT_POSTFIELDS, $NVPRequest);

	// Get response from the server.
	$HTTPResponse = curl_exec($ch);

	if(!$HTTPResponse) {
		message_log('PayPal ' . $MethodName . ' ' . _('failed with curl error:') . ' ' . curl_error($ch) . '(' . curl_errno($ch),'error');
		return 0;
	}

	// Extract the response details.
	$HTTPResponseArray = explode('&', $HTTPResponse);

	$ResponseArray = array();
	foreach ($HTTPResponseArray as $i => $value) {
		$TmpArray = explode('=', $value);
		if(sizeof($TmpArray) > 1) {
			$ResponseArray[$TmpArray[0]] = $TmpArray[1];
		}
	}
	if ($debug==1) {
		$Message ='';
		foreach ($ResponseArray as $Key=>$Response) {
			$Message .= '<br />' . $Key . ' = ' . urldecode($Response);
		}
		message_log($Message,'info');
	}
	if(sizeof($ResponseArray) == 0 OR !array_key_exists('ACK', $ResponseArray)) {
		message_log(_('Invalid PayPal response. Unable to pay using paypal'),'error');
		return 0;
	}

	return $ResponseArray;
}

function credit_card_type ($CreditCardNumber) {
	$card_type = '';
	$card_regexes = array(
	  "/^4\d{12}(\d\d\d){0,1}$/" => 'visa',
	  "/^5[12345]\d{14}$/"       => 'mastercard',
	  "/^3[47]\d{13}$/"          => 'amex'

	  /* ,
	  "/^6011\d{12}$/"           => 'discover',
	  "/^30[012345]\d{11}$/"     => 'diners',
	  "/^3[68]\d{12}$/"          => 'diners',
	  */
	);

	foreach ($card_regexes as $regex => $CardType) {
	   if (preg_match($regex, $CreditCardNumber)) {
		   return $CardType;
		   break;
	   }
	}
	return false;
}

function validate_credit_card_number($cc_number) {
	/* Validate; return value is card type if valid. */
	$card_type = '';
	$card_regexes = array(
	  "/^4\d{12}(\d\d\d){0,1}$/" => "visa",
	  "/^5[12345]\d{14}$/"       => "mastercard",
	  "/^3[47]\d{13}$/"          => "amex",
	  "/^6011\d{12}$/"           => "discover",
	  "/^30[012345]\d{11}$/"     => "diners",
	  "/^3[68]\d{12}$/"          => "diners",
	);

	foreach ($card_regexes as $regex => $type) {
	   if (preg_match($regex, $cc_number)) {
		   $card_type = $type;
		   break;
	   }
	}

	if (!$card_type) {
	   return false;
	}

	/*  mod 10 checksum algorithm  */
	$revcode = strrev($cc_number);
	$checksum = 0;

	for ($i = 0; $i < strlen($revcode); $i++) {
	   $current_num = intval($revcode[$i]);
	   if($i & 1) {  /* Odd  position */
		  $current_num *= 2;
	   }
	   /* Split digits and add. */
	   $checksum += $current_num % 10;

	   if ($current_num >  9) {
		   $checksum += 1;
	   }
	}

	if ($checksum % 10 == 0) {
	   return $card_type;
	} else {
	   return false;
	}
}

function ResetForNewOrder ($LogOff=false) {
	/* Reset session variables for new order */
	for ($i=0;$i<sizeof($_SESSION['ShoppingCart']);$i++) {
		$Empty = array_shift($_SESSION['ShoppingCart']);
	}
	unset($_SESSION['ShoppingCart']);
	unset($_SESSION['OrderPlaced']);
	unset($_SESSION['ConfirmedDeliveryAddress']);
	unset($_SESSION['SelectedPaymentMethod']);
	unset($_SESSION['Paid']);
	unset($_SESSION['TotalDue']);
	unset($_SESSION['SurchargeAmount']);
	if ($LogOff == true) {
		unset($_SESSION['LoggedIn']);
		unset($_SESSION['CustomerDetails']);
		unset($_SESSION['UsersRealName']);
		unset($_SESSION['CompanyDefaultsLoaded']);
	}
}

function InsertCustomerReceipt ($BankAccount,$TransactionID, $OrderNo) {
	global $db;
	DB_Txn_Begin($db);

	$CustomerReceiptNo = GetNextSequenceNo(12);
	$PeriodNo = GetPeriod(Date($_SESSION['DefaultDateFormat']),$db);

	$HeaderSQL = "INSERT INTO debtortrans (transno,
											type,
											debtorno,
											branchcode,
											trandate,
											inputdate,
											prd,
											reference,
											order_,
											rate,
											ovamount,
											invtext )
							VALUES ('". $CustomerReceiptNo  . "',
									'12',
									'" . $_SESSION['ShopDebtorNo'] . "',
									'" . $_SESSION['ShopBranchCode'] . "',
									'" .Date('Y-m-d H:i') . "',
									'" . Date('Y-m-d H:i') . "',
									'" . $PeriodNo . "',
									'" . $TransactionID ."',
									'". $OrderNo . "',
									'" . $_SESSION['CustomerDetails']['rate'] . "',
									'" . round(-$_SESSION['TotalDue'],2) . "',
									'" . _('web payment') . "')";
	$DbgMsg = _('The SQL that failed was');
	$ErrMsg = _('The customer receipt cannot be added because');
	$InsertQryResult = DB_query($HeaderSQL,$db,$ErrMsg,$DbgMsg);

	$SQL = "UPDATE debtorsmaster
				SET lastpaiddate = '" . Date('Y-m-d') . "',
				lastpaid='" . $_SESSION['TotalDue'] ."'
			WHERE debtorsmaster.debtorno='" . $_SESSION['ShopDebtorNo'] . "'";

	$DbgMsg = _('The SQL that failed to update the date of the last payment received was');
	$ErrMsg = _('Cannot update the customer record for the date of the last payment received because');
	$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg,true);

	/*now enter the BankTrans entry */
	//First get the currency and rate for the bank account
	$BankResult = DB_query("SELECT rate FROM bankaccounts INNER JOIN currencies ON bankaccounts.currcode=currencies.currabrev WHERE accountcode='" . $BankAccount . "'",$db);
	$BankRow = DB_fetch_array($BankResult);
	$FunctionalRate = $BankRow['rate'];

	$SQL="INSERT INTO banktrans (type,
								transno,
								bankact,
								ref,
								exrate,
								functionalexrate,
								transdate,
								banktranstype,
								amount,
								currcode)
		VALUES (12,
			'" . $CustomerReceiptNo . "',
			'" . $BankAccount . "',
			'" . _('web shop receipt') . ' ' . $_SESSION['ShopDebtorNo'] . ' ' . $TransactionID  . "',
			'" . $_SESSION['CustomerDetails']['rate'] / $FunctionalRate  . "',
			'" . $FunctionalRate . "',
			'" . Date('Y-m-d') . "',
			'" . $_SESSION['SelectedPaymentMethod'] . ' ' . _('web') . "',
			'" . $_SESSION['TotalDue'] . "',
			'" . $_SESSION['CustomerDetails']['currcode'] . "'
		)";
	$DbgMsg = _('The SQL that failed to insert the bank account transaction was');
	$ErrMsg = _('Cannot insert a bank transaction');
	$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg,true);


	// Insert GL entries too if integration enabled

	if ($_SESSION['CompanyRecord']['gllink_debtors']==1){ /* then enter GLTrans records for discount, bank and debtors */
		/* Bank account entry first */
		$Narrative = $_SESSION['ShopDebtorNo'] . ' ' . _('payment for order') . ' ' . $OrderNo . ' ' . _('Transaction ID') . ': ' . $TransactionID;
		$SQL="INSERT INTO gltrans (	type,
									typeno,
									trandate,
									periodno,
									account,
									narrative,
									amount)
				VALUES (12,
						'" . $CustomerReceiptNo . "',
						'" . Date('Y-m-d') . "',
						'" . $PeriodNo . "',
						'" . $BankAccount . "',
						'" . $Narrative . "',
						'" . $_SESSION['TotalDue'] /$_SESSION['CustomerDetails']['rate'] . "'
					)";
		$DbgMsg = _('The SQL that failed to insert the GL transaction fro the bank account debit was');
		$ErrMsg = _('Cannot insert a GL transaction for the bank account debit');
		$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg,true);

	/* Now Credit Debtors account with receipts + discounts */
		$SQL="INSERT INTO gltrans ( type,
									typeno,
									trandate,
									periodno,
									account,
									narrative,
									amount)
					VALUES (12,
							'" . $CustomerReceiptNo . "',
							'" . Date('Y-m-d') . "',
							'" . $PeriodNo . "',
							'". $_SESSION['CompanyRecord']['debtorsact'] . "',
							'" . $Narrative . "',
							'" . -($_SESSION['TotalDue'] /$_SESSION['CustomerDetails']['rate']). "' )";
		$DbgMsg = _('The SQL that failed to insert the GL transaction for the debtors account credit was');
		$ErrMsg = _('Cannot insert a GL transaction for the debtors account credit');
		$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg,true);
		EnsureGLEntriesBalance(12,$CustomerReceiptNo);
	} //end if there is GL work to be done - ie config is to link to GL

	DB_Txn_Commit($db);
}

function GetPrice ($StockID, $DebtorNo, $BranchCode, $CurrCode){
	global $db;
	$Price = 0;
	/*Search by branch and customer for a date specified price */
	$sql="SELECT prices.price
			FROM prices INNER JOIN debtorsmaster
			WHERE debtorsmaster.salestype=prices.typeabbrev
			AND debtorsmaster.debtorno='" . $DebtorNo . "'
			AND prices.stockid = '" . $StockID . "'
			AND prices.currabrev = '" . $CurrCode . "'
			AND prices.debtorno=debtorsmaster.debtorno
			AND prices.branchcode='" . $BranchCode . "'
			AND prices.startdate <='" . Date('Y-m-d') . "'
			AND prices.enddate >='" . Date('Y-m-d') . "'";

	$ErrMsg =  _('There is a problem in retrieving the pricing information for part') . ' ' . $StockID  . ' ' . _('and for Customer') . ' ' . $DebtorNo .  ' ' . _('the error message returned by the SQL server was');
	$result = DB_query($sql, $db,$ErrMsg);
	if (DB_num_rows($result)==0){
		/*Need to try same specific search but for a default price with a zero end date */
		$sql="SELECT prices.price,
					prices.startdate
				FROM prices,
					debtorsmaster
				WHERE debtorsmaster.salestype=prices.typeabbrev
				AND debtorsmaster.debtorno='" . $DebtorNo . "'
				AND prices.stockid = '" . $StockID . "'
				AND prices.currabrev = '" . $CurrCode . "'
				AND prices.debtorno=debtorsmaster.debtorno
				AND prices.branchcode='" . $BranchCode . "'
				AND prices.startdate <='" . Date('Y-m-d') . "'
				AND prices.enddate ='0000-00-00'
				ORDER BY prices.startdate DESC";

		$result = DB_query($sql, $db,$ErrMsg);

		if (DB_num_rows($result)==0){

			/* No result returned for customer and branch search try for just a customer match */
			$sql = "SELECT prices.price
					FROM prices,
					debtorsmaster
					WHERE debtorsmaster.salestype=prices.typeabbrev
					AND debtorsmaster.debtorno='" . $DebtorNo . "'
					AND prices.stockid = '" . $StockID . "'
					AND prices.currabrev = '" . $CurrCode . "'
					AND prices.debtorno=debtorsmaster.debtorno
					AND prices.branchcode=''
					AND prices.startdate <='" . Date('Y-m-d') . "'
					AND prices.enddate >='" . Date('Y-m-d') . "'";


			$result = DB_query($sql,$db,$ErrMsg);
			if (DB_num_rows($result)==0){
				//if no specific price between the dates maybe there is a default price with no end date specified
				$sql = "SELECT prices.price,
							   prices.startdate
						FROM prices,
							debtorsmaster
						WHERE debtorsmaster.salestype=prices.typeabbrev
						AND debtorsmaster.debtorno='" . $DebtorNo . "'
						AND prices.stockid = '" . $StockID . "'
						AND prices.currabrev = '" . $CurrCode . "'
						AND prices.debtorno=debtorsmaster.debtorno
						AND prices.branchcode=''
						AND prices.startdate <='" . Date('Y-m-d') . "'
						AND prices.enddate >='0000-00-00'
						ORDER BY prices.startdate DESC";

				$result = DB_query($sql,$db,$ErrMsg);

				if (DB_num_rows($result)==0){

					/*No special customer specific pricing use the customers normal price list but look for special limited time prices with specific end date*/
					$sql = "SELECT prices.price
							FROM prices,
							debtorsmaster
							WHERE debtorsmaster.salestype=prices.typeabbrev
							AND debtorsmaster.debtorno='" . $DebtorNo . "'
							AND prices.stockid = '" . $StockID . "'
							AND prices.debtorno=''
							AND prices.currabrev = '" . $CurrCode . "'
							AND prices.startdate <='" . Date('Y-m-d') . "'
							AND prices.enddate >='" . Date('Y-m-d') . "'";

					$result = DB_query($sql,$db,$ErrMsg);

					if (DB_num_rows($result)==0){
						/*No special customer specific pricing use the customers normal price list but look for default price with 0000-00-00 end date*/
						$sql = "SELECT prices.price,
									   prices.startdate
								FROM prices,
									debtorsmaster
								WHERE debtorsmaster.salestype=prices.typeabbrev
								AND debtorsmaster.debtorno='" . $DebtorNo . "'
								AND prices.stockid = '" . $StockID . "'
								AND prices.debtorno=''
								AND prices.currabrev = '" . $CurrCode . "'
								AND prices.startdate <='" . Date('Y-m-d') . "'
								AND prices.enddate ='0000-00-00'
								ORDER BY prices.startdate DESC";

						$result = DB_query($sql,$db,$ErrMsg);

						if (DB_num_rows($result)==0){

							/* Now use the default salestype/price list cos all else has failed */
							$sql="SELECT prices.price
									FROM prices
									WHERE prices.stockid = '" . $StockID . "'
									AND prices.currabrev = '" . $CurrCode . "'
									AND prices.typeabbrev='" . $_SESSION['DefaultPriceList'] . "'
									AND prices.debtorno=''
									AND prices.startdate <='" . Date('Y-m-d') . "'
									AND prices.enddate >='" . Date('Y-m-d') . "'";;

							$result = DB_query($sql, $db,$ErrMsg);

							if (DB_num_rows($result)==0){

								/* Now use the default salestype/price list cos all else has failed */
								$sql="SELECT prices.price,
											 prices.startdate
										FROM prices,
											debtorsmaster
										WHERE prices.stockid = '" . $StockID . "'
										AND prices.currabrev = '" . $CurrCode . "'
										AND debtorsmaster.debtorno='" . $DebtorNo . "'
										AND prices.typeabbrev='" . $_SESSION['DefaultPriceList'] . "'
										AND prices.debtorno=''
										AND prices.startdate <='" . Date('Y-m-d') . "'
										AND prices.enddate ='0000-00-00'
										ORDER BY prices.startdate DESC";

								$result = DB_query($sql, $db,$ErrMsg);

								if (DB_num_rows($result)==0){
									/*Not even a price set up in the default price list so return 0 */
									Return 0;
								}
							}
						}
					}
				}
			}
		}
	}

	if (DB_num_rows($result)!=0){
		/*There is a price from one of the above so return that */
		$myrow=DB_fetch_row($result);
		Return $myrow[0];
	} else {
		Return 0;
	}

}

function update_currency_prices ($CurrCode) {
	global $db;
	//gets the appropriate price for the customer/currency now it could have been changed
	foreach ($_SESSION['ShoppingCart'] as $CartKey=>$CartItem){
		$Price = GetPrice($CartItem->StockID,
							$_SESSION['ShopDebtorNo'],
							$_SESSION['ShopBranchCode'],
							$CurrCode);
		$Discount = GetDiscount($CartItem->DiscountCategory, $CartItem->Quantity);
		$Price = $Price * (1- $Discount);
		if ($Price !=0) { //if there is a price set up for the item/currency/customer
			$_SESSION['ShoppingCart'][$CartKey]->PriceExcl = $Price;
		} else {
			unset($_SESSION['ShoppingCart'][$CartKey]);
		}
	}
}

function GetDiscount($DiscountCategory, $Quantity){
	global $db;
	/* Select the disount rate from the discount Matrix */
	$result = DB_query("SELECT MAX(discountrate) AS discount
						FROM discountmatrix
						WHERE salestype='" .  $_SESSION['CustomerDetails']['salestype'] . "'
						AND discountcategory ='" . $DiscountCategory . "'
						AND quantitybreak <= '" .$Quantity ."'",$db);
	$myrow = DB_fetch_row($result);
	if ($myrow[0]==NULL){
		$DiscountMatrixRate = 0;
	} else {
		$DiscountMatrixRate = $myrow[0];
	}
	return $DiscountMatrixRate;
}

function ShowBankDetails ($OrderNo) {
	global $db;

	/*Used on check out and in the confirmation email */
	$ShowBankDetails = true;
	$BankResult = DB_query("SELECT bankaccountname,
									bankaddress,
									bankaccountnumber
							FROM bankaccounts
							WHERE invoice=2
							AND currcode='" . $_SESSION['CustomerDetails']['currcode'] . "'",
							$db);
	if (DB_num_rows($BankResult)==0){
		/* If no currency default check the fall back default */
		$BankResult = DB_query("SELECT bankaccountname,
									bankaddress,
									bankaccountnumber
								FROM bankaccounts
								WHERE invoice=1",
								$db);
		if (DB_num_rows($BankResult)==0){
			$ShowBankDetails = false;
		}
	}
	if ($ShowBankDetails == true){
		$BankDetailsRow= DB_fetch_array($BankResult);

		return'<table border="1">
					<tr>
						<th colspan="2"><strong>' . _('Bank Account Details') . '</strong></th>
					</tr>
					<tr>
						<td>' . _('Pay to') . ':</td><td>' . $_SESSION['CompanyRecord']['coyname'] . '</td>
					</tr>
					<tr>
						<td>' . _('Bank') .':</td><td>' . $BankDetailsRow['bankaccountname'] . '</td>
					</tr>
					<tr>
						<td>' . _('Address') . ':</td><td>' . $BankDetailsRow['bankaddress'] . '</td>
					</tr>
					<tr>
						<td>' . _('Account Number') . ':</td><td>' . $BankDetailsRow['bankaccountnumber'] . '</td>
					</tr>
					<tr>
						<td>' . _('Reference') . ':</td><td>'  . $OrderNo . '</td>
				</tr>
				</table>';
	} else {
		return '';
	}
}

function CreateWebCustomerCode($i){
	/* For the first 10.000.000 webSHOP customers, get a nice customercode so they appear better on various webERP reports
	   Above 10.000.000 customers... contact Phil for an improved customer code system version.
	   You should be happy to reach more than 10 milion customers :-) */
	if($i <= 9){
		$i = "WEB000000" . $i;
	}elseif($i <= 99){
		$i = "WEB00000"  . $i;
	}elseif($i <= 999){
		$i = "WEB0000"   . $i;
	}elseif($i <= 9999){
		$i = "WEB000"    . $i;
	}elseif($i <= 99999){
		$i = "WEB00"     . $i;
	}elseif($i <= 999999){
		$i = "WEB0"      . $i;
	}elseif($i <= 9999999){
		$i = "WEB"       . $i;
	}
	return $i;
}


?>
