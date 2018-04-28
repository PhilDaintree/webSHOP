<?php
if(!isset($_GET['token']) AND !isset($_GET['PayerID'])) { //then we must first make the call to paypal to get the token and PayerID

	$PayPalData = 	'&PAYMENTREQUEST_0_PAYMENTACTION=SALE'.
					'&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode($_SESSION['CustomerDetails']['currcode']) .
					'&PAYMENTREQUEST_0_AMT=' . urlencode(number_format($_SESSION['TotalDue'],2,'.','')) .
					'&PAYMENTREQUEST_0_ITEMAMT=' . urlencode(number_format($_SESSION['TotalDue'],2,'.','')).
					'&PAYMENTREQUEST_0_DESC=' . urlencode($_SESSION['ShopName'] . ' ' . _('Purchases')) .
					'&L_PAYMENTREQUEST_0_AMT0=' . urlencode(number_format($_SESSION['TotalDue'],2,'.','')).
					'&L_PAYMENTREQUEST_0_NAME0=' . urlencode($_SESSION['ShopName'] . ' ' . _('Purchases')) .
					'&NOSHIPPING=1' .
					'&ALLOWNOTE=0' .
					'&RETURNURL=' . urlencode(htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8')) .
					'&CANCELURL=' . urlencode(htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8'));
	//We need to execute the "SetExpressCheckOut" method to obtain paypal token
	//pay_pal_request() function is in includes/Functions.php
	$PayPalResponseArray = pay_pal_request('SetExpressCheckout', $PayPalData, $_SESSION['ShopPayPalUser'], $_SESSION['ShopPayPalPassword'],$_SESSION['ShopPayPalSignature']);

	if ($PayPalResponseArray != 0) {
		//Respond according to message we receive from Paypal
		if(strtoupper($PayPalResponseArray['ACK']) == 'SUCCESS' OR strtoupper($PayPalResponseArray['ACK']) == 'SUCCESSWITHWARNING'){
			//Redirect user to PayPal store with Token received.
			if($_SESSION['ShopMode']=='test'){
				header('Location: ' .  'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $PayPalResponseArray['TOKEN'] . '');
			} else {
				header('Location: ' .  'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $PayPalResponseArray['TOKEN'] . '');
			}

		} else{
			//Show error message
			message_log(_('Unable to pay using pay-pal at this time. Please use a different payment type or try again'),'error');
		}
	}
} else {
	//Paypal has redirected back to this page using ReturnURL, We should receive the $_GET['token'] and $_GET['PayerID']
	//we will be using these two variables to execute the "DoExpressCheckoutPayment"
	//Note: we haven't received any payment yet.

	//get session variables
	$PayPalData = '&TOKEN=' . urlencode($_GET['token']) .
					'&PAYERID=' . urlencode($_GET['PayerID']) .
					'&PAYMENTREQUEST_0_PAYMENTACTION=SALE' .
					'&PAYMENTREQUEST_0_AMT=' . urlencode(number_format($_SESSION['TotalDue'],2,'.','')).
					'&PAYMENTREQUEST_0_ITEMAMT=' . urlencode(number_format($_SESSION['TotalDue'],2,'.','')).
					'&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode($_SESSION['CustomerDetails']['currcode']) .
					'&PAYMENTREQUEST_0_SOFTDESCRIPTOR=' . urlencode($_SESSION['ShopName'] . ' ' . _('Purchases'));

	//We need to execute the "DoExpressCheckoutPayment" at this point to Receive payment from user.
	$PayPalResponseArray = pay_pal_request('DoExpressCheckoutPayment', $PayPalData, $_SESSION['ShopPayPalUser'], $_SESSION['ShopPayPalPassword'],$_SESSION['ShopPayPalSignature']);

	//Check if everything went ok..
	if(strtoupper($PayPalResponseArray['ACK']) == 'SUCCESS' OR strtoupper($PayPalResponseArray['ACK']) == 'SUCCESSWITHWARNING'){

		/*
		//Sometimes Payment are kept pending even when transaction is complete.
		//May be because of Currency change, or user choose to review each payment etc.
		//hence we need to notify user about it and ask him manually approve the transiction
		*/
		$TransactionID = urldecode($PayPalResponseArray['PAYMENTINFO_0_TRANSACTIONID']);
		$_SESSION['PaypalTransactionID'] = $TransactionID;

		if($PayPalResponseArray['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Completed'){
			$_SESSION['Paid'] = true;
			include('includes/PlaceOrder.php');
			message_log(_('Thanks for your order. Please quote your order number') . ': ' . $OrderNo . ' ' . _('in all correspondence') . '<br />' . _('PayPal payment has been successfully completed with  PayPal transaction ID') . ': ' . $TransactionID ,'success');
			InsertCustomerReceipt($_SESSION['ShopPayPalBankAccount'],$TransactionID, $OrderNo); //see includes/Functions.php
			PaypalTransactionCommision($_SESSION['ShopPayPalBankAccount'], $_SESSION['ShopPayPalCommissionAccount'], urldecode($PayPalResponseArray['PAYMENTINFO_0_FEEAMT']), $_SESSION['CustomerDetails']['currcode'], $TransactionID);
		} elseif($PayPalResponseArray['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Pending') {
			message_log(_('Although the PayPal payment has been initiated it will require your approval from your PayPal account, before it can be completed.') . '<br />' . _('The PayPal payment is recorded as pending because') . ' ' . $PayPalResponseArray['PAYMENTINFO_0_PENDINGREASON'] . '<br />' . _('The PayPal transactions ID is') . ': ' . $TransactionID ,'error');
			$_SESSION['Paid'] = false;
			include('includes/PlaceOrder.php');
		}
	} else { //there was an error completing the payment
		message_log(_('PayPal payment was unsuccesful - the request returned the error:') . ' '. urldecode($PayPalResponseArray['L_LONGMESSAGE0']) . '<br />' . _('Please retry or use another method to process your payment'), 'error');
	}
}

function PaypalTransactionCommision ($BankAccount, $CommissionAccount, $Commission, $Currency, $TransactionID) {
	global $db;
	DB_Txn_Begin($db);

	$PaymentNo = GetNextSequenceNo(1);
	$PeriodNo = GetPeriod(Date($_SESSION['DefaultDateFormat']),$db);

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
		VALUES (1,
			'" . $PaymentNo . "',
			'" . $BankAccount . "',
			'" . _('PayPal Transaction Fees') . ' ' . $_SESSION['ShopDebtorNo'] . ' ' . $TransactionID  . "',
			'" . $_SESSION['CustomerDetails']['rate'] / $FunctionalRate  . "',
			'" . $FunctionalRate . "',
			'" . Date('Y-m-d') . "',
			'" . _('PayPal Transaction Fees') . "',
			'" . -($Commission * $_SESSION['CustomerDetails']['rate'] / $FunctionalRate) . "',
			'" .$Currency . "'
		)";
	$DbgMsg = _('The SQL that failed to insert the bank account transaction was');
	$ErrMsg = _('Cannot insert a bank transaction');
	$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg,true);


	// Insert GL entries too if integration enabled

	if ($_SESSION['CompanyRecord']['gllink_debtors']==1){ /* then enter GLTrans records for discount, bank and debtors */
		/* Bank account entry first */
		$Narrative = $_SESSION['ShopDebtorNo'] . ' ' . _('Paypal Fees for Transaction ID') . ': ' . $TransactionID;
		$SQL="INSERT INTO gltrans (	type,
									typeno,
									trandate,
									periodno,
									account,
									narrative,
									amount)
				VALUES (1,
						'" . $PaymentNo . "',
						'" . Date('Y-m-d') . "',
						'" . $PeriodNo . "',
						'" . $BankAccount . "',
						'" . $Narrative . "',
						'" . -($Commission /$_SESSION['CustomerDetails']['rate']) . "'
					)";
		$DbgMsg = _('The SQL that failed to insert the Paypal transaction fee from the bank account debit was');
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
					VALUES (1,
							'" . $PaymentNo . "',
							'" . Date('Y-m-d') . "',
							'" . $PeriodNo . "',
							'". $CommissionAccount . "',
							'" . $Narrative . "',
							'" . ($Commission /$_SESSION['CustomerDetails']['rate']). "' )";
		$DbgMsg = _('The SQL that failed to insert the Paypal transaction fee for the commission account credit was');
		$ErrMsg = _('Cannot insert a GL transaction for the debtors account credit');
		$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg,true);
		EnsureGLEntriesBalance(1,$PaymentNo);
	} //end if there is GL work to be done - ie config is to link to GL

	DB_Txn_Commit($db);
}
?>