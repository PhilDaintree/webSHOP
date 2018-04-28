<?php

$MenuLinksHtml = display_sub_categories('','');//recursive function to display through all levels of categories defined
if ($_SESSION['ShopShowTopCategoryMenu'] != 0){/* We should show the left vertical menu */
	echo '<div id="menu_block">' . $MenuLinksHtml . '</div>';
}else{
	echo '<div id="menu_block"></div>';
}

echo '	<div id="content_block">';

if ($_SESSION['ShopShowLeftCategoryMenu'] != 0){/* We should show the left vertical menu */
	//menu_block - showing category link buttons
	echo '		<div id="column_left">
					<div id="column_heading">' . _('Categories') . '</div>';
	//substitute the horizontal css dropdown class for the vertical one
	echo str_replace(' class="dropdown dropdown-horizontal"','',$MenuLinksHtml);
	//end of left_category_menu div
	echo '</div>';
}

?>