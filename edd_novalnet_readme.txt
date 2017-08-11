#################################################################
#                                                               #
#  INSTALLATION GUIDE                                           #
#                                                               #
#  Direct Debit SEPA,  						#
#  Credit Card (3DSecure and non 3DSecure): Visa, Mastercard, 	#
#  Amex, JCB, CUP. Debitcard: Maestro         			#
#  Prepayment, Invoice, Online Transfer, iDEAL, eps, PayPal,    #
#  Instant Bank Transfer, Telephone.		         	#
#                                                               #
#  These modules are programmed in high standard and supports	#
#  PCI DSS Standard and the Trustshops Standard	used for 	# 
#  real time processing of transactions through Novalnet	#
#                                                               #
#  Released under the GNU General Public License                #
#                                                               #
#  This free contribution made by request.                      #
#  If you have found this script usefull a small recommendation #
#  as well as a comment on merchant form would be greatly       #
#  appreciated.                                                 #
#                                                               #
#  Copyright (c) Novalnet AG   		                        #
#                                                               #
#################################################################
#					    			#
#  SPECIFICATION DETAILS		                	#
#					    			#
#  Created	     	        	  - Novalnet AG         #
#					    			#
#  CMS(WordPress) Version		  - 4.0.1	        #
#					    			#
#  CMS Compatibility			  - 3.9.1 - 4.0.1       #
#					    			#
#  Shop (Easy Digital Downloads) Version  - 2.2                 #
#					    			#
#  Shop Compatibility			  - 2.0 - 2.2           #
#				                		#
#  Novalnet Version             	  - 1.0.0		#
#				                		#
#  Last Updated	        		  - 10-12-2014          #
#				                		#
#  Categories	        		  - Payment & Gateways  #
#					    			#
#################################################################


IMPORTANT: The files freewarelicenseagreement.txt and testdata.txt are parts of this readme file.

How to install:
---------------

Step 1: 
========

You have to install php modules: curl and php-curl in your Webserver.
      Refer to the following website for installation instructions: 
        http://curl.haxx.se/docs/install.html.

      If you use Ubantu/Debian, you can try the following commands:
        sudo apt-get install curl php5-curl php5-mcrypt
        apachectl restart (restart the Webserver)


Step 2: 
========

a) To install NovalnetAG payment module, 
	kindly refer "IG-wordpress_3.9.1-4.0.1_easydigitaldownloads_2.0-2.2_novalnet_1.0.0.pdf".

b) To install NovalnetAG Callback Script,

  	Please Copy the 'callback_novalnet2easydigitaldownloads.php' file and place into the " Wordpress <Root_Directory>/ ".

  Example: /var/www/wordpress/

Step 3:
======

To display Novalnet Transaction details in Frontend Order success page, please do below changes.

File Path: root/wp-content/plugins/easy-digital-downloads/templates
File Name: shortcode-receipt.php

Search "<?php echo edd_get_gateway_checkout_label( edd_get_payment_gateway( $payment->ID ) ); ?>" and replce the line with below codes

<?php $nn_post_id = get_post( $payment->ID );
echo edd_get_gateway_checkout_label( edd_get_payment_gateway( $payment->ID ) ). wpautop($nn_post_id->post_excerpt); ?>

Step 4:
======

To display Novalnet Transaction details in Order confirmation mail, please do below changes.

File Path: root/wp-content/plugins/easy-digital-downloads/includes/emails
File Name: email-tags.php (version 2.0 - 2.0.4)
File Name: class-edd-email-tags.php (version 2.1 - 2.2) 

Serach "return edd_get_gateway_checkout_label( edd_get_payment_gateway( $payment_id ) );" and replce the line with below codes

$nn_post_id = get_post( $payment_id );
return edd_get_gateway_checkout_label( edd_get_payment_gateway( $payment_id ) ). wpautop($nn_post_id->post_excerpt);

Note: You should use the {payment_method} tag in the "Downloads-->Settings-->E-mails-->Order confirmation, to display Novalnet Transaction details along with Payment method name.

Step 5:
======

If you wish to display Novalnet Transaction details and comments in proper manner in shop backend, please do below changes.

File Path: root/wp-content/plugins/easy-digital-downloads/includes/payments
File Name: functions.php

Search "$note_html .= $note->comment_content;" and replce the line with below codes

$note_html .= wpautop($note->comment_content);
====================================================================================================================
Important Note: 
* 1. Shop has default Test mode setting for all payment gateway. In addition to that, we are providing Test mode 
setting for each individual Novalnet payment methods. To test a particular Novalnet payment method in Test mode, 
you can enable Test mode in the particular payment method's configuration.

* 2. Address fields (Billing Address, Billing City, Billing Zip / Postal Code) are mandatory to proceed with Novalnet 
payments. Kindly enable the address fields in Shop Backend to display in Frontend.

To enable address fields Admin-->Downloads-->Settings-->Taxes-->Enable Taxes

---------------------------------------------------------------------------------------------------------------------
Note: In Telephone payment method the guest user has to enter his/her address details in checkout form for both first 
call and second call. This is not necessary for registered user. [shop default flow]
---------------------------------------------------------------------------------------------------------------------	
Note: I
=======

If you wish to display Novalnet Credit Card and Novalnet Direct Debit SEPA form in your specified template , Please do following changes
File Path: root/wp-content/plugins/edd-novalnet-gateway 
File Name: novalnet_css_link.php

i) Novalnet Credit Card
-----------------------

Kindly search the following codes and fill-out the respective values in below mentioned HTML tags

// code to add css values

define('NOVALNET_CC_CUSTOM_CSS','');	## enter here your css value between the single quotation as per your style
define('NOVALNET_CC_CUSTOM_CSS_VALUE','');	## enter here your css value between the single quotation as per your style

// code to add css values

for example :-

define('NOVALNET_CC_CUSTOM_CSS', 'body~~~input, select~~~td~~~#novalnetCc_cc_type, #novalnetCc_expiration, #novalnetCc_expiration_yr~~~#novalnetCc_cc_type~~~#novalnetCc_expiration~~~#novalnetCc_expiration_yr~~~td');
define('NOVALNET_CC_CUSTOM_CSS_STYLE', 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~color:#5E5E5E;~~~height:34px !important;~~~width:196px !important;~~~width:107px !important;~~~width:80px;~~~padding:0.428571rem !important;');


ii) Novalnet Direct Debit SEPA
-----------------------------

Kindly search the following codes and fill-out the respective values in below mentioned HTML tags

// code to add css values

define('NOVALNET_SEPA_CUSTOM_CSS','');	## enter here your css value between the single quotation as per your style
define('NOVALNET_SEPA_CUSTOM_CSS_VALUE','');	## enter here your css value between the single quotation as per your style

// code to add css values

for example :-

define('NOVALNET_SEPA_CUSTOM_CSS', 'body~~~input, select~~~#novalnet_sepa_country~~~input.mandate_confirm_btn');
define('NOVALNET_SEPA_CUSTOM_CSS_STYLE', 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~height:34px !important;width:196px !important;~~~height:32px !important;');
-------------------------------------------------------------------------------	
----------------------------------------------------------
Important Notice for Online Transfer (Sofortüberweisung):
----------------------------------------------------------
For testing, please always use the test transaction data (bank code, bank account number, etc.) provided by Novalnet, otherwise the real transactions will be performed, even though the test mode is on/activated!

CALLBACK SCRIPT: this is necessary for keeping your database/system actual and synchrone with the Novalnet's transaction status.
--------------------------------------------------------------------------------------------------------------------------------
Your system will be notified through Novalnet system(asynchrone) about each transaction and its status.

For example, if you use Novalnet's "Invoice/Prepayment/PayPal" payment methods then on recieval of the credit entry, your system will be notified through the Novalnet system and your system can automatically change the status of the order: from "pending" to "complete". 

Please use the "callback_novalnet2easydigitaldownloads.php" provided in this payment package. Please follow the instructions in the "Callbackscript_testing_procedure.txt" file. You will find more details in the "callback_novalnet2easydigitaldownloads.php" script itself.

Step to update callback script url in Novalnet Administration area for callback script execution :
After logging into Novalnet Administration area, please choose your particular project navigate to "PROJECT" menu, then select appropriate "Project" and navigate to "Project Overview" tab and then update callback script url in "Vendor script URL" field.
Ex: https://edd.novalnet.de/callback_novalnet2easydigitaldownloads.php

please contact us on sales@novalnet.de for activating other payment methods
===========================================================================

OUR CONTACT DETAILS / YOU CAN REACH US ON:      

Tel    : +49 (0)89 923 068 320

Web    : www.novalnet.de
E-mail : sales@novalnet.de
===========================================================================
