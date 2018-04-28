<?php
/* Process the session variables into a webERP sales order */
if (!mb_strpos($_SERVER['PHP_SELF'],'PlaceOrder.php')) { //to ensure the script is only run as an include from another file

	if ((isset($_SESSION['SelectedPaymentMethod']) AND $_SESSION['SelectedPaymentMethod'] == 'BankTransfer') OR !isset($_SESSION['Paid']) OR $_SESSION['Paid']==false){
		// if customer selected bank transfer it means she did not pay yet. We consider it a quotation only until transfer is made.
		// if Paypal status was pending, it was not paid yet. needs our attention
		$Quotation = 1;
	} else{
		// customer already paid or she has credit. We should consider it a firm order.
		$Quotation = 0;
	}

	$Result = DB_Txn_Begin($db);

	$OrderNo = GetNextSequenceNo(30);

	$HeaderSQL = "INSERT INTO salesorders (
								orderno,
								debtorno,
								branchcode,
								customerref,
								comments,
								orddate,
								ordertype,
								shipvia,
								deliverto,
								deladd1,
								deladd2,
								deladd3,
								deladd4,
								deladd5,
								deladd6,
								contactphone,
								contactemail,
								salesperson,
								fromstkloc,
								freightcost,
								quotation,
								deliverydate,
								quotedate,
								confirmeddate)
							VALUES (
								'". $OrderNo . "',
								'" . $_SESSION['ShopDebtorNo'] . "',
								'" . $_SESSION['ShopBranchCode'] . "',
								'". DB_escape_string($_SESSION['CustomerDetails']['orderreference']) ."',
								'". DB_escape_string($_SESSION['CustomerDetails']['comments']) ."',
								'" . Date('Y-m-d H:i') . "',
								'" . $_SESSION['CustomerDetails']['salestype'] . "',
								'" . $_SESSION['CustomerDetails']['defaultshipvia'] ."',
								'".  DB_escape_string($_SESSION['CustomerDetails']['contactname']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['braddress1']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['braddress2']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['braddress3']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['braddress4']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['braddress5']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['braddress6']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['phoneno']) . "',
								'" . DB_escape_string($_SESSION['CustomerDetails']['email']). "',
								'" . $_SESSION['CustomerDetails']['salesman'] . "',
								'" . $_SESSION['CustomerDetails']['defaultlocation'] ."',
								'" . $_SESSION['FreightCost'] ."',
								'" . $Quotation ."',
								'" . Date('Y-m-d') . "',
								'" . Date('Y-m-d') . "',
								'" . Date('Y-m-d') . "')";
	$DbgMsg = _('The SQL that failed was');
	$ErrMsg = _('The order cannot be added because');
	$InsertQryResult = DB_query($HeaderSQL,$db,$ErrMsg,$DbgMsg,true);

	$StartOf_LineItemsSQL = "INSERT INTO salesorderdetails (orderlineno,
															orderno,
															stkcode,
															unitprice,
															quantity,
															poline,
															itemdue,
															discountpercent)
												VALUES (";
	$i=0;
	foreach($_SESSION['ShoppingCart'] as $CartItem) {

		$LineItemsSQL = $StartOf_LineItemsSQL . "
					'" . $i . "',
					'" . $OrderNo . "',
					'" . $CartItem->StockID . "',
					'" . ($CartItem->PriceExcl/(1-$CartItem->Discount)) . "',
					'" . $CartItem->Quantity . "',
					'" . $_SESSION['CustomerDetails']['orderreference'] . "',
					'" . Date('Y-m-d') . "',
					'" . $CartItem->Discount . "')";
		$ErrMsg = _('Unable to add the sales order line');
		$Ins_LineItemResult = DB_query($LineItemsSQL,$db,$ErrMsg,$DbgMsg,true);
		$i++;
	} /* end inserted line items into sales order details */

	if($_SESSION['SurchargeAmount']>0.01){
		//Need to get the tax category of the SurchargeItem
		//$SurchargeTaxCatResult = DB_query("SELECT taxcatid FROM stockmaster WHERE stockid='" . $_SESSION['ShopSurchargeStockID'] ."'",$db);
		//$TaxCatRow = DB_fetch_row($SurchargeTaxCatResult);

		//$NetSurcharge = $_SESSION['SurchargeAmount']/(1+$_SESSION['TaxRates'][$TaxCatRow[0]]);

		$LineItemsSQL = $StartOf_LineItemsSQL . "
					'" . $i . "',
					'" . $OrderNo . "',
					'" . $_SESSION['ShopSurchargeStockID'] . "',
					'" . $_SESSION['SurchargeAmount'] . "',
					'1',
					'" . $_SESSION['CustomerDetails']['orderreference'] . "',
					'" . Date('Y-m-d') . "',
					0)";
		$ErrMsg = _('Unable to add the payment surcharge line to the sales order');
		$Ins_LineItemResult = DB_query($LineItemsSQL,$db,$ErrMsg,$DbgMsg,true);
		$i++;
	}
	if($_SESSION['FreightCost']>0){
		$LineItemsSQL = $StartOf_LineItemsSQL . "
					'" . $i . "',
					'" . $OrderNo . "',
					'" . $FreightStockID . "',
					'" . $_SESSION['FreightCost'] . "',
					'1',
					'" . $_SESSION['CustomerDetails']['orderreference'] . "',
					'" . Date('Y-m-d') . "',
					0)";
		$ErrMsg = _('Unable to add the freight charge line to the sales order');
		$Ins_LineItemResult = DB_query($LineItemsSQL,$db,$ErrMsg,$DbgMsg,true);
	}

	$result = DB_Txn_Commit($db);
	if (isset($_SESSION['MessageLog']) AND count($_SESSION['MessageLog']) > 0){
		$OrderError = true;
	} else {
		$_SESSION['OrderPlaced'] = true;
		SendConfirmationEmail($OrderNo,$db);
	}
}

function SendConfirmationEmail($OrderNo, $db){

	//Get Out if we have no order number to work with
	If (!isset($OrderNo) OR $OrderNo==''){
		exit;
	}

	if($_SESSION['ShopMode']=='test'){
		// do not bother customers if we are doing tests with their data
		$MailTo = $_SESSION['ShopManagerEmail'];
	} else { //not in test send to customer. Shop manager gets CCed
		$MailTo = $_SESSION['CustomerDetails']['email'] ;
	}

	$headers = "From: " . $_SESSION['ShopName'] . " <" . strip_tags($_SESSION['ShopManagerEmail']) . ">\r\n";
	$headers .= "Reply-To: " . $_SESSION['ShopName'] . " <". strip_tags($_SESSION['ShopManagerEmail']) . ">\r\n";
	$headers .= "Cc: " . $_SESSION['ShopName'] . " <". strip_tags($_SESSION['ShopManagerEmail']) . ">\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=utf-8\r\n";

	$MailSubject = $_SESSION['ShopName'] . ' ' . _('Order Confirmation') . ': ' .  $OrderNo;

	/* Introduction text */
	$MailMessage = '
		<html>
		<head>
			<title>' .$MailSubject . '</title>
		</head>
		<body>
			<table cellpadding="2" cellspacing="2">
				<tr>
					<td align="center" colspan="4">
						<h2>' . $MailSubject . '</h2>
					</td>
				</tr>
			</table>
			<table>
				<tr>
					<td> <b>' . _('Order to be delivered to') . ':</b>
					</td>
					<td>' . DB_escape_string($_SESSION['CustomerDetails']['contactname']) . '
					</td>
				</tr>';
	if(mb_strlen(trim($_SESSION['CustomerDetails']['braddress1']))) {
		  $MailMessage .= '
				<tr>
					<td>
					</td>
					<td>' . DB_escape_string($_SESSION['CustomerDetails']['braddress1']) . '
					</td>
				</tr>';
	}
	if(mb_strlen(trim($_SESSION['CustomerDetails']['braddress2']))) {
		  $MailMessage .= '
				<tr>
					<td>
					</td>
					<td>' . DB_escape_string($_SESSION['CustomerDetails']['braddress2']) . '
					</td>
				</tr>';
	}
	if(mb_strlen(trim($_SESSION['CustomerDetails']['braddress3']))) {
		  $MailMessage .= '
				<tr>
					<td>
					</td>
					<td>' . DB_escape_string($_SESSION['CustomerDetails']['braddress3']) . '
					</td>
				</tr>';
	}
	if(mb_strlen(trim($_SESSION['CustomerDetails']['braddress4']))) {
		  $MailMessage .= '
				<tr>
					<td>
					</td>
					<td>' . DB_escape_string($_SESSION['CustomerDetails']['braddress4']) . '
					</td>
				</tr>';
	}
	if(mb_strlen(trim($_SESSION['CustomerDetails']['braddress5']))) {
		  $MailMessage .= '
				<tr>
					<td>
					</td>
					<td>' . DB_escape_string($_SESSION['CustomerDetails']['braddress5']) . '
					</td>
				</tr>';
	}
	if(mb_strlen(trim($_SESSION['CustomerDetails']['braddress6']))) {
		  $MailMessage .= '
				<tr>
					<td>
					</td>
					<td>' . DB_escape_string($_SESSION['CustomerDetails']['braddress6']) . '
					</td>
				</tr>';
	}

	$MailMessage .= '
			</table>';

	$MailMessage .= '<br/>';

	/* order items details */

	$MailMessage .= '
			<table border="1" width="90%">
				<tr>
					<th>' . _('Stock Code') . '</th>
					<th>' . _('Description') . '</th>
					<th>' . _('Quantity') . '</th>
					<th>' . _('Unit Price') . '</th>
					<th>' . _('Line Price') . '</th>
				</tr>';
	$CartTotalValue =0;
	$CartTotalWeight =0;
	$CartTotalVolume =0;

	foreach($_SESSION['ShoppingCart'] as $CartItem) {
		$GrossPrice = $CartItem->PriceExcl*(1+$_SESSION['TaxRates'][$CartItem->TaxCatID]);
		$LineTotal = $GrossPrice * $CartItem->Quantity;
		$CartTotalValue += $LineTotal;
		$CartTotalWeight += $CartItem->Weight * $CartItem->Quantity;
		$CartTotalVolume += $CartItem->Volume * $CartItem->Quantity;

		$MailMessage .= '
				<tr>
					<td>' . $CartItem->StockID . '</td>
					<td>' . $CartItem->Description . '</td>
					<td align="right">' . locale_number_format($CartItem->Quantity,0) . '</td>
					<td align="right">' .  locale_number_format($GrossPrice,$_SESSION['CustomerDetails']['currdecimalplaces'])  . '</td>
					<td align="right">' .  locale_number_format($LineTotal,$_SESSION['CustomerDetails']['currdecimalplaces'])  . '</td>
				</tr>';
	}

	$MailMessage .= '<tr>
						<td colspan="4" align="right">' . _('Total Items Ordered Value') . '</td>
						<td align="right">' . locale_number_format($CartTotalValue,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</td>
					</tr>';

	/* freight details */

	if ($_SESSION['FreightCost'] != "NOT AVAILABLE"){
		if ($_SESSION['FreightCost'] != 0){
			$FreightCostInclTax = $_SESSION['FreightCost']*(1+$_SESSION['TaxRates'][$_SESSION['FreightTaxCategory']]);
			$MailMessage .=  '<tr>
								<td colspan="4" align="right">' . _('Freight Costs') . '</td>
								<td align="right">' . locale_number_format($FreightCostInclTax,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</td>
							</tr>';
		}else{
			$FreightCostInclTax=0;
			$MailMessage .=  '
				<tr>
					<td colspan="4" align="right">' . _('Freight Costs paid by') . ' ' . $_SESSION['ShopName'] . '</td>
				</tr>';
		}
		$MailMessage .=  '
				<tr>
					<td colspan="4" align="right">' . _('Total') . ' (' . $_SESSION['CustomerDetails']['currcode'] . ') ' . '</td>
					<td align="right">' . locale_number_format($CartTotalValue + $FreightCostInclTax,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</td>
				</tr>';
	}
	$MailMessage .= '
			</table>';

	$MailMessage .= '<br/>';

	/* PAYMENT INFORMATION */
	if ($_SESSION['CustomerDetails']['creditcustomer']==false) {
		$MailMessage .= '
			<b>' . _('Payment information') . '</b>';
		$MailMessage .= '<br/>
						<br/>Thank you for your business.
						<br/>';
		switch ($_SESSION['SelectedPaymentMethod']) {
			case 'PayPal':
				if ($_SESSION['Paid']){
					$MailMessage .= '
						<p>' . _('Payment was made by') . ' ' . $_SESSION['SelectedPaymentMethod']. ' ' . _('Transaction ID') . ' ' . $_SESSION['PaypalTransactionID'] .'</p>';
				} else{
					// when Paypal returns PENDING. Payment has not been done yet...
					$MailMessage .= '
						<p>' . $_SESSION['SelectedPaymentMethod']. ' ' . _('payment attempt was not successful. We will contact you via email to double check the details and complete the payment process.') .'</p>';
				}
				break;
			case 'PayFlow': //Credit Card
			case 'PayPalPro': //Credit Card
			case 'SwipeHQ': //Credit Card
				if ($_SESSION['Paid']){
					$MailMessage .= '<p>' . _('Payment made by') . ' ' . $_SESSION['SelectedPaymentMethod'] .'</p>';
				}
				break;
			case 'CreditAccount': //if the customer is defined with payment terms this is defaulted
				$MailMessage .= '<p>' . _('This order will be charged to your account') . '</p>';
				break;
			case 'BankTransfer':
				$MailMessage .= '<p>' . _('Payment to be made by bank transfer to our account') .'</p>';

				$MailMessage .= ShowBankDetails($OrderNo);
				$MailMessage .= '
					<p>' . _('Please note that goods will be shipped once your funds transfer is confirmed') . '.</p>';
				break;
		}
	} else {
		$MailMessage .= '
			<b>' . _('This order has been credited to your account with us. Invoicing will be done as usual.') . '
			</b>';
	}
	$MailMessage .= '<br/>';

	/* SHIPMENT INFORMATION */
	if (($_SESSION['FreightMethodSelected'] != 'NOT AVAILABLE') AND ($_SESSION['Paid'])) {
		$MailMessage .= '<b>' . _('Shipment information') . '</b>
						<br/>
						<p>' . _('Your order will be shipped to you shortly via') . ' ' . $_SESSION['FreightMethodSelected'] . '. ' . _('We will send you to this email address the tracking number once shipped.') .'</p>';

	}
	$MailMessage .= '<br/>
					<p>' . _('Do not hesitate to contact us for any further detail you might need.') . '</p>
					<br/>
					</body>
					</html>';

	$result = mail( $MailTo, $MailSubject, $MailMessage, $headers );
}

?>