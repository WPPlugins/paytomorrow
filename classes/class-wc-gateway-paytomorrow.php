<?php
/**
 * PayTomorrow Standard Payment Gateway.
 *
 * Provides a PayTomorrow Standard Payment Gateway.
 *
 * @class 		WC_Gateway_Paytomorrow
 * @extends		WC_Payment_Gateway
 * @version		2.3.0
 * @package		WooCommerce/Classes/Payment
 * @author 		WooThemes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Paytomorrow Class.
 */
class WC_Gateway_Paytomorrow extends WC_Payment_Gateway {

	/** @var bool Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;


    public static $oauth_postfix        = "/uaa/oauth/token";
    public static $checkout_postfix     = "/api/application/checkWoo";
    public static $validateipn_postfix  = "/api/application/validateipn";
    public static $popup_postfix        = "/popup?";
    public static $api_url;

    /**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->id                 = 'paytomorrow';
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Proceed to PayTomorrow', 'wc_paytomorrow' );
		$this->method_title       = __( 'PayTomorrow', 'wc_paytomorrow' );
		$this->method_description = sprintf( __( 'PayTomorrow standard sends customers to PayTomorrow to enter their payment information. PayTomorrow IPN requires fsockopen/cURL support to update order statuses after payment. Check the %ssystem status%s page for more details.', 'wc_paytomorrow' ), '<a href="' . admin_url( 'admin.php?page=wc-status' ) . '">', '</a>' );
		$this->supports           = array(
			'products'
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
        // $this->testmode       = 'yes' === $this->get_option( 'testmode', 'no' );
		// $this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
		$this->debug          = true;
        self::$api_url        = $this->get_option( 'api_url' );
        $this->email          = $this->get_option( 'email' );
		$this->receiver_email = $this->get_option( 'receiver_email', $this->email );

		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		} else {
			include_once( dirname( __FILE__ ) . '/class-wc-gateway-paytomorrow-ipn-handler.php' );
			new WC_Gateway_Paytomorrow_IPN_Handler( $this->receiver_email, self::$api_url, self::$validateipn_postfix);
		}
	}

	/**
	 * Logging method.
	 * @param string $message
	 */
	public static function log( $message ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'paytomorrow', $message );
		}
	}

	/**
	 * Get gateway icon.
	 * @return string
	 */
	public function get_icon() {
		$icon_html = '';
		$icon      = (array) $this->get_icon_image( WC()->countries->get_base_country() );

		// foreach ( $icon as $i ) {
		// 	$icon_html .= '<img src="' . esc_attr( $i ) . '" alt="' . esc_attr__( 'PayTomorrow Acceptance Mark', 'wc_paytomorrow' ) . '" />';
		// }

		$icon_html .= sprintf( '<a href="%1$s" class="about_paytomorrow" onclick="javascript:window.open(\'%1$s\',\'WIPaytomorrow\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;" title="' . esc_attr__( 'What is PayTomorrow?', 'wc_paytomorrow' ) . '">' . esc_attr__( 'What is PayTomorrow?', 'wc_paytomorrow' ) . '</a>', esc_url( $this->get_icon_url( WC()->countries->get_base_country() ) ) );

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	/**
	 * Get the link for an icon based on country.
	 * @param  string $country
	 * @return string
	 */
	protected function get_icon_url( $country ) {
		$url           = 'https://www.paytomorrow.com/' . strtolower( $country );
		$home_counties = array( 'BE', 'CZ', 'DK', 'HU', 'IT', 'JP', 'NL', 'NO', 'ES', 'SE', 'TR');
		$countries     = array( 'DZ', 'AU', 'BH', 'BQ', 'BW', 'CA', 'CN', 'CW', 'FI', 'FR', 'DE', 'GR', 'HK', 'IN', 'ID', 'JO', 'KE', 'KW', 'LU', 'MY', 'MA', 'OM', 'PH', 'PL', 'PT', 'QA', 'IE', 'RU', 'BL', 'SX', 'MF', 'SA', 'SG', 'SK', 'KR', 'SS', 'TW', 'TH', 'AE', 'GB', 'US', 'VN' );

		if ( in_array( $country, $home_counties ) ) {
			return  $url . '/webapps/mpp/home';
		} else if ( in_array( $country, $countries ) ) {
			return $url . '/webapps/mpp/paytomorrow-popup';
		} else {
			return $url . '/cgi-bin/webscr?cmd=xpt/Marketing/general/WIPaytomorrow-outside';
		}
	}

	/**
	 * Get PayTomorrow images for a country.
	 * @param  string $country
	 * @return array of image URLs
	 */
	protected function get_icon_image( $country ) {
		$icon = WC_HTTPS::force_https_url( WC()->plugin_url() . '/gateways/paytomorrow/assets/images/paytomorrow.png' );
		return apply_filters( 'woocommerce_paytomorrow_icon', $icon );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paytomorrow_supported_currencies', array( 'USD' ) ) );
	}

	/**
	 * Admin Panel Options.
	 * - Options for bits like 'title' and availability on a country-by-country basis.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'wc_paytomorrow' ); ?></strong>: <?php _e( 'PayTomorrow does not support your store currency.', 'wc_paytomorrow' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'settings-paytomorrow.php' );
	}

	/**
	 * Get the transaction URL.
	 * @param  WC_Order $order
	 * @return string
	 */
	public function get_transaction_url( $order ) {

			$this->view_transaction_url = 'https://www.paytomorrow.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
		return parent::get_transaction_url( $order );
	}

	/**
	 * Process the payment and return the result.
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
        paytomorrow_log_me('ENTERING process_payment');
        $this->init_api();

		include_once( dirname( __FILE__ ) . '/class-wc-gateway-paytomorrow-request.php' );
		include_once( dirname( __FILE__ ) . '/class-wc-gateway-paytomorrow-api-handler.php' );

		$order          = wc_get_order( $order_id );
		$paytomorrow_request = new WC_Gateway_Paytomorrow_Request( $this );

//        $requestUrl = 'http://localhost:9000/api/application/checkWoo';

        $requestUrl = self::$api_url . self::$checkout_postfix;


        $bodyRequest = array_merge(WC_Gateway_Paytomorrow_API_Handler::get_capture_request($order), $paytomorrow_request->get_paytomorrow_order_body_args($order));
        $request = array(
    				'method'      => 'POST',
    				'headers'     => array( 'Content-Type' => 'application/json', "Authorization" =>'bearer '. WC_Gateway_Paytomorrow_API_Handler::$api_token ),
    				'body'        => wp_json_encode($bodyRequest),
    				'timeout'     => 70,
    				'user-agent'  => 'WooCommerce/' . WC()->version,
    				'httpversion' => '1.1',
		            'sslverify' => false
    			);

        paytomorrow_log_me(array('requestUrl' => $requestUrl, 'request' => $request, '$bodyRequest' => wp_json_encode($bodyRequest)));

        if(is_ssl()) {
            paytomorrow_log_me('ssl call');
            $raw_response = wp_safe_remote_post( $requestUrl, $request );
        } else {
            paytomorrow_log_me('NO ssl call');
            $raw_response = wp_remote_post( $requestUrl, $request );
        }

        paytomorrow_log_me( array( 'raw_response' => $raw_response ) );

        if ( is_wp_error( $raw_response ) ) {
            paytomorrow_log_me( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.' );
        } else {
            $uuid = wp_remote_retrieve_body( $raw_response );
            update_post_meta($order_id, 'PT-ReferenceID', $uuid);
			$session = WC_Gateway_Paytomorrow_API_Handler::$api_token;
            paytomorrow_log_me( array( 'response_body' => $session ) );
        }

		return array(
			'result'   => 'success',
			'redirect' => $paytomorrow_request->get_request_url( $order, $session, $uuid )
		);
	}

	/**
	 * Can the order be refunded via PayTomorrow?
	 * @param  WC_Order $order
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Init the API class and set the username/password etc.
	 */
	protected function init_api() {
		include_once( dirname( __FILE__ ) . '/class-wc-gateway-paytomorrow-api-handler.php' );

		WC_Gateway_Paytomorrow_API_Handler::$api_username  = $this->get_option( 'api_username' );
		WC_Gateway_Paytomorrow_API_Handler::$api_password  = $this->get_option( 'api_password' );
		WC_Gateway_Paytomorrow_API_Handler::$api_signature = $this->get_option( 'api_signature' );
		WC_Gateway_Paytomorrow_API_Handler::$api_url       = self::$api_url;
		WC_Gateway_Paytomorrow_API_Handler::$checkout_postfix = self::$checkout_postfix;
		WC_Gateway_Paytomorrow_API_Handler::$oauth_postfix = self::$oauth_postfix;
		WC_Gateway_Paytomorrow_API_Handler::$popup_postfix = self::$popup_postfix;

		WC_Gateway_Paytomorrow_API_Handler::do_authorize();
		paytomorrow_log_me("sig: " . WC_Gateway_Paytomorrow_API_Handler::$api_signature);
		paytomorrow_log_me("token: " . WC_Gateway_Paytomorrow_API_Handler::$api_token);

	}

	/**
	 * Process a refund if supported.
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 * @return bool True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log( 'Refund Failed: No transaction ID' );
			return new WP_Error( 'error', __( 'Refund Failed: No transaction ID', 'wc_paytomorrow' ) );
		}

		$this->init_api();

		$result = WC_Gateway_Paytomorrow_API_Handler::refund_transaction( $order, $amount, $reason );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Refund Failed: ' . $result->get_error_message() );
			return new WP_Error( 'error', $result->get_error_message() );
		}

		$this->log( 'Refund Result: ' . print_r( $result, true ) );

		switch ( strtolower( $result->ACK ) ) {
			case 'success':
			case 'successwithwarning':
				$order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'wc_paytomorrow' ), $result->GROSSREFUNDAMT, $result->REFUNDTRANSACTIONID ) );
				return true;
			break;
		}

		return isset( $result->L_LONGMESSAGE0 ) ? new WP_Error( 'error', $result->L_LONGMESSAGE0 ) : false;
	}

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
        paytomorrow_log_me('ENTERING capture_payment');

		$order = wc_get_order( $order_id );

        paytomorrow_log_me(array('paytomorrow' => $order->get_payment_method(), 'pending' => get_post_meta( $order->get_id(), '_paytomorrow_status', true ), 'get_transaction_id' => $order->get_transaction_id()));

		if ( 'paytomorrow' === $order->get_payment_method() && 'pending' === get_post_meta( $order->get_id(), '_paytomorrow_status', true ) && $order->get_transaction_id() ) {
			$this->init_api();
            paytomorrow_log_me('ENTERING do_capture (capture_payment)');
	        $result = WC_Gateway_Paytomorrow_API_Handler::do_capture( $order );

			if ( is_wp_error( $result ) ) {
				$this->log( 'Capture Failed: ' . $result->get_error_message() );
				$order->add_order_note( sprintf( __( 'Payment could not captured: %s', 'wc_paytomorrow' ), $result->get_error_message() ) );
				return;
			}

			$this->log( 'Capture Result: ' . print_r( $result, true ) );

			if ( ! empty( $result->PAYMENTSTATUS ) ) {
				switch ( $result->PAYMENTSTATUS ) {
					case 'Completed' :
						$order->add_order_note( sprintf( __( 'Payment of %s was captured - Auth ID: %s, Transaction ID: %s', 'wc_paytomorrow' ), $result->AMT, $result->AUTHORIZATIONID, $result->TRANSACTIONID ) );
						update_post_meta( $order->get_id(), '_paytomorrow_status', $result->PAYMENTSTATUS );
						update_post_meta( $order->get_id(), '_transaction_id', $result->TRANSACTIONID );
					break;
					default :
						$order->add_order_note( sprintf( __( 'Payment could not captured - Auth ID: %s, Status: %s', 'wc_paytomorrow' ), $result->AUTHORIZATIONID, $result->PAYMENTSTATUS ) );
					break;
				}
			}
		}
	}
}
