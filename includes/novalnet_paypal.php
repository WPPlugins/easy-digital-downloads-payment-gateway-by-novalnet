<?php
#########################################################
#                                                       #
#  PAYPAL payment method script                			#
#  This module is used for real time processing of      #
#  PAYPAL data of customers.                       		#
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_paypal.php                         #
#                                                       #
#########################################################

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// register the gateway
function register_novalnet_paypal( $gateways ) {
	$gateways['novalnet_paypal'] = array(
		'admin_label' 	 => __( 'Novalnet PayPal', 'edd-novalnet-gateway' ),
		'checkout_label' => __( 'Novalnet PayPal', 'edd-novalnet-gateway' )
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'register_novalnet_paypal' );

// register the action to display Test mode description
function novalnet_paypal_display_testmode() {
	global $edd_options;
    $test_mode   = ( $test_mode = ( edd_is_test_mode() ) ? 1 : ( isset( $edd_options['novalnet_paypal_test_mode'] ) ? 1 : 0 ) );
    $information = $edd_options['novalnet_paypal_information'] ? trim( $edd_options['novalnet_paypal_information'] ) : '';

	if( $test_mode ) {
		// display test mode description
		echo wpautop( '<strong><font color="red">' . __( 'Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'edd-novalnet-gateway' ) . '</font></strong>' );
	}

	echo  __( 'You will be redirected to Novalnet AG website when you place the order.', 'edd-novalnet-gateway' );
	echo $information ? '<br />' . $information : '';
}
add_action( 'edd_novalnet_paypal_cc_form', 'novalnet_paypal_display_testmode' );

// register the action to initiate and processes the payment
function novalnet_paypal_process_payment( $purchase_data ) {
	global $edd_options;
	$payment_code		= 'novalnet_paypal';
	$paygate_url 		= ( is_ssl() ? 'https://' : 'http://' ) . 'payport.novalnet.de/paypal_payport';
	$config_data   		= novalnet_get_merchant_data( $purchase_data, $payment_code );
	$customer_data 		= novalnet_get_customer_data( $purchase_data );
	$params		   		= array_merge( $config_data, $customer_data );
	$encoded_params		= array( 'auth_code', 'product', 'tariff', 'amount', 'test_mode', 'uniqid', 'api_user', 'api_pw', 'api_signature' );
	novalnet_encode ( $params, $encoded_params );
	$params['hash']		= novalnet_generate_hash( $params );
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
	novalnet_get_redirect( $paygate_url, $params );
}
add_action( 'edd_gateway_novalnet_paypal', 'novalnet_paypal_process_payment' );

// add the settings of the Novalnet PayPal
function novalnet_paypal_add_settings( $settings ) {

	$novalnet_paypal_gateway_settings = array(
		array(
			'id' 		=> 'novalnet_paypal_settings',
			'name' 		=> '<strong> <font color="red">' . __( 'Novalnet PayPal Settings', 'edd-novalnet-gateway' ) . '</font> </strong>',
			'desc' 		=> __( 'Configure the gateway settings', 'edd-novalnet-gateway' ),
			'type' 		=> 'header'
		),
		array(
			'id' 		=> 'novalnet_paypal_test_mode',
			'name' 		=> __( 'Enable Test Mode', 'edd-novalnet-gateway' ),
			'type' 		=> 'checkbox',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_merchant_id',
			'name' 		=> __( 'Novalnet Merchant ID ', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_auth_code',
			'name' 		=> __( 'Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_product_id',
			'name' 		=> __( 'Novalnet Product ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Product ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_tariff_id',
			'name' 		=> __( 'Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_access_key',
			'name' 		=> __( 'Novalnet Payment access key', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet payment access key', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_api_username',
			'name' 		=> __( 'PayPal API User Name', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Please enter your PayPal API username', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_api_password',
			'name' 		=> __( 'PayPal API Password', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Please enter your PayPal API password', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_api_signature',
			'name' 		=> __( 'PayPal API Signature', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Please enter your PayPal API signature', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_order_status_before_payment',
			'name' 		=> __( 'Order Status Before Payment', 'edd-novalnet-gateway' ),
			'type' 		=> 'select',
			'options'	=> edd_get_payment_statuses(),
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_order_completion_status',
			'name' 		=> __( 'Order Status After Payment', 'edd-novalnet-gateway' ),
			'type' 		=> 'select',
			'options'	=> edd_get_payment_statuses(),
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_information',
			'name' 		=> __( 'Information to the end customer (this will appear in the payment page)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_referrer_id',
			'name' 		=> __( 'Referrer ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Referrer ID of the partner at Novalnet, who referred you (only numbers allowed)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_reference_1',
			'name' 		=> __( 'Transaction reference 1', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_paypal_reference_2',
			'name' 		=> __( 'Transaction reference 2', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
	);
	return array_merge( $settings, $novalnet_paypal_gateway_settings );
}
add_filter( 'edd_settings_gateways', 'novalnet_paypal_add_settings' );