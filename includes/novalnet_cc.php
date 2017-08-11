<?php
#########################################################
#                                                       #
#  CREDIT CARD payment method script               		#
#  This module is used for real time processing of      #
#  CREDIT CARD data of customers.                       #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_cc.php                             #
#                                                       #
#########################################################

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// register the payment
function register_novalnet_cc( $gateways ) {
	require_once(NOVALNET_PLUGIN_DIR . 'novalnet_css_link.php');
	$gateways['novalnet_cc'] = array(
		'admin_label' 	 => __( 'Novalnet Credit Card', 'edd-novalnet-gateway' ),
		'checkout_label' => __( 'Novalnet Credit Card', 'edd-novalnet-gateway' )
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'register_novalnet_cc' );

// register the action to display Novalnet Credit Card iFrame
function novalnet_cc_display_iframe() {
	global $edd_options;	
    $test_mode 	 = ( $test_mode = ( edd_is_test_mode() ) ? 1 : ( isset( $edd_options['novalnet_cc_test_mode'] ) ? 1 : 0 ) );
    $information = $edd_options['novalnet_cc_information'] ? trim( $edd_options['novalnet_cc_information'] ) : '';

	if ( $test_mode ) {
	// display test mode description
		echo wpautop( '<strong><font color="red">' . __( 'Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'edd-novalnet-gateway' ) . '</font></strong>' );
	}

	echo  __( 'The amount will be booked immediately from your credit card when you submit the order.', 'edd-novalnet-gateway' );
	echo $information ? '<br />' . $information : '';

	$vendor_id  = isset( $edd_options['novalnet_cc_merchant_id'] ) ? trim( $edd_options['novalnet_cc_merchant_id'] ) : '';
	$auth_code  = isset( $edd_options['novalnet_cc_auth_code'] ) ? trim( $edd_options['novalnet_cc_auth_code'] ) : '';
	$product_id = isset( $edd_options['novalnet_cc_product_id'] ) ? trim( $edd_options['novalnet_cc_product_id'] ) : '';
	$auto_refil = isset( $edd_options['novalnet_cc_auto_refil'] ) ? trim( $edd_options['novalnet_cc_auto_refil'] ) : '';

	if ( defined( 'NOVALNET_CC_CUSTOM_CSS' ) ) {
        $original_cc_css = NOVALNET_CC_CUSTOM_CSS;
	}
    else {
		$original_cc_css = 'body~~~input, select~~~td~~~#novalnetCc_cc_type, #novalnetCc_expiration, #novalnetCc_expiration_yr~~~#novalnetCc_cc_type~~~#novalnetCc_expiration~~~#novalnetCc_expiration_yr~~~td';
	}

	if ( defined( 'NOVALNET_CC_CUSTOM_CSS_STYLE' ) ) {
        $original_cc_cssval = NOVALNET_CC_CUSTOM_CSS_STYLE;
	}
    else {
		$original_cc_cssval = 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~color:#5E5E5E;~~~height:34px !important;~~~width:196px !important;~~~width:107px !important;~~~width:80px;~~~padding:0.428571rem !important;';
	}
	
	$cc_refill_details 	= EDD()->session->get( 'novalnet_cc' );
	$nncc_hash 	= ( ( isset( $auto_refil ) && 1 == $auto_refil ) ? ( isset( $cc_refill_details['hash'] ) ? $cc_refill_details['hash'] : ''  ) : '' );
	$fldVdr 	= ( ( isset( $cc_refill_details['flvdr'] ) && !empty( $cc_refill_details ) ) ?	$cc_refill_details['flvdr'] : '' );
	$language 	= strtoupper( substr( get_bloginfo( 'language' ), 0, 2 ) );
	$config_details = "&lang=$language&key=6&panhash=$nncc_hash&fldVdr=$fldVdr";

	include_once(NOVALNET_PLUGIN_DIR.'templates/novalnet_cc.php');
}
add_action( 'edd_novalnet_cc_cc_form', 'novalnet_cc_display_iframe' );

//register the action to initiate and process the Payment
function novalnet_cc_process_payment( $purchase_data ) {
	global $edd_options;
	$payment_code		= 'novalnet_cc';
	$cc3d_active  		= isset( $edd_options['novalnet_cc_3d_activate'] ) ? trim( $edd_options['novalnet_cc_3d_activate'] ) : '';
	$paygate_url 		= ( is_ssl() ? 'https://' : 'http://' ) . 'payport.novalnet.de/paygate.jsp';
	$config_data   		= novalnet_get_merchant_data( $purchase_data, $payment_code );
	$customer_data 		= novalnet_get_customer_data( $purchase_data );
	$card_data	   		= novalnet_cc_get_card_data( $purchase_data );
	$params		   		= array_merge( $config_data, $card_data, $customer_data );
	$purchase_summary 	= edd_get_purchase_summary( $purchase_data );
	
	/**********************************
	* set up the payment details      *
	**********************************/

	$payment = array(
		'price' 		=> $purchase_data['price'],
		'date' 			=> $purchase_data['date'],
		'user_email' 	=> $purchase_data['user_email'],
		'purchase_key' 	=> $purchase_data['purchase_key'],
		'currency' 		=> $edd_options['currency'],
		'downloads' 	=> $purchase_data['downloads'],
		'cart_details' 	=> $purchase_data['cart_details'],
		'user_info' 	=> $purchase_data['user_info'],
		'status' 		=> 'pending'
	);
	// Initiate the pending payment
	$params['order_no']	= edd_insert_payment( $payment );
	$nn_config_values 	= array(
		'vendor' 	=> $config_data['vendor'],
		'auth_code' => $config_data['auth_code'],
		'product' 	=> $config_data['product'],
		'tariff' 	=> $config_data['tariff'],
		'key' 		=> $config_data['key']
	);
	update_post_meta( $params['order_no'], '_nn_config_values', $nn_config_values );

	if ( isset( $cc3d_active ) && 1 == $cc3d_active ) {
		$cc3d_paygate_url 			= (is_ssl() ? 'https://' : 'http://') . 'payport.novalnet.de/global_pci_payport';
		$params['encoded_amount']   = $params['amount'];
        $encoded_params             = array('encoded_amount');
        novalnet_encode( $params,  $encoded_params); 
		novalnet_get_redirect( $cc3d_paygate_url, $params );
	} else {
		$urlparam = http_build_query( $params );
		list($errno, $errmsg, $data) = novalnet_handle_communication( $paygate_url, $urlparam );
		parse_str( $data, $aryResponse );
		novalnet_check_response( $aryResponse, $params['order_no'] );
	}
}
add_action( 'edd_gateway_novalnet_cc', 'novalnet_cc_process_payment' );

//get Credit Card values from iFrame
function novalnet_cc_get_card_data( $purchase_data ) {
	array_map( "trim", $_POST );
	$cc_type 		= $_POST['cc_type'];
	$cc_holder 		= $_POST['cc_holder'];
	$exp_month 		= $_POST['cc_exp_month'];
	$exp_year 		= $_POST['cc_exp_year'];
	$cvc 			= $_POST['cc_cvv_cvc'];
	$nncc_hash 		= $_POST['nncc_hash'];
	$nncc_unique 	= $_POST['nn_unique'];
	$nncc_flvdr 	= $_POST['cc_field_validator'];
	$nncc = array(
		'hash' 	=> $nncc_hash,
		'flvdr' => $nncc_flvdr
	);	
	EDD()->session->set( 'novalnet_cc', $nncc );

	if ( empty( $cc_type ) || empty( $nncc_hash )	|| empty( $nncc_unique ) || ! ctype_digit( $cvc )
	|| empty( $cc_holder )	|| preg_match("/[#%\^<>@$=*!]+/i", $cc_holder) || empty( $exp_year ) || empty( $exp_month )
	|| ( ( $exp_year == date('Y') ) && ( $exp_month < date('m') ) ) ) {
		edd_set_error( 'card_validation', __( 'Please enter valid credit card details!', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
	} else {
		$card_data = array(
			'cc_type' 		=> $cc_type,
			'cc_holder' 	=> $cc_holder,
			'cc_no' 		=> '',
			'cc_exp_month' 	=> $exp_month,
			'cc_exp_year' 	=> $exp_year,
			'cc_cvc2' 		=> $cvc,
			'pan_hash' 		=> $nncc_hash,
			'unique_id' 	=> $nncc_unique,
		);
	}

	return $card_data;
}

// adds the settings of the Novalnet Credit Card section
function novalnet_cc_add_settings( $settings ) {

	$novalnet_cc_gateway_settings = array(
		array(
			'id' 		=> 'novalnet_cc_settings',
			'name' 		=> '<strong><font color="red">' . __( 'Novalnet Credit Card Settings', 'edd-novalnet-gateway' ) . '</font> </strong>',
			'desc' 		=> __( 'Configure the gateway settings', 'edd-novalnet-gateway' ),
			'type' 		=> 'header'
		),
		array(
			'id' 		=> 'novalnet_cc_test_mode',
			'name' 		=> __( 'Enable Test Mode', 'edd-novalnet-gateway' ),
			'type' 		=> 'checkbox',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_3d_activate',
			'name' 		=> __( '3D Secure(note : this has to be set up at Novalnet first. Please contact support@novalnet.de, in case you wish this.)', 'edd-novalnet-gateway' ),
			'desc' 		=> __( '(Please note that this procedure has a low acceptance among end customers.) As soon as 3D-Secure is activated for credit cards, the bank prompts the end customer for a password, to prevent credit card abuse. This can serve as a proof, that the customer is actually the owner of the credit card', 'edd-novalnet-gateway' ),
			'type' 		=> 'checkbox',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_auto_refil',
			'name' 		=> __( 'Auto refill the payment data entered in payment page', 'edd-novalnet-gateway' ),
			'type' 		=> 'checkbox',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_merchant_id',
			'name' 		=> __( 'Novalnet Merchant ID ', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_auth_code',
			'name' 		=> __( 'Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_product_id',
			'name' 		=> __( 'Novalnet Product ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Product ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_tariff_id',
			'name' 		=> __( 'Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_manual_limit',
			'name' 		=> __( 'Manual checking of order, above amount in cents (Note: this is a onhold booking, needs your manual verification and activation)', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'All the orders above this amount will be set on hold by Novalnet and only after your manual verifcation and confirmation at Novalnet the booking will be done', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_second_product_id',
			'name' 		=> __( 'Second Product ID for manual check condition', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Second Product ID in Novalnet to use the manual check condition', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_second_tariff_id',
			'name' 		=> __( 'Second Tariff ID for manual check condition', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Second Tariff ID in Novalnet to use the manual check condition', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_access_key',
			'name' 		=> __( 'Novalnet Payment access key', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet payment access key', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_order_completion_status',
			'name' 		=> __( 'Order completion status', 'edd-novalnet-gateway' ),
			'type' 		=> 'select',
			'options'	=> edd_get_payment_statuses(),
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_information',
			'name' 		=> __( 'Information to the end customer (this will appear in the payment page)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_referrer_id',
			'name' 		=> __( 'Referrer ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Referrer ID of the partner at Novalnet, who referred you (only numbers allowed)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_reference_1',
			'name' 		=> __( 'Transaction reference 1', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_cc_reference_2',
			'name' 		=> __( 'Transaction reference 2', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size'		=> 'regular'
		)
	);
	return array_merge( $settings, $novalnet_cc_gateway_settings );
}
add_filter( 'edd_settings_gateways', 'novalnet_cc_add_settings' );