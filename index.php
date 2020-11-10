<?php

include('includes/DefineCartItemClass.php'); //must be before header.php
include('includes/config.php');
include('includes/session.php');

$Title = $_SESSION['ShopName'];

include('includes/header.php'); // adds deletes updates to the cart also done in header
?>
<script>
	jQuery(document).ready(function() {
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
		jQuery('#FreightPolicy').click(function(){
			jQuery('#content_block').html('<?php echo '<h1>' . _('Freight Policy') . '</h1>' . html_entity_decode(str_replace($CarriageReturnOrLineFeed,'',$_SESSION['ShopFreightPolicy'])) ?>');
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
		/* AJAX load results of sales category selections */
		jQuery('a.sales_category').click(function(){
			var url=jQuery(this).attr('href');
			jQuery('#content_block').load(url + ' #content_block');
			return false;
		});
		/* AJAX load results of description search */
		jQuery('#SearchForm').submit(function(){
			var QueryString = 'SearchDescription=' + jQuery('#SearchForm :text').val() + '&FormID=' + jQuery('#SearchForm :hidden').val() + '&CurrCode=' + jQuery('#SearchForm :select').val();
			jQuery.post('index.php',QueryString,function(data) {
							var content_block = jQuery(data).filter( '#content_block' );
							var cart_summary = jQuery(data).filter( '#cart_summary' );
							jQuery('#content_block').html(content_block.html());
							jQuery('#cart_summary').html(cart_summary.html());
						}
			);
			return false;
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

		jQuery('#CartForm :text').change(function(){
			var QueryString = jQuery('#CartForm').serialize();
			jQuery.post('index.php',QueryString,function(data) {
							var cart_summary = jQuery(data).filter( '#cart_summary' );
							var content_block = jQuery(data).filter( '#content_block' );
							jQuery('#content_block').html(content_block.html());
							jQuery('#cart_summary').html(cart_summary.html());
						}
			);
			return false;
		});

	}); /* End document ready */
</script>

<?php

ShowSalesCategoriesMenu();

include('includes/InfoLinks.php');

if (isset($_GET['Page'])){
	if ($_GET['Page']=='ShoppingCart'){  //user selected to see the cart
		echo ' <div class="column_main">
					<h1>' . _('Order Details') . '</h1>';
		//code to display the cart
		if (count($_SESSION['ShoppingCart'])>0){
			echo '<form id="CartForm" method="post" action="' . $RootPath . '/index.php">
						<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
			include('includes/DisplayShoppingCart.php'); //also used on checkout
			echo '<div class="row"><span class="potxt">' ._('Click the Order Button to process your order and enter delivery and payment options') .' >> </span><a class="link_button" href="Checkout.php">' . _('Place Order') . '</a></div>
				</div>
			</form>';
			//Now the grand total
		} else {
			echo _('The shopping cart is empty');
		}
	} // $_GET['Page'] != ShoppingCart

} else { //show the featured items by default or if a category is selected show that category of products

	$SQL = "SELECT stockmaster.stockid,
							description,
							longdescription,
							taxcatid,
							discountcategory,
							decimalplaces,
							mbflag,
							units,
							sum(locstock.quantity) AS quantity
			FROM stockmaster INNER JOIN salescatprod
			ON stockmaster.stockid = salescatprod.stockid
			INNER JOIN locstock
			ON stockmaster.stockid = locstock.stockid";

	if (isset($_GET['SalesCategory']) OR isset($_POST['SearchDescription'])) {

		if (isset($_GET['SalesCategory'])){
			echo ' <div class="column_main">
					<h1 id="focuspage">' . get_sales_category_name(DB_escape_string($_GET['SalesCategory'])) . '</h1>';
		/* Do the search for items for this category (and perhaps we should explore below this category too) */
			$SQL .= " WHERE salescatprod.salescatid IN (" . DB_escape_string($_GET['SalesCategory']) . list_sales_categories($_GET['SalesCategory']) . ")";
		} else { //only search below the specified $RootSalesCategory in includes/config.php
			$SQL .= " WHERE salescatprod.salescatid IN (" . DB_escape_string($RootSalesCategory) . list_sales_categories($RootSalesCategory) . ")";
		}
		if (isset($_POST['SearchDescription'])){
			echo ' <div class="column_main">
					<h1>' . _('Searching for:') . ' ' . $_POST['SearchDescription'] . '</h1>';
			$SQL .= " AND (stockmaster.description LIKE '%" . $_POST['SearchDescription'] . "%'
							OR stockmaster.stockid LIKE '%" . $_POST['SearchDescription'] . "%')";
		}

	} else {
		echo ' <div class="column_main">
				<h1>' . _('Featured Items') . '</h1>';
		$SQL .= " WHERE salescatprod.featured=1 AND salescatprod.salescatid IN (" . DB_escape_string($RootSalesCategory) . list_sales_categories($RootSalesCategory) . ")";


	}
	$SQL .= " AND locstock.loccode IN ('" . str_replace(',', "','", $_SESSION['ShopStockLocations']) . "')
					GROUP BY stockmaster.stockid,
									description,
									longdescription,
									taxcatid,
									decimalplaces,
									mbflag,
									units,
									salescatid";


	if ($_SESSION['ShopShowOnlyAvailableItems'] != 0){/* We should show only items with QOH > 0 */
		$SQL .= " HAVING sum(locstock.quantity) > 0";
	}
	$SQL .= " ORDER BY salescatid, stockmaster.description";

	//echo $SQL;
	//exit;

	$ItemsToDisplayResult = DB_query($SQL,_('Could not get the items to display for this category because'));

	$ItemsToDisplay =0; //counter for how many items were actually displayed

	$ItemsTableHTML = '<br />';

	display_messages(); //just in case the user has registered or logged in
	while($ItemRow = DB_fetch_array($ItemsToDisplayResult)){
		//need to get description translation and price grossed up for tax
		$DisplayItemRowHTML = display_item($ItemRow['stockid'],
											html_entity_decode($ItemRow['description']),
											html_entity_decode($ItemRow['longdescription']),
											$ItemRow['taxcatid'],
											$ItemRow['discountcategory'],
											$ItemRow['quantity'],
											$ItemRow['decimalplaces'],
											$ItemRow['mbflag'],
											$ItemRow['units'] );
		if ($DisplayItemRowHTML != '0'){
			$ItemsTableHTML .= $DisplayItemRowHTML;
			$ItemsToDisplay++;
		}
	} // end loop around the items

	if ($ItemsToDisplay ==0 ) {
		echo _('There are no items matching this search');
	} else {
		echo $ItemsTableHTML;
	}
}
echo '</div>'; //end column_main
echo '</div>'; //end content_inner
echo '</div>'; //end content_block
include ('includes/footer.php');

/* **************** END of main script ***************************** */

?>