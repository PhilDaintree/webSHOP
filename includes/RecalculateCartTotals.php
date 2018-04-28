<?php
//update the shopping cart quantities from the shopping cart form quantities entered
$TotalCartValue = 0;
$CountItems = 0;
$SurchargeAmount = 0;
$PhysicalDeliveryRequired = false;
if (isset($_SESSION['ShoppingCart']) AND count($_SESSION['ShoppingCart'])>0) {
	if (isset($_POST['UpdateCart'])){
			//first off update the quantities
		foreach ($_POST as $PostVariableName=>$Quantity) {
			if (mb_strpos($PostVariableName,'Quantity')!==false) {
				$_GET['Page']='ShoppingCart';
				if (is_numeric(filter_number_format($Quantity))){
					$_SESSION['ShoppingCart'][$_POST['StockID' . mb_substr($PostVariableName,8)]]->Quantity = filter_number_format($Quantity);
				}
			}
		}
	}
	foreach ($_SESSION['ShoppingCart'] as $CartItem){
		if (is_object($CartItem)){
			$TotalCartValue += ($CartItem->Quantity*$CartItem->PriceExcl*(1+$_SESSION['TaxRates'][$CartItem->TaxCatID]));
			$CountItems += $CartItem->Quantity;
			if ($CartItem->MBFlag != 'D') {
				$PhysicalDeliveryRequired = true;
			} 
		}
	}
	//add any surcharge for payment method 
	if (isset($_SESSION['SelectedPaymentMethod'])){
		$Surcharge = $PaymentMethods[$_SESSION['SelectedPaymentMethod']]['Surcharge'];
		if ($Surcharge !=0){
			$SurchargeAmount = $Surcharge/100 * $TotalCartValue;
			$TotalCartValue += $SurchargeAmount;
		}
	}
	//add any freight charge
	if (isset($_SESSION['FreightCost']) AND $_SESSION['FreightCost']!=0){
		$TotalCartValue += $_SESSION['FreightCost']*(1+$_SESSION['TaxRates'][$_SESSION['FreightTaxCategory']]);
	}
}
$_SESSION['SurchargeAmount'] = $SurchargeAmount;
$_SESSION['TotalDue'] = $TotalCartValue;
?>