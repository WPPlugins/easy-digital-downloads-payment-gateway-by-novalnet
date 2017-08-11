<?php
/*
 * Plugin Name: Easy Digital Downloads Payment Gateway by Novalnet
 * Plugin URI:  https://www.novalnet.de
 * Description: Adds Novalnet Payment Gateway to Easy Digital Downloads plugin
 * Author:      Novalnet AG
 * Author URI:  https://www.novalnet.de
 * Version:     1.0.0
 * Text Domain: edd-novalnet-gateway
 * Domain Path: /languages/
 * License: 	GPLv2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'novalnet_initialize_payments', 0 );
add_action( 'admin_notices', 'novalnet_admin_notices' );
add_action( 'wp_logout', 'novalnet_unset_all_sessions' );
add_action( 'wp_login', 'novalnet_unset_all_sessions' );
register_deactivation_hook( __FILE__, 'novalnet_deactivate_plugin' );

// actions to initialize Novalnet Payments
function novalnet_initialize_payments() {
		novalnet_setup_constants();		
		load_plugin_textdomain( 'edd-novalnet-gateway', false, dirname( plugin_basename( NOVALNET_PLUGIN_FILE ) ) . '/languages/' );
		foreach ( glob( NOVALNET_PLUGIN_DIR."includes/*.php" ) as $filename ) {
			include_once $filename;
		}
}

// Display admin notice at WordPress admin during Plug-in activation
function novalnet_admin_notices() {
    if ( ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
        $nn_message = '<b>' . __( 'Easy Digital Downloads Payment Gateway by Novalnet', 'edd-novalnet-gateway' ) . '</b> ' . __( 'add-on requires', 'edd-novalnet-gateway' ) . ' ' . '<a href="https://easydigitaldownloads.com" target="_new">' . __('Easy Digital Downloads', 'edd-novalnet-gateway' ) . '</a>' . ' ' . __( 'plugin. Please install and activate it.', 'edd-novalnet-gateway' );
    }
    elseif ( ! function_exists( 'curl_init' ) ) {
		$nn_message = '<b>' . __( 'Easy Digital Downloads Payment Gateway by Novalnet', 'edd-novalnet-gateway' ) . '</b> ' . __( 'requires ', 'edd-novalnet-gateway' ) . __( 'PHP CURL.', 'edd-novalnet-gateway' ) . '</a>' . ' ' . __( ' Please install/enable php_curl!', 'edd-novalnet-gateway' );    
	}
	echo isset( $nn_message ) ? '<div id="notice" class="error"><p>' . $nn_message.  '</p></div>' : '';
}

// actions to perform once on Plug-in deactivation
function novalnet_deactivate_plugin() {
    $config_settings = get_option( 'edd_settings' );        
    $config_tmp = array_merge( $config_settings, $config_settings['gateways'] );
    foreach ( $config_tmp as $key => $value ) {
        if ( preg_match( '/novalnet/', $key ) ) {
			if ( isset( $config_settings[$key] ) ) {
				unset( $config_settings[$key]);
			}
			else {
				unset( $config_settings['gateways'][$key] );
			}
        }
    }
    update_option( 'edd_settings', $config_settings );
}

add_action( 'wp_enqueue_scripts', 'novalnet_load_admin_scripts' );

 //Enqueue Novalnet scripts
function novalnet_load_admin_scripts() {
	wp_enqueue_script( 'nnsepa', plugins_url( '/assets/js/nnsepa.js' , __FILE__ ), '', NOVALNET_VERSION, true );
	wp_enqueue_script( 'nncc', plugins_url( '/assets/js/nncc.js' , __FILE__ ), '', NOVALNET_VERSION, true );
}

//get and form Novalnet configuration data
function novalnet_get_merchant_data( $purchase_data, $payment_method ) {
	global $edd_options;		
	$form_payments 		= array( 'novalnet_cc', 'novalnet_sepa' );
    $redirect_payments 	= array( 'novalnet_banktransfer', 'novalnet_ideal', 'novalnet_paypal' );
    $invoice_payments 	= array( 'novalnet_invoice', 'novalnet_prepayment' );
    $payment_key 		= array(
		'novalnet_cc' 			=> 6,
		'novalnet_sepa' 		=> 37,
		'novalnet_invoice' 		=> 27,
		'novalnet_prepayment' 	=> 27,
		'novalnet_banktransfer' => 33,
		'novalnet_paypal' 		=> 34,
		'novalnet_ideal' 		=> 49,
		'novalnet_telephone' 	=> 18
    );		   
	$config_data['key']  		= $payment_key[$payment_method];	
	$config_data['vendor']  	= trim( $edd_options[$payment_method.'_merchant_id'] );		
	$config_data['auth_code']  	= trim( $edd_options[$payment_method.'_auth_code'] );	
	$config_data['product'] 	= trim( $edd_options[$payment_method.'_product_id'] );
	$config_data['tariff'] 		= trim( $edd_options[$payment_method.'_tariff_id'] );
    $config_data['test_mode'] 	= ( $test_mode = ( edd_is_test_mode() ) ? 1 : ( isset( $edd_options[$payment_method.'_test_mode'] ) ? 1 : 0 ) );
	$cc3d_enable  				= isset( $edd_options['novalnet_cc_3d_activate'] ) ? trim( $edd_options['novalnet_cc_3d_activate'] ) : '';

	if ( ! ctype_digit( $config_data['vendor'] ) || ! ctype_digit( $config_data['product'] )
	|| ! ctype_digit( $config_data['tariff'] ) || empty( $config_data['auth_code'] ) ) {
		edd_set_error( 'basic_validation', __( 'Basic Parameter not valid', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
	}
	
	$config_data['amount']		= sprintf("%0.2f", $purchase_data['price'])*100;
	if ( preg_match( '/[^\d\.]/', $config_data['amount'] ) ) {
        edd_set_error( 'amount_validation', __( 'Amount value is not valid', 'edd-novalnet-gateway' ) );
        edd_send_back_to_checkout();
	}
	
	if ( ( in_array( $payment_method, $redirect_payments ) ) || ( $payment_method == 'novalnet_cc'
	 && $cc3d_enable ) ) {
		if ( trim( $edd_options[$payment_method.'_access_key'] ) == '' ) {
			edd_set_error( 'basic_validation', __( 'Basic Parameter not valid', 'edd-novalnet-gateway' ) );
			edd_send_back_to_checkout();
		}
				
		//form return URL and error return URL data
		$config_data['return_url'] 			= get_permalink( $edd_options['success_page'] );
		$config_data['return_method'] 		= 'POST';
		$config_data['error_return_url'] 	= edd_get_checkout_uri() . '&payment-mode=' . $purchase_data['post_data']['edd-gateway'];
		$config_data['error_return_method'] = 'POST';
		$config_data['user_variable_0'] 	= site_url();
		
		if ( $payment_method == 'novalnet_paypal' ) {
			$config_data['api_user']  = trim( $edd_options[$payment_method.'_api_username'] );
			$config_data['api_pw']    = trim( $edd_options[$payment_method.'_api_password'] );
			$config_data['api_signature'] = trim( $edd_options[$payment_method.'_api_signature'] );
			if ( empty( $config_data['api_user'] )	|| empty( $config_data['api_pw'] ) || empty( $config_data['api_signature'] ) ) {
				edd_set_error( 'basic_validation', __( 'Basic Parameter not valid', 'edd-novalnet-gateway' ) );
				edd_send_back_to_checkout();
			}
		}		
	} 
	if ( in_array( $payment_method, $form_payments ) ) {
		$manual_limit 	= intval( trim( $edd_options[$payment_method.'_manual_limit'] ) );		
		if ( $manual_limit > 0 ) {
			$product_id2 	= trim( $edd_options[$payment_method.'_second_product_id'] );
			$tariff_id2 	= trim( $edd_options[$payment_method.'_second_tariff_id'] );
			if ( ! ctype_digit( $product_id2 ) || ! ctype_digit( $tariff_id2 ) ) {				
				edd_set_error( 'manual_limit_validation', __( 'Manual limit amount / Product-ID2 / Tariff-ID2 is not valid', 'edd-novalnet-gateway' ) );
				edd_send_back_to_checkout();
			} elseif ( $manual_limit <= $config_data['amount'] ) {				
					$config_data['product'] = $product_id2;
					$config_data['tariff']  = $tariff_id2;
			}
		}
	}
	
	if ( in_array( $payment_method, $redirect_payments ) ) {
		$config_data['uniqid'] = uniqid();		
		$config_data['implementation'] = 'PHP';			
	} elseif ( in_array( $payment_method, $invoice_payments ) ) {
		if( $payment_method == 'novalnet_invoice' ) {
			$config_data['invoice_type'] = 'INVOICE';
			$payment_duration  = trim( $edd_options['novalnet_invoice_due_date'] );
			if ( ctype_digit( $payment_duration ) ) {
				$config_data['due_date'] = date('Y-m-d', strtotime( "+ " . $payment_duration . "days" ) );
			}
		} else {
			$config_data['invoice_type'] = 'PREPAYMENT';
		}	
		
	} elseif ( $payment_method == 'novalnet_sepa' ) {
		$sepa_payment_duration = ( trim( $edd_options['novalnet_sepa_due_date'] ) == '' ) ? 7 : trim( $edd_options['novalnet_sepa_due_date'] ) ;
        if ( ! is_numeric( $sepa_payment_duration ) || $sepa_payment_duration < 7 ) {
            edd_set_error( 'sepa_duedate_validation', __( 'SEPA Due date is not valid', 'edd-novalnet-gateway' ) );
            edd_send_back_to_checkout();
        } else {
            $config_data['sepa_due_date'] = date('Y-m-d', strtotime('+ '. $sepa_payment_duration . "days"));
        }
	}	
	$referrer_id 		= trim( $edd_options[$payment_method.'_referrer_id'] );
	$reference_1 		= trim( strip_tags( $edd_options[$payment_method.'_reference_1'] ) );
	$reference_2 		= trim( strip_tags( $edd_options[$payment_method.'_reference_2'] ) );	
	if ( ! empty( $referrer_id ) && is_numeric( $referrer_id ) ) {
		$config_data['referrer_id'] = $referrer_id;
	}
	
	if ( ! empty( $reference_1 ) ) {
        $config_data['input1']    = 'reference1';
        $config_data['inputval1'] = $reference_1;
    }
    
    if ( ! empty( $reference_2 ) ) {
        $config_data['input2']    = 'reference2';
        $config_data['inputval2'] = $reference_2;
    }
	return $config_data;
}

//get and form customer details need to Novalnet server
function novalnet_get_customer_data( $purchase_data ) {
	$language           = strtoupper( substr( get_bloginfo( 'language' ) , 0, 2 ) );
	$firstname 			= $purchase_data['user_info']['first_name'];
	$lastname 			= $purchase_data['user_info']['last_name'];
	$email 				= isset( $purchase_data['user_email'] ) ? $purchase_data['user_email'] : $purchase_data['user_info']['email'];
	$address_field1 	= isset( $purchase_data['user_info']['address']['line1'] ) ? trim( $purchase_data['user_info']['address']['line1'] ) : '';	
	$address_field2 	= isset( $purchase_data['user_info']['address']['line2'] ) ? trim( $purchase_data['user_info']['address']['line2'] ) : '';
	$address_field2     = $address_field2 ? ',' . $address_field2 : '';
	$street 			= $address_field1 . $address_field2;
	$remote_ip 			= edd_get_ip();
    $remote_ip 			= ( ( $remote_ip == '::1' ) ? '127.0.0.1' : $remote_ip );
    $customer_no        = ( ( $purchase_data['user_info']['id'] == '-1' ) ? 'guest' : $purchase_data['user_info']['id'] );
    
	if ( empty( $firstname ) || empty( $lastname ) ) {
		$name = $firstname.$lastname;
		list( $firstname, $lastname ) = preg_match( '/\s/', $name ) ? explode( ' ', $name, 2 ) : array($name, $name);
	}
	
	if ( empty( $firstname ) || empty( $lastname ) || empty( $email ) ) {
		edd_set_error( 'customer_validation', __( 'Customer name/email fields are not valid', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
	}

	if ( empty( $purchase_data['user_info']['address']['city'] ) || empty( $purchase_data['user_info']['address']['zip'] )
	|| empty( $purchase_data['user_info']['address']['country'] ) || empty( $street ) ) {
		edd_set_error( 'customer_validation', __( 'Note: The filed tax was not enabled in this shop to get the invoice details necessary for executing Novalnet payments.', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
	}
		
	$customer_data = array(
		'first_name' 		=> $firstname,
		'last_name' 		=> $lastname,
		'email' 			=> $email,
		'street' 			=> $street,
		'search_in_street' 	=> 1,
		'city' 				=> $purchase_data['user_info']['address']['city'],
		'country_code' 		=> $purchase_data['user_info']['address']['country'],
		'country' 			=> $purchase_data['user_info']['address']['country'],
		'zip' 				=> $purchase_data['user_info']['address']['zip'],		
		'currency' 			=> edd_get_currency(),
		'customer_no' 		=> $customer_no,
		'use_utf8' 			=> 1,
		'gender' 			=> 'u',		
		'remote_ip' 		=> $remote_ip,
		'lang' 				=> $language,
		'system_name'		=> 'wordpress-easydigitaldownloads',
		'system_version'	=> get_bloginfo( 'version' ) . ' - ' . EDD_VERSION . ' - ' . NOVALNET_VERSION,
		'system_url'		=> site_url(),
		'system_ip'			=> $_SERVER['SERVER_ADDR']
	);
								
	return $customer_data;
}

//redirect to Novalnet paygate for redirection payments
function novalnet_get_redirect( $paygate_url, $params ) {
    $frmData = '<form name="frmnovalnet_payment" method="post" action="' . $paygate_url . '">';
    $frmEnd = __( 'You will be redirected to Novalnet AG in a few seconds', 'edd-novalnet-gateway' ).'<br> <input type="submit" name="enter" value='.__( 'Redirecting...', 'edd-novalnet-gateway' ).'></form>';
    $js = '<script>document.forms.frmnovalnet_payment.submit();</script>';
    foreach ( $params as $k => $v ) {
        $frmData .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />' . "\n";
    }
    echo $frmData, $frmEnd, $js;
    exit();
}

//send acknowledgement to Novalnet server
function novalnet_send_acknowledgement( $tid, $order_no ) {		
	$config_data 			= get_post_meta( $order_no, '_nn_config_values', true );
	$invoice_payments 		= array( 'novalnet_invoice', 'novalnet_prepayment' );
	$payment_gateways 		= EDD()->session->get( 'edd_purchase' );
	$config_data['status'] 	= 100;
	$config_data['tid'] 	= $tid;
	$config_data['order_no']= $order_no;
	
	if ( in_array( $payment_gateways['gateway'], $invoice_payments ) ) {
		$config_data['invoice_ref'] = 'BNR-' . $config_data['product'] . '-' . $order_no;
		$config_data['invoice_type'] = $payment_gateways['gateway'] == 'novalnet_invoice' ? 'INVOICE' : 'PREPAYMENT';
	}
	
	if ( ! empty( $config_data ) ) {		
		$urlparam = http_build_query( $config_data );				
		$postback_url = ( is_ssl() ? 'https://' : 'http://' ) . 'payport.novalnet.de/paygate.jsp';
		list( $errno, $errmsg, $data ) = novalnet_handle_communication( $postback_url, $urlparam );			
	}
}

//check Novalnet response
function novalnet_check_response( $response, $nn_order_no ) {
	$payment_gateways 	= EDD()->session->get( 'edd_purchase' );					
	if ( isset( $response['status'] ) && ( $response['status'] == 100 || ( $payment_gateways['gateway'] == 'novalnet_paypal'
	&& $response['status'] == 90 ) ) ) { 
		novalnet_success( $response, $nn_order_no );
	} else {
		novalnet_failure( $response, $nn_order_no );
	}
}

//update and insert Novalnet Transaction details in database and payment note for Payment success
function novalnet_success( $response, $nn_order_no ) {
	global $edd_options;	
	$invoice_payments 	= array( 'novalnet_invoice', 'novalnet_prepayment' );
	$redirect_payments 	= array( 'novalnet_banktransfer', 'novalnet_ideal', 'novalnet_paypal' );
	$payment_gateways 	= EDD()->session->get( 'edd_purchase' );
	$new_line 			= "\n";
	$novalnet_comments 	= '';
	$total_amount = get_post_meta( $nn_order_no, '_edd_payment_total' );
	if ( in_array( $payment_gateways['gateway'], $invoice_payments ) || ( $payment_gateways['gateway'] == 'novalnet_paypal'
	&& $response['status'] == 90 ) ) {
		$final_order_status = $edd_options[$payment_gateways['gateway'] . '_order_status_before_payment'];			
		update_post_meta( $nn_order_no, '_nn_callback_amount', 0 );
	} else {
		//set the purchase to complete
		$final_order_status = $edd_options[$payment_gateways['gateway'] . '_order_completion_status'];		
		update_post_meta( $nn_order_no, '_nn_callback_amount', $total_amount['0']*100 );
	}									
	update_post_meta( $nn_order_no, '_nn_order_tid', $response['tid'] );				                 
    $novalnet_comments .= __( 'Novalnet Transaction ID: ', 'edd-novalnet-gateway' ) . $response['tid'] . $new_line;
    
    if ( in_array( $payment_gateways['gateway'], $redirect_payments ) ) {
		$response['test_mode'] = novalnet_decode( $response['test_mode'] );
	}
    $novalnet_comments .= ( $test_mode = ( edd_is_test_mode() ) ? 1 : ( isset( $edd_options[$payment_gateways['gateway'].'_test_mode'] ) ? 1 : ( $response['test_mode'] ? 1 : 0 ) ) ) ? __('Test order', 'edd-novalnet-gateway') : '' ;         
    if ( in_array( $payment_gateways['gateway'], $invoice_payments ) ) {			
		$novalnet_comments .= $response['test_mode'] ? $new_line . $new_line : $new_line;
		$novalnet_comments .= __( 'Please transfer the amount to the following information to our payment service Novalnet AG', 'edd-novalnet-gateway' ) . $new_line;
		if ( $payment_gateways['gateway'] == 'novalnet_invoice' ) {
				$novalnet_comments .= __( 'Due date: ', 'edd-novalnet-gateway' ) . date_i18n( get_option( 'date_format' ), strtotime( $response['due_date'] ) ) . $new_line;
		}
		$novalnet_comments.= __( 'Account holder: Novalnet AG', 'edd-novalnet-gateway' ) . $new_line;
		$novalnet_comments.= __( 'IBAN: ', 'edd-novalnet-gateway' ) . $response['invoice_iban'] . $new_line;
		$novalnet_comments.= __( 'BIC: ', 'edd-novalnet-gateway' ) . $response['invoice_bic'] . $new_line;
		$novalnet_comments.= __( 'Bank: ', 'edd-novalnet-gateway' ) . $response['invoice_bankname'] . " " . trim($response['invoice_bankplace']) . $new_line;
		$novalnet_comments.= __( 'Amount: ', 'edd-novalnet-gateway' ) . edd_currency_filter( edd_format_amount( $response['amount'] ) ) . $new_line;
		$novalnet_comments.= __( 'Reference: TID ', 'edd-novalnet-gateway' ) . $response['tid'];				
	}
    $novalnet_comments = html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8' );                               
	/* Update Novalnet Transaction details into shop database */
    $nn_order_notes = array(
        'ID' 			=> $nn_order_no,
        'post_excerpt' 	=> $novalnet_comments
    );
    wp_update_post( $nn_order_notes );
    edd_update_payment_status( $nn_order_no, $final_order_status );
    /* Update Novalnet Transaction details into payment note */
    edd_insert_payment_note( $nn_order_no, $novalnet_comments );                    
	novalnet_send_acknowledgement( $response['tid'], $nn_order_no );
	if ( in_array( $payment_gateways['gateway'], $invoice_payments ) && $final_order_status != 'publish') {
		edd_trigger_purchase_receipt ( $nn_order_no );
	}	
	edd_empty_cart();		
	novalnet_unset_all_sessions();
	// go to the Success page			
	edd_send_to_success_page();
}

//update and insert Novalnet Transaction details in database and payment note for Payment failure
function novalnet_failure( $response, $nn_order_no ) {	
	update_post_meta( $nn_order_no, '_nn_order_tid', $response['tid'] );
	$novalnet_comments  = __( 'Novalnet Transaction ID: ', 'edd-novalnet-gateway' ) . $response['tid'] . "\n";
	$response_text 		= isset( $response['status_text'] ) ? $response['status_text'] : ( isset( $response['status_desc'] ) ? $response['status_desc'] : '' );
	$novalnet_comments .= $response_text;
	$novalnet_comments = html_entity_decode( $novalnet_comments, ENT_QUOTES, 'UTF-8' );                        
	/* Update Novalnet Transaction details into shop database */
	$nn_order_notes = array(
        'ID' 			=> $nn_order_no,
        'post_excerpt' 	=> $novalnet_comments
    );       
             
    wp_update_post( $nn_order_notes );
    edd_update_payment_status( $nn_order_no, 'failed' );
    /* Update Novalnet Transaction details into payment note */
    edd_insert_payment_note( $nn_order_no, $novalnet_comments );
    update_post_meta( $nn_order_no, '_nn_order_tid', $response['tid'] );
	edd_set_error( 'server_direct_validation', __( $response_text, 'edd-novalnet-gateway' ) );
	// go to the Checkout page	
	edd_send_back_to_checkout();
}

//receive Novalnet response for redirection payments
function novalnet_get_redirect_response() {
	if ( isset( $_REQUEST['novalnet_iframe'] ) ) {
		novalnet_get_iframe();
	}
	$redirect_response = $_POST;
	if ( isset( $redirect_response['status'] ) && isset( $redirect_response['tid'] ) && isset( $redirect_response['user_variable_0'] ) ) {
		if ( isset( $redirect_response['hash'] ) ) {
			if ( ! novalnet_check_hash( $redirect_response ) ) {
				edd_set_error( 'hash_validation', __( 'Check Hash failed', 'edd-novalnet-gateway' ) );
				edd_send_back_to_checkout();
			} else {
				novalnet_check_response( $redirect_response, $redirect_response['order_no'] );				
			} 
		} else {
			novalnet_check_response( $redirect_response, $redirect_response['order_no'] );
		}
	} else
		return;
}
add_action( 'init', 'novalnet_get_redirect_response' );

//check first call response and display telephone number to the  end user
function novalnet_check_telephone_payment_status( $response ) {
	global $edd_options;
    $new_line = "<br />";
    if ( $response['status'] == 100 && $response['tid'] ) {
        $response['status_desc'] = '';
        $response_amount 	= $response['amount']*100;
        $novalnet_tid 		= EDD()->session->get( 'novalnet_telephone' );
        $novalnet_tid = $novalnet_tid['tid'] ;
        if ( empty( $novalnet_tid ) ) {
			$nntelephone = array(
				'tid' 		=> $response['tid'],
				'test_mode' => $response['test_mode'],
				'amount' 	=> $response_amount,
			);	
			EDD()->session->set( 'novalnet_telephone', $nntelephone );
		}
        $sess_tel = trim( $response['novaltel_number'] );        
		if ( $sess_tel ) {
			$aryTelDigits = str_split( $sess_tel, 4 );
			$count = 0;
			$str_sess_tel = '';
			foreach ( $aryTelDigits as $ind => $digits ) {
				$count++;
				$str_sess_tel .= $digits;
				if ( $count == 1 ) {
					$str_sess_tel .= '-';
				}
				else {
					$str_sess_tel .= ' ';
				}
			}
			$str_sess_tel = trim( $str_sess_tel );			
			if ( $str_sess_tel ) {
				$sess_tel = $str_sess_tel;
			}
		}
		
		$messages['novalnet_telephone'] = sprintf( __( 'Following steps are required to complete your payment:', 'edd-novalnet-gateway' ) . $new_line . $new_line . __( 'Step 1: Please call the telephone number displayed:', 'edd-novalnet-gateway' ) . ' ' . $sess_tel . $new_line . str_replace( '{amount}', edd_currency_filter( edd_format_amount( $response['amount'] ) ), __( '* This call will cost {amount} (including VAT) and it is possible only for German landline connection! *', 'edd-novalnet-gateway' ) ) . $new_line . $new_line . __( 'Step 2: Please wait for the beep and then hang up the listeners.', 'edd-novalnet-gateway' ) . $new_line . __( 'After your successful call, please proceed with the payment.', 'edd-novalnet-gateway' ) );
	
		EDD()->session->set( 'novalnet_tel_userdisplayed', $messages );						
        edd_send_back_to_checkout();
	}
    else {
		edd_set_error( 'server_direct_validation', __( $response['status_desc'], 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();
    }
}

//display telephone number to the end user
function novalnet_display_telephone_firstcall_messages() {
	$messages = EDD()->session->get( 'novalnet_tel_userdisplayed' );
	
	if ( $messages ) {
		$classes = apply_filters( 'edd_error_class', array( 'edd_errors' ) );
		echo '<div class="' . implode( ' ', $classes ) . '">';
		    // Loop message codes and display messages
		foreach ( $messages as $message_id => $message ) {
		    echo '<p class="edd_error" id="edd_msg_' . $message_id . '">' . $message . '</p>';
		}
		echo '</div>';
		// Remove all messages of the end user
		EDD()->session->set( 'novalnet_tel_userdisplayed', null );
	}
}
add_action( 'edd_before_checkout_cart', 'novalnet_display_telephone_firstcall_messages' );

//send Telephone Payment status call to Novalnet server
function novalnet_send_telephone_statuscall( $order_id ) {
    global $edd_options;
    $info_payport_url 	= ( is_ssl() ? 'https://' : 'http://' ) . 'payport.novalnet.de/nn_infoport.xml';
    $config_data 		= get_post_meta( $order_id,'_nn_config_values', true );
    $novalnet_tel_tid 	= EDD()->session->get( 'novalnet_telephone' );
    $novalnet_tel_tid 	= $novalnet_tel_tid['tid'];
    $language 			= get_bloginfo( 'language' );
	$language 			= strtoupper( substr( $language, 0, 2 ) );
	
	if ( ctype_digit( $config_data['vendor'] ) && ! empty( $config_data['auth_code'] ) && isset( $novalnet_tel_tid )
	&& $novalnet_tel_tid != null && isset( $language ) && $language != null ) {
		
    ## Process the payment to infoport ##
    $urlparam = '<?xml version="1.0" encoding="UTF-8"?><nnxml><info_request><vendor_id>' . $config_data['vendor'] . '</vendor_id>';
    $urlparam .= '<vendor_authcode>' . $config_data['auth_code'] . '</vendor_authcode>';
    $urlparam .= '<request_type>NOVALTEL_STATUS</request_type><tid>' . $novalnet_tel_tid . '</tid>';
    $urlparam .= '<lang>' . strtoupper( $language ) . '</lang></info_request></nnxml>';    
	list( $errno, $errmsg, $data ) = novalnet_handle_communication( $info_payport_url, $urlparam );		
    if ( strstr( $data, '<novaltel_status>' ) ) {
        preg_match( '/novaltel_status>?([^<]+)/i', $data, $matches );
        $aryResponse['status'] = $matches[1];
        preg_match( '/novaltel_status_message>?([^<]+)/i', $data, $matches );
        $aryResponse['status_desc'] = $matches[1];
    } else {
		parse_str( $data, $aryResponse );
    }
    $nnovalnet_telephone_details = EDD()->session->get( 'novalnet_telephone' );
    $aryResponse['tid'] 		= $nnovalnet_telephone_details['tid'];
    $aryResponse['test_mode'] 	= $nnovalnet_telephone_details['test_mode'];
    $aryResponse['order_no'] 	= $nnovalnet_telephone_details['order_no'];

    //Manual Testing
    //$aryResponse['status_desc'] = __( 'Successful', 'edd-novalnet-gateway' );
    //$aryResponse['status']      = 100;
    //Manual Testing
        novalnet_check_response( $aryResponse, $order_id );
    } else {
        novalnet_unset_telephone();
        edd_set_error( 'seconde_call_basic_validation', __( 'Required parameter not valid', 'edd-novalnet-gateway' ) );
		edd_send_back_to_checkout();        
    }
}
                
//set default payment as chosen payment
function novalnet_set_default_gateway() {
	global $edd_options;
	$payment_gateways = EDD()->session->get( 'edd_purchase' );
	$current_payment = $payment_gateways['gateway'];	
	novalnet_unset_session( $current_payment );	
	if ( isset( $current_payment ) && !empty( $current_payment ) ) {
		$set_default_payment = $current_payment;
	} else {		
		$set_default_payment = isset( $edd_options['default_gateway'] ) && edd_is_gateway_active( $edd_options['default_gateway'] ) ? $edd_options['default_gateway'] : '';		
	}
	
	return $set_default_payment;
}
add_filter( 'edd_default_gateway', 'novalnet_set_default_gateway' );

//unset all Novalnet session
function novalnet_unset_session( $payment_method ) {
	if ( $payment_method !='novalnet_sepa' ) {
		novalnet_unset_sepa();
	} elseif( $payment_method !='novalnet_cc' ) {
		novalnet_unset_cc();
	} elseif( $payment_method !='novalnet_telephone' ) {
		novalnet_unset_telephone();
	}
}

//unset Novalnet Credit Card session
function novalnet_unset_cc() {
	EDD()->session->set( 'novalnet_cc', null );
}

//unset Novalnet Direct Debit SEPA session
function novalnet_unset_sepa() {
	EDD()->session->set( 'novalnet_sepa', null );
}

//unset Novalnet Telephone Payment session
function novalnet_unset_telephone() {
	EDD()->session->set( 'novalnet_telephone', null );
}

function novalnet_unset_all_sessions() {
	novalnet_unset_cc();
	novalnet_unset_sepa();
	novalnet_unset_telephone();
}
function novalnet_get_iframe() {
	global $edd_options;
	$request = array_map( 'trim', $_REQUEST );
	$error = '';	
	if ( 6 == $request['key'] ) {
		$iframe_params = array(
			'nn_lang_nn' 		=> $request['lang'],
			'nn_vendor_id_nn' 	=> trim( $edd_options['novalnet_cc_merchant_id'] ),
			'nn_authcode_nn' 	=> trim( $edd_options['novalnet_cc_auth_code'] ),
			'nn_product_id_nn' 	=> trim( $edd_options['novalnet_cc_product_id'] ),
			'nn_payment_id_nn' 	=> $request['key'],
			'nn_hash' 			=> $request['panhash'],
			'fldVdr' 			=> $request['fldVdr']
		);			
		$url = 'payport.novalnet.de/direct_form.jsp';
		// 	basic validation for iFrame request parameter
		if ( ! ctype_digit( $iframe_params['nn_vendor_id_nn'] )	|| ! ctype_digit( $iframe_params['nn_product_id_nn'] )
			|| empty( $iframe_params['nn_authcode_nn'] ) || ! ctype_digit( $iframe_params['nn_payment_id_nn'] )
			|| empty( $iframe_params['nn_lang_nn'] ) ) {
			$error =  __( 'Basic Parameter not valid', 'edd-novalnet-gateway' );
		}
	} else {	
		$name = $request['first_name'] . " " . $request['last_name'];
		$iframe_params = array(
			'lang' 			=> $request['lang'],
			'vendor_id' 	=> trim( $edd_options['novalnet_sepa_merchant_id'] ),
			'product_id'	=> trim( $edd_options['novalnet_sepa_product_id'] ),
			'authcode'		=> trim( $edd_options['novalnet_sepa_auth_code'] ),
			'payment_id'	=> $request['key'],
			'country' 		=> $request['country'],
			'panhash' 		=> $request['sepahash'],
			'fldVdr' 		=> $request['fldVdr'],
			'name' 			=> htmlentities( $name, ENT_QUOTES, "UTF-8" ),
			'comp' 			=> '',
			'address' 		=> htmlentities( $request['address'], ENT_QUOTES, "UTF-8" ),
			'zip' 			=> $request['postcode'],
			'city' 			=> htmlentities( $request['city'], ENT_QUOTES, "UTF-8" ),
			'email' 		=> $request['email']
		);
		$url = 'payport.novalnet.de/direct_form_sepa.jsp';
		//basic validation for iFrame request parameter
		if ( empty( $name ) || empty( $iframe_params['address'] ) || empty( $iframe_params['zip'] )
		|| empty( $iframe_params['city'] ) || empty( $iframe_params['country'] )
		|| empty( $iframe_params['email'] ) ) {
			$error = __( 'In order to use SEPA direct debit, please log in or enter your address. ', 'edd-novalnet-gateway' );
		} elseif ( ! ctype_digit( $iframe_params['vendor_id'] )|| ! ctype_digit( $iframe_params['product_id'] )
		|| empty( $iframe_params['authcode'] ) || ! ctype_digit( $iframe_params['payment_id'] )
		|| empty( $iframe_params['lang'] ) ) {
			$error =  __( 'Basic Parameter not valid', 'edd-novalnet-gateway' );
		}
	}
	if ( $error ) {
		print $error;
	}
	else {
		$form_url = ( is_ssl() ? 'https://' : 'http://' ) . $url;
		list($errno, $errmsg, $iframe) = novalnet_handle_communication( $form_url, $iframe_params);
		print $iframe;
	}
	exit;
}

/**
 * Setup plugin constants
 *
 * @since 1.0.0
 * @return void
 */
function novalnet_setup_constants() {
	
	// Plugin version
	if ( ! defined( 'NOVALNET_VERSION' ) ) {
		define( 'NOVALNET_VERSION', '1.0.0' );
	}

	// Plugin Folder Path
	if ( ! defined( 'NOVALNET_PLUGIN_DIR' ) ) {
		define( 'NOVALNET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}

	// Plugin Folder URL
	if ( ! defined( 'NOVALNET_PLUGIN_URL' ) ) {
		define( 'NOVALNET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	// Plugin Root File
	if ( ! defined( 'NOVALNET_PLUGIN_FILE' ) ) {
		define( 'NOVALNET_PLUGIN_FILE', __FILE__ );
	}
}

// Set curl request function
function novalnet_handle_communication( $nn_url, $urlparam ) {	
    ## some prerequisite for the connection
    $ch = curl_init($nn_url);

    // a non-zero parameter tells the library to do a regular HTTP post.
    curl_setopt($ch, CURLOPT_POST, 1);

    // add POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $urlparam);

    // don't allow redirects
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

    // de-comment it if you want to have effective SSL checking
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // de-comment it if you want to have effective SSL checking
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // return into a variable
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // maximum time, in seconds, that you'll allow the CURL functions to take
    curl_setopt($ch, CURLOPT_TIMEOUT, 240);

    ## establish connection
    $data = curl_exec($ch);

    ## determine if there were some problems on cURL execution
    $errno = curl_errno($ch);
    $errmsg = curl_error($ch);

    ###bug fix for PHP 4.1.0/4.1.2 (curl_errno() returns high negative value in case of successful termination)
    if ($errno < 0)
        $errno = 0;
    #close connection
    curl_close($ch);
    return array( $errno, $errmsg, $data );
}

// encode the given data
function novalnet_encode( &$data, $encoded_params ) {	
	global $edd_options;
	$payment_gateways 	= EDD()->session->get( 'edd_purchase' );
	$access_key  		= trim( $edd_options[$payment_gateways['gateway'] . '_access_key'] );		
    foreach( $encoded_params as $key => $value ) {		
		$encodedata = $data[$value];
		try {
            $crc  = sprintf( '%u', crc32( $encodedata ) );# %u is a must for ccrc32 returns a signed value
            $encodedata = $crc . "|" . $encodedata;
            $encodedata = bin2hex( $encodedata . $access_key );
            $encodedata = strrev( base64_encode( $encodedata ) );
        } catch (Exception $e) {
            echo( 'Error: '.$e );
        }
        $data[$value] = $encodedata;
	}    
}

// decode the given data
function novalnet_decode( $data ) {
	global $edd_options;
	$payment_gateways 	= EDD()->session->get( 'edd_purchase' );
	$access_key  		= trim( $edd_options[$payment_gateways['gateway'] . '_access_key'] );	
    $data = trim( $data );
        
    try {
        $data = base64_decode(strrev($data));
        $data = pack("H" . strlen($data), $data);
        $data = substr($data, 0, stripos($data, $access_key));
        $pos = strpos($data, "|");
        
        if ( $pos === false ) {
            return( "Error: CKSum not found!" );
		}
        $crc = substr( $data, 0, $pos );
        $value = trim( substr( $data, $pos + 1 ) );
        if ( $crc != sprintf( '%u', crc32( $value ) ) ) {
            return( "Error; CKSum invalid!" );
		}            
		return $value;
    } catch (Exception $e) {
        echo( 'Error: ' . $e );
    }
}

//generate hash
function novalnet_generate_hash( $h ) {
	global $edd_options;	
	$payment_gateways = EDD()->session->get( 'edd_purchase' );
	$access_key  = trim( $edd_options[$payment_gateways['gateway'] . '_access_key'] );	
    return md5( $h['auth_code'] . $h['product'] . $h['tariff'] . $h['amount'] . $h['test_mode'] . $h['uniqid'] . strrev( $access_key ) );
}

//check the response hash is equal to request hash
function novalnet_check_hash( $request ) {
	return ( $request && $request['hash2'] == novalnet_generate_hash( $request ) );
}