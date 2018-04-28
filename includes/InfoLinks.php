<?php

if (isset($_GET['Page'])){
	switch ($_GET['Page']) {
		case 'TermsAndConditions' :
			echo '<h1>' . _('Terms and Conditions') . '</h1>';
			echo html_entity_decode(str_replace($CarriageReturnOrLineFeed,'', $_SESSION['ShopTermsConditions']),ENT_QUOTES,'utf-8');
			break;
		case 'PrivacyPolicy':
			echo '<h1>' . _('Privacy Policy') . '</h1>';
			echo html_entity_decode(str_replace($CarriageReturnOrLineFeed,'', $_SESSION['ShopPrivacyStatement']),ENT_QUOTES,'utf-8');
			break;
		case 'FreightPolicy':
			echo '<h1>' . _('Freight Policy') . '</h1>';
			echo html_entity_decode(str_replace($CarriageReturnOrLineFeed,'', $_SESSION['ShopFreightPolicy']),ENT_QUOTES,'utf-8');
			break;
		case 'AboutUs':
			echo '<h1>' . _('About Us') . '</h1>';
			echo html_entity_decode(str_replace($CarriageReturnOrLineFeed,'', $_SESSION['ShopAboutUs']),ENT_QUOTES,'utf-8');
			break;
		case 'ContactUs':
			echo '<h1>' . _('Contact Details') . '</h1>';
			echo html_entity_decode(str_replace($CarriageReturnOrLineFeed,'', $_SESSION['ShopContactUs']),ENT_QUOTES,'utf-8');
			break;
	} //end switch GET['Page']
}
?>
