<?php
/* $Id: footer.php 5785 2012-12-29 04:47:42Z daintree $ */
echo '<div id="footer_block">
		<div class="navbar">
			<div class="top-links">
				<a id="TermsAndConditions" href="' . htmlspecialchars($_SERVER['PHP_SELF'] . '?Page=TermsAndConditions', ENT_QUOTES,'UTF-8') . '">' . _('Terms and Conditions') . '</a>
				<a id="PrivacyPolicy" href="' . htmlspecialchars($_SERVER['PHP_SELF'] . '?Page=PrivacyPolicy', ENT_QUOTES,'UTF-8') .'">' . _('Privacy Policy') . '</a>
				<a id="FreightPolicy" href="' . htmlspecialchars($_SERVER['PHP_SELF'] . '?Page=FreightPolicy', ENT_QUOTES,'UTF-8') . '">' . _('Freight Policy') . '</a>
				<a id="AboutUs" href="' . htmlspecialchars($_SERVER['PHP_SELF'] . '?Page=AboutUs', ENT_QUOTES,'UTF-8') . '">' . _('About Us') . '</a>
				<a id="ContactUs" href="' . htmlspecialchars($_SERVER['PHP_SELF'] . '?Page=ContactUs', ENT_QUOTES,'UTF-8') .'">' . _('Contact Us') . '</a>
			</div><!-- End top-links div -->
		</div><!-- End navbar div -->
	</div><!-- End footer-block div -->
	</body>
</html>'; //end footer_block

?>
