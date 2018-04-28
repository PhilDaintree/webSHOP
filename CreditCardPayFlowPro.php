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

$Title = _('Secure Credit Card Payment Using Pay Flow Pro');
$Errors= array();

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
	
	if ($InputError==0) { //no input errors reported so get on with the payment

		if ($_POST['ExpiryMonth'] < 10){
			$FormattedExpiryDate = '0' . $_POST['ExpiryMonth'] . $_POST['ExpiryYear'];
		} else {
			$FormattedExpiryDate = $_POST['ExpiryMonth'] . $_POST['ExpiryYear'];
		}
		$CharsToReplace = array('-', ' ', '/', '\\', '"', "'", '=','&');
		  
		$PayFlowString = 	'HOSTPORT=443' .
							'&USER=' . $_SESSION['ShopPayFlowUser'] .
							'&VENDOR=' . $_SESSION['ShopPayFlowVendor'] .
							'&PARTNER=' . $_SESSION['ShopPayFlowMerchant'] . 
							'&PWD=' . $_SESSION['ShopPayFlowPassword'] .
							'&TRXTYPE=S' .
							'&TENDER=C' .
							'&COMMENT1=' . $_SESSION['ShopDebtorNo'] .
							'&COMMENT2=' . str_replace($CharsToReplace,'_',$_SESSION['CustomerDetails']['orderreference']) .
							'&ACCT=' . $_POST['CardNumber'] .
							'&EXPDATE=' . $FormattedExpiryDate .
							'&CVV2=' . $_POST['Cvv'] .
							'&AMT=' . number_format($_SESSION['TotalDue'],2,'.','') .
							'&TIMEOUT=60' .
							'&CLIENTIP=' . $_SERVER['REMOTE_ADDR'] .
							'&CURRENCY=' . $_SESSION['CustomerDetails']['currcode'] .
							'&VERBOSITY=HIGH';

		if($_SESSION['ShopMode']=='test'){
			$API_Endpoint = 'https://pilot-payflowpro.paypal.com';
		} else {
			$API_Endpoint = 'https://payflowpro.paypal.com';
		}

		$Headers = array();
      
		$Headers[] = "Content-Type: text/namevalue"; //or maybe text/xml
		$Headers[] = "X-VPS-Timeout: 30";
		$Headers[] = "X-VPS-VIT-OS-Name: Linux";  // Name of your OS
		//$Headers[] = "X-VPS-VIT-OS-Version: RHEL 4";  // OS Version
		$Headers[] = "X-VPS-VIT-Client-Type: PHP/cURL";  // What you are using
		$Headers[] = "X-VPS-VIT-Client-Version: 0.01";  // For your info
		$Headers[] = "X-VPS-VIT-Client-Architecture: x86";  // For your info
		//$Headers[] = "X-VPS-VIT-Client-Certification-Id: " . $this->ClientCertificationId . ""; // get this from payflowintegrator@paypal.com
		$Headers[] = "X-VPS-VIT-Integration-Product: webERP Shop By Logic Works Ltd";  // For your info, would populate with application name
		$Headers[] = "X-VPS-VIT-Integration-Version: 0.01"; // Application version    
		//$Headers[] = "X-VPS-Request-ID: " . $request_id;
		
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $Headers);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");// play as Mozilla
		curl_setopt($ch, CURLOPT_HEADER, 1); // tells curl to include headers in response
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
		curl_setopt($ch, CURLOPT_TIMEOUT, 45); // times out after 45 secs
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // this line makes it work under https
		curl_setopt($ch, CURLOPT_POSTFIELDS, $PayFlowString); //adding POST data
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2); //verifies ssl certificate
		curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); //forces closure of connection when done 
		curl_setopt($ch, CURLOPT_POST, 1); //data sent as POST 
		
		$result = curl_exec($ch);
		$Headers = curl_getinfo($ch);
		curl_close($ch);
		
		$ResponseArray = array(); //result arrray
		if (empty($result)){
			message_log(_('Pay Flow payment failed'),'error');
		};

		$result = strstr($result, 'RESULT');    
		$ValuesArray = explode('&', $result);
		foreach($ValuesArray as $ResultValue) {
			$ValueArray2 = explode('=', $ResultValue);
			$ResponseArray[$ValueArray2[0]] = $ValueArray2[1];
		}

		if ($debug==1) {
			message_log(_('Sent the Pay Flow Pro Request') . '<br />' . $PayFlowString,'info');
		}
		if ($debug==1) {
			$Message ='';
			foreach ($ResponseArray as $Key=>$Response) {
				$Message .= '<br />' . $Key . ' = ' . urldecode($Response);
			}
			message_log($Message,'error');
		}
		if(sizeof($ResponseArray) == 0 OR $ResponseArray['RESULT']!=0) {
			message_log(_('Invalid Pay Flow response. Unable to pay using Pay Flow Credit Card Processing'),'error');
			if ($debug==1) {
				$Message ='';
				foreach ($ResponseArray as $Key=>$Response) {
					$Message .= '<br />' . $Key . ' = ' . urldecode($Response);
				}
				message_log($Message,'error');
			}
		} else { //transaction successful
			$_SESSION['Paid'] = true;
			$TransactionID = $ResponseArray['PNREF'];
			include('includes/PlaceOrder.php');
			message_log(_('Thanks for your order. Please quote your order number') . ': ' . $OrderNo . ' ' . _('in all correspondence')  . '<br />' . _('Credit card payment has been successfully completed with the transaction ID') . ': ' . $TransactionID, 'success');
			InsertCustomerReceipt($_SESSION['ShopCreditCardBankAccount'], $TransactionID, $OrderNo);
			
			if ($debug==1){
				$Message ='';
				foreach ($CreditCardResponseArray as $Key=>$Response) {
					$Message .= '<br />' . $Key . ' = ' . urldecode($Response);
				}
				message_log($Message,'info');
			}
			header('Location: http://' . $_SERVER['HTTP_HOST'] . $RootPath . '/Checkout.php');
			exit();
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
				Cvv:{
					minlength: 3
				}
			},
			messages: {
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
		jQuery('#CardNumber, #Cvv').bind('input', function() {
			jQuery(this).val($(this).val().replace(/[^0-9]/gi, ''));
		});
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

		}
	);
</script>

<?php

$MenuLinksHtml = display_sub_categories('','');//recursive function to display through all levels of categories defined
//menu_block - showing category link buttons
echo '<div id="menu_block">' . $MenuLinksHtml . '</div>
	<div id="content_block">';

include('includes/InfoLinks.php'); //at the bottom

echo '<div id="credit_card_info">
		<form id="CreditCardForm" method="post" action="'. htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" >
		<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
	<table width="100%">
		<tr>
			<th id="column_heading" colspan="5"><image src="css/secure.png" alt="" />&nbsp;&nbsp;' . _('Secure Credit Card Payment') . '&nbsp;&nbsp;<image src="css/secure.png" alt="" /></th>
		</tr>
		<tr>
			<td><label for="CardNumber" ' . (in_array('CardNumber',$Errors) ?  'class="error"' : '' ) . '>' . _('Card Number') . ':</label></td>
			<td><input type="text" name="CardNumber" id="CardNumber" class="required creditcard' . (in_array('CardNumber',$Errors) ?  ' error' : '' ) . '" autocomplete="off" maxlength="16" size="17" title="' . _('Enter the credit card number with no spaces or hyphens') . '" value="' . (isset($_POST['CardNumber']) ? $_POST['CardNumber'] : '' ) . '" /></td>
			<td class="center"><img id="CardImage" src=""></td>
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
		<td rowspan="2" class="center"><img src="css/cvv.jpg"></td>
		</tr>
		<tr>
			<td><label for="Cvv" ' . (in_array('Cvv',$Errors) ?  'class="error"' : '' ) . '">' . _('Verification Code') . ':</label></td>
			<td><input type="text" name="Cvv" id="Cvv" title="' . _('Enter the 3 digit verification code or CVV - note that on AMEX cards this is a 4 digit code') . '" autocomplete="off" class="required digits' . (in_array('Cvv',$Errors) ?  'error' : '' ) . '" value="' . (isset($_POST['Cvv']) ? $_POST['Cvv'] : '' ) . '" maxlength="4" size="5" /></td>
		</tr>
		<tr>
			<td>' . _('Amount') . ' ' . $_SESSION['CustomerDetails']['currcode'] . ':</td>
			<td>' . locale_number_format($_SESSION['TotalDue'],$_SESSION['CustomerDetails']['currdecimalplaces']) . '</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2"></td>
			<td class="center"><input class="button" type="submit" name="PayByCreditCard" title="' . _('Pay By Credit Card') . '"  value="' . _('Submit Details and Process Payment') . '"></td>
		</tr>
		</table>
		</div><!-- end credit_card_info --!>
		<br />';
		if ($_SESSION['ShopMode']=='test'){
			message_log(_('The shop is in TEST MODE - no payments will be processed'),'error');
		}
		display_messages();

echo '</div>'; //end content_block
include ('includes/footer.php');

?>
