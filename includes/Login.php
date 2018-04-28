<?php
/* includes/Login.php
processes a login attempt and populates $_SESSION['CustomerDetails']
and sets $_SESSION['LoggedIn'] = true
OR returns an error message
*/

//error trapping

if (mb_strlen($_POST['UserEmail']) < 5 OR !IsEmailAddress($_POST['UserEmail'])) {
	message_log( _('The email address does not appear to be in a valid email address format'),'error');
	$Errors[] = 'UserEmail';
} elseif (mb_strlen($_POST['Password']) < 5) {
	message_log( _('The password must contain at least five characters'),'error');
	$Errors[] = 'Password';
} else {

	$_SESSION['LoggedIn'] = false;

	$sql = "SELECT *
			FROM www_users
			WHERE www_users.email='" . $_POST['UserEmail'] . "'
			AND customerid<>''";

	$PasswordVerified = false;
	$Auth_Result = DB_query($sql,$db);

	if (DB_num_rows($Auth_Result) > 0) {
		$LoginRow = DB_fetch_array($Auth_Result);
		if (password_verify($_POST['Password'],$LoginRow['password'])) {
			$PasswordVerified = true;
		} elseif ($LoginRow['password'] == sha1($_POST['Password']))  { //have another go it might be an older version try with sha1()
			$PasswordVerified = true;
		}
	}
	if ($PasswordVerified) {
		if ($LoginRow['blocked']==1){
			message_log(_('This account is blocked due to a number of unsuccessful login attempts. Please contact store support to reset your account'), 'error');
		} else {
			$_SESSION['LoggedIn']=true;
			$_SESSION['ShopDebtorNo'] = $LoginRow['customerid'];
			$_SESSION['ShopBranchCode'] = $LoginRow['branchcode'];
			$_SESSION['UsersRealName'] = $LoginRow['realname'];
			$_SESSION['UsersEmail'] = $_POST['UserEmail'];
			include('includes/GetCustomerDetails.php');
			update_currency_prices($_SESSION['CustomerDetails']['currcode']);

		} //successful login
	} else { //no user returned for the login details submitted
		message_log(_('We would like to do business with you but these login credentials are unknown to us.') . ' Password = ' . $Password . ' Hasd from DB = ' . $$loginRow['password'],'error');
	}
}

?>
