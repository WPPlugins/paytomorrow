<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once( dirname( __FILE__ ) . '/class-wc-gateway-paytomorrow-response.php' );

/**
 * Handles responses from PayTomorrow IPN.
 */
class WC_Gateway_Paytomorrow_IPN_Handler extends WC_Gateway_Paytomorrow_Response {

	/** @var string Receiver email address to validate */
	protected $receiver_email;
	protected $validateipn_postfix;
	protected $api_url;

	/**
	 * Constructor.
	 *
	 * @param bool $sandbox
	 * @param string $receiver_email
	 */
	public function __construct( $receiver_email = '', $api_url, $validateipn_posfix) {
		add_action( 'woocommerce_api_wc_gateway_paytomorrow', array( $this, 'check_response' ) );
		add_action( 'valid-paytomorrow-standard-ipn-request', array( $this, 'valid_response' ) );

		$this->receiver_email = $receiver_email;
		$this->api_url        = $api_url;
		$this->validateipn_postfix = $validateipn_posfix;
	}

	/**
	 * Check for PayTomorrow IPN Response.
	 */
	public function check_response() {
        paytomorrow_log_me('ENTERING check_response');
		if ( ! empty( $_POST ) ) {
            $posted = wp_unslash($_POST);
            if($this->validate_ipn($posted['uuid'])) {

                do_action('valid-paytomorrow-standard-ipn-request', $posted);
                exit;
            }
		}

		wp_die( 'PayTomorrow IPN Request Failure', 'PayTomorrow IPN', array( 'response' => 500 ) );
	}

	/**
	 * There was a valid response.
	 * @param  array $posted Post data after wp_unslash
	 */
	public function valid_response() {

        $posted = wp_unslash( $_POST );
        paytomorrow_log_me('ENTERING valid_response');
        paytomorrow_log_me(array('posted' => $posted));
        paytomorrow_log_me('UUID: ' . $posted['uuid'] );
		if ( $order = $this->get_paytomorrow_order( $posted['uuid'] ) ) {

			// Lowercase returned variables.
			$posted['payment_status'] = strtolower( $posted['payment_status'] );

			// Sandbox fix.
			if ( isset( $posted['test_ipn'] ) && 1 == $posted['test_ipn'] && 'pending' == $posted['payment_status'] ) {
				$posted['payment_status'] = 'completed';
			}

			WC_Gateway_Paytomorrow::log( 'Found order #' . $order->id );
			WC_Gateway_Paytomorrow::log( 'Payment status: ' . $posted['payment_status'] );

			if ( method_exists( $this, 'payment_status_' . $posted['payment_status'] ) ) {
				call_user_func( array( $this, 'payment_status_' . $posted['payment_status'] ), $order, $posted );
			}
		} else {
            paytomorrow_log_me('order not found');
        }
	}

	/**
	 * Check PayTomorrow IPN validity.
	 */
	public function validate_ipn($uuid) {
		WC_Gateway_Paytomorrow::log( 'Checking IPN response is valid' );

		// Get received values from post data
		$validate_ipn = array( 'cmd' => '_validate-me' , 'uuid' => $uuid);
		$validate_ipn += wp_unslash( $_POST );

		// Send back post vars to paytomorrow
		$params = array(
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode($validate_ipn),
			'timeout'     => 60,
			'httpversion' => '1.1',
			'compress'    => false,
			'decompress'  => false,
			'user-agent'  => 'WooCommerce/' . WC()->version
		);

        paytomorrow_log_me('ENTERING validate_ipn');
        paytomorrow_log_me($params);

        $requestUrl = $this->api_url . $this->validateipn_postfix;

        // Post back to get a response.
        if(is_ssl()) {
            paytomorrow_log_me('ssl call');
            $response = wp_safe_remote_post( $requestUrl, $params );
        } else {
            paytomorrow_log_me('NO ssl call');
            $response = wp_remote_post( $requestUrl, $params );
        }

        paytomorrow_log_me($response);

		WC_Gateway_Paytomorrow::log( 'IPN Request: ' . print_r( $params, true ) );
		WC_Gateway_Paytomorrow::log( 'IPN Response: ' . print_r( $response, true ) );

		// Check to see if the request was valid.
		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && strstr( $response['body'], 'VERIFIED_PT' ) ) {
			WC_Gateway_Paytomorrow::log( 'Received valid response from PayTomorrow' );
			return true;
		}

		WC_Gateway_Paytomorrow::log( 'Received invalid response from PayTomorrow' );

		if ( is_wp_error( $response ) ) {
			WC_Gateway_Paytomorrow::log( 'Error response: ' . $response->get_error_message() );
		}

		return false;
	}

	/**
	 * Check for a valid transaction type.
	 * @param string $txn_type
	 */
	protected function validate_transaction_type( $txn_type ) {
		$accepted_types = array( 'cart', 'instant', 'express_checkout', 'web_accept', 'masspay', 'send_money' );

		if ( ! in_array( strtolower( $txn_type ), $accepted_types ) ) {
			WC_Gateway_Paytomorrow::log( 'Aborting, Invalid type:' . $txn_type );
			exit;
		}
	}

	/**
	 * Check currency from IPN matches the order.
	 * @param WC_Order $order
	 * @param string $currency
	 */
	protected function validate_currency( $order, $currency ) {
		if ( $order->get_order_currency() != $currency ) {
			WC_Gateway_Paytomorrow::log( 'Payment error: Currencies do not match (sent "' . $order->get_order_currency() . '" | returned "' . $currency . '")' );

			// Put this order on-hold for manual checking.
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: PayTomorrow currencies do not match (code %s).', 'wc_paytomorrow' ), $currency ) );
			exit;
		}
	}

	/**
	 * Check payment amount from IPN matches the order.
	 * @param WC_Order $order
	 * @param int $amount
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) != number_format( $amount, 2, '.', '' ) ) {
			WC_Gateway_Paytomorrow::log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );
			paytomorrow_log_me( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			// Put this order on-hold for manual checking.
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: PayTomorrow amounts do not match (gross %s).', 'wc_paytomorrow' ), $amount ) );
			exit;
		}
	}

	/**
	 * Check receiver email from PayTomorrow. If the receiver email in the IPN is different than what is stored in.
	 * WooCommerce -> Settings -> Checkout -> PayTomorrow, it will log an error about it.
	 * @param WC_Order $order
	 * @param string $receiver_email
	 */
	protected function validate_receiver_email( $order, $receiver_email ) {
		if ( strcasecmp( trim( $receiver_email ), trim( $this->receiver_email ) ) != 0 ) {
			WC_Gateway_Paytomorrow::log( "IPN Response is for another account: {$receiver_email}. Your email is {$this->receiver_email}" );

			// Put this order on-hold for manual checking.
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: PayTomorrow IPN response from a different email address (%s).', 'wc_paytomorrow' ), $receiver_email ) );
			exit;
		}
	}

	/**
	 * Handle a completed payment.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_completed( $order, $posted ) {
		if ( $order->has_status( 'completed' ) ) {
			WC_Gateway_Paytomorrow::log( 'Aborting, Order #' . $order->id . ' is already complete.' );
			exit;
		}
        paytomorrow_log_me('payment_status_completed!!!');

		// $this->validate_transaction_type( $posted['txn_type'] );
		$this->validate_currency( $order, $posted['pt_currency'] );
		$this->validate_amount( $order, $posted['pt_ammount'] );
		// $this->validate_receiver_email( $order, $posted['receiver_email'] );
		$this->save_paytomorrow_meta_data( $order, $posted );

		paytomorrow_log_me('$posted[\'payment_status\'] : '. $posted['payment_status']);

		if ( 'completed' === $posted['payment_status'] ) {
			$this->payment_complete( $order, ( ! empty( $posted['trans_id'] ) ? wc_clean( $posted['trans_id'] ) : '' ), __( 'IPN payment completed', 'wc_paytomorrow' ) );

			// if ( ! empty( $posted['mc_fee'] ) ) {
			// 	// Log paytomorrow transaction fee.
			// 	update_post_meta( $order->id, 'PayTomorrow Transaction Fee', wc_clean( $posted['mc_fee'] ) );
			// }

		} else {
			$this->payment_on_hold( $order, sprintf( __( 'Payment pending: %s', 'wc_paytomorrow' ), $posted['pending_reason'] ) );
		}
	}

	/**
	 * Handle a pending payment.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_pending( $order, $posted ) {
		$this->payment_status_completed( $order, $posted );
	}

	/**
	 * Handle a failed payment.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_failed( $order, $posted ) {
		$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.', 'wc_paytomorrow' ), wc_clean( $posted['payment_status'] ) ) );
	}

	/**
	 * Handle a denied payment.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_denied( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	/**
	 * Handle an expired payment.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_expired( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	/**
	 * Handle a voided payment.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_voided( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	/**
	 * Handle a refunded order.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_refunded( $order, $posted ) {
		// Only handle full refunds, not partial.
		if ( $order->get_total() == ( $posted['pt_ammount'] * -1 ) ) {

			// Mark order as refunded.
			$order->update_status( 'refunded', sprintf( __( 'Payment %s via IPN.', 'wc_paytomorrow' ), strtolower( $posted['payment_status'] ) ) );

			$this->send_ipn_email_notification(
				sprintf( __( 'Payment for order %s refunded', 'wc_paytomorrow' ), '<a class="link" href="' . esc_url( admin_url( 'post.php?post=' . $order->id . '&action=edit' ) ) . '">' . $order->get_order_number() . '</a>' ),
				sprintf( __( 'Order #%s has been marked as refunded - PayTomorrow reason code: %s', 'wc_paytomorrow' ), $order->get_order_number(), $posted['reason_code'] )
			);
		}
	}

	/**
	 * Handle a reveral.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_reversed( $order, $posted ) {
		$order->update_status( 'on-hold', sprintf( __( 'Payment %s via IPN.', 'wc_paytomorrow' ), wc_clean( $posted['payment_status'] ) ) );

		$this->send_ipn_email_notification(
			sprintf( __( 'Payment for order %s reversed', 'wc_paytomorrow' ), '<a class="link" href="' . esc_url( admin_url( 'post.php?post=' . $order->id . '&action=edit' ) ) . '">' . $order->get_order_number() . '</a>' ),
			sprintf( __( 'Order #%s has been marked on-hold due to a reversal - PayTomorrow reason code: %s', 'wc_paytomorrow' ), $order->get_order_number(), wc_clean( $posted['reason_code'] ) )
		);
	}

	/**
	 * Handle a cancelled reveral.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function payment_status_canceled_reversal( $order, $posted ) {
		$this->send_ipn_email_notification(
			sprintf( __( 'Reversal cancelled for order #%s', 'wc_paytomorrow' ), $order->get_order_number() ),
			sprintf( __( 'Order #%s has had a reversal cancelled. Please check the status of payment and update the order status accordingly here: %s', 'wc_paytomorrow' ), $order->get_order_number(), esc_url( admin_url( 'post.php?post=' . $order->id . '&action=edit' ) ) )
		);
	}

	/**
	 * Save important data from the IPN to the order.
	 * @param WC_Order $order
	 * @param array $posted
	 */
	protected function save_paytomorrow_meta_data( $order, $posted ) {
		if ( ! empty( $posted['payer_email'] ) ) {
			update_post_meta( $order->id, 'Payer PayTomorrow address', wc_clean( $posted['payer_email'] ) );
		}
		if ( ! empty( $posted['first_name'] ) ) {
			update_post_meta( $order->id, 'Payer first name', wc_clean( $posted['first_name'] ) );
		}
		if ( ! empty( $posted['last_name'] ) ) {
			update_post_meta( $order->id, 'Payer last name', wc_clean( $posted['last_name'] ) );
		}
		if ( ! empty( $posted['payment_type'] ) ) {
			update_post_meta( $order->id, 'Payment type', wc_clean( $posted['payment_type'] ) );
		}
	}

	/**
	 * Send a notification to the user handling orders.
	 * @param string $subject
	 * @param string $message
	 */
	protected function send_ipn_email_notification( $subject, $message ) {
		$new_order_settings = get_option( 'woocommerce_new_order_settings', array() );
		$mailer             = WC()->mailer();
		$message            = $mailer->wrap_message( $subject, $message );

		$mailer->send( ! empty( $new_order_settings['recipient'] ) ? $new_order_settings['recipient'] : get_option( 'admin_email' ), strip_tags( $subject ), $message );
	}
}