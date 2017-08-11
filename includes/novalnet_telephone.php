<?php
#########################################################
#                                                       #
#  TELEPHONE PAYMENT payment method script              #
#  This module is used for real time processing of      #
#  TELEPHONE PAYMENT data of customers.                 #
#                                                       #
#  Released under the GNU General Public License.       #
#  This free contribution made by request.              #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Script : novalnet_telephone.php                      #
#                                                       #
#########################################################

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// register the payment
function register_novalnet_telephone( $gateways ) {
	$gateways['novalnet_telephone'] = array(
		'admin_label' 	 => __( 'Novalnet Telephone Payment', 'edd-novalnet-gateway' ),
		'checkout_label' => __( 'Novalnet Telephone Payment', 'edd-novalnet-gateway' )
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'register_novalnet_telephone' );

// register the action to display Test mode description
function novalnet_telephone_display_testmode() {
	global $edd_options;
    $test_mode 	 = ( $test_mode = ( edd_is_test_mode() ) ? 1 : ( isset( $edd_options['novalnet_telephone_test_mode'] ) ? 1 : 0 ) );
    $information = $edd_options['novalnet_telephone_information'] ? trim($edd_options['novalnet_telephone_information']) : '';

	if ( $test_mode ) {
		// display test mode description
		echo wpautop( '<strong><font color="red">' . __( 'Please Note: This transaction will run on TEST MODE and the amount will not be charged', 'edd-novalnet-gateway' ) . '</font></strong>' );
	}

	echo  __( 'Your amount will be added in your telephone bill when you place the order', 'edd-novalnet-gateway' );
	echo $information ? '<br />' . $information : '';
}
add_action( 'edd_novalnet_telephone_cc_form', 'novalnet_telephone_display_testmode' );

// register the action to initiate and processes the payment
function novalnet_telephone_process_payment( $purchase_data ) {
	global $edd_options;
	$payment_code		= 'novalnet_telephone';
	$paygate_url 		= ( is_ssl() ? 'https://' : 'http://' ) . 'payport.novalnet.de/paygate.jsp';
	$config_data   		= novalnet_get_merchant_data( $purchase_data, $payment_code );
	$customer_data 		= novalnet_get_customer_data( $purchase_data );
	$params		   		= array_merge( $config_data, $customer_data );
	$purchase_summary 	= edd_get_purchase_summary( $purchase_data );
	$novalnet_tid 		= EDD()->session->get('novalnet_telephone');
	$novalnet_tid 		= $novalnet_tid['tid'];
	$validate_amount 	= novalnet_validate_telephone_amount( $purchase_data );

	if ( $validate_amount ) {
		return $validate_amount;
	}

    if ( empty( $novalnet_tid ) ) {
		$urlparam = http_build_query( $params );
		list($errno, $errmsg, $data) = novalnet_handle_communication( $paygate_url, $urlparam );
		parse_str( $data, $aryResponse );
		novalnet_check_telephone_payment_status( $aryResponse );
    } else {
		$amount_variation_validate = novalnet_validate_amount_variation( $purchase_data );
		if ( $amount_variation_validate ) {
			return $amount_variation_validate;
		}

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
        return(novalnet_send_telephone_statuscall( $params['order_no'] ));
	}
}
add_action( 'edd_gateway_novalnet_telephone', 'novalnet_telephone_process_payment' );

//validate amount variation after first call
function novalnet_validate_amount_variation( $purchase_data ) {
	$order_total 		= $purchase_data['price']*100;
	$first_call_amount 	= $novalnet_tid['amount'];
    if ( isset( $first_call_amount ) && (string) $first_call_amount != (string) $order_total ) {
        novalnet_unset_telephone();
        edd_set_error( 'novalnet_tel_amount_variation_validation', __( 'You have changed the order amount after receiving telephone number, please try again with a new call', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
    }
    return('');
}

//validate cart amount within the Telephone Payment limit
function novalnet_validate_telephone_amount( $purchase_data ) {
    $order_total = $purchase_data['price']*100;
    if ( $order_total < 99 || $order_total > 1000 ) {
        edd_set_error( 'novalnet_tel_amount_validation', __( 'Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted!', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
    }
}

// add the settings of the Novalnet Telephone Payment
function novalnet_telephone_add_settings( $settings ) {

	$novalnet_telephone_gateway_settings = array(
		array(
			'id' 		=> 'novalnet_telephone_settings',
			'name' 		=> '<strong> <font color="red">' . __( 'Novalnet Telephone Payment Settings', 'edd-novalnet-gateway' ) . '</font> </strong>',
			'desc' 		=> __( 'Configure the gateway settings', 'edd-novalnet-gateway' ),
			'type' 		=> 'header'
		),
		array(
			'id' 		=> 'novalnet_telephone_test_mode',
			'name' 		=> __( 'Enable Test Mode', 'edd-novalnet-gateway' ),
			'type' 		=> 'checkbox',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_merchant_id',
			'name' 		=> __( 'Novalnet Merchant ID ', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_auth_code',
			'name' 		=> __( 'Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Merchant Authorisation code', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_product_id',
			'name' 		=> __( 'Novalnet Product ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Product ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_tariff_id',
			'name' 		=> __( 'Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Enter your Novalnet Tariff ID', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_order_completion_status',
			'name' 		=> __( 'Order completion status', 'edd-novalnet-gateway' ),
			'type' 		=> 'select',
			'options'	=> edd_get_payment_statuses(),
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_information',
			'name' 		=> __( 'Information to the end customer (this will appear in the payment page)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_referrer_id',
			'name' 		=> __( 'Referrer ID', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'Referrer ID of the partner at Novalnet, who referred you (only numbers allowed)', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_reference_1',
			'name' 		=> __( 'Transaction reference 1', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
		array(
			'id' 		=> 'novalnet_telephone_reference_2',
			'name' 		=> __( 'Transaction reference 2', 'edd-novalnet-gateway' ),
			'desc' 		=> __( 'This will appear in the transactions details / account statement', 'edd-novalnet-gateway' ),
			'type' 		=> 'text',
			'size' 		=> 'regular'
		),
	);
	return array_merge( $settings, $novalnet_telephone_gateway_settings );
}
add_filter( 'edd_settings_gateways', 'novalnet_telephone_add_settings' );