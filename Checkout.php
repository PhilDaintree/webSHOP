<?php
include('includes/DefineCartItemClass.php'); //must be before header.php
include('includes/config.php');
include('includes/session.php');
include($PathPrefix . 'includes/CountriesArray.php');
include_once ($PathPrefix . 'includes/FreightCalculation.inc');

$Title = _('Checkout');

/*The SelectPaymentMethod button is clicked by the javascript when the user clicks on one of the payment method bullet options - the PaymentMethod */

if (isset($_POST['SelectPaymentMethod']) AND (!isset($_SESSION['Paid']) OR $_SESSION['Paid']==false)){
	if (!isset($_POST['PaymentMethod'])){
		$_POST['ReSelectPaymentMethod']=true;
	} else {
		$_SESSION['SelectedPaymentMethod'] = $_POST['PaymentMethod'];
		include('includes/RecalculateCartTotals.php');
		switch ($_POST['PaymentMethod']) {
		case 'PayPalPro':
			header('Location: https://' . $_SERVER['HTTP_HOST'] . $RootPath . '/CreditCardPayPalPro.php');
			exit();
			break;
		case 'PayFlowPro':
			header('Location: https://' . $_SERVER['HTTP_HOST'] . $RootPath . '/CreditCardPayFlowPro.php');
			exit();
			break;

		case 'SwipeHQ': //need to get a transaction id first
			$_SESSION['Paid'] = false;
			$CharsToReplace = array('-', ' ', '/', '\\', '"', "'", '=','&');
			$SwipeHQArray = array('merchant_id' => $_SESSION['ShopSwipeHQMerchantID'],
									'api_key' => $_SESSION['ShopSwipeHQAPIKey'],
									'td_item' => $_SESSION['ShopName'] . ' ' . _('purchases'),
									'td_user_data' => $_SESSION['ShopDebtorNo'] . ' from IP:' .  $_SERVER['REMOTE_ADDR'] . ' order ref: ' . str_replace($CharsToReplace,'_',$_SESSION['CustomerDetails']['orderreference']),
									'td_amount' => number_format($_SESSION['TotalDue'],2,'.',''),
									'td_currency' => $_SESSION['CustomerDetails']['currcode'],
									'td_callback_url' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?' . htmlspecialchars(session_id()));

			$ch = curl_init ('https://api.swipehq.com/createTransactionIdentifier.php');
			curl_setopt ($ch, CURLOPT_POST, 1);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $SwipeHQArray);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
			curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
			$SwipeHQResult = curl_exec ($ch);
			if (curl_errno($ch) !== 0){
				message_log(_('The swipeHQ payment request failed with a curl error') . ': ' . curl_errno($ch) . ' ' ._('We are not able to process payments with your credit card at this time'),'error');
			} else { //success with the curl call at least
				$ResponseArray = json_decode($SwipeHQResult,true); //result arrray
				switch ($ResponseArray['response_code']) {
					case '400':
						message_log(_('The SwipeHQ payment request returned an access denied error') . ': ' . $ResponseArray['message'],'error');
						break;
					case '402':
						message_log(_('The SwipeHQ payment request returned a system error') . ': ' . $ResponseArray['message'],'error');
						break;
					case '403':
						message_log(_('The SwipeHQ payment request requires more parameters') . ': ' . $ResponseArray['message'],'error');
						break;
					case '407':
						message_log(_('The SwipeHQ payment request returned an inactive account error') . ': ' . $ResponseArray['message'],'error');
						break;
					case '410':
						message_log(_('The SwipeHQ payment request returned a permission denied error') . ': ' . $ResponseArray['message'],'error');
						break;
					case '413':
						message_log(_('The SwipeHQ payment request returned an invalid format error') . ': ' . $ResponseArray['message'],'error');
						break;
					case '200': //hurrah it worked
						$_SESSION['SwipeHQIdentifierID'] = $ResponseArray['data']['identifier'];
						header('Location: https://payment.swipehq.com/?identifier_id=' . $ResponseArray['data']['identifier']);
					default:
						message_log(_('The SwipeHQ payment request returned an error with the message') . ': ' . $ResponseArray['message'],'error');
						break;
				} //end switch $ResponseArray['response_code']
			}
			curl_close ($ch);
			break;
		case 'PayPal':
			include('includes/ProcessPayPalPayment.php');
			break;
		} //end switch PaymentMethod
	} //end if isset($_POST['PaymentMethod'])
}

//Check SwipeHQ return values
if(isset($_GET['result']) AND isset($_SESSION['SelectedPaymentMethod']) AND $_SESSION['SelectedPaymentMethod']=='SwipeHQ') {

	if ($_GET['result']=='accepted' OR $_GET['result']=='test-accepted') {

		//verify the transaction was succesful
		$SwipeHQArray = array('merchant_id' => $_SESSION['ShopSwipeHQMerchantID'],
								'api_key' => $_SESSION['ShopSwipeHQAPIKey'],
								'identifier_id' => $_SESSION['SwipeHQIdentifierID']);
		$ch = curl_init ('https://api.swipehq.com/verifyTransaction.php');
		curl_setopt ($ch, CURLOPT_POST, 1);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $SwipeHQArray);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
		$SwipeHQResult = curl_exec ($ch);
		if (curl_errno($ch) !== 0){
			echo '<br />' . _('The swipeHQ payment request failed with a curl error') . ': ' . curl_errno($ch) . ' ' ._('We are not able to process payments with your credit card at this time');
			message_log(_('The swipeHQ payment request failed with a curl error') . ': ' . curl_errno($ch) . ' ' ._('We are not able to process payments with your credit card at this time'),'error');
		} else { //success with the curl call at least
			$ResponseArray = json_decode($SwipeHQResult,true); //result arrray
			if ($ResponseArray['response_code']=='200') { //API success
				if ($ResponseArray['data']['transaction_approved']=='yes' AND ($ResponseArray['data']['status']=='accepted' OR $ResponseArray['data']['status']=='approved' OR $ResponseArray['data']['status']=='test-accepted')) {
					 //hurrah all good
					$_SESSION['Paid'] = true;
					include('includes/PlaceOrder.php');
					InsertCustomerReceipt($_SESSION['ShopCreditCardBankAccount'],$ResponseArray['data']['transaction_id'],$OrderNo);
					message_log(_('Your credit card payment was processed sucessfully and your order number') . ' ' . $OrderNo . ' ' . _('is now being processed. Please quote this order number in all correspondence. Thanks for your business'),'success');
				} else {
					message_log(_('The credit card transaction was declined.'),'error');
					if ($debug==1) {
						message_log(_('The error code') . ': ' . $ResponseArray['response_code'] . ' ' . _('and the message') . ': ' . $ResponseArray['message'] . ' ' . _('was returned from') . ' SwipeHQ','error');
						message_log( print_r($ResponseArray),'error');
					}
				}
			} else {
				message_log(_('The SwipeHQ verification API call failed with the message') . ' ' . $ResponseArray['message'],'error');
			}
		}
	} elseif ($_GET['result']=='declined') {
		message_log(_('The SwipeHQ transaction was declined with the message') . ' ' . $ResponseArray['message'],'error');

	}
}

if (isset($_POST['PayByPayPal']) OR (isset($_GET['token']) AND isset($_GET['PayerID']))){
	include('includes/ProcessPayPalPayment.php');
}

if (isset($_POST['PlaceOrder'])){ //set when doing bank transfer
	include('includes/PlaceOrder.php');
}

include('includes/header.php');

if (isset($_POST['ConfirmDeliveryAddress'])){
	$InputError =0;
	$i=0;
	if (mb_strlen($_POST['Phone'])<5) {
		$InputError = 1;
		message_log( _('The phone number for the billing address is too short to be valid. 5 or more numbers are expected.'),'error');
		$Errors[$i] = 'Phone';
		$i++;
	} elseif (mb_strlen($_POST['DeliveryAddress1']) <2) {
		$InputError = 1;
		message_log( _('The delivery building address is too short to be valid'),'error');
		$Errors[$i] = 'DeliveryAddress1';
		$i++;
	} elseif (mb_strlen($_POST['DeliveryAddress2']) <4) {
		$InputError = 1;
		message_log( _('The delivery street address is too short to be valid. At least 4 characters are expected'),'error');
		$Errors[$i] = 'DeliveryAddress2';
		$i++;
	} elseif (mb_strlen($_POST['DeliveryAddress4']) <3) {
		$InputError = 1;
		message_log( _('The delivery city name is too short to be valid'),'error');
		$Errors[$i] = 'DeliveryAddress4';
		$i++;
	} elseif (mb_strlen($_POST['Email']) >1 AND !IsEmailAddress($_POST['Email'])) {
		$InputError = 1;
		message_log( _('The delivery contact email address does not appear to be a valid email address'),'error');
		$Errors[$i] = 'Email';
		$i++;
	}

	if ($InputError==0){ //no input errors then do the update

		//echo '<br />Shop Freight Method = ' . $_SESSION['ShopFreightMethod'];

		$_SESSION['CustomerDetails']['braddress1'] = $_POST['DeliveryAddress1'];
		$_SESSION['CustomerDetails']['braddress2'] = $_POST['DeliveryAddress2'];
		$_SESSION['CustomerDetails']['braddress3'] = $_POST['DeliveryAddress3'];
		$_SESSION['CustomerDetails']['braddress4'] = $_POST['DeliveryAddress4'];
		$_SESSION['CustomerDetails']['braddress5'] = $_POST['DeliveryAddress5'];
		$_SESSION['CustomerDetails']['braddress6'] = $_POST['DeliveryAddress6'];
		$_SESSION['CustomerDetails']['phoneno'] = $_POST['Phone'];
		$_SESSION['CustomerDetails']['contactname'] = $_POST['ContactName'];
		$_SESSION['CustomerDetails']['orderreference'] = $_POST['OrderReference'];
		$_SESSION['CustomerDetails']['comments'] = $_POST['Comments'];
		$_SESSION['CustomerDetails']['email'] = $_POST['Email'];
		$_SESSION['ConfirmedDeliveryAddress'] = true;
		if ($_SESSION['ShopFreightMethod']=='webERPCalculation' AND $PhysicalDeliveryRequired==true){

			list ($FreightCost, $BestShipper) = CalcFreightCost($_SESSION['TotalDue'],
																$_SESSION['CustomerDetails']['braddress2'],
																$_SESSION['CustomerDetails']['braddress3'],
																$_SESSION['CustomerDetails']['braddress4'],
																$_SESSION['CustomerDetails']['braddress5'],
																$_SESSION['CustomerDetails']['braddress6'],
																$_SESSION['TotalVolume'],
																$_SESSION['TotalWeight'],
																$_SESSION['CustomerDetails']['defaultlocation'],
																$_SESSION['CustomerDetails']['currcode'],
																$db);

			//echo '<br />Freight Cost = ' . $FreightCost;

			if ($FreightCost != 'NOT AVAILABLE'){
				$_SESSION['FreightCost'] = $FreightCost;
				$sqlShipper = "SELECT shippername FROM shippers WHERE shipper_id= '" . $BestShipper . "'";
				$resultShipper = DB_query($sqlShipper,$db);
				while ($myrowShipper = DB_fetch_array($resultShipper)) {
					$_SESSION['FreightMethodSelected'] = $myrowShipper['shippername'];
				}
			} else{
				$_SESSION['FreightCost'] = 'NOT AVAILABLE';
				$_SESSION['FreightMethodSelected'] = 'NOT AVAILABLE';
			}

		} elseif ($_SESSION['ShopFreightMethod']=='AusPost' AND $PhysicalDeliveryRequired==true) {
			//Australia Post API
			//shamelessly used code from http://damiandennis.com/blog/2012/02/25/australia-post-api-with-php/

			if($_SESSION['ShopMode']=='test'){
				$API_Endpoint = 'https://test.npe.auspost.com.au';
				$AusPostAPIKey = '28744ed5982391881611cca6cf5c2409';
			} else {
				$API_Endpoint = 'https://auspost.com.au';
			}
			if ($_SESSION['TotalVolume']>0) {
				$EqualDimension = pow($_SESSION['TotalVolume'],1/3)*100; //cubic measurement will be in metres cubed but we want centimeters so x100
			} else {
				$EqualDimension = 10; //use silly default of 10cm cubed == 0.001 metre cubed if volume is not specified - this is what Terry's perl system did i think?
			}

			if (strtoupper($_SESSION['CustomerDetails']['dispatch_country'])!='AUSTRALIA' OR $_SESSION['CustomerDetails']['from_postal_code']='') {
				message_log(_('The webSHOP is configured to get freight costs using the Australia post API, but the dispatch location is not based in Australia or there is no postal code defined for the dispatch location'),'error');
			} elseif (strtoupper($_SESSION['CustomerDetails']['braddress6']) ==  strtoupper($_SESSION['CustomerDetails']['dispatch_country'])) {
				//then its a domestic delivery get domestic rate
				//First off get the domestic parcel service types for the package - we have the volume and the weight

				$APIMethod = $API_EndPoint . '/api/postage/parcel/domestic/calculate.xml?from_postcode='  .  $_SESSION['CustomerDetails']['from_postal_code'] .
																					'&to_postcode=' . $_SESSION['CustomerDetails']['braddress5'] .
																					'&service_code=AUS_PARCEL_REGULAR' .
																					'&weight=' . $_SESSION['TotalWeight'] .
																					'&width=' . $EqualDimension .
																					'&height=' . $EqualDimension .
																					'&length=' . $EqualDimension;
			} else {
			//it must be an international shipment as the country of the customer is different to the country of the dispatch warehouse
				$APIMethod = $API_EndPoint . '/api/postage/parcel/international/calculate.xml?country_code='  .  $_SESSION['CustomerDetails']['braddress6'] .
																					'&weight=' . $_SESSION['TotalWeight'] .
																					'&service_code=AUS_PARCEL_REGULAR';
			}

			// Set the curl parameters.
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $APIMethod);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Auth-Key: ' . $AusPostAPIKey));
			curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			if ($debug==1) {
				message_log(_('Sending the Aus Post request') . '<br />' . $APIMethod,'info');
			}

			// Get response from the server.
			$AusPostResult = new SimpleXMLElement(curl_exec($ch));

			print_r($AusPostResult);
			if ($AusPostResult->total_cost!=0){
				$FreightCost = $AusPostResult->total_cost;
				$_SESSION['FreightMethodSelected'] = 'AusPost';
			} else {
				$_SESSION['FreightCost'] = 'NOT AVAILABLE';
				$_SESSION['FreightMethodSelected'] = 'NOT AVAILABLE';
			}
		} else {
			$_SESSION['FreightCost'] =0;
		}
	}
} //end update delivery address


?>
<script>
	jQuery(document).ready(function() {
			/* Focus on user name input field*/
		jQuery('#TermsAndConditions').click(function() {
			jQuery('#content_block').html('<?php echo '<h1>' . _('Terms and Conditions') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopTermsConditions'])) ?>');
			return false;
		});
		jQuery('#AboutUs').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('About Us') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopAboutUs'])) ?>');
			return false;
		});
		jQuery('#PrivacyPolicy').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Privacy Policy') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopPrivacyStatement'])) ?>');
			return false;
		});
		jQuery('#ContactUs').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Contact Details') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopContactUs'])) ?>');
			return false;
		});
		jQuery('#FreightPolicy').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Freight Policy') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopFreightPolicy'])) ?>');
 			return false;
 		});
		jQuery('#cart_summary').click(function(){
			jQuery('#content_block').load('index.php?Page=ShoppingCart' + ' #content_block');
			return false;
		});
		jQuery('#SelectPaymentMethodForm :radio').click(function(){
			jQuery('#select_payment_method').trigger('click');
		});
		jQuery('#Currency').change(function(){
			var QueryString = 'FormID=' + jQuery('#SearchForm :hidden').val() + '&CurrCode=' + jQuery('#Currency').val();
			jQuery.post('index.php',QueryString,function(data) {
							var content_block = jQuery(data).filter( '#content_block' );
							var cart_summary = jQuery(data).filter( '#cart_summary' );
							jQuery('#content_block').html(content_block.html());
							jQuery('#cart_summary').html(cart_summary.html());
						});
		});
		jQuery('#ConfirmDeliveryAddress').validate({
			rules: {
				Email: {
					email:true,
					minlength: 7
				},
				DeliveryAddress1: {
					minlength: 2
				},
				DeliveryAddress2: {
					minlength: 4
				},
				DeliveryAddress3: {
					minlength: 3
				},
				Phone: {
					minlength: 6,
				}
			}, //end rules
			messages : {
				Email: {
					required: "<?php echo _('An email address is required') ?>",
					email: "<?php echo _('The email address must be a valid email address') ?>",
					minlength: "<?php echo _('The email address is expected to 5 characters or more long') ?>"
				},
				DeliveryAddress1: {
					minlength: "<?php echo _('The delivery building number/street address is too short to be valid') ?>"
				},
				DeliveryAddress2: {
					minlength: "<?php echo _('The delivery street address is too short to be valid') ?>"
				},
				DeliveryAddress3: {
					minlength: "<?php echo _('The delivery suburb address is too short to be valid') ?>",
				},
				Phone: {
					required: "<?php echo _('A phone number for the delivery address contact must be entered') ?>",
					minlength: "<?php echo _('The phone number for the contact the the delivery address is too short to be valid') ?>"
				}
			}, //end messages
			errorPlacement: function(error, element) {
				error.insertAfter(element);
				error.wrap('<p>');
			} // end errorPlacement
		}); //end validation
		}
	);
</script>

<?php

ShowSalesCategoriesMenu();

include('includes/InfoLinks.php'); //at the bottom
echo '<div class="column_main">';

if (!isset($_SESSION['ShoppingCart']) OR count($_SESSION['ShoppingCart'])==0) { //then there is nowt to checkout!!
	echo '<h1>' . _('Checkout') . '</h1>';
	echo '<p>' . _('The shopping cart is empty') . '</p>';

} else { // there is sommat in the cart and we are really ready to check out

	if (isset($_POST['SendEMailErrorDeliveryAddress'])){
		// Send the email to webSHOP manager to revise WHY did we have a problem with Freight Costs
		$EmailSubject = _('Error calculating freight costs.');
		$EmailText = _('webSHOP could not calculate freight costs for') . ':' . "\n" .
					_('Customer Name') .':' . $_SESSION['CustomerDetails']['contactname'] . "\n" .
					_('Customer Phone') .':' . $_SESSION['CustomerDetails']['phoneno'] . "\n" .
					_('Customer Email') .':' . $_SESSION['CustomerDetails']['email'] . "\n" .
					_('Total Due') .':' . $_SESSION['TotalDue'] . "\n" .
					_('Street') .':' . $_SESSION['CustomerDetails']['braddress2'] . "\n" .
					_('Suburb') .':' . $_SESSION['CustomerDetails']['braddress3'] . "\n" .
					_('City') .':' . $_SESSION['CustomerDetails']['braddress4'] . "\n" .
					_('ZIP') .':' . $_SESSION['CustomerDetails']['braddress5'] . "\n" .
					_('Country') .':' . $_SESSION['CustomerDetails']['braddress6'] . "\n" .
					_('Total Volume') .':' . $_SESSION['TotalVolume'] . "\n" .
					_('Total Weight') .':' . $_SESSION['TotalWeight'] . "\n" .
					_('From Location') .':' . $_SESSION['CustomerDetails']['defaultlocation'] . "\n" .
					_('Currency') .':' . $_SESSION['CustomerDetails']['currcode'];
		if($_SESSION['SmtpSetting']==0){
			mail($_SESSION['ShopManagerEmail'],$EmailSubject,$EmailText);
		}else{
			include($PathPrefix .'includes/htmlMimeMail.php');
			$mail = new htmlMimeMail();
			$mail->setSubject($EmailSubject);
			$result = SendmailBySmtp($EmailText,array($_SESSION['ShopManagerEmail']));
		}

		echo'<div id="send_email_error_freight_costs">
			<form id="ReviseDeliveryAddress" method="post" action="' . $RootPath . '/Checkout.php">
			<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
			<table>
				<tr>
					<td colspan="4"><h2>' . _('Thanks for your report.') . '</h2></td>
				</tr>
				<tr>
					<td colspan="4"><h2>' . _('We will contact you shortly.') . '</h2></td>
				</tr>
				<tr>
					<td colspan="4" class="center">
						<a href="index.php">' . _('Return to Shop Home') . '</a>
				</td>
			</tr>
			</table>
			</form><!-- End of ReviseDeliveryAddressForm -->
			</div><!-- End of send_email_error_freight_costs div --> ';

	} elseif (!isset($_SESSION['LoggedIn']) OR $_SESSION['LoggedIn']==false){
		/* if NOT logged in then show the login dialog */

		if (!isset($_POST['UserEmail'])){
			$_POST['UserEmail']='';
		}
		echo '<h1>' . _('Login Or Register') . '</h1>
			<div id="login_panel">
				<div id="login">
				<form id="LoginForm" method="post" action="' . $RootPath . '/Checkout.php">
				<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
				<div class="loginleft">
					<div class="loginemail">
						<div class="loginemail-label">' . _('Email') . ':</div>
						<div class="loginemail-value"><input type="email" autofocus="autofocus" required="required" class="required ' . (in_array('UserEmail',$Errors) ?  'error' : '' ) . '" name="UserEmail" size="20" maxlength="30" value="' . $_POST['UserEmail'] . '" /></div>
					</div>
					<div class="loginpassword">
						<div class="loginpassword-label">' . _('Password') . ':</div>
						<div class="loginpassword-value"><input type="password" required="required" class="required ' . (in_array('Password',$Errors) ?  'error' : '' ) . '" name="Password" size="15" maxlength="15" /></div>
					</div>
				</div>
				<div class="loginbutton"><input class="button" type="submit" name="Login" value="' . _('Login') . '" /></div>
				<br />
				<br />
				<br />';
				display_messages();
				echo '</form><!-- End of LoginForm -->
				</div><!-- End of login div -->
					<div id="register_button">
					<form id="RegisterForm" method="post" action="' . $RootPath . '/Register.php">
					<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
					<div class="registerright">
						<div class="regtxt">
							' . _('To enable us to process your order, we need some details. Please click on the register button below to setup your account.') . '</br>
						</div>
					</div>
					<div class="regbutton"><input class="button" type="submit" name="RegisterButton" value="' . _('Register') . '" /></div>
					</form>
				</div><!-- End of register_button div -->
				</div>';

	} elseif ((!isset($_SESSION['ConfirmedDeliveryAddress']) OR isset($_POST['ReCheckDeliveryAddress'])) AND $PhysicalDeliveryRequired == true) {

		/* if not yet confirmed the delivery address or hit the reconfirm delivery button */
		echo'<div id="confirm_delivery_address">
			<form id="ConfirmDeliveryAddress" method="post" action="' . $RootPath . '/Checkout.php">
			<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

		if (!isset($_POST['DeliveryAddress1'])){
			$_POST['DeliveryAddress1']=$_SESSION['CustomerDetails']['braddress1'];
		}
		if (!isset($_POST['DeliveryAddress2'])){
			$_POST['DeliveryAddress2']=$_SESSION['CustomerDetails']['braddress2'];
		}
		if (!isset($_POST['DeliveryAddress3'])){
			$_POST['DeliveryAddress3']=$_SESSION['CustomerDetails']['braddress3'];
		}
		if (!isset($_POST['DeliveryAddress4'])){
			$_POST['DeliveryAddress4']=$_SESSION['CustomerDetails']['braddress4'];
		}
		if (!isset($_POST['DeliveryAddress5'])){
			$_POST['DeliveryAddress5']=$_SESSION['CustomerDetails']['braddress5'];
		}
		if (!isset($_POST['DeliveryAddress6'])){
			$_POST['DeliveryAddress6']=$_SESSION['CustomerDetails']['braddress6'];
		}
		if (!isset($_POST['Phone'])){
			$_POST['Phone']=$_SESSION['CustomerDetails']['phoneno'];
		}
		if (!isset($_POST['ContactName'])){
			$_POST['ContactName']=$_SESSION['CustomerDetails']['contactname'];
		}
		if (!isset($_POST['OrderReference'])){
			if (isset($_SESSION['CustomerDetails']['orderreference'])){
				$_POST['OrderReference']=$_SESSION['CustomerDetails']['orderreference'];
			} else {
				$_POST['OrderReference']='';
			}
		}
		if (!isset($_POST['Comments'])){
			if (isset($_SESSION['CustomerDetails']['comments'])){
				$_POST['Comments']=$_SESSION['CustomerDetails']['comments'];
			} else {
				$_POST['Comments']='';
			}
		}
		if (!isset($_POST['Email'])){
			$_POST['Email']=$_SESSION['UsersEmail'];
		}
		echo '<h1>' . _('Checkout') . ' -> ' . _('Confirm Delivery Address') . '</h1>
			<div class="panel">
				<div class="row">
					<div class="row-left">
						<div class="row-label">' . _('Delivery Address - Building/Number') . ':</div>
						<div class="row-value"><input type="text" required="required" title="' . _('Enter the street address for the delivery') . '" class="required ' . (in_array('DeliveryAddress1',$Errors) ?  ' error' : '' ) . '" name="DeliveryAddress1" size="30" maxlength="30"  value="' . $_POST['DeliveryAddress1'] . '" /></div>
					</div>
					<div class="row-right">
						<div class="row-label">' . _('Contact Name') . ':</div>
						<div class="row-value"><input type="text" required="required" title="' . _('Enter the contact name at the delivery address') . '"  class="required ' . (in_array('ContactName',$Errors) ?  'error' : '' ) . '" name="ContactName" size="20" maxlength="20" value="' . $_POST['ContactName'] . '" /></div>
					</div>
				</div>
				<div class="row">
					<div class="row-left">
						<div class="row-label">' . _('Delivery Address - Street') . ':</div>
						<div class="row-value"><input type="text" required="required" title="' . _('Enter the street of the delivery address') . '" name="DeliveryAddress2" class="required ' . (in_array('DeliveryAddress2',$Errors) ?  ' error' : '' ) . '" size="30" maxlength="30"  value="' . $_POST['DeliveryAddress2'] . '" /></div>
					</div>
					<div class="row-right">
						<div class="row-label">' . _('Phone') . ':</div>
						<div class="row-value"><input type="tel" required="required" title="' . _('Enter the telephone number of the delivery address') . '" class="required ' . (in_array('Phone',$Errors) ?  'error' : '' ) . '" name="Phone" pattern="[0-9+()\-\s]*" size="20" maxlength="20"  value="' . $_POST['Phone'] . '" /></div>
					</div>
				</div>
				<div class="row">
					<div class="row-left">
						<div class="row-label">' . _('Delivery Address - Suburb') . ':</div>
						<div class="row-value"><input type="text" title="' . _('Enter the suburb of the delivery address') . '" ' . (in_array('Address3',$Errors) ?  'class="error"' : '' ) . ' name="DeliveryAddress3" size="30" maxlength="30" value="' . $_POST['DeliveryAddress3'] . '" /></div>
					</div>
					<div class="row-right">
						<div class="row-label">' . _('Your order reference / no') . ':</div>
						<div class="row-value"><input type="text" name="OrderReference" title="' . _('Enter your order reference') . '" size="20" maxlength="20"  value="' . $_POST['OrderReference'] . '" /></div>
					</div>
				</div>
				<div class="row">
					<div class="row-left">
						<div class="row-label">' . _('Delivery Address - City') . ':</div>
						<div class="row-value"><input type="text" required="required" title="' . _('Enter the city of the delivery address') . '" class="required ' . (in_array('DeliveryAddress4',$Errors) ?  ' error' : '' ) . '"  name="DeliveryAddress4" size="30" maxlength="30" value="' . $_POST['DeliveryAddress4'] . '" /></div>
					</div>
					<div class="row-right">
						<div class="row-label">' . _('Delivery Address - ZIP') . ':</div>
						<div class="row-value"><input type="text" name="DeliveryAddress5" required="required" title="' . _('Enter the zip code of the delivery address') . '" class="required ' . (in_array('DeliveryAddress5',$Errors) ?  ' error' : '' ) . '" size="10" maxlength="10"  value="' . $_POST['DeliveryAddress5'] . '" /></div>
					</div>
				</div>
				<div class="row">
					<div class="row-left">
						<div class="row-label">' . _('Country') . ':</div>
						<div class="row-value"><select title="' . _('Select the country for the delivery') . '" name="DeliveryAddress6" ' . (in_array('Address6',$Errors) ?  'class="error"' : '' ) . ' >';

		foreach ($CountriesArray as $CountryEntry => $CountryName){
			if (isset($_POST['DeliveryAddress6']) AND (strtoupper($_POST['DeliveryAddress6']) == strtoupper($CountryName))){
				echo '<option selected="selected" value="' . $CountryName . '">' . $CountryName .'</option>';
			}elseif (!isset($_POST['DeliveryAddress6']) AND $CountryName == "") {
				echo '<option selected="selected" value="' . $CountryName . '">' . $CountryName .'</option>';
			} else {
				echo '<option value="' . $CountryName . '">' . $CountryName .'</option>';
			}
		}
		echo '</select></div>
				</div>
				<div class="row-right">
					<div class="row-label">' . _('Email Address') . ':</div>
					<div class="row-value"><input type="email" ' . (in_array('Address6',$Errors) ?  'class="error"' : '' ) . ' title="' . _('Enter email address of a contact person at the delivery address') . '" name="Email" size="30" maxlength="50"  value="' . $_POST['Email'] . '" /></div>
				</div>
		</div>
		<div class="row">
				<div class="row-label">' . _('Comments') . ':</div>
				<div class="row-value"><input type="text" title="' . _('Enter any comments you wish to note about the order') . '" name="Comments" size="30" maxlength="50"  value="' . $_POST['Comments'] . '" /></div>
		</div>';
		if (isset($_SESSION['MessageLog']) AND count($_SESSION['MessageLog'])>0){
			echo '<div class="row">';
			display_messages();
			echo '</div>';
		}
		echo '<div class="row"><input class="button" type="submit" name="ConfirmDeliveryAddress" value="' . _('Confirm Delivery Details') . '" /></div>
			</div>
		</form><!-- End of ConfirmDeliveryAddressForm -->
		</div><!-- End of confirm_delivery_address div --> ';

	} elseif ($_SESSION['ShopFreightMethod']!='NoFreight' AND $_SESSION['FreightCost'] == 'NOT AVAILABLE') { //report the freight calculation problem


		if ($_SESSION['ShopFreightMethod']=='webERPCalculation') {
			unset($_SESSION['ConfirmedDeliveryAddress']);

			echo'<div id="revise_delivery_address">
				<form id="ReviseDeliveryAddress" method="post" action="' . $RootPath . '/Checkout.php">
				<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
				<table>
					<tr>
						<td colspan="4"><h1>' . _('Checkout') . ' -> ' . _('Revise Delivery Address') . '</h1></td>
					</tr>
					<tr>
						<td colspan="4"><h2>' . _('Regrettably, there was a problem calculating freight costs for your chosen destination.') . '</h2></td>
					</tr>
					<tr>
						<td colspan="4"><h2>' . _('Please revise your delivery address.') . '</h2></td>
					</tr>
					<tr>
						<td colspan="4"><h2>' . _('If your address is correct, please report this issue and we will contact you ASAP by email to continue the process.') . '</h2></td>
					</tr>
					<tr>
						<td colspan="2" class="center">
						<input class="button" type="submit" name="ReviseDeliveryAddress" value="' . _('Revise Delivery Details') . '" />
					</td>
					<td colspan="2" class="center">
						<input class="button" type="submit" name="SendEMailErrorDeliveryAddress" value="' . _('My address is correct. Report this problem') . '" />
					</td>
				</tr>
				</table>
			</form><!-- End of ReviseDeliveryAddressForm -->
			</div><!-- End of Revise_delivery_address div --> ';
		}
	} elseif ($_SESSION['CustomerDetails']['creditcustomer']==false AND (!isset($_SESSION['SelectedPaymentMethod']) OR isset($_POST['ReSelectPaymentMethod']))) {
		echo '<div id="payment_method">
			<form id="SelectPaymentMethodForm" method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">
			<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
			<h1>' . _('Checkout') . ' -> ' . _('Select Payment Method') . '</h1>
			<div class="row"><h2> ' . _('Delivery Address') . '</h2></div>
				<div class="row">
					<div class="row-label">' . _('Street') . ':</div>
					<div class="row-value">' . $_SESSION['CustomerDetails']['braddress1'] . '</div>
				</div>
				<div class="row">
					<div class="row-label">' . _('Street') . ':</div>
					<div class="row-value">' . $_SESSION['CustomerDetails']['braddress2'] . '</div>
				</div>
				<div class="row">
					<div class="row-label">' . _('Suburb') . ':</div>
					<div class="row-value">' . $_SESSION['CustomerDetails']['braddress3'] . '</div>
				</div>
				<div class="row">
					<div class="row-label">' . _('City') . ':</div>
					<div class="row-value">' . $_SESSION['CustomerDetails']['braddress4'] . '</div>
				</div>
				<div class="row">
					<div class="row-label">' . _('ZIP') . ':</div>
					<div class="row-value">' . $_SESSION['CustomerDetails']['braddress5'] . '</div>
				</div>
				<div class="row">
					<div class="row-label">' . _('Country') . ':</div>
					<div class="row-value">' . $_SESSION['CustomerDetails']['braddress6'] . '</div>
				</div>
				<div class="row"><input class="button" type="submit" name="ReCheckDeliveryAddress" value="' . _('Change Delivery Details') . '" /></div>
				<div class="row">
					<div class="row-headings">
						<div class="paymethod_column"><h3>' . _('Choose Your Preferred Payment Method') . '</h3></div>';
		if ($_SESSION['ShopAllowSurcharges']=='1') {
			echo '<div class="surcharge_column"><h3>' . _('Surcharge') . '</h3></div>';
		}
		echo '</div><!--end row-headings -->
			</div><!--end row -->';

		foreach ($PaymentMethods as $Method=>$PaymentMethodArray) {
			echo '<div class="payoptions">';

			if (isset($_SESSION['SelectedPaymentMethod']) AND $_SESSION['SelectedPaymentMethod'] == $Method){
				echo '<div class="paymethod_column"><input type="radio" name="PaymentMethod" checked="checked" value="' . $Method . '" />';
			} else {
				echo '<div class="paymethod_column"><input type="radio" name="PaymentMethod" value="' . $Method . '" />';
			}
			echo '&nbsp;' . $PaymentMethodArray['MethodName'];
			if ($Method == 'PayPal') {
				echo '<br /><image src="css/paypal_small.jpg" alt="' . _('Pay By PayPal') . '" title="' . _('Pay By PayPal') . '" />';
			} elseif ($PaymentMethodArray['MethodName'] == 'Credit Card') {
				echo '<br /><image src="css/credit_card.gif" alt="' . _('Pay By Credit Card') . '" title="' . _('Pay By Credit Card') . '" />';
			}
			echo '</div><!-- end div paymethod_column -->';

			if ($_SESSION['ShopAllowSurcharges'] == '1') {
				echo '<div class="surcharge_column">' . locale_number_format($PaymentMethodArray['Surcharge'],1) . '&nbsp;%&nbsp;&nbsp;</div>';
			}
			echo '</div><!-- end div payoptions -->';
		}

		echo '<div class="row center">
				<input class="button" type="submit" name="ReCheckDeliveryAddress" value="' . _('Back to Delivery Details') . '" />';
		if ($_SESSION['ShopFreightMethod']!='NoFreight') {
			echo '<input class="button" type="submit" name="ReviewFreightMethod" value="' . _('Review Freight Method and Charges') . '" />';
		}
		echo '<input id="select_payment_method" class="button" type="submit" name="SelectPaymentMethod" value="' . _('Confirm Selected Payment Method') . '" />
			</div><!-- end row div -->
			</form>
			</div>'; //end payment_method div
	} else { //we are into the payment/order confirmation
		echo '<div id="payment_confirmation">
				<form id="PaymentConfirmForm" method="post" action="' . $RootPath . '/Checkout.php">
				<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

		if ($_SESSION['CustomerDetails']['creditcustomer']==false) { //don't insult the customer with payment options if they have a credit account
			echo '<h1>' . _('Checkout') . ' -> ' . _('Payment and Order Confirmation') . '</h1>';
		} else {
			echo '<h1>' . _('Order Confirmation') . '</h1>';
		}
		include('includes/DisplayShoppingCart.php');

		switch ($_SESSION['SelectedPaymentMethod']) {
			case 'PayPal':
				if (!isset($_SESSION['Paid'])){
					echo '<div class="row">
							<input class="button" type="submit" name="PayByPayPal" title="' . _('Pay By PayPal') . '"  value="' . _('Make payment from your PayPal Account') . '" />
						</div>';
					if ($_SESSION['ShopMode']=='test'){
						message_log(_('The shop is in TEST MODE - no payments will be processed'),'error');
						echo '<div class="row">';
						display_messages();
						echo '</div>';
					}
				}
				echo '<div class="row center">';
				display_messages();
				echo '</div>';
				if (isset($_SESSION['Paid']) AND $_SESSION['Paid']==true){
					ResetForNewOrder();
				}
				break;

			case 'PayFlow': //Credit Card
				$URL = 'https://' . $_SERVER['HTTP_HOST'] . $RootPath . '/CreditCardPayFlowPro.php';
				//note no break ... do other stuff below too
			case 'PayPalPro': //Credit Card
				if (!isset($URL)){
					$URL = 'https://' . $_SERVER['HTTP_HOST'] . $RootPath . '/CreditCardPayPalPro.php';
				}
				//note no break ... do other stuff below too
			case 'SwipeHQ': //Credit Card
				if (!isset($URL)){
					$URL = 'https://payment.swipehq.com/?identifier_id=' . $_SESSION['SwipeHQIdentifierID'];
				}
				if (!isset($_SESSION['Paid'])){
					echo '<div class="row">
							<a class="link_button" href="' . $URL . '">' . _('Pay By Credit Card') . '</a>
						</div>';
				} //end if not paid
				echo '<div class="row center">';
				display_messages();
				echo '</div>';
				if (isset($_SESSION['Paid']) AND $_SESSION['Paid']==true){
					ResetForNewOrder();
				}
				break;
			case 'CreditAccount': //if the customer is defined with payment terms this is defaulted
				if (!isset($_SESSION['OrderPlaced'])){
					echo '<tr>
							<td colspan="3"></td>
							<td colspan="6"><input class="button" type="submit" name="PlaceOrder" value="' . _('Confirm and Place Order') . '" /></td>
						</tr>';
				} else {
					echo '<tr>
							<td colspan="4">' . _('Thank you for purchasing from us. Please note the order number') . ': ' . $OrderNo . ' ' . _('for your reference') . '</td>
						</tr>';
					ResetForNewOrder();
				}
				break;
			case 'BankTransfer':
				if (!isset($_SESSION['OrderPlaced'])){
					echo '<tr>
							<td colspan="4"><input class="button" type="submit" name="PlaceOrder" value="' . _('Confirm and Place Order') . '" /></td>
						</tr>';
					if (isset($OrderError) AND $OrderError==true){
						echo '<div class="row">' . _('No order has been created due to an internal error. Please contact us to advise.');
						display_messages();
						echo '</div>';
					}

				} else {
					echo '<div class="row">' . _('Thank you for your order. Please note the order number') . ': ' . $OrderNo . ' ' . _('for your reference') . '</div>
						<div class="row">' . display_messages() . '</div>';
					$BankTransferMessage = _('Please deposit the amount due (in full) to our bank account.');

					if ($_SESSION['ShopManagerEmail'] != ''){
						$BankTransferMessage .= ' ' . _('To speed up the process, please send us a scanned copy of your transfer slip to') . ' ' . $_SESSION['ShopManagerEmail'] . '. <br />';
					}
					echo '<div class="row">' . $BankTransferMessage . '<br />';
					echo ShowBankDetails($OrderNo);
					echo '</div>
						<div class="row">' . _('Please note that goods will be shipped once your funds transfer is confirmed') . '</div>';
					ResetForNewOrder();
				}
				break;
		}
		if (isset($_SESSION['ShoppingCart'])){
			// We still are processing a Shopping Cart
			echo '<div class="row center">
						<input class="button" type="submit" name="ReCheckDeliveryAddress" value="' . _('Back to Delivery Details') . '" />
						<input class="button" type="submit" name="ReSelectPaymentMethod" value="' . _('Change Payment Method') . '" />
				</div>';
		} else{
			// We just finished the process and Shopping Cart is empty
				echo '<div class="row center">
						<a class="link_button" href="index.php">' . _('Back to Shop Home Page') . '</a>
					</div>';
		}
		echo '</form>';

	}//end if user logged in - checkout proper
} //end of if there is something in the shopping cart to checkout

echo '</div>'; //end content_inner
echo '</div>'; //end content_inner
echo '</div>'; //end content_main
//echo '</div>'; //end content_block
include ('includes/footer.php');
?>
