<?php
/*Credit Card ssl form for collection of credit card details and submission to bank */
if($_SERVER['SERVER_PORT'] != 443) {
	header('HTTP/1.1 301 Moved Permanently');
	header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
	exit();
}

include('includes/DefineCartItemClass.php'); //must be before header.php
include('includes/config.php');
include('includes/session.php');
include('includes/Functions.php');
include($PathPrefix .'includes/CountriesArray.php');

$Title = _('Secure Credit Card Payment');
$Errors= array();
$_SESSION['Paid'] = false;
//validation
if (isset($_POST['PayByCreditCard'])){
	$InputError = 0;
	if(!validate_credit_card_number($_POST['CardNumber'])){
		message_log(_('The credit card number does not validate as a mastercard, visa or american express card number'),'error');
		$Errors[] = 'CardNumber';
		$InputError = 1;
	}
	if (mktime(1,1,1,intval($_POST['ExpiryMonth']),1,intval($_POST['ExpiryYear'])) < time()){
		message_log(_('The credit card appears to have expired, please check the expiry month and year'),'error');
		$Errors[] = 'ExpiryMonth';
		$Errors[] = 'ExpiryYear';
		$InputError = 1;
	}
	if (!is_numeric($_POST['Cvv'])){
		message_log(_('The credit verification value must be numeric'),'error');
		$Errors[] = 'Cvv';
		$InputError = 1;
	}
	if (mb_strlen($_POST['FirstName']) < 3) {
		message_log(_('The first name must be at least 3 characters long'),'error');
		$Errors[] = 'FirstName';
		$InputError = 1;
	}
	if (mb_strlen($_POST['LastName']) < 3) {
		message_log(_('The last name must be at least 3 characters long'),'error');
		$Errors[] = 'LastName';
		$InputError = 1;
	}
	if (mb_strlen($_POST['Street']) < 3) {
		message_log(_('The street must be at least 3 characters long'),'error');
		$Errors[] = 'Street';
		$InputError = 1;
	}
	if (mb_strlen($_POST['City']) < 3) {
		message_log(_('The city must be at least 3 characters long'),'error');
		$Errors[] = 'City';
		$InputError = 1;
	}
	if (mb_strlen($_POST['State']) < 2) {
		message_log(_('The state must be at least 2 characters long'),'error');
		$Errors[] = 'State';
		$InputError = 1;
	}
	if (mb_strlen($_POST['Zip']) < 3) {
		message_log(_('The zip code must be at least 3 characters long'),'error');
		$Errors[] = 'Zip';
		$InputError = 1;
	}

	if ($InputError==0) { //no input errors reported so get on with the payment

		if ($_POST['ExpiryMonth'] < 10){
			$FormattedExpiryDate = '0' . $_POST['ExpiryMonth'] . $_POST['ExpiryYear'];
		} else {
			$FormattedExpiryDate = $_POST['ExpiryMonth'] . $_POST['ExpiryYear'];
		}  
		$PayPalData = '&IPADDRESS='.urlencode($_SERVER['REMOTE_ADDR']) .
					'&PAYMENTACTION=SALE' .
					'&CREDITCARDTYPE=' . urlencode(credit_card_type($_POST['CardNumber'])) .
					'&ACCT=' . urlencode($_POST['CardNumber']) .
					'&EXPDATE=' . urlencode($FormattedExpiryDate) .
					'&CVV2=' . urlencode($_POST['Cvv']) .
					'&AMT=' . urlencode(number_format($_SESSION['TotalDue'],2)).
					'&CURRENCYCODE=' . urlencode($_SESSION['CustomerDetails']['currcode']) .
					'&FIRSTNAME=' . urlencode($_POST['FirstName']) .
					'&LASTNAME=' . urlencode($_POST['LastName']) .
					'&STREET=' . urlencode($_POST['Street']) .
					'&CITY=' . urlencode($_POST['City']) .
					'&STATE=' . urlencode($_POST['State']) .
					'&COUNTRYCODE=' . urlencode($_POST['CountryCode']) .
					'&ZIP=' . urlencode($_POST['Zip']) .
					'&DESC=' . urlencode($_SESSION['ShopName'] . ' ' . _('Purchases'));
	
		//We need to execute the "DoExpressCheckoutPayment" at this point to Receive payment from user.
		$CreditCardResponseArray = pay_pal_request('DoDirectPayment', $PayPalData, $_SESSION['ShopPayPalProUser'],$_SESSION['ShopPayPalProPassword'],$_SESSION['ShopPayPalProSignature']);

		//Check if everything went ok..
		if(strtoupper($CreditCardResponseArray['ACK']) == 'SUCCESS' OR strtoupper($CreditCardResponseArray['ACK']) == 'SUCCESSWITHWARNING'){
			$TransactionID = urldecode($CreditCardResponseArray['TRANSACTIONID']);
			$_SESSION['Paid'] = true;
			include('includes/PlaceOrder.php');
			message_log(_('Thanks for your order. Please quote your order number') . ': ' . $OrderNo . ' ' . _('in all correspondence')  . '<br />' . _('Credit card payment has been successfully completed with the transaction ID') . ': ' . $TransactionID,'success');

			InsertCustomerReceipt($_SESSION['ShopCreditCardBankAccount']);
			
			if ($debug==1){
				$Message ='';
				foreach ($CreditCardResponseArray as $Key=>$Response) {
					$Message .= '<br />' . $Key . ' = ' . urldecode($Response);
				}
				message_log($Message,'info');
			}
			header('Location: http://' . $_SERVER['HTTP_HOST'] . $RootPath . '/Checkout.php');
			exit();
		} else { //there was an error completing the payment
			message_log(_('Credit cart payment was unsuccesful - the request returned the error:') . ' '. urldecode($CreditCardResponseArray['L_LONGMESSAGE0'] .'<br />' . _('Please either try again with a different card or use a different payment method')), 'error');
			if ($debug==1){
				$ResponseText ='';
				foreach ($CreditCardResponseArray as $ResponseVariable => $ResponseValue) {
					$ResponseText .= '<br />' . $ResponseVariable . ' => ' . urldecode($ResponseValue);
				}
				message_log($ResponseText,'info');
			}
		}
	}
}
include('includes/header.php');


?>
<script>
	jQuery(document).ready(function() {
			/* Focus on user name input field*/
		jQuery('#CreditCardForm').validate({
			rules: {
				FirstName: {
					minlength: 3
				},
				LastName: {
					minlength: 3
				},
				Street: {
					minlength: 3
				},
				City: {
					minlength: 3
				},
				State: {
					minlength: 3
				},
				Zip: {
					minlength: 3
				},
				Cvv:{
					minlength: 3
				}
			},
			messages: {
				FirstName: {
					minlength: "<?php echo _('At least 3 characters') ?>"
				},
				LastName: {
					minlength: "<?php echo _('At least 3 characters') ?>"
				},
				Street: {
					minlength: "<?php echo _('At least 3 characters') ?>"
				},
				City: {
					minlength: "<?php echo _('At least 3 characters') ?>"
				},
				State: {
					minlength: "<?php echo _('At least 3 characters') ?>"
				},
				Zip: {
					minlength: "<?php echo _('At least 3 characters') ?>"
				},
				CardNumber: {
					creditcard: "<?php echo _('A valid credit card number must be entered') ?>"
				},
				Cvv: {
					digits: "<?php echo _('3 or 4 (for AMEX) digits are expected') ?>",
					minlength: "<?php echo _('3 or 4 (for AMEX) digits are expected') ?>"
				}
			},
			errorPlacement: function(error, element) {
				error.insertAfter(element);
				error.wrap('<p>');
			} // end errorPlacement
		});
		jQuery('#CardNumber').keyup(function(){
			var CardNumber = jQuery('#CardNumber').val();
			if (CardNumber.substring(0,2) == '51' || CardNumber.substring(0,2) == '52' || CardNumber.substring(0,2) == '53' || CardNumber.substring(0,2) == '54' || CardNumber.substring(0,2) == '55'){
				jQuery('#CardImage').attr('src','css/mastercard.jpg');
			}
			if (CardNumber.substring(0,1) == '4'){
				jQuery('#CardImage').attr('src','css/visa.jpg');
			}
			if (CardNumber.substring(0,2) == '37'){
				jQuery('#CardImage').attr('src','css/amex.jpg');
			}
			if (CardNumber.length==0){
				jQuery('#CardImage').attr('src','');
			}
		});
		jQuery('#CreditCardForm :text:first').focus();

		jQuery('#TermsAndConditions').click(function() {
			jQuery('#content_block').html('<?php echo '<h1>' . _('Terms and Conditions') . '</h1>' . html_entity_decode($_SESSION['ShopTermsConditions']) ?>');
			return false;
		});
		jQuery('#AboutUs').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('About Us') . '</h1>' . html_entity_decode($_SESSION['ShopAboutUs']) ?>');
			return false;
		});
		jQuery('#PrivacyPolicy').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Privacy Policy') . '</h1>' . html_entity_decode($_SESSION['ShopPrivacyStatement']) ?>');
			return false;
		});
		jQuery('#ContactUs').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Contact Details') . '</h1>' . html_entity_decode($_SESSION['ShopContactUs']) ?>');
			return false;
		});
		jQuery('#cart_summary').click(function(){
			jQuery('#content_block').load('index.php?Page=ShoppingCart' + ' #content_block');
			return false;
		});
		jQuery('#SelectPaymentMethodForm :radio').click(function(){
			jQuery('#select_payment_method').trigger('click');
		});
		
		}
	);
</script>

<?php

$MenuLinksHtml = display_sub_categories('','');//recursive function to display through all levels of categories defined
//menu_block - showing category link buttons
echo '<div id="menu_block">' . $MenuLinksHtml . '</div>
	<div id="content_block">';

include('includes/InfoLinks.php'); //at the bottom

if (!isset($_POST['FirstName'])){
	$CustomerNameArray = explode(' ',$_SESSION['UsersRealName']);
	if (count($CustomerNameArray)==2){
		$_POST['FirstName'] = $CustomerNameArray[0];
		$_POST['LastName'] = $CustomerNameArray[1];
	}
}
if (!isset($_POST['Street'])){
	$_POST['Street'] = $_SESSION['CustomerDetails']['braddress1'];
}
if (!isset($_POST['City'])){
	$_POST['City'] = $_SESSION['CustomerDetails']['braddress2'];
}
if (!isset($_POST['State'])){
	$_POST['State'] = $_SESSION['CustomerDetails']['braddress3'];
}
if (!isset($_POST['Zip'])){
	$_POST['Zip'] = $_SESSION['CustomerDetails']['braddress4'];
}
if (!isset($_POST['CountryCode'])){
	$_POST['CountryCode'] = array_search($_SESSION['CustomerDetails']['braddress6'],$CountriesArray);
}



echo '<div id="credit_card_info">
		<form id="CreditCardForm" method="post" action="'. htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" >
		<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
	<table width="100%">
		<tr>
			<th id="column_heading" colspan="4"><image src="css/secure.png" alt="" />&nbsp;&nbsp;' . _('Secure Credit Card Payment') . '&nbsp;&nbsp;<image src="css/secure.png" alt="" /></th>
		</tr>
		<tr>
			<td><label for="FirstName" ' . (in_array('FirstName',$Errors) ?  'class="error"' : '' ) . '>' . _('First Name') . ':</label></td>
			<td><input type="text" name="FirstName" id="FirstName"  class="required' . (in_array('FirstName',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="20" size="20" title="' . _('The first name of the credit card holder') . '" value="' . (isset($_POST['FirstName']) ? $_POST['FirstName'] : '' ) . '" /></td>
			<td><label for="LastName" ' . (in_array('LastName',$Errors) ?  'class="error"' : '' ) . '>' . _('Last Name') . ':</label></td>
			<td><input type="text" name="LastName" id="LastName"  class="required' . (in_array('LastName',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="20" size="20" title="' . _('The last name of the credit card holder') . '" value="' . (isset($_POST['LastName']) ? $_POST['LastName'] : '' ) . '" /></td>
		</tr>
		<tr>
			<td><label for="Street" ' . (in_array('Street',$Errors) ?  'class="error"' : '' ) . '>' . _('Street') . ':</label></td>
			<td><input type="text" name="Street" id="Street"  class="required' . (in_array('Street',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="20" size="20" title="' . _('The street of the card holder') . '" value="' . (isset($_POST['Street']) ? $_POST['Street'] : '' ) . '" /></td>
			<td><label for="City" ' . (in_array('City',$Errors) ?  'class="error"' : '' ) . '>' . _('City') . ':</label></td>
			<td><input type="text" name="City" id="City"  class="required' . (in_array('City',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="15" size="15" title="' . _('The city of the card holder') . '" value="' . (isset($_POST['City']) ? $_POST['City'] : '' ) . '" /></td>
		</tr>
		<tr>
			<td><label for="State" ' . (in_array('State',$Errors) ?  'class="error"' : '' ) . '>' . _('State') . ':</label></td>
			<td><input type="text" name="State" id="State"  class="required' . (in_array('State',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="15" size="15" title="' . _('The state of the card holder') . '" value="' . (isset($_POST['State']) ? $_POST['State'] : '' ) . '" /></td>
			<td><label for="Zip" ' . (in_array('Zip',$Errors) ?  'class="error"' : '' ) . '>' . _('Zip Code') . ':</label></td>
			<td><input type="text" name="Zip" id="Zip"  class="required' . (in_array('Zip',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="5" size="5" title="' . _('The Zip of the card holder') . '" value="' . (isset($_POST['Zip']) ? $_POST['Zip'] : '' ) . '" /></td>
		</tr>
		<tr>
			<td class="center" colspan="2" rowspan="2"><img id="CardImage" src=""></td>
			<td><label for="CountryCode" ' . (in_array('CountryCode',$Errors) ?  'class="error"' : '' ) . '">' . _('Country') . ':</label></td>
			<td><select name="CountryCode" id="CountryCode" title="' . _('The Country of the card holder') . '" class="required' . (in_array('CountryCode',$Errors) ?  'error' : '' ) . '" >';

			foreach ($CountriesArray as $CountryCode => $CountryName) {
				if ($_POST['CountryCode'] == $CountryCode){
					echo '<option selected="selected" value="' . $CountryCode . '">' . $CountryName . '</option>';
				} else {
					echo '<option value="' . $CountryCode . '">' . $CountryName . '</option>';
				}
			}
			echo '</select></td>
		</tr>
		<tr>
			<td><label for="CardNumber" ' . (in_array('CardNumber',$Errors) ?  'class="error"' : '' ) . '>' . _('Card Number') . ':</label></td>
			<td><input type="text" name="CardNumber" id="CardNumber" class="required creditcard' . (in_array('CardNumber',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="16" size="17" title="' . _('Enter the credit card number with no spaces or hyphens') . '" value="' . (isset($_POST['CardNumber']) ? $_POST['CardNumber'] : '' ) . '" /></td>
		</tr>
		<tr>
			<td><label for="ExpiryYear"' . (in_array('ExpiryYear',$Errors) ?  'class="error"' : '' ) . '>' . _('Expiry Date') . ':</label></td>
			<td><select name="ExpiryYear" id="ExpiryYear" class="required' . (in_array('ExpiryMonth',$Errors) ?  ' error' : '' ) . '" title="' . _('Select the year of your credit card\'s expiry date') . '" >';
	$i=0;
	$Year = intval(Date('Y'));
	while ($i<10){
		if (!isset($_POST['Year'])) {
			echo '<option value="' . ($Year + $i) . '">' . ($Year + $i) . '</option>';
		} else {
			echo '<option ' . ($_POST['ExpiryYear']==$i+1 ? 'selected="selected"' : '' ) . ' value="' . ($Year + $i) . '">' . ($Year + $i) . '</option>';
		}
		$i++;
	}
	echo '</select>
			&nbsp;/&nbsp;&nbsp;<select name="ExpiryMonth" id="ExpiryMonth" class="required' . (in_array('ExpiryMonth',$Errors) ?  ' error' : '' ) . '" title="' . _('Select the month of your credit card\'s expiry date') . '" >';
	$i=1;
	while ($i<13){
		if (!isset($_POST['ExpiryMonth'])) {
			echo '<option ' . (Date('m')==$i ? 'selected="selected"' : '' ) . ' value="' . $i . '">' . $i . '</option>';
		} else {
			echo '<option ' . ($_POST['ExpiryMonth']==$i ? 'selected="selected"' : '' ) . ' value="' . $i . '">' . $i . '</option>';
		}
		$i++;
	}
	echo '</select></td>
		<td colspan="2" class="center"><img src="css/cvv.jpg"></td>
		</tr>
		<tr>
			<td>' . _('Amount') . ' ' . $_SESSION['CustomerDetails']['currcode'] . ':</td>
			<td>' . number_format($_SESSION['TotalDue'],$_SESSION['CustomerDetails']['currdecimalplaces']) . '</td>
			<td><label for="Cvv" ' . (in_array('Cvv',$Errors) ?  'class="error"' : '' ) . '">' . _('Verification Code') . ':</label></td>
			<td><input type="text" name="Cvv" id="Cvv" title="' . _('Enter the 3 digit verification code or CVV - note that on AMEX cards this is a 4 digit code') . '" autocomplete="off" class="required digits' . (in_array('Cvv',$Errors) ?  'error' : '' ) . '" value="' . (isset($_POST['Cvv']) ? $_POST['Cvv'] : '' ) . '" maxlength="4" size="5" /></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2"></td>
			<td colspan="2" class="center"><input class="button" type="submit" name="PayByCreditCard" title="' . _('Pay By Credit Card') . '"  value="' . _('Submit Details and Process Payment') . '"></td>
		</tr>
		</table>
		</div><!-- end credit_card_info --!>
		<br />';
		display_messages();

echo '</div>'; //end content_block
include ('includes/footer.php');
?>
