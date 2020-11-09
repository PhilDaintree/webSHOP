<?php
include('includes/DefineCartItemClass.php'); //must be before header.php
include('includes/config.php');
include('includes/session.php');

$ItemResult = DB_query("SELECT description, longdescription, taxcatid, units FROM stockmaster WHERE stockid='" . $_GET['StockID'] . "'");
$ItemRow = DB_fetch_array($ItemResult);
$Title = _($ItemRow['description']) . ' - ' . $_SESSION['ShopTitle'];

if (isset($_GET['StockID'])){
	$StockID = $_GET['StockID'];
} elseif (isset($_POST['StockID'])){
	$StockID = $_POST['StockID'];
}

include('includes/header.php');

?>
<script>
	jQuery(document).ready(function() {
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
		jQuery('#FreightPolicy').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Freight Policy') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopFreightPolicy'])) ?>');
			return false;
		});
		jQuery('#ContactUs').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Contact Details') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopContactUs'])) ?>');
			return false;
		});
		jQuery('#cart_summary').click(function(){
			jQuery('#content_block').load('index.php?Page=ShoppingCart' + ' #content_block');
			return false;
		});
		jQuery('#ItemQuantity').bind('input', function() {
			jQuery(this).val($(this).val().replace(/[^0-9\.]/gi, ''));
		});
	});
</script>

<?php
ShowSalesCategoriesMenu();
include('includes/InfoLinks.php');

echo'<form id="ItemsTable"  method="post" action="index.php">
	<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
	<input type="hidden" name="StockID" value="' . $StockID . '" />
	<div class="column_main">
	<h1 id="focuspage">'. $ItemRow['description'] . '</h1>
	<div class="full_description_box itempage">
		<div class="main_image">
			<div class="image_column">';

if(isset($StockID) AND file_exists($PathPrefix . $_SESSION['part_pics_dir'] . '/' . $StockID . '.jpg') ) {
	echo '<img src="GetStockImage.php?automake=1&amp;textcolor=FFFFFF&amp;bgcolor=CCCCCC&amp;StockID=' .$StockID . '&amp;text=&amp;width=400&amp;height=400" />';
} else {
	echo '<img src="css/no_image.png" />';
}

echo '</div>
		<div class="itemdetailscont">
			<div class="code_column"><h9>' . _('Code') . ':<strong> ' .  $StockID . '</strong></h9></div>
				<div class="price_column">';

$ItemResult = DB_query("SELECT stockmaster.stockid,
							decimalplaces,
							units,
							longdescription,
							taxcatid,
							discountcategory,
							sum(locstock.quantity) AS quantityonhand
						FROM stockmaster INNER JOIN locstock
						ON stockmaster.stockid = locstock.stockid
						WHERE stockmaster.stockid='" . $StockID . "'
						GROUP BY stockmaster.stockid,
								decimalplaces,
								units,
								longdescription,
								taxcatid");
$ItemRow = DB_fetch_array($ItemResult);

$Price = GetPrice(($StockID), $_SESSION['ShopDebtorNo'], $_SESSION['ShopBranchCode'], $_SESSION['CustomerDetails']['currcode']);
$Discount = GetDiscount($ItemRow['discountcategory'], 1);
$GrossPrice = $Price * (1 - $Discount) * (1 + $_SESSION['TaxRates'][$ItemRow['taxcatid']]);

echo '<div class="price_column_value">'. $_SESSION['CustomerDetails']['currcode'] . ' ' . locale_number_format($GrossPrice, $_SESSION['CustomerDetails']['currdecimalplaces']) . '&nbsp;</div>';

if($Discount != 0){
	// the item has some discount, show it! so the customer see how cool we are :-)
	$PriceBeforeDiscount = $Price * (1 + $_SESSION['TaxRates'][$ItemRow['taxcatid']]);
	echo '<div class="price_was_column_value">'. _('Price was') . ' ' . $_SESSION['CustomerDetails']['currcode'] . ' ' . locale_number_format($PriceBeforeDiscount, $_SESSION['CustomerDetails']['currdecimalplaces']) . '</div>';
}

echo '	<div class="price_column_label_excl">&nbsp;' . _('incl GST') . '</div>
	</div>';

if ($_SESSION['ShopShowQOHColumn'] == 1){
	if ($ItemRow['quantityonhand'] <= 0) {
		$DisplayOnHand = _('Arriving Soon');
	} elseif ($ItemRow['quantityonhand'] > 20) {
		$DisplayOnHand = '20+';
	} else {
		$DisplayOnHand = locale_number_format($ItemRow['quantityonhand'],$ItemRow['decimalplaces']);
	}
	echo '<div class="qoh_column">
			<div class="qoh_column_label">' . _('In Stock QTY') . ':&nbsp;</div>
			<div class="qoh_column_value">' . $DisplayOnHand . '</div>';
	if ($DisplayOnHand != _('Arriving Soon')){
		echo '<div class="qoh_column_uom">' . $ItemRow['units'] . '</div>';
	}
	echo '</div>';
}
echo '<div class="quantity_ordered_column">
		<input id="ItemQuantity" type="text" class="number" size="6" maxlength="6" pattern="[0-9\.]*" require="required" title="' . _('Enter the quantity you wish to purchase of this item') . '" name="Quantity" value="1" />
	</div>
	<div class="button_column"><input class="button" type="submit" name="AddToCart" value="'. _('Add to Order') . '" /></div>
	<div class="otherinfo_column">
		<div class="back_column"><a href="javascript:history.go(-1)">' . _('Back to Category') . '</a></div>
	</div>

	<div class="socialshare_column">
		<span class="st_facebook_large" displayText="Facebook"></span>
		<span class="st_twitter_large" displayText="Tweet"></span>
		<span class="st_googleplus_large" displayText="Google +"></span>
		<span class="st_email_large" displayText="Email"></span>
	</div>

	</div>
	</div>
	<div class="bordersep"></div>
	<h1 class="prodh1">' . _('Product Details') . '</h1>
	<div class="long_description">'. str_replace($CarriageReturnOrLineFeed,'',html_entity_decode($ItemRow['longdescription'])) . '</div>
	</div>';

echo '</div>'; // end prodbox
echo '</div>'; //end content_main
echo '</div>'; //end content_inner
echo '</div>'; //end content_block
echo '</form>';
include ('includes/footer.php');

?>
