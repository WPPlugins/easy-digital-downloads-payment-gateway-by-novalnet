<?php
#########################################################
#                                                       #
#  DIRECT DEBIT SEPA payment method script       		#
#  This module is used for real time processing of      #
#  Debit Card data of customers.                        #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_sepa.php                           #
#                                                       #
#########################################################

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// register the gateway
function register_novalnet_sepa( $gateways ) {
	require_once( NOVALNET_PLUGIN_DIR . 'novalnet_css_link.php' );
	$gateways['novalnet_sepa'] = array(
		'admin_label' 	 => __( 'Novalnet Direct Debit SEPA', 'edd-novalnet-gateway' ),
		'checkout_label' => __( 'Novalnet Direct Debit SEPA', 'edd-novalnet-gateway' )
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'register_novalnet_sepa' );

//register the action to display Novalnet Direct Debit SEPA iFrame
function novalnet_sepa_display_iframe() {
	global $edd_options;
	$user_data 		 	= get_user_meta( get_current_user_id() );
	$address_details 	= unserialize( $user_data['_edd_user_address'][0] );
    $test_mode 			= ( $test_mode = ( edd_is_test_mode() ) ? 1 : ( isset( $edd_options['novalnet_sepa_test_mode'] ) ? 1 : 0 ) );
    $information 		= $edd_options['novalnet_sepa_information'] ? trim( $edd_options['novalnet_sepa_information'] ) : '';

	if ( $test_mode ) {
		// display test mode description
		echo wpautop( '<strong><font color="red">' . __( 'Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'edd-novalnet-gateway' ) . '</font></strong>' );
	}
	echo  __( 'Your account will be debited upon delivery of goods.', 'edd-novalnet-gateway' );
	echo $information ? '<br />'. $information : ''.'<br>';

	$vendor_id  = isset( $edd_options['novalnet_sepa_merchant_id'] ) ? trim( $edd_options['novalnet_sepa_merchant_id'] ) : '';	
	$auth_code  = isset( $edd_options['novalnet_sepa_auth_code'] ) ? trim( $edd_options['novalnet_sepa_auth_code'] ) : '';
	$product_id = isset( $edd_options['novalnet_sepa_product_id'] ) ? trim( $edd_options['novalnet_sepa_product_id'] ) : '';
	$auto_refil = isset( $edd_options['novalnet_sepa_auto_refil'] ) ? trim( $edd_options['novalnet_sepa_auto_refil'] ) : '';

	if ( defined( 'NOVALNET_SEPA_CUSTOM_CSS' ) ) {
        $original_sepa_css = NOVALNET_SEPA_CUSTOM_CSS;
	}
    else {
		$original_sepa_css = 'body~~~input, select~~~td~~~#novalnetCc_cc_type, #novalnetCc_expiration, #novalnetCc_expiration_yr~~~#novalnetCc_cc_type~~~#novalnetCc_expiration~~~#novalnetCc_expiration_yr~~~td';
	}

    if ( defined( 'NOVALNET_SEPA_CUSTOM_CSS_STYLE' ) ) {
        $original_sepa_cssval = NOVALNET_SEPA_CUSTOM_CSS_STYLE;
	}
    else {
		$original_sepa_cssval = 'font-family:Open Sans,Helvetica,Arial,sans-serif;font-size:12px;~~~border: 1px solid #CCCCCC; border-radius: 3px; padding: 0.428571rem;height:17px !important;width:180px;~~~color:#5E5E5E;~~~height:34px !important;~~~width:196px !important;~~~width:107px !important;~~~width:80px;~~~padding:0.428571rem !important;';
	}

	$sepa_refill_details = EDD()->session->get( 'novalnet_sepa' );	
	$nnsepa_hash 		= ( ( isset( $auto_refil ) && 1 == $auto_refil ) ? ( isset( $sepa_refill_details['hash'] ) ? $sepa_refill_details['hash'] : '' ) : '' );	
	$fldVdr 			= ( isset( $sepa_refill_details['flvdr'] ) && ! empty( $nnsepa_hash ) ) ? $sepa_refill_details['flvdr'] : '' ;	
	$language           = strtoupper( substr( get_bloginfo( 'language' ) , 0, 2 ) );
	$firstname 			= urlencode( $user_data['first_name'][0] );
	$lastname  			= urlencode( $user_data['last_name'][0] );
	$email 	   			= urlencode( wp_get_current_user()->user_email );
	$address   			= urlencode( $address_details['line1'] );
	$country   			= urlencode( $address_details['country'] );
	$city      			= urlencode( $address_details['city'] );
	$zip       			= $address_details['zip'];
	$customer_details 	= "&first_name=$firstname&last_name=$lastname&email=$email&country=$country&postcode=$zip&city=$city&address=$address";
	$config_details    = "&lang=$language&key=37&sepahash=$nnsepa_hash&fldVdr=$fldVdr";	
	include_once( NOVALNET_PLUGIN_DIR . 'templates/novalnet_sepa.php' );
}
add_action( 'edd_novalnet_sepa_cc_form', 'novalnet_sepa_display_iframe' );

//register the action to initiate and process the Payment
function novalnet_sepa_process_payment( $purchase_data ) {
	global $edd_options;
	$payment_code		= 'novalnet_sepa';
	$paygate_url 		= ( is_ssl() ? 'https://' : 'http://' ) . 'payport.novalnet.de/paygate.jsp';
	$config_data   		= novalnet_get_merchant_data( $purchase_data, $payment_code );
	$customer_data 		= novalnet_get_customer_data( $purchase_data );
	$bank_data	   		= novalnet_sepa_get_bank_data( $purchase_data );
	$params		   		= array_merge( $config_data, $bank_data, $customer_data );
	$purchase_summary 	= edd_get_purchase_summary( $purchase_data );

	/**********************************
	 * set up the payment details     *
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
	$urlparam = http_build_query( $params );
	list($errno, $errmsg, $data) = novalnet_handle_communication( $paygate_url, $urlparam );
	parse_str( $data, $aryResponse );
	novalnet_check_response( $aryResponse, $params['order_no'] );
}
add_action( 'edd_gateway_novalnet_sepa', 'novalnet_sepa_process_payment' );

//get SEPA Account details from iFrame
function novalnet_sepa_get_bank_data( $purchase_data ) {
	array_map( "trim", $_POST );
	$sepa_owner 		= $_POST['sepa_owner'];
	$sepa_uniquid 		= $_POST['sepa_uniqueid'];
	$sepa_panhash 		= $_POST['panhash'];
	$sepa_iban_confirm 	= $_POST['sepa_confirm'];
	$sepa_flvdr 		= $_POST['sepa_field_validator'];
	$nnsepa = array(
		'hash' 	=> $sepa_panhash,
		'flvdr' => $sepa_flvdr
	);	
	EDD()->session->set( 'novalnet_sepa', $nnsepa );

	if($sepa_iban_confirm != 1) {
		edd_set_error( 'iban_confirm_validation', __( 'Please confirm IBAN & BIC!', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
	} elseif( ! $sepa_panhash || ! $sepa_uniquid || preg_match( '/[#%\^<>@$=*!]+/i', $sepa_owner ) ) {
		edd_set_error( 'card_validation', __( 'Please enter valid account details!', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
	} else {
		$bank_data = array(
			'bank_account_holder' 	=> $sepa_owner,
			'sepa_hash' 			=> $sepa_panhash,
			'sepa_unique_id' 		=> $sepa_uniquid,
			'bank_account' 			=> '',
			'bank_code' 			=> '',
			'bic' 					=> '',
			'iban' 					=> '',
			'iban_bic_confirmed' 	=> $sepa_iban_confirm,
		);
	}
	return $bank_data;
}

// adds the settings to the Payment Gateways section
function novalnet_sepa_add_settings( $settings ) {

	$novalnet_sepa_gateway_settings = array(
		array(
			'id' 		=> 'novalnet_sepa_settings',
			'name' 		=> '<strong> <font color="red">' . __( 'Novalnet Direct Debit SEPA Settings', 'edd-novalnet-gateway' ) . '</font> </strong>',
			'desc' 		=> __( 'Configure the gateway settings', 'edd-novalnet-gateway' ),
			'type' 		=> 'header'
		),
		array(
			'id' 		=> 'novalnet_sepa_test_mode',
			'name' 		=> __( 'Enable Test Mode', 'edd-novalnet-gateway' ),
			'type' 		=> 'checkbox',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_auto_refil',
			'name' 		=> __( 'Auto refill the payment data entered in payment page', 'edd-novalnet-gateway' ),
			'type' 		=> 'checkbox',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_merchant_id',
			'name' 		=> __( 'Novalnet Merchant ID ', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_auth_code',
			'name' 		=> __( 'Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_product_id',
			'name' 		=> __( 'Novalnet Product ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Product ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_tariff_id',
			'name' 		=> __( 'Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_manual_limit',
			'name' 		=> __( 'Manual checking of order, above amount in cents (Note: this is a onhold booking, needs your manual verification and activation)', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'All the orders above this amount will be set on hold by Novalnet and only after your manual verifcation and confirmation at Novalnet the booking will be done', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_second_product_id',
			'name' 		=> __( 'Second Product ID for manual check condition', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Second Product ID in Novalnet to use the manual check condition', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_second_tariff_id',
			'name' 		=> __( 'Second Tariff ID for manual check condition', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Second Tariff ID in Novalnet to use the manual check condition', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_due_date',
			'name' 		=> __( 'SEPA Payment duration in days', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter the Due date in days, it should be greater than 6. If you leave as empty means default value will be considered as 7 days', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_order_completion_status',
			'name' 		=> __( 'Order completion status', 'edd-novalnet-gateway' ),
			'type' 		=> 'select',
			'options'	=> edd_get_payment_statuses(),
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_information',
			'name' 		=> __( 'Information to the end customer (this will appear in the payment page)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_referrer_id',
			'name' 		=> __( 'Referrer ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Referrer ID of the partner at Novalnet, who referred you (only numbers allowed)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_reference_1',
			'name' 		=> __( 'Transaction reference 1', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_sepa_reference_2',
			'name' 		=> __( 'Transaction reference 2', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
	);
	return array_merge( $settings, $novalnet_sepa_gateway_settings );
}
add_filter( 'edd_settings_gateways', 'novalnet_sepa_add_settings' );