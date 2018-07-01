# webSHOP
Shopping cart application integrated to webERP


Multi-level dynamic css menu structure based on sales categories - sales categories can have tranlations so customers in other languages can see the translation in their language. Also a vertical indented menu structure to view sales categories and a search function to find products.

Coded in the same accessible style as webERP - a truly integrated shopping cart solution - that creates native webERP customers and user logins, orders and receipts.
jQuery validation and user interface
The long description shows as a tool tip when the user hovers above the item. Thumbnail picture shows on search and a large image shows as a tool tip when the user hovers over the thumbnail - thus saving screen real-estate and allowing fast review of items to purchase without recourse to multiple screens.

Sales in the currency of the customer
When a customer registers they can select their currency and their preferred language - prices are re-calculated at that time. The currencies in webERP can be flagged as available to web-shop customers . However, webERP prices for the currency under review for the sales type of the default web-customer need to be defined otherwise items will not display.

Only items with prices setup in the currency and price list of the customer will display

When a customer registers in the web-shop, they are set up with a customer account - the account reference is based on a numercial sequence maintained in webERP (systype =500). Newly created customer accounts inherit all the attributes of the default web-shop customer. The default web-shop customer is defined in the web-shop configuration in webERP. The new customer accounts will have the same pricing (sales type), same terms, same area, sales rep, tax group etc. However, they can select an alternative currency (and the pricing for that currency will be used in pricing web-orders) and they can also select their own interface language. If alternative terms are agreed with the customer then these can be changed inside webERP.

Customers with a credit account are not presented with paypal or credit card payment methods - the shop only places the order for sale on credit to their account.

Payment options by:
- bank transfer
- paypal
- credit card

Variable surcharge can be applied depending on the payment method selected by the customer.
If the customer elects to pay by bank transfer, then this is a low cost alternative - saving the bank fee which can be up to 4% on credit cards/paypal. The convenience and immediacy of payment by paypal or credit card is still available and some shops may elect to have no surcharges on any payment method.

Using paypal requires a paypal account. The credit card processing uses the paypal "payments pro" API and there are fees associated with its use currently USD $30 per month plus 3.9% and $0.30 USD per transaction (not cheap!). A paypal mercant account subscribed to "Payments Pro" is required to use this credit card processing option. This is only available in the US and Canada.
Also the Payflow Pro credit card processing API is implemented which allows payments over the Payflow pro API - this is a PayPal initiative with partners available at many countries throughout the world - again a Pay Pal merchant account is required subscribed to Pay Flow pro through a PayPal partner gateway.
SwipeHQ - credit card processing also implemented - available in New Zealand only. 

All the necessary data for the store is entered via webERP - including:

- item pictures
- customer pricing
- descriptions
- long descriptions

Stock quantities on the shop front are reported from the shop inventory location only and reported as 20+ if more than 20 of an item is held. If no stock on hand in the location it is reported as "Arriving Soon".

Customer registration creates a webERP customer login for the customer account - new accounts are created with numerical account codes based on the webERP systype counter

webERP now has some extended configuration to allow the store configuration from the webERP setup module - this allows entry of html that will display on the information pages - the standard webERP includes the shop configuration page and is populated with sample English "Privacy Statement" and "Terms and Condition" which need to be reviewed as appropriate in a live installation:
- "About Us"
- "Privacy Statement"
- "Terms and Conditions"
- "Contact Us"


Item descriptions can now be held in several languages - the languages that are to be used for item descriptions can be specified in the webERP configuration now and new fields in the stock maintenance screen come up for the translations required. webERP invoices and statements now use the language of the customer.

How To setup your web shop

All the set up work is really done inside webERP.

To define the items which shold display in your web-shop, you must set up sales categories and define the items under each category. If an item is to be listed on the opening screen as a "featured" item it must be flagged as featured in webERP sales categories set up.

Also, only items that have a price defined in the currency and sales type of the default shop customer will show - if there is no price set up then the item will not even be listed.

Sales Categories are defined from the webERP main menu - Inventory->Maintenance->Sales Category Maintenance

Sales Categories are much more flexible than stock categories. It is possible to set up a heirarchy of sales categories for example in the demo we have a sales category for DVDs and underneath that we have sales categories for Drama, Action, Thriller etc then under Action we have Cruise, Shwartzeneger, Gibson - any number of levels of categories can be set up. This category heirarchy defines the menu structure for the web-shop again illustrated in the shop demo.

It doesn't matter what the stock category of the item is in webERP the sales category is completely separate and any item can be allocated to any sales category.
Only items defined as belonging to a sales category will be displayed in the web-shop so items that are not for resale to the public should not be set up under the sales category structure.

The web-shop requires configuration of the following:

The web-shop default customer and customer branch

The web-shop customer defines all the defaults for new customers set up through the shop - the currency of transactions (although the web-user can alter this), sales area, sales type (price list), sales person, tax group etc - all new customers defined through the web-shop will inherit all the settings of this default customer. All web-shop customers created will appear in webERP - using a numerical code - and if necessary any of their setting can be modified to allow then to use a special price list (sales type) or have credit terms. The default customer would normally have cash terms and if this is the case the options for payment will come up in the web-shop. If the customer has credit terms then the order will not bring up any payment options and it is assumed that the sale will be on credit.

When a customer first goes into the web-shop to order, the shop shows using all the settings of the default customer. Only when the user logs in do the settings applicable to their customer account show

Any existing webERP customer can be set up as a web-shop customer simply by adding a user login for the customer account having selected the customer there is an option to add a customer login account. However, when a customer logs into the web-shop they must use their email address as their login name not their username. When the user/customer logs in the setup of the customer will define the currency of the order, the tax treatment, credit terms etc.
