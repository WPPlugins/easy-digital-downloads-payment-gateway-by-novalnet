<?php
#########################################################
#                                                       #
#  This script is used for real time capturing of       #
#  parameters passed from Novalnet AG after Payment     #
#  processing of customers.                             #
#                                                       #
#  Copyright (c) Novalnet AG                            #
#                                                       #
#  This script is only free to the use for Merchants of #
#  Novalnet AG                                          #
#                                                       #
#  If you have found this script useful a small         #
#  recommendation as well as a comment on merchant form #
#  would be greatly appreciated.                        #
#                                                       #
#  Version : 1.0.0                                      #
#                                                       #
#  Please contact sales@novalnet.de for enquiry or Info #
#                                                       #
#########################################################

include_once('wp-load.php');
/**
 * END LOAD WORDPRESS
 */

/* Novalnet callback script starts */

$debugMode = false; //false|true; adapt: set to false for go-live
$testMode  = false; //false|true; adapt: set to false for go-live

$lineBreak = empty($_SERVER['HTTP_HOST']) ? PHP_EOL : '<br />';

$requestParams = array_map("trim", $_REQUEST);

//Mail Settings
$mailHost       = '';
$mailPort       = '';
$emailFromAddr  = '';// sender email address, mandatory, adapt it
$emailToAddr    = '';// recipient email address, mandatory, adapt it
$emailSubject   = 'Novalnet Callback Script Access Report';// email subject (adapt if necessary;)
$emailBody      = '';// Email text, adapt
$emailFromName  = '';// Sender name (adapt)
$emailToName    = '';// Recipient name (adapt)

//Test Data Settings
if ( isset( $requestParams['debug_mode'] ) && $requestParams['debug_mode'] == 1 ) {
    $debugMode      = true;
    $testMode       = true;
    $mailHost       = 'mail.novalnet.de';// email host (adapt)
    $mailPort       = '25';// email port (adapt)
    $emailFromName  = "Novalnet"; // Sender name, adapt
    $emailToName    = "Novalnet"; // Recipient name, adapt
    $emailFromAddr  = 'testadmin@novalnet.de'; //mandatory for test; adapt
    $emailToAddr    = 'test@novalnet.de'; //mandatory for test; adapt
    $emailSubject   = $emailSubject . ' - TEST'; //adapt
}

global $wpdb, $lineBreak, $debugMode, $testMode;

$emailContent  = array(
    'mailhost'      => $mailHost,
    'mailport'      => $mailPort,
    'emailfrom'     => $emailFromAddr,
    'emailto'       => $emailToAddr,
    'emailsubject'  => $emailSubject,
    'emailfromname' => $emailFromName,
    'emailtoname'   => $emailToName
);

$vendorScript = new NovalnetVendorScript( $requestParams );
$order_reference = $vendorScript->getOrderReference(); # Order reference of given callback request

$requestedArrayParams = $vendorScript->getRequestedParams();

if ( ! empty( $order_reference ) ) {
	$payment_gateway 		= edd_get_payment_gateway( $order_reference );
	$order_total 			= get_post_meta( $order_reference,'_edd_payment_total' );
	$order_total 			= $order_total['0'];	
	$final_status 			= $edd_options[$payment_gateway.'_order_completion_status'];
	$order_metadata 		= get_post_meta( $order_reference,'_edd_payment_meta',true );
	$requestedArrayParams['currency'] = isset( $requestedArrayParams['currency'] ) ? $requestedArrayParams['currency'] : $order_metadata['currency'];
	$paid_amount = $requestedArrayParams['amount'];
	$callback_amount = get_post_meta( $order_reference,'_nn_callback_amount',true );				
	$sum_amount = $paid_amount + $callback_amount;
	$org_amount = sprintf('%0.2f', $order_total)*100;
	
	if ( $vendorScript->getPaymentTypeLevel() === 0 ) {
		if ( $requestedArrayParams['subs_billing']== 1 ) { ##IF PAYMENT MADE ON SUBSCRIPTION RENEWAL
                #### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ####

                #### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###

                #### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###
        }
		if ( $requestedArrayParams['payment_type'] == 'PAYPAL' ) {
			if ( $callback_amount < $org_amount ) {
				$requestedArrayParams['message'] = "Novalnet Callback Script executed successfully for the TID :";
				$vendorScript->emailBodyContent($requestedArrayParams, $order_reference, $emailContent);
				update_post_meta( $order_reference,'_nn_callback_amount', $org_amount );   
				edd_update_payment_status( $order_reference, $final_status );
			}
			else {
				$vendorScript->debugError('Novalnet Callbackscript received. Callback Script executed already. Refer Order :' . $order_reference);
			}
		}
		else {
			$vendorScript->debugError('Novalnet Callbackscript received. Payment type ( '.$requestedArrayParams['payment_type'].' ) is not applicable for this process!');
		}

		if ( $payment=='INVOICE_START' ) { ##INVOICE START
            if($aryCaptureParams['subs_billing']==1) {
                #### Step4: ENTER THE NECESSARY REFERENCE & BANK ACCOUNT DETAILS IN THE NEW ORDER CONFIRMATION EMAIL ####
            }
        }		       
	}
    else if ( $vendorScript->getPaymentTypeLevel() == 1 ) {
        if ( in_array( $requestedArrayParams['payment_type'], $vendorScript->aryChargebacks ) ) {
            $requestedArrayParams['message'] = "Novalnet Callback received. Charge back was executed successfully for the TID ";
            $vendorScript->emailBodyContent( $requestedArrayParams, $order_reference, $emailContent );
        }
    }
    else if ( $vendorScript->getPaymentTypeLevel() == 2 ) {
		$language	= get_bloginfo('language');
		$language 	= strtoupper(substr($language, 0, 2));
		$comments 	= '';       
        if ( $callback_amount < $org_amount ) {
            if ( $requestedArrayParams['payment_type'] == 'INVOICE_CREDIT' ) {
                $requestedArrayParams['message'] = "$lineBreak Novalnet Callback Script executed successfully for the TID: ";
                $comments .= $vendorScript->emailBodyContent( $requestedArrayParams, $order_reference );
                $emailContent['comments'] .= $comments;
                update_post_meta( $order_reference,'_nn_callback_amount', $sum_amount );                
				if( $sum_amount >= $org_amount ) {
					if( $sum_amount > $org_amount )
						$emailContent['comments'] .= " Customer paid amount is greater than Order amount.". $lineBreak;
					if ( $language == 'EN' ) {
						$final_comments  = 'Novalnet Transaction ID: ' . $requestedArrayParams['shop_tid'].'<br />';
						$final_comments .= $requestedArrayParams['test_mode'] ? 'Test order' : '';
					}
					else {
						$final_comments  = 'Novalnet Transaktions-ID: ' . $requestedArrayParams['shop_tid'].'<br />';
						$final_comments .= $requestedArrayParams['test_mode'] ? 'Testbestellung' : '';
					}
					$query ="SELECT post_excerpt FROM $wpdb->posts where ID =".$order_reference;
					$result = $wpdb->get_results($query);
					$exist_comments = $result[0]->post_excerpt;
					$nn_order_notes = array(
						'ID' 			=> $order_reference,
						'post_excerpt' 	=> $final_comments
					);
					wp_update_post( $nn_order_notes );
					edd_update_payment_status( $order_reference, $final_status );
					$nn_order_notes = array(
						'ID' 			=> $order_reference,
						'post_excerpt' 	=> $exist_comments
					);
					wp_update_post( $nn_order_notes );
				}				
                $vendorScript->sendNotifyMail( $emailContent );
            } 
        } else {
            $vendorScript->debugError('Novalnet Callbackscript received. Order already Paid');
        }
    }
    if ( $payment=='SUBSCRIPTION_STOP' ) { 
		### Cancellation of a Subscription
        ### UPDATE THE STATUS OF THE USER SUBSCRIPTION ###
    }    
}

class NovalnetVendorScript {
			
	protected $aryRequestParams = array();

	/** @Array Type of payment available - Level : 0 */
	protected $aryPayments = array('CREDITCARD','INVOICE_START','DIRECT_DEBIT_SEPA','GUARANTEED_INVOICE_START','PAYPAL','ONLINE_TRANSFER','IDEAL','EPS','NOVALTEL_DE','PAYSAFECARD');

	/** @Array Type of Charge backs available - Level : 1 */
	public $aryChargebacks = array('RETURN_DEBIT_SEPA','CREDITCARD_BOOKBACK','CREDITCARD_CHARGEBACK','REFUND_BY_BANK_TRANSFER_EU','NOVALTEL_DE_CHARGEBACK');

	/** @Array Type of CreditEntry payment and Collections available - Level : 2 */
	protected $aryCollection = array('INVOICE_CREDIT','GUARANTEED_INVOICE_CREDIT','CREDIT_ENTRY_CREDITCARD','CREDIT_ENTRY_SEPA','DEBT_COLLECTION_SEPA','DEBT_COLLECTION_CREDITCARD','NOVALTEL_DE_COLLECTION','NOVALTEL_DE_CB_REVERSAL');

	protected $aryPaymentGroups = array(
		'novalnet_cc' 			=> array('CREDITCARD', 'CREDITCARD_CHARGEBACK','SUBSCRIPTION_STOP'),
		'novalnet_sepa' 		=> array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA','SUBSCRIPTION_STOP'),
		'novalnet_ideal' 		=> array('IDEAL'),
		'novalnet_banktransfer' => array('ONLINE_TRANSFER'),
		'novalnet_paypal' 		=> array('PAYPAL'),
		'novalnet_prepayment' 	=> array('INVOICE_START','INVOICE_CREDIT', 'SUBSCRIPTION_STOP'),
		'novalnet_invoice' 		=> array('INVOICE_START',  'INVOICE_CREDIT',  'SUBSCRIPTION_STOP'),
		'novalnet_telephone' 	=> array('NOVALTEL_DE','NOVALTEL_DE_CHARGEBACK'),
		'novalnet_eps' 			=> array('EPS')
	);
	Protected $paramsRequired = array('vendor_id' => '', 'status' => '', 'amount' => '', 'payment_type' => '', 'tid' => '');

	protected $ipAllowed = '195.143.189.210';

    function __construct( $params ) {		
        if (isset( $params ) && empty( $params )) {
            self::debugError('Novalnet callback received. No params passed over!');
        }
		if(isset($params['subs_billing']) && $params['subs_billing'] ==1){
		  $this->paramsRequired['signup_tid'] = '';
		}
        if ( in_array( $params['payment_type'], array_merge( $this->aryChargebacks, array('INVOICE_START',  'INVOICE_CREDIT') ) ) ) {
            $this->paramsRequired['tid_payment'] = '';
        }
        $this->aryRequestParams = self::validateRequestParams( $params );
    }
    
	function validateRequestParams( $params ) {
		global $testMode, $lineBreak;	
		if ( ! empty( $params ) ) {
			$error = '';
			//Validate Authenticated IP
			if ( $this->ipAllowed != self::getClientIP() && $testMode == false ) {
				self::debugError();
			}
			$arySetNullvalueIfnotExist = array('reference', 'vendor_id', 'tid', 'status', 'status_messge', 'payment_type', 'signup_tid');
			foreach($arySetNullvalueIfnotExist as $key => $value) {
				if(!isset($params[$value])) {
					$params[$value] = '';
				}
			}
			if(!$params['tid']) {
				$params['tid'] = $params['signup_tid'];
			}
			foreach ( $this->paramsRequired as $k => $v ) {
				if ( empty( $params[$k] ) ) {
					$error .= 'Required param ( ' . $k . '  ) missing!' . $lineBreak;
				}
			}
			if ( ! empty( $error ) ) {
				self::debugError( $error );
			}
			if ( !in_array( $params['payment_type'], array_merge( $this->aryPayments, $this->aryChargebacks, $this->aryCollection ) ) ) {
				$error = 'Novalnet callback received. Payment type ( '.$params['payment_type'].' ) is mismatched!';
				self::debugError( $error );
			}
			if ( isset( $params['status'] ) && ! ctype_digit( $params['status'] ) ) {
				self::debugError('Novalnet callback received. Status ('.$params['status'].') is not valid.');
			}
			if ( in_array( $params['payment_type'], array_merge( $this->aryChargebacks, array('INVOICE_START', 'INVOICE_CREDIT') ) ) && ( ! ctype_digit( $params['tid_payment'] ) || strlen( $params['tid_payment'] ) != 17 ) ) {
				$error = 'Novalnet callback received. Invalid TID ['. $params['tid_payment'] . '] for Order.';
				self::debugError( $error );
			}
			if ( ctype_digit( $params['tid'] ) && strlen( $params['tid'] ) != 17 ) {				
				if ( in_array( $params['payment_type'], array_merge( $this->aryChargebacks, array('INVOICE_START', 'INVOICE_CREDIT') ) ) ) {
					$error = 'Novalnet callback received. New TID is not valid.';
				} else {
					$error = 'Novalnet callback received. Invalid TID ['.$params['tid'].'] for Order';
				}
				self::debugError( $error );
			}
			if ( ! $params['amount'] || ! ctype_digit( $params['amount'] ) || $params['amount'] < 0 ) {
				$error = 'Novalnet callback received. The requested amount ('. $params['amount'] .') is not valid';
				self::debugError( $error );
			}
			if ( in_array( $params['payment_type'], array_merge( $this->aryChargebacks, array('INVOICE_START', 'INVOICE_CREDIT') ) ) ) { #Invoice
				$params['shop_tid'] = $params['tid_payment'];
			} else if ( isset( $params['tid'] ) && $params['tid'] != '' ) {
				$params['shop_tid'] = $params['tid'];
			}
		}
		return $params;
	}
  
	public function emailBodyContent( $req_params, $orderid, $emailContent = array() ) {
		global $lineBreak;
		$comments = $lineBreak. $req_params['message'] . $req_params['shop_tid'] . " with amount " . str_replace('.',',',number_format(sprintf('%0.2f', $req_params['amount']/100), 2)) . ' ' . $req_params['currency'] . " on " . date('Y-m-d H:i:s') . '. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: ' . $req_params['tid'];
		$argComments = array(
			'comments'  => $comments,
			'order_no'  => $orderid
		);
		$this->updateCallbackComments( $argComments );
		$argComments['comments'] = str_replace( '\n', '<br / >', $argComments['comments'] );	
		if ( !empty( $emailContent ) )
			$this->sendNotifyMail( array_merge( $argComments, $emailContent ) );
		else
			return $argComments['comments'];
	}
  
	public function updateCallbackComments( $param = array() ) {
		global $wpdb;
		$query ="SELECT post_excerpt FROM $wpdb->posts where ID =".$param['order_no'];						
		$result = $wpdb->get_results( $query );
		$comments = $result[0]->post_excerpt.$param['comments'];
		$nn_order_notes = array(
			'ID' 			=> $param['order_no'],
			'post_excerpt' 	=> $comments
		);										
		wp_update_post( $nn_order_notes );
		edd_insert_payment_note( $param['order_no'], $param['comments'] );
	}
  
	public function sendNotifyMail($data = array()) {
		global $debugMode, $lineBreak;
		$mail_error = false;		
		//Send Email
		if ( $data['comments'] && ! empty( $data['emailfrom'] ) && is_email( $data['emailto'] ) ) {
			
			if ( ! empty($data['mailhost'] ) && ! empty( $data['mailport'] ) ) {
				ini_set( 'SMTP', $data['mailhost'] );
				ini_set( 'smtp_port', $data['mailport'] );
			}
			$headers  = 'Content-Type: text/html; charset=iso-8859-1' . '\r\n';
			$headers .= 'From: ' . $data['emailfrom'] . "\r\n";
			if ( $debugMode )
				echo __FUNCTION__ , ': Sending Email suceeded!' , $lineBreak;
			$sendMail = wp_mail( $data['emailto'], $data['emailsubject'], $data['comments'], $headers ); # WordPress Sending Mail Function
        
			if ( ! $sendMail ) {
				$mail_error = true;
			}
			if ( $debugMode )
				echo 'This text has been sent:' , $lineBreak , $data['comments'];
			return true;
		} else {
			$mail_error = true;
		}
		if ( $mail_error && $debugMode ) {
			echo "Mailing failed!" , $lineBreak;
			echo "This mail text should be sent: " , $lineBreak;
			echo $data['comments'];
			return false;
		}
	}
  
	public function getOrderReference(){
      global $wpdb;
      $org_tid     = $this->aryRequestParams['shop_tid'];
      
      $nn_order_id = ( ! empty( $this->aryRequestParams['order_no'] ) ) ? $this->aryRequestParams['order_no'] : ( ! empty( $this->aryRequestParams['order_id'] ) ? $this->aryRequestParams['order_id'] : '' );

	$query =  $wpdb->prepare( "SELECT post_id  FROM wp_postmeta WHERE ( meta_value = %s AND meta_key = '_nn_order_tid' )" , $org_tid );
	$result = $wpdb->get_results( $query );		
	if ( $result ) {				
		$order_id = $result[0]->post_id;	// getting the order id					
		$payment_method = get_post_meta( $order_id,'_edd_payment_gateway',true );			
	} else {
		$post_details = get_post( $nn_order_id );		
		if ( $post_details->ID ) {
			$order_id = $post_details->ID;	// getting the order id				
		}
		else {
			$error = 'Novalnet callback received. Transaction Mapping failed';
			self::debugError( $error );		
		}
	}
	
	$payment_method = get_post_meta( $order_id,'_edd_payment_gateway',true );
	$order_tid = get_post_meta( $order_id,'_nn_order_tid',true );
	$order_total = get_post_meta( $order_id,'_edd_payment_total',true );
		
	if ( ! empty( $nn_order_id ) && $order_id != $nn_order_id ) {
		$error = "Novalnet callback received. Order no is not valid";
		self::debugError( $error );
	}

	if ( $this->aryRequestParams['payment_type'] == 'INVOICE_START' && $this->aryRequestParams['status'] <= 100 ) {
        $error = 'Novalnet callback received. Callback Script executed already. Refer Order :' . $order_id;
        self::debugError( $error );
    }
       
    if ( ! in_array( $this->aryRequestParams['payment_type'], $this->aryPaymentGroups[$payment_method] ) ) {
        $error = "Novalnet callback received. Payment type (".$this->aryRequestParams['payment_type'].") is mismatched!";
        self::debugError( $error );
    }

    if ( isset( $org_tid ) && $this->aryRequestParams['status'] ==100 && $order_tid && $order_tid !== $org_tid ){
		$error = "Novalnet callback received. TID [".$org_tid."] is mismatched!";
        self::debugError( $error );
	}
	
	$transaction_details = self::getTransactionDetails( $order_id );
	if (empty( $transaction_details ) ) {
		self::updateTransactionDetails( $order_id );
	}
	if ( isset( $this->aryRequestParams['status'] ) && $this->aryRequestParams['status'] !=100 ){
		$error = 'Novalnet callback received. Callback Script executed already. Refer Order :' . $order_id;
        self::debugError( $error );
	}
	
    return $order_id;
  }

  /*
  * Get given payment_type level for process
  *
  * @return Integer
  */
	public function getPaymentTypeLevel() {
		if ( ! empty( $this->aryRequestParams ) ) {
			if ( in_array( $this->aryRequestParams['payment_type'], $this->aryPayments ) ) {
				return 0;
			} else if ( in_array( $this->aryRequestParams['payment_type'], $this->aryChargebacks ) ) {
				return 1;
			} else if ( in_array( $this->aryRequestParams['payment_type'], $this->aryCollection ) ) {
				return 2;
			}
		}
		return false;
	}

	public function getRequestedParams(){
		return $this->aryRequestParams;
	}

	//get Transaction details from Database
	public function getTransactionDetails( $order_no ) {
		$comments = edd_get_payment_notes( $order_no );
		$novalnet_comment = '';
		foreach($comments as $comment) :
			$novalnet_comment = wpautop($comment->comment_content);
		endforeach;
		return $novalnet_comment;
	}

	//handling the Communication failure
	public function updateTransactionDetails( $order_no ){
		global $lineBreak, $edd_options;
		$new_line = "\n";
		$order_total = get_post_meta( $order_no,'_edd_payment_total' );
		$payment_gateway = edd_get_payment_gateway( $order_no );
		$comments = 'Novalnet Transaction ID : ' . $this->aryRequestParams['shop_tid'] . $lineBreak;
		if ( isset( $this->aryRequestParams['test_mode'] ) && $this->aryRequestParams['test_mode'] )
			$comments .= 'Test Order' . $lineBreak;
		if ( isset( $this->aryRequestParams['status'] ) && $this->aryRequestParams['status'] !=100 ) {
			$comments .= $this->aryRequestParams['status_text'] ? $this->aryRequestParams['status_text'] : ($this->aryRequestParams['status_desc'] ? $this->aryRequestParams['status_desc'] : $this->aryRequestParams['status_message']);
		}
		$transaction_details = self::getTransactionDetails( $order_no );	
		if ( $transaction_details )
			$transaction_details .= $new_line;

		$transaction_details .= html_entity_decode( $comments, ENT_QUOTES, 'UTF-8' );
		$nn_order_notes = array(
			'ID' => $order_no,
			'post_excerpt' => $transaction_details
		);

		wp_update_post( $nn_order_notes );
		edd_insert_payment_note( $order_no, $transaction_details );
		update_post_meta( $order_no,'_nn_order_tid', $this->aryRequestParams['shop_tid'] );
		$total_amount = get_post_meta( $order_no,'_edd_payment_total' );
		if ( ( in_array( $this->aryRequestParams['payment_type'], array('INVOICE_START', 'INVOICE_CREDIT') ) ) || ( $this->aryRequestParams['payment_type'] == 'PAYPAL' ) ) {
			if ( $this->aryRequestParams['payment_type'] == 'PAYPAL' && $this->aryRequestParams['status'] == 100 ) {
				edd_update_payment_status( $order_no, $edd_options[$payment_gateway.'_order_completion_status'] );
				update_post_meta( $order_no,'_nn_callback_amount', $order_total['0']*100 );
			}
			else {
				edd_update_payment_status( $order_no, $edd_options[$payment_gateway.'_order_status_before_payment'] );
				update_post_meta( $order_no,'_nn_callback_amount', 0 );
			}
		}
		else {
			if ( $this->aryRequestParams['status'] == 100 ) {
				edd_update_payment_status( $order_no, $edd_options[$payment_gateway.'_order_completion_status'] );
				update_post_meta( $order_no,'_nn_callback_amount', $order_total['0']*100 );
			} else {
				update_post_meta( $order_no,'_nn_callback_amount', $order_total['0']*100 );
			}			
			
		}
		self::debugError($this->aryRequestParams['payment_type'] . ' payment status updated');
	}
	
	function getClientIP() {
		$ipaddress = '';
		if ($_SERVER['HTTP_CLIENT_IP'])
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		else if($_SERVER['HTTP_X_FORWARDED_FOR'])
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if($_SERVER['HTTP_X_FORWARDED'])
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		else if($_SERVER['HTTP_FORWARDED_FOR'])
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		else if($_SERVER['HTTP_FORWARDED'])
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		else if($_SERVER['REMOTE_ADDR'])
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		else
			$ipaddress = 'UNKNOWN';

		return $ipaddress;
	}
	function debugError( $error_msg = 'Authentication Failed!' ) {
		global $debugMode;
		if ( $debugMode ) {
			echo $error_msg; exit;
		}
	}
}
?>