<?php

global  $db;		// Make sure it IS global, regardless of our context

if (!isset($mysqlport)){
	$mysqlport = 3306;
}

$db = mysqli_connect($host , $DBUser, $DBPassword,$DatabaseName, $mysqlport);

//this statement sets the charset to be used for sending data to and from the db server
//if not set, both mysql server and mysql client/library may assume otherwise

mysqli_set_charset($db, 'utf8');


if ( !$db ) {
	echo '<br />' . _('The configuration in the file config.php for the database user name and password do not provide the information required to connect to the database server');
	session_unset();
	session_destroy();
	echo '<p>' . _('Click') . ' ' . '<a href="index.php">' . _('here') . '</a>' . ' '  ._('to try logging in again') . '</p>';

	exit;
}

if (isset($DatabaseName)) {
	if (!mysqli_select_db($db,$DatabaseName)) {
		echo '<br />' . _('The company name entered does not correspond to a database on the database server specified in the config.php configuration file. Try logging in with a different company name');
		unset ($DatabaseName);
		exit;
	}
} else {
	if (!mysqli_select_db($db,$_SESSION['DatabaseName'])) {
		echo '<br />' . _('The company name entered does not correspond to a database on the database server specified in the config.php configuration file. Try logging in with a different company name');
		unset ($_SESSION['DatabaseName']);
		exit;
	}
}

require_once ($PathPrefix .'includes/MiscFunctions.php');

//DB wrapper functions to change only once for whole application

function DB_query ($SQL,
		&$Conn,
		$ErrorMessage='',
		$DebugMessage= '',
		$Transaction=false,
		$TrapErrors=true){

	global $debug;
	global $PathPrefix;


	$result=mysqli_query($Conn, $SQL);

	$_SESSION['LastInsertId'] = mysqli_insert_id($Conn);

	if ($DebugMessage == '') {
		$DebugMessage = _('The SQL that failed was');
	}

	if (DB_error_no($Conn) != 0 AND $TrapErrors==true){

		message_log($ErrorMessage . '<br />' . DB_error_msg($Conn),'error');
		if ($debug==1){
			message_log($DebugMessage. '<br />' . $SQL . '<br />','error');
		}
		if ($Transaction){
			$SQL = 'rollback';
			$Result = DB_query($SQL,$Conn);
			if (DB_error_no($Conn) !=0){
				message_log(_('Error Rolling Back Transaction'), 'error');
			}
		}
	}

	return $result;
}

function DB_fetch_row (&$ResultIndex) {
	$RowPointer=mysqli_fetch_row($ResultIndex);
	Return $RowPointer;
}

function DB_fetch_assoc (&$ResultIndex) {

	$RowPointer=mysqli_fetch_assoc($ResultIndex);
	Return $RowPointer;
}

function DB_fetch_array (&$ResultIndex) {
	$RowPointer=mysqli_fetch_array($ResultIndex);
	Return $RowPointer;
}

function DB_data_seek (&$ResultIndex,$Record) {
	mysqli_data_seek($ResultIndex,$Record);
}

function DB_free_result (&$ResultIndex){
	mysqli_free_result($ResultIndex);
}

function DB_num_rows (&$ResultIndex){
	return mysqli_num_rows($ResultIndex);
}
function DB_affected_rows(&$ResultIndex){
	global $db;
	return mysqli_affected_rows($db);
}
function DB_error_no (&$Conn){
	return mysqli_errno($Conn);
}

function DB_error_msg(&$Conn){
	return mysqli_error($Conn);
}
function DB_Last_Insert_ID(&$Conn, $Table, $FieldName){
//	return mysqli_insert_id($Conn);
	if (isset($_SESSION['LastInsertId'])) {
		$Last_Insert_ID = $_SESSION['LastInsertId'];
	} else {
		$Last_Insert_ID = 0;
	}
//	unset($_SESSION['LastInsertId']);
	return $Last_Insert_ID;
}

function DB_escape_string($String){
	global $db;
	return mysqli_real_escape_string($db, htmlspecialchars($String, ENT_COMPAT,'utf-8', false));
}
function DB_Txn_Begin($db){
	$result=mysqli_query($db,"BEGIN");
}
function DB_Txn_Commit($db){
	$result=mysqli_query($db,"COMMIT");
}

?>
