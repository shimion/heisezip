<?php
//add_action('init', 'paypal_return_custom');
function paypal_return_custom(){
global $appointments;
if ((isset($_POST['payment_status']) || isset($_POST['txn_type'])) && isset($_POST['custom'])) {

			if ($appointments->options['mode'] == 'live') {
				$domain = 'https://www.paypal.com';
			} else {
				$domain = 'https://www.sandbox.paypal.com';
			}

			$req = 'cmd=_notify-validate';
			if (!isset($_POST)) $_POST = $HTTP_POST_VARS;
			foreach ($_POST as $k => $v) {
				if (get_magic_quotes_gpc()) $v = stripslashes($v);
				$req .= '&' . $k . '=' . $v;
			}

			$header = 'POST /cgi-bin/webscr HTTP/1.0' . "\r\n"
					. 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
					. 'Content-Length: ' . strlen($req) . "\r\n"
					. "\r\n";

			@set_time_limit(60);
			if ($conn = @fsockopen($domain, 80, $errno, $errstr, 30)) {
				fputs($conn, $header . $req);
				socket_set_timeout($conn, 30);

				$response = '';
				$close_connection = false;
				while (true) {
					if (feof($conn) || $close_connection) {
						fclose($conn);
						break;
					}

					$st = @fgets($conn, 4096);
					if ($st === false) {
						$close_connection = true;
						continue;
					}

					$response .= $st;
				}

				$error = '';
				$lines = explode("\n", str_replace("\r\n", "\n", $response));
				// looking for: HTTP/1.1 200 OK
				if (count($lines) == 0) $error = 'Response Error: Header not found';
				else if (substr($lines[0], -7) != ' 200 OK') $error = 'Response Error: Unexpected HTTP response';
				else {
					// remove HTTP header
					while (count($lines) > 0 && trim($lines[0]) != '') array_shift($lines);

					// first line will be empty, second line will have the result
					if (count($lines) < 2) $error = 'Response Error: No content found in transaction response';
					else if (strtoupper(trim($lines[1])) != 'VERIFIED') $error = 'Response Error: Unexpected transaction response';
				}

				if ($error != '') {
					print( $error );
					//exit;
				}
			}

			// We are using server time. Not Paypal time.
			$timestamp = $appointments->local_time;

			$new_status = false;
			// process PayPal response
			switch ($_POST['payment_status']) {
				case 'Partially-Refunded':
					break;

				case 'In-Progress':
					break;

				case 'Completed':
				case 'Processed':
				
						$message = sprintf( __('Paypal confirmation arrived. Please check appointment name: %s', 'appointments'), mail_massage_name($_POST['custom']) );
					wp_mail( $appointments->get_admin_email(), 'Appointment time', $message ); 
					$reservation_massage = '';
					//$reservation_massage .= 'Hello ';
					wp_mail( $appointments->collect_email_address($_POST['custom']), 'Appointment are registered', $appointments->mail_massage($_POST['custom']) ); 
					//mail( 'shimion_b@yahoo.com', 'Diff one', 'verifing if it is working.' ); 
					$message = sprintf( __('You have an appointment at %s', 'appointments'), '' );
					//$appointments->Worker_send_mail($_POST['custom'], 'You have a new appointments', $appointments->message_headers());
					
					$workers = $appointments->set_uniquer_ids_for_sending_email($_POST['custom']);
					foreach($workers as $worker){
									$user = get_user_by( 'id',$worker);
									wp_mail( $user->user_email, __('You have a new appointment','appointments'), sprintf( __('You have an appointment at %s', 'appointments'), $appointments->get_start_date_for_sending_email($_POST['custom']) ), $header );
						}
					
					
					$appointments->receive_paypal_2($_POST['custom']);
					//$appointments->semt_email_service_provider_by_id($_POST['custom']);
					// case: successful payment
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$appointments->record_transaction('1', $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], '');
					if ( $appointments->change_status( 'paid', $_POST['custom'] ) ){
						//$appointments->send_confirmation( $appointments->count_ids_for_sending_email($_POST['custom']) );
					}else {
						// Something wrong. Warn admin
						$message = sprintf( __('Paypal confirmation arrived. Please check appointment with ID\'s %s', 'appointments'),  $appointments->count_ids_for_sending_email($_POST['custom']) );

						wp_mail( $appointments->get_admin_email( ), __('Appointments have being taken.','appointments'), $message, $appointments->message_headers() );
					}
					break;

				case 'Reversed':
					// case: charge back
					$note = __('Last transaction has been reversed. Reason: Payment has been reversed (charge back)', 'appointments');
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$appointments->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);
					break;

				case 'Refunded':
					// case: refund
					$note = __('Last transaction has been reversed. Reason: Payment has been refunded', 'appointments');
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$appointments->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);
					break;

				case 'Denied':
					// case: denied
					$note = __('Last transaction has been reversed. Reason: Payment Denied', 'appointments');
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$appointments->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);

					break;

				case 'Pending':
					// case: payment is pending
					$pending_str = array(
						'address' => __('Customer did not include a confirmed shipping address', 'appointments'),
						'authorization' => __('Funds not captured yet', 'appointments'),
						'echeck' => __('eCheck that has not cleared yet', 'appointments'),
						'intl' => __('Payment waiting for aproval by service provider', 'appointments'),
						'multi-currency' => __('Payment waiting for service provider to handle multi-currency process', 'appointments'),
						'unilateral' => __('Customer did not register or confirm his/her email yet', 'appointments'),
						'upgrade' => __('Waiting for service provider to upgrade the PayPal account', 'appointments'),
						'verify' => __('Waiting for service provider to verify his/her PayPal account', 'appointments'),
						'*' => ''
						);
					$reason = @$_POST['pending_reason'];
					$note = __('Last transaction is pending. Reason: ', 'appointments') . (isset($pending_str[$reason]) ? $pending_str[$reason] : $pending_str['*']);
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					// Save transaction.
					$appointments->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);

					break;

				default:
					// case: various error cases
			}
		} else {
			// Did not find expected POST variables. Possible access attempt from a non PayPal site.
			// This is IPN response, so echoing will not help. Let's log it.
			//print( 'Error: Missing POST variables. Identification is not possible.' );
			//exit;
		}
		//exit;
		
}