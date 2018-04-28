<?php

class CartItem {
	
	var $StockID;
	var $Description;
	var $LongDescription;
	var $PriceExcl;
	var $Quantity;
	var $DecimalPlaces;
	var $TaxCatID;
	var $Weight;
	var $Volume;
	var $MBFlag;
	var $DiscountCategory;
	var $Discount;
	
	function CartItem ($StockID,
						$Description,
						$LongDescription,
						$PriceExcl,
						$Quantity,
						$DecimalPlaces,
						$TaxCatID,
						$DiscountCategory,
						$Weight,
						$Volume,
						$MBFlag,
						$Discount) {
							
		$this->StockID = $StockID;
		$this->Description = $Description;
		$this->LongDescription = $LongDescription;
		$this->PriceExcl = $PriceExcl;
		$this->Quantity = $Quantity;
		$this->DecimalPlaces = $DecimalPlaces;
		$this->TaxCatID = $TaxCatID;
		$this->DiscountCategory = $DiscountCategory;
		$this->Weight = $Weight;
		$this->Volume = $Volume;
		$this->MBFlag = $MBFlag;
		$this->Discount = $Discount;
	}
}

class Message {
	
	var $MessageText;
	var $Severity;
	
	function Message ($MessageText, $Severity) {
		$this->MessageText = $MessageText;
		$this->Severity = $Severity;
	}
}
?>
