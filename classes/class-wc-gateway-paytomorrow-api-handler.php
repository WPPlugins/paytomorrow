<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Refunds and other API requests such as capture.
 * @since 2.7.0
 */
class WC_Gateway_Paytomorrow_API_Handler {

	/** @var string API Username */
	public static $api_username;

	/** @var string API Password */
	public static $api_password;

	/** @var string API Signature */
	public static $api_signature;
	
	/** @var string token */
	public static $api_token;

    public static $api_url     ;
    public static $oauth_postfix      ;
    public static $checkout_postfix   ;
    public static $popup_postfix      ;


	/**
	 * Get capture request args.
	 * See https://developer.paytomorrow.com/docs/classic/api/merchant/DoCapture_API_Operation_NVP/.
	 * @param  WC_Order $order
	 * @param  float    $amount
	 * @return array
	 */
	public static function get_capture_request( $order, $amount = null ) {
		$request = array(
			'VERSION'         => '1.0',
			'signature'       => self::$api_signature,
			'username'        => self::$api_username,
			'password'        => self::$api_password
		);
		return apply_filters( 'woocommerce_paytomorrow_capture_request', $request, $order, $amount );
	}

    /**
     * Get capture request args.
     * See https://developer.paytomorrow.com/docs/classic/api/merchant/DoCapture_API_Operation_NVP/.
     * @param  WC_Order $order
     * @param  float    $amount
     * @return array
     */
    public static function get_auth_body() {


    	paytomorrow_log_me('in get_auth_body');
    	paytomorrow_log_me(self::$api_username);
    	paytomorrow_log_me(self::$api_password);
        $request = array(
            'username'        => self::$api_username,
            'password'        => self::$api_password,
            'grant_type'      => 'password',
            'scope'           => 'openid'
        );
        return apply_filters( 'woocommerce_paytomorrow_auth_request', $request);
    }

	/**
	 * Get refund request args.
	 * @param  WC_Order $order
	 * @param  float    $amount
	 * @param  string   $reason
	 * @return array
	 */
	public static function get_refund_request( $order, $amount = null, $reason = '' ) {
		$request = array(
			'VERSION'       => '84.0',
			'SIGNATURE'     => self::$api_signature,
			'USER'          => self::$api_username,
			'PWD'           => self::$api_password,
			'METHOD'        => 'RefundTransaction',
			'TRANSACTIONID' => $order->get_transaction_id(),
			'NOTE'          => html_entity_decode( wc_trim_string( $reason, 255 ), ENT_NOQUOTES, 'UTF-8' ),
			'REFUNDTYPE'    => 'Full',
		);
		if ( ! is_null( $amount ) ) {
			$request['AMT']          = number_format( $amount, 2, '.', '' );
			$request['CURRENCYCODE'] = $order->get_currency();
			$request['REFUNDTYPE']   = 'Partial';
		}
		return apply_filters( 'woocommerce_paytomorrow_refund_request', $request, $order, $amount, $reason );
	}

	/**
	 * Capture an authorization.
	 * @param  WC_Order $order
	 * @param  float $amount
	 * @return object Either an object of name value pairs for a success, or a WP_ERROR object.
	 */
	public static function do_capture( $order, $amount = null ) {

		$raw_response = wp_safe_remote_post(
			'https://api-3t.sandbox.paytomorrow.com/nvp',
			array(
				'method'      => 'POST',
				'body'        => self::get_capture_request( $order, $amount ),
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
			)
		);

		WC_Gateway_Paytomorrow::log( 'DoCapture Response: ' . print_r( $raw_response, true ) );

		if ( empty( $raw_response['body'] ) ) {
			return new WP_Error( 'paytomorrow-api', 'Empty Response' );
		} elseif ( is_wp_error( $raw_response ) ) {
			return $raw_response;
		}

		parse_str( $raw_response['body'], $response );

		return (object) $response;
	}

    /**
     * Capture an authorization.
     * @param  WC_Order $order
     * @param  float $amount
     * @return object Either an object of name value pairs for a success, or a WP_ERROR object.
     */
    public static function do_authorize() {

	    paytomorrow_log_me('signature: ' . self::$api_signature);
	    paytomorrow_log_me('username: ' . self::$api_username);

       $raw_response = wp_remote_post(
            self::$api_url . self::$oauth_postfix,
            array(
                'method'      => 'POST',
                'body'        => self::get_auth_body(),
                'timeout'     => 70,
                'user-agent'  => 'WooCommerce/' . WC()->version,
                'httpversion' => '1.1',
                'headers'     => array( 'Accept' => 'application/json', 'Authorization' =>'Basic ' . self::$api_signature),
		'sslverify' => false
            )
        );

       paytomorrow_log_me($raw_response);
	paytomorrow_log_me(self::$api_signature);
	paytomorrow_log_me(self::$api_username);
       paytomorrow_log_me(json_decode($raw_response['body']));
       $json =json_decode($raw_response['body']);
       self::$api_token = $json->{'access_token'};
       paytomorrow_log_me("my token :" . self::$api_token);
        WC_Gateway_Paytomorrow::log( 'DoCapture Response: ' . print_r( $raw_response, true ) );

        if ( empty( $raw_response['body'] ) ) {
            return new WP_Error( 'paytomorrow-api', 'Empty Response' );
        } elseif ( is_wp_error( $raw_response ) ) {
            return $raw_response;
        }
        parse_str( $raw_response['body'], $response );

        return (object) $response;
    }

	/**
	 * Refund an order via Paytomorrow.
	 * @param  WC_Order $order
	 * @param  float    $amount
	 * @param  string   $reason
	 * @return object Either an object of name value pairs for a success, or a WP_ERROR object.
	 */
	public static function refund_transaction( $order, $amount = null, $reason = '' ) {
		$raw_response = wp_safe_remote_post(
			'https://api-3t.sandbox.paytomorrow.com/nvp',
			array(
				'method'      => 'POST',
				'body'        => self::get_refund_request( $order, $amount, $reason ),
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
			)
		);

		WC_Gateway_Paytomorrow::log( 'Refund Response: ' . print_r( $raw_response, true ) );

		if ( empty( $raw_response['body'] ) ) {
			return new WP_Error( 'paytomorrow-api', 'Empty Response' );
		} elseif ( is_wp_error( $raw_response ) ) {
			return $raw_response;
		}

		parse_str( $raw_response['body'], $response );

		return (object) $response;
	}
}

/**
 * Here for backwards compatibility.
 * @since 2.7.0
 */
class WC_Gateway_Paytomorrow_Refund extends WC_Gateway_Paytomorrow_API_Handler {
	public static function get_request( $order, $amount = null, $reason = '' ) {
		return self::get_refund_request( $order, $amount, $reason );
	}
	public static function refund_order( $order, $amount = null, $reason = '') {

		$result = self::refund_transaction( $order, $amount, $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			return (array) $result;
		}
	}
}
