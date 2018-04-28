<?php
include('includes/DefineCartItemClass.php'); //must be before header.php
include('includes/config.php');
include('includes/session.php');
include($PathPrefix .'includes/CountriesArray.php');
include($PathPrefix .'includes/LanguagesArray.php');



if (mb_strlen($_SESSION['ItemDescriptionLanguages'])>0){
	$LanguagesAvailable = explode(',',$_SESSION['ItemDescriptionLanguages']);
	$LanguagesAvailable[] = $_SESSION['CustomerDetails']['language_id'];
	unset($LanguagesAvailable[array_search('',$LanguagesAvailable)]);
} else {
	$LanguagesAvailable =array();
	$LanguagesAvailable[] = $_SESSION['CustomerDetails']['language_id'];
}

$Title = _('Account Details');

if (isset($_POST['DeliveryAsPerBilling'])){
	$_POST['DeliveryAddress1']=$_POST['Address1'];
	$_POST['DeliveryAddress2']=$_POST['Address2'];
	$_POST['DeliveryAddress3']=$_POST['Address3'];
	$_POST['DeliveryAddress4']=$_POST['Address4'];
	$_POST['DeliveryAddress5']=$_POST['Address5'];
	$_POST['DeliveryAddress6']=$_POST['Address6'];
}

if (isset($_POST['Register'])){
	//Validation
	$InputError=0; // always hope/assume the best
	$i=0; //error counter
	$CheckEmailResult = DB_query("SELECT email FROM www_users WHERE email='" . $_POST['UserEmail'] ."' AND customerid<>''",$db);
	if (DB_num_rows($CheckEmailResult)>0 AND (!isset($_SESSION['LoggedIn']) OR $_SESSION['LoggedIn']==false)){
		$InputError = 1;
		message_log( _('You have already registered this email address on the system please login to use your existing account'),'error');
		$Errors[$i] = 'UserEmail';
		$i++;
		//include('includes/Login.php');
		header('Location: http://' . $_SERVER['HTTP_HOST'] . $RootPath . '/index.php');
	} elseif (mb_strlen($_POST['UserEmail']) < 5 OR !IsEmailAddress($_POST['UserEmail'])) {
		$InputError = 1;
		message_log( _('The email address does not appear to be in a valid email address format'),'error');
		$Errors[$i] = 'UserEmail';
		$i++;
	} elseif (mb_strlen($_POST['Password']) < 5) {
		$InputError = 1;
		message_log( _('The password must contain at least five characters'),'error');
		$Errors[$i] = 'Password';
		$i++;
	} elseif ($_POST['Password']!= $_POST['ConfirmPassword']) {
		$InputError = 1;
		message_log( _('The password and the confirmation do not match'),'error');
		$Errors[$i] = 'Password';
		$i++;
		$Errors[$i] = 'ConfirmPassword';
		$i++;
	} elseif (mb_strlen($_POST['ContactName']) < 3) {
		$InputError = 1;
		message_log( _('The contact name must be entered'),'error');
		$Errors[$i] = 'ContactName';
		$i++;
	} elseif (mb_strlen($_POST['Address1']) <2) {
		$InputError = 1;
		message_log( _('The building/street address entered is too short to be valid. At least 2 characters are required'),'error');
		$Errors[$i] = 'Address1';
		$i++;
	} elseif (mb_strlen($_POST['Address2']) <4) {
		$InputError = 1;
		message_log( _('The street address entered is too short to be valid'),'error');
		$Errors[$i] = 'Address1';
		$i++;
	} elseif (mb_strlen($_POST['Address4']) <4) {
		$InputError = 1;
		message_log( _('The city entered is too short to be valid'),'error');
		$Errors[$i] = 'Address4';
		$i++;
	} elseif (mb_strlen($_POST['Phone']) <5) {
		$InputError = 1;
		message_log( _('The phone number for the billing address is too short to be valid'),'error');
		$Errors[$i] = 'Phone';
		$i++;
	} elseif (mb_strlen($_POST['DeliveryAddress1']) <3) {
		$InputError = 1;
		message_log( _('The delivery street address is too short to be valid'),'error');
		$Errors[$i] = 'DeliveryAddress1';
		$i++;
	} elseif (mb_strlen($_POST['DeliveryAddress4']) <3) {
		$InputError = 1;
		message_log( _('The delivery city address is too short to be valid'),'error');
		$Errors[$i] = 'DeliveryAddress4';
		$i++;
	}

	if ($InputError==0){ //no errors identified
		if (mb_strlen($_POST['CompanyName']) <3) {
			$CustomerName = $_POST['ContactName'];
		} else {
			$CustomerName = $_POST['CompanyName'];
		}
		if (!isset($_SESSION['LoggedIn']) OR $_SESSION['LoggedIn']==false){ //customer is not logged in so setting up a new customer
			do {
				$CustomerCode = CreateWebCustomerCode(GetNextSequenceNo(500));
				$CheckDoesntExistResult = DB_query("SELECT count(*) FROM debtorsmaster WHERE debtorno='" . $CustomerCode . "'",$db);
				$CheckDoesntExistRow = DB_fetch_row($CheckDoesntExistResult);
			} while ($CheckDoesntExistRow[0]==1);

			$SQL = "INSERT INTO debtorsmaster (debtorno,
											name,
											address1,
											address2,
											address3,
											address4,
											address5,
											address6,
											currcode,
											clientsince,
											holdreason,
											paymentterms,
											salestype,
											typeid,
											creditlimit,
											language_id,
											taxref)
								VALUES ('" . $CustomerCode ."',
										'" . $CustomerName ."',
										'" . $_POST['Address1'] ."',
										'" . $_POST['Address2'] ."',
										'" . $_POST['Address3'] . "',
										'" . $_POST['Address4'] . "',
										'" . $_POST['Address5'] . "',
										'" . $_POST['Address6'] . "',
										'" . $_SESSION['CustomerDetails']['currcode'] . "',
										'" . Date('Y-m-d H-i-s') . "',
										'" . $_SESSION['CustomerDetails']['holdreason'] . "',
										'" . $_SESSION['CustomerDetails']['paymentterms'] . "',
										'" . $_SESSION['CustomerDetails']['salestype'] . "',
										'" . $_SESSION['CustomerDetails']['typeid'] . "',
										'" . $_SESSION['CustomerDetails']['creditlimit'] . "',
										'" . $_POST['Language'] . "',
										'" . $_POST['TaxRef'] . "')";

			$ErrMsg = _('This customer could not be added because');
			$result = DB_query($SQL,$db,$ErrMsg);
			//Now add the customer branch record
			$SQL = "INSERT INTO custbranch (branchcode,
											debtorno,
											brname,
											braddress1,
											braddress2,
											braddress3,
											braddress4,
											braddress5,
											braddress6,
											brpostaddr1,
											brpostaddr2,
											brpostaddr3,
											brpostaddr4,
											brpostaddr5,
											brpostaddr6,
											salesman,
											phoneno,
											contactname,
											area,
											email,
											taxgroupid,
											defaultlocation,
											defaultshipvia)
									VALUES ('" . $CustomerCode . "',
										'" . $CustomerCode . "',
										'" . $CustomerName . "',
										'" . $_POST['DeliveryAddress1'] . "',
										'" . $_POST['DeliveryAddress2'] . "',
										'" . $_POST['DeliveryAddress3'] . "',
										'" . $_POST['DeliveryAddress4'] . "',
										'" . $_POST['DeliveryAddress5'] . "',
										'" . $_POST['DeliveryAddress6'] . "',
										'" . $_POST['DeliveryAddress1'] . "',
										'" . $_POST['DeliveryAddress2'] . "',
										'" . $_POST['DeliveryAddress3'] . "',
										'" . $_POST['DeliveryAddress4'] . "',
										'" . $_POST['DeliveryAddress5'] . "',
										'" . $_POST['DeliveryAddress6'] . "',
										'" . $_SESSION['CustomerDetails']['salesman'] . "',
										'" . $_POST['Phone'] . "',
										'" . $_POST['ContactName'] . "',
										'" . $_SESSION['CustomerDetails']['area'] . "',
										'" . $_POST['UserEmail'] . "',
										'" . $_SESSION['CustomerDetails']['taxgroupid'] . "',
										'" . $_SESSION['CustomerDetails']['defaultlocation'] . "',
										'" . $_SESSION['CustomerDetails']['defaultshipvia'] . "' )";
			$ErrMsg = _('This customer branch could not be added because');
			$result = DB_query($SQL,$db,$ErrMsg);

			$SQL = "INSERT INTO www_users (userid,
											realname,
											customerid,
											branchcode,
											password,
											phone,
											email,
											pagesize,
											fullaccess,
											defaultlocation,
											modulesallowed,
											displayrecordsmax,
											theme,
											language)
										VALUES ('" . $CustomerCode . "',
												'" . $_POST['ContactName'] ."',
												'" . $CustomerCode ."',
												'" . $CustomerCode ."',
												'" . password_hash($_POST['Password'],PASSWORD_DEFAULT) ."',
												'" . $_POST['Phone'] . "',
												'" . $_POST['UserEmail'] ."',
												'A4',
												'7',
												'" . $_SESSION['CustomerDetails']['defaultlocation'] ."',
												'1,0,0,0,0,0,0,0,0,0,0',
												'30',
												'" . $_SESSION['DefaultTheme'] ."',
												'" . $_POST['Language'] ."')";

			$ErrMsg = _('The user could not be added because');
			$DbgMsg = _('The SQL that was used to insert the new user and failed was');
			$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg);
			message_log( _('Successfully registered'),'success');

			$MailTo = $_POST['UserEmail'] . ', ' . $_SESSION['ShopManagerEmail'];

			$headers = 'From: ' . $_SESSION['ShopName'] . " <" . strip_tags($_SESSION['ShopManagerEmail']) . ">\r\n";
			$headers .= "Reply-To: " . $_SESSION['ShopName'] . " <". strip_tags($_SESSION['ShopManagerEmail']) . ">\r\n";
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-Type: text/html; charset=utf-8\r\n";

			$MailSubject = $_SESSION['ShopName'] . ' ' . _('Confirmation of Registration');

			$MailMessage = '
				<html>
				<head>
					<title>' . $MailSubject . '</title>
				</head>
				<body>
				<br />
				<h2>' . $MailSubject . '</h2>
				<p>' . _('Thanks for registering as a') . ' ' . $_SESSION['ShopName'] . ' ' . _('customer') . '.</p>
				<br />
				<p>' . _('The details that we have on record for you are as follows') . ':</p>
					<table>
						<tr>
							<td> <b>' . _('Login Email') . ':</b></td>
							<td>' . DB_escape_string($_POST['UserEmail']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Primary Contact') . ':</b></td>
							<td>' . DB_escape_string($_POST['ContactName']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Phone') . ':</b></td>
							<td>' . DB_escape_string($_POST['Phone']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Delivery Building') . ':</b></td>
							<td>' . DB_escape_string($_POST['DeliveryAddress1']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Delivery Street') . ':</b></td>
							<td>' . DB_escape_string($_POST['DeliveryAddress2']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Delivery Suburb') . ':</b></td>
							<td>' . DB_escape_string($_POST['DeliveryAddress3']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Delivery City') . ':</b></td>
							<td>' . DB_escape_string($_POST['DeliveryAddress4']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Delivery ZIP') . ':</b></td>
							<td>' . DB_escape_string($_POST['DeliveryAddress5']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Delivery Country') . ':</b></td>
							<td>' . DB_escape_string($_POST['DeliveryAddress6']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Postal Address 1 ') . ':</b></td>
							<td>' . DB_escape_string($_POST['Address1']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Postal Address 2 ') . ':</b></td>
							<td>' . DB_escape_string($_POST['Address2']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Postal Address 3 ') . ':</b></td>
							<td>' . DB_escape_string($_POST['Address3']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Postal Address 4 ') . ':</b></td>
							<td>' . DB_escape_string($_POST['Address4']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Postal Address 5 ') . ':</b></td>
							<td>' . DB_escape_string($_POST['Address5']) . '</td>
						</tr>
						<tr>
							<td> <b>' . _('Postal Address Country') . ':</b></td>
							<td>' . DB_escape_string($_POST['Address6']) . '</td>
						</tr>
						</table>
						<br/>
						<br/>
						<p>' . _('We look forward to doing business with you') . '
						</p>
						<br/>
						<p><i>' . $_SESSION['ShopName'] . '</i></p>
						<p><a href="' . 'http://' . $_SERVER['HTTP_HOST'] . $RootPath . '">' . _('Click to go to') . ' ' . $_SESSION['ShopName'] . '</a></p>
					</body>
				</html>';

			$result = mail( $MailTo, $MailSubject, $MailMessage, $headers );

		} //end of adding new customer
			else { //modifying an existing customer

			$SQL = "UPDATE debtorsmaster SET name =  '" . $CustomerName ."',
											address1='" . $_POST['Address1'] ."',
											address2='" . $_POST['Address2'] ."',
											address3='" . $_POST['Address3'] . "',
											address4='" . $_POST['Address4'] . "',
											address5='" . $_POST['Address5'] . "',
											address6='" . $_POST['Address6'] . "',
											currcode='" . $_POST['CurrCode'] . "',
											language_id='" . $_POST['Language'] . "',
											taxref='" . $_POST['TaxRef'] . "'
					WHERE debtorno='" . $_SESSION['ShopDebtorNo'] ."'";


			$ErrMsg = _('This customer could not be updated because');
			$result = DB_query($SQL,$db,$ErrMsg);
			//Now add the customer branch record
			$SQL = "UPDATE custbranch SET	brname   = '" . $CustomerName ."',
											braddress1='" . $_POST['DeliveryAddress1'] . "',
											braddress2='" . $_POST['DeliveryAddress2'] . "',
											braddress3='" . $_POST['DeliveryAddress3'] . "',
											braddress4='" . $_POST['DeliveryAddress4'] . "',
											braddress5='" . $_POST['DeliveryAddress5'] . "',
											braddress6='" . $_POST['DeliveryAddress6'] . "',
											brpostaddr1='" . $_POST['DeliveryAddress1'] . "',
											brpostaddr2='" . $_POST['DeliveryAddress2'] . "',
											brpostaddr3='" . $_POST['DeliveryAddress3'] . "',
											brpostaddr4='" . $_POST['DeliveryAddress4'] . "',
											brpostaddr5='" . $_POST['DeliveryAddress5'] . "',
											brpostaddr6='" . $_POST['DeliveryAddress6'] . "',
											phoneno   ='" . $_POST['Phone'] . "',
											contactname='" . $_POST['ContactName'] . "',
											email     ='" . $_POST['UserEmail'] ."'
					WHERE debtorno='" . $_SESSION['ShopDebtorNo'] ."'
					AND branchcode='" . $_SESSION['ShopBranchCode']  . "'";

			$ErrMsg = _('This customer branch could not be updated because');
			$result = DB_query($SQL,$db,$ErrMsg);

			$SQL = "UPDATE www_users SET realname = '" . $_POST['ContactName'] . "',
										password = '" . password_hash($_POST['Password'],PASSWORD_DEFAULT) . "',
										phone ='" . $_POST['Phone'] . "',
										email='" . $_POST['UserEmail'] . "',
										language='" . $_POST['Language'] . "'
					WHERE email='" . $_SESSION['UsersEmail'] . "'
					AND customerid='" . $_SESSION['ShopDebtorNo'] . "'
					AND branchcode='" . $_SESSION['ShopBranchCode'] . "'";

			$ErrMsg = _('The user could not be updated because');
			$DbgMsg = _('The SQL that was used to update the user and failed was');
			$result = DB_query($SQL,$db,$ErrMsg,$DbgMsg);
			message_log( _('Successfully updated your details'),'success');
		}

 		include('includes/Login.php');
		if (count($_SESSION['ShoppingCart'])>0) { //then head directly to checkout
			header('Location: http://' . $_SERVER['HTTP_HOST'] . $RootPath . '/Checkout.php');
		} else { //go back to browsing
			header('Location: http://' . $_SERVER['HTTP_HOST'] . $RootPath . '/index.php');
		}
 		exit();
 	} // end if there were no input errors
} //end if registering new customer details

include('includes/header.php');

?>
<script>
	jQuery(document).ready(function() {
			/* Focus on user name input field*/
		jQuery('#RegistrationForm').validate({
			rules: {
				UserEmail: {
					email:true,
					minlength: 7
				},
				Password: {
					minlength: 5
				},
				ConfirmPassword: {
					equalTo: '#Password',
					minlength: 5
				},
				ContactName: {
					minlength: 3
				},
				Address1: {
					minlength: 2
				},
				Address2: {
					minlength: 4
				},
				Address4: {
					minlength: 4
				},
				Address5: {
					minlength: 3
				},
				Phone: {
					minlength: 6
				},
				DeliveryAddress1: {
					minlength: 4
				},
				DeliveryAddress5: {
					minlength: 3
				}
			}, //end rules
			messages : {
				UserEmail: {
					required: "<?php echo _('An email address is required') ?>",
					email: "<?php echo _('The email address must be a valid email address') ?>",
					minlength: "<?php echo _('The email address is expected to 5 characters or more long') ?>"
				},
				Password: {
					required: "<?php echo _('A password is required') ?>",
					minlength: "<?php echo _('The password is expected to 5 characters or more long') ?>"
				},
				ConfirmPassword: {
					required: "<?php echo _('The confirmation password must be entered') ?>",
					equalTo: "<?php echo _('The confrimation password and the password fields must be identical') ?>",
					minlength: "<?php echo _('The confirmation password is expected to 5 characters or more long') ?>"
				},
				ContactName: {
					required: "<?php echo _('The contact name must be entered') ?>",
					minlength: "<?php echo _('The contact name is expected to 3 characters or more long') ?>"
				},
				Address1: {
					required: "<?php echo _('The billing building address or number must be entered') ?>",
					minlength: "<?php echo _('The billing building address or number is expected to 2 characters or more long') ?>"
				},
				Address2: {
					required: "<?php echo _('The billing street address must be entered') ?>",
					minlength: "<?php echo _('The billing street address is expected to 4 characters or more long') ?>"
				},
				Address4: {
					required: "<?php echo _('The billing address city must be entered') ?>",
					minlength: "<?php echo _('The billing address city is expected to 4 characters or more long') ?>"
				},
				Address5: {
					required: "<?php echo _('The billing address zip code must be entered') ?>",
					minlength: "<?php echo _('The billing address zip code is expected to 3 characters or more long') ?>"
				},
				Phone: {
					required: "<?php echo _('The phone number must be entered') ?>",
					minlength: "<?php echo _('The phone must be at least 5 digits long') ?>",
					digits: "<?php echo _('The phone number can only contain digits') ?>",
				},
				DeliveryAddress1: {
					required: "<?php echo _('The delivery street address must be entered') ?>",
					minlength: "<?php echo _('The delivery street address is expected to 4 characters or more long') ?>"
				},
				DeliveryAddress4: {
					required: "<?php echo _('The delivery city must be entered') ?>",
					minlength: "<?php echo _('The delivery city is expected to 3 characters or more long') ?>"
				},
				DeliveryAddress5: {
					required: "<?php echo _('The delivery zip code must be entered') ?>",
					minlength: "<?php echo _('The delivery zip code is expected to 3 characters or more long') ?>"
				}
			}, //end messages
			errorPlacement: function(error, element) {
				error.insertAfter(element);
				error.wrap('<p>');
			} // end errorPlacement
		}); //end validation

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
		jQuery('#FreightPolicy').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Freight Policy') . '</h1>' . html_entity_decode($_SESSION['ShopFreightPolicy']) ?>');
			return false;
		});
		jQuery('#cart_summary').click(function(){
			jQuery('#content_block').load('index.php?Page=ShoppingCart' + ' #content_block');
			return false;
		});
		jQuery('#DeliveryAsPerBilling').click(function(){
			jQuery('#DeliveryAddress1').val(jQuery('#Address1').val());
			jQuery('#DeliveryAddress2').val(jQuery('#Address2').val());
			jQuery('#DeliveryAddress3').val(jQuery('#Address3').val());
			jQuery('#DeliveryAddress4').val(jQuery('#Address4').val());
			jQuery('#DeliveryAddress5').val(jQuery('#Address5').val());
			jQuery('#DeliveryAddress6').val(jQuery('#Address6').val());
			return false;
		});
		jQuery('#Phone').bind('input', function() {
			jQuery(this).val($(this).val().replace(/[^+()\-\s0-9]/gi, ''));
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
	});
</script>

<?php

ShowSalesCategoriesMenu();

include('includes/InfoLinks.php');

echo'<div class="column_main">';
if (!isset($_SESSION['LoggedIn']) OR $_SESSION['LoggedIn']==false){ //customer is not logged in so setting up a new customer
	echo '<h1>' . _('Register') . '</h1>';
} else { //updating an existing customer
	echo '<h1>' . _('Update Your Account Details') . '</h1>';
}
echo'<div id="register">
		<form id="RegistrationForm" method="post" action="'. htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">
		<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

if (isset($_SESSION['LoggedIn']) AND $_SESSION['LoggedIn']==true){ // need to get the user details for modification
	$_POST['UserEmail'] = $_SESSION['UsersEmail'] ;
	$_POST['ContactName'] = $_SESSION['UsersRealName'];
	$_POST['CompanyName'] = $_SESSION['CustomerDetails']['name'];
	$_POST['Address1'] = $_SESSION['CustomerDetails']['address1'];
	$_POST['Address2'] = $_SESSION['CustomerDetails']['address2'];
	$_POST['Address3'] = $_SESSION['CustomerDetails']['address3'];
	$_POST['Address4'] = $_SESSION['CustomerDetails']['address4'];
	$_POST['Address5'] = $_SESSION['CustomerDetails']['address5'];
	$_POST['Address6'] = $_SESSION['CustomerDetails']['address6'];
	$_POST['Phone'] = $_SESSION['CustomerDetails']['phoneno'];
	$_POST['DeliveryAddress1'] = $_SESSION['CustomerDetails']['braddress1'];
	$_POST['DeliveryAddress2'] = $_SESSION['CustomerDetails']['braddress2'];
	$_POST['DeliveryAddress3'] = $_SESSION['CustomerDetails']['braddress3'];
	$_POST['DeliveryAddress4'] = $_SESSION['CustomerDetails']['braddress4'];
	$_POST['DeliveryAddress5'] = $_SESSION['CustomerDetails']['braddress5'];
	$_POST['DeliveryAddress6'] = $_SESSION['CustomerDetails']['braddress6'];
	$_POST['Language'] = $_SESSION['CustomerDetails']['language_id'];
	$_POST['CurrCode'] = $_SESSION['CustomerDetails']['currcode'];
	$_POST['TaxRef'] = $_SESSION['CustomerDetails']['taxref'];
	$_POST['ConfirmPassword'] = '';
	$_POST['Password'] = '';
} else {
	if (!isset($_POST['UserEmail'])){ //user is not logged in so default all entries to blanks
		 $_POST['Password'] = '';
		 $_POST['ConfirmPassword'] = '';
		 $_POST['UserEmail'] = '';
		 $_POST['ContactName'] = '';
		 $_POST['CompanyName'] = '';
		 $_POST['Address1'] = '';
		 $_POST['Address2'] = '';
		 $_POST['Address3'] = '';
		 $_POST['Address4'] = '';
		 $_POST['Address5'] = '';
		 $_POST['Address6'] = $CountriesArray[$_SESSION['CountryOfOperation']];
		 $_POST['Phone'] = '';
		 $_POST['DeliveryAddress1'] = '';
		 $_POST['DeliveryAddress2'] = '';
		 $_POST['DeliveryAddress3'] = '';
		 $_POST['DeliveryAddress4'] = '';
		 $_POST['DeliveryAddress5'] = '';
		 $_POST['DeliveryAddress6'] = $CountriesArray[$_SESSION['CountryOfOperation']];;
		 $_POST['Language'] = $_SESSION['CustomerDetails']['language_id'];
		 $_POST['CurrCode'] = $_SESSION['CustomerDetails']['currcode'];
		  $_POST['TaxRef'] = '';
	}
}
//display_messages();

echo '<div class="row">
<div class="row-left">' . _('Email') . ':</div>
	<div class="row-right"><input tabindex="1" type="email" required="required" autofocus="autofocus" class="required ' . (in_array('UserEmail',$Errors) ?  'error' : '' ) . '" name="UserEmail" size="30" maxlength="30"  value="' . $_POST['UserEmail'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Password') . ':</div>
	<div class="row-right"><input tabindex="2" id="Password" type="password" required class="required ' . (in_array('Password',$Errors) ?  'error' : '' ) . '" name="Password" size="15" maxlength="15"  value="' . $_POST['Password'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Confirm Password') . ':</div>
	<div class="row-right"><input tabindex="3" type="password" required class="required ' . (in_array('ConfirmPassword',$Errors) ?  'error' : '' ) . '" name="ConfirmPassword" size="15" maxlength="15"  value="' . $_POST['ConfirmPassword'] . '"/></div>
</div>

<div class="row">
	<div class="row-left">' . _('Contact Name') . ':</div>
	<div class="row-right"><input tabindex="4" type="text" required class="required ' . (in_array('ContactName',$Errors) ?  'error' : '' ) . '" name="ContactName" size="30" maxlength="30"  value="' . $_POST['ContactName'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Company Name') . ':</div>
	<div class="row-right"><input tabindex="5" type="text" ' . (in_array('CompanyName',$Errors) ?  'class="error"' : '' ) . ' name="CompanyName" size="30" maxlength="30"  value="' . $_POST['CompanyName'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Billing Address - Number/Street') . ':</div>
	<div class="row-right"><input tabindex="6" id="Address1" type="text" required class="required ' . (in_array('Address1',$Errors) ?  'error' : '' ) . '" name="Address1" size="30" maxlength="30"  value="' . $_POST['Address1'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Billing Address - Street') . ':</div>
	<div class="row-right"><input tabindex="7" type="text" id="Address2" name="Address2" required class="required ' . (in_array('Address2',$Errors) ?  'error' : '' ) . '" size="30" maxlength="30"  value="' . $_POST['Address2'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Billing Address - Suburb') . ':</div>
	<div class="row-right"><input tabindex="8"  id="Address3" type="text" ' . (in_array('Address3',$Errors) ?  'class="error"' : '' ) . ' name="Address3" size="20" maxlength="20"  value="' . $_POST['Address3'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Billing Address - City') . ':</div>
	<div class="row-right"><input tabindex="9" id="Address4" type="text" class="required' . (in_array('Address4',$Errors) ?  ' error' : '' ) . '" name="Address4" size="20" maxlength="20"  value="' . $_POST['Address4'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Billing Address - ZIP') . ':</div>
	<div class="row-right"><input tabindex="10" id="Address5" type="text" class="required' . (in_array('Address5',$Errors) ?  ' error' : '' ) . '" name="Address5" size="10" maxlength="10"  value="' . $_POST['Address5'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('VAT No.') . ':</div>
	<div class="row-right"><input tabindex="11" id="TaxRef" type="text" name="TaxRef" size="16" maxlength="16"  value="' . $_POST['TaxRef'] . '" /></div>
</div>


<div class="row">
	<div class="row-left">' . _('Country') . ':</div>
	<div><select tabindex="12" id="Address6" name="Address6" ' . (in_array('Address6',$Errors) ?  'class="error"' : '' ) . ' >';

foreach ($CountriesArray as $CountryName){
	if (isset($_POST['Address6']) AND (strtoupper($_POST['Address6']) == strtoupper($CountryName))){
		echo '<option selected="selected" value="' . $CountryName . '">' . $CountryName .'</option>';
	} else {
		echo '<option value="' . $CountryName . '">' . $CountryName .'</option>';
	}
}
echo '</select></div>
</div>

<div class="row">
	<input id="DeliveryAsPerBilling" tabindex="13" class="button" type="submit" title="' . _('Click this button to make the delivery details the same as the billing address') . '" name="DeliveryAsPerBilling" value="' . _('Delivery Address As Billing Address') . '" />
</div>
<div class="row">
	<div class="row-left">' . _('Delivery Address - Number/Street') . ':</div>
	<div class="row-right"><input tabindex="14"  id="DeliveryAddress1" type="text" class="required' . (in_array('DeliveryAddress1',$Errors) ?  ' error' : '' ) . '" name="DeliveryAddress1" size="30" maxlength="30"  value="' . $_POST['DeliveryAddress1'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Delivery Address - Street') . ':</div>
	<div class="row-right"><input tabindex="15" id="DeliveryAddress2" type="text" name="DeliveryAddress2" ' . (in_array('DeliveryAddress2',$Errors) ?  'class="error"' : '' ) . ' size="30" maxlength="30"  value="' . $_POST['DeliveryAddress2'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Delivery Address - Suburb') . ':</div>
	<div class="row-right"><input tabindex="16" id="DeliveryAddress3" type="text" ' . (in_array('Address3',$Errors) ?  'class="error"' : '' ) . ' name="DeliveryAddress3" size="20" maxlength="20" value="' . $_POST['DeliveryAddress3'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Delivery Address - City') . ':</div>
	<div class="row-right"><input tabindex="17" id="DeliveryAddress4" type="text" name="DeliveryAddress4"  class="required' . (in_array('DeliveryAddress4',$Errors) ?  ' error' : '' ) . '" size="20" maxlength="20" value="' . $_POST['DeliveryAddress4'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Delivery Address - ZIP') . ':</div>
	<div class="row-right"><input tabindex="18" id="DeliveryAddress5" type="text" name="DeliveryAddress5"  class="required' . (in_array('DeliveryAddress5',$Errors) ?  ' error' : '' ) . '" size="10" maxlength="10" value="' . $_POST['DeliveryAddress5'] . '" /></div>
</div>

<div class="row">
	<div class="row-left">' . _('Country') . ':</div>
	<div class="row-right"><select tabindex="19" id="DeliveryAddress6" name="DeliveryAddress6" ' . (in_array('Address6',$Errors) ?  'class="error"' : '' ) . ' >';
			foreach ($CountriesArray as $CountryEntry => $CountryName){
				if (isset($_POST['DeliveryAddress6']) AND (strtoupper($_POST['DeliveryAddress6']) == strtoupper($CountryName))){
					echo '<option selected="selected" value="' . $CountryName . '">' . $CountryName .'</option>';
				} else {
					echo '<option value="' . $CountryName . '">' . $CountryName .'</option>';
				}
			}
					echo '</select></div>
</div>

<div class="row">
	<div class="row-left">' . _('Billing Currency') . ':</div>
	<div class="row-right"><select tabindex="20" ' . (in_array('CurrCode',$Errors) ?  'class="error"' : '' ) . ' name="CurrCode">';
	$CurrenciesResult = DB_query("SELECT currabrev FROM currencies WHERE webcart=1",$db);
	while ($CurrRow = DB_fetch_array($CurrenciesResult)){
		echo '<option ';
		if ($_SESSION['CustomerDetails']['currcode']==$CurrRow['currabrev']){
			echo 'selected="selected" ';
		}
			echo 'value="' . $CurrRow['currabrev'] . '">' . $CurrRow['currabrev'] . '</option>';
		}

	echo '</select></div>
</div>

<input type="hidden"  name="Language" value="en_GB.utf8" />

<div class="row">
	<div class="row-left">' . _('Phone') . ':</div>
	<div class="row-right"><input tabindex="21" id="Phone" type="tel" pattern="[0-9+\-\s()]*" class="required ' . (in_array('Phone',$Errors) ?  'error' : '' ) . '" name="Phone" size="20" maxlength="20"  value="' . $_POST['Phone'] . '" /></div>
</div>

<div class="row">
	<input tabindex="22" class="button" type="submit" name="Register" value="' . ((isset($_SESSION['LoggedIn']) AND $_SESSION['LoggedIn']==true)?_('Update Details'):_('Register')) . '" />
</div>
</form><!-- End of RegistrationForm -->
</div><!-- End of register <div> -->
</div><!-- End of column main <div> -->';

echo '</div>'; //end content_inner
echo '</div>'; //end content_block
include ('includes/footer.php');

?>
