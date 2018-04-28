<?php
include_once($PathPrefix . 'includes/CountriesArray.php');
include_once($PathPrefix . 'includes/FreightCalculation.inc');

echo '<div id="shopping_cart">
			<div class="row headings">
				<div class="description_column">' . _('Item Description') . '</div>
				<div class="totalnumber">' . _('Total') . '</div>
				<div class="quantity_heading">' . _('Quantity') . '</div>
				<div class="price_column">' . _('Price') . '</div>
			</div>';
$i=0;
$CartTotalValue=0;
$CartTotalWeight=0;
$CartTotalVolume=0;
foreach($_SESSION['ShoppingCart'] as $CartItem) {
	$GrossPrice = $CartItem->PriceExcl*(1+$_SESSION['TaxRates'][$CartItem->TaxCatID]);
	$LineTotal = $GrossPrice * $CartItem->Quantity;
	$CartTotalValue += $LineTotal;
	$CartTotalWeight += $CartItem->Weight * $CartItem->Quantity;
	$CartTotalVolume += $CartItem->Volume * $CartItem->Quantity;
	echo '<div class="row">
			<div class="description_column">' . $CartItem->Description . '</div>
			<div class="totalnumber">' . locale_number_format($LineTotal,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</div>
			<div class="refreshqty">
				<button id="update_cart" type="submit" name="UpdateCart"><img src="css/refresh-icon.png" title="' . _('Update Quantity') . '" ></button>
				<a title="'. _('Remove item') . '" href="' . $RootPath . '/index.php?Delete=' . $CartItem->StockID . '"><img src="css/remove-icon.png"></a>
			</div>
			<div class="price_column">' . locale_number_format($GrossPrice,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</div>
			<div class="number" title="'. _('Enter New Quantity') . '"><input type="text" class="number" size="6" maxlength="6" pattern="[0-9\.]*" name="Quantity' . $i . '" value="' . locale_number_format($CartItem->Quantity,$CartItem->DecimalPlaces) . '" /><input type="hidden" name="StockID' . $i . '" value="' . $CartItem->StockID . '" />
			</div>
		</div>';
	$i++;
} //end loop around the cart items

if (isset($_SESSION['LoggedIn'])){
	// if customer is logged in we know destination, so we can calculate freight costs. Otherwise (before logging in) makes no sense.
	$_SESSION['TotalDue'] = $CartTotalValue;
	$_SESSION['TotalVolume'] = $CartTotalVolume;
	$_SESSION['TotalWeight'] = $CartTotalWeight;
	/*	ReCalculate freight costs by webERP functions */
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

	if ($FreightCost != 'NOT AVAILABLE'){
		$_SESSION['FreightCost'] = $FreightCost;
		$sqlShipper = "SELECT shippername FROM shippers WHERE shipper_id= '" . $BestShipper . "'";
		$resultShipper = DB_query($sqlShipper,$db);
		while ($myrowShipper = DB_fetch_array($resultShipper)) {
			$_SESSION['FreightMethodSelected'] = $myrowShipper[0];
		}
		if ($_SESSION['FreightCost'] != 0){
			$FreightCostInclTax = $_SESSION['FreightCost']*(1+$_SESSION['TaxRates'][$_SESSION['FreightTaxCategory']]);
			$_SESSION['TotalDue'] += $FreightCostInclTax;
			echo '<div class="row">
					<div class="number_total">' . locale_number_format($FreightCostInclTax,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</div>
					<div class="total_label">' . _('Freight cost') . '</div>
				</div>';
		} else {
			echo '<div class="row">
					<div class="number_total">'. _('Freight Costs paid by') . ' ' . $_SESSION['ShopName'] . '</div>
				</div>';
		}
	} else {
		$_SESSION['FreightMethodSelected'] = 'NOT AVAILABLE';
	}
}
if (isset($_SESSION['SelectedPaymentMethod'])){
	if ($PaymentMethods[$_SESSION['SelectedPaymentMethod']]['Surcharge']!=0 AND $_SESSION['ShopAllowSurcharges']==1){
		$_SESSION['TotalDue'] += $SurchargeAmount; //SurchargeAmount calculated in header.php
		echo '<div class="row">
				<div class="number_total">' . locale_number_format($SurchargeAmount,$_SESSION['CustomerDetails']['currdecimalplaces']) . '</div>
				<div class="total_label">' . $PaymentMethods[$_SESSION['SelectedPaymentMethod']]['MethodName'] . ' ' . _('Surcharge') . ' @ ' . $Surcharge . ' %&nbsp;</div>
			</div>';
	}
}
echo '<div class="row totalvalue">
		<div class="number_total">' . locale_number_format($_SESSION['TotalDue'],$_SESSION['CustomerDetails']['currdecimalplaces']) . '</div>
		<div class="total_label">' . _('Total Due') . '&nbsp;' . $_SESSION['CustomerDetails']['currcode'] . '&nbsp;<span class="tax_label">' . _('incl tax') . '</span></div>
	</div>';
?>
