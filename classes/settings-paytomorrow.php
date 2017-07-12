<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for PayTomorrow Gateway.
 */
return array(
	'enabled' => array(
		'title'   => __( 'Enable/Disable', 'wc_paytomorrow' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable PayTomorrow standard', 'wc_paytomorrow' ),
		'default' => 'yes'
	),
	'title' => array(
		'title'       => __( 'Title', 'wc_paytomorrow' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'wc_paytomorrow' ),
		'default'     => __( 'PayTomorrow', 'wc_paytomorrow' ),
		'desc_tip'    => true,
	),
	'description' => array(
		'title'       => __( 'Description', 'wc_paytomorrow' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'wc_paytomorrow' ),
		'default'     => __( 'Pay via PayTomorrow; you can pay with your credit card if you don\'t have a PayTomorrow account.', 'wc_paytomorrow' )
	),
	'email' => array(
		'title'       => __( 'PayTomorrow Email', 'wc_paytomorrow' ),
		'type'        => 'email',
		'description' => __( 'Please enter your PayTomorrow email address; this is needed in order to take payment.', 'wc_paytomorrow' ),
		'default'     => get_option( 'admin_email' ),
		'desc_tip'    => true,
		'placeholder' => 'you@youremail.com'
	),
//	'testmode' => array(
//		'title'       => __( 'PayTomorrow Sandbox', 'wc_paytomorrow' ),
//		'type'        => 'checkbox',
//		'label'       => __( 'Enable PayTomorrow sandbox', 'wc_paytomorrow' ),
//		'default'     => 'no',
//		'description' => sprintf( __( 'PayTomorrow sandbox can be used to test payments. Sign up for a developer account <a href="%s">here</a>.', 'wc_paytomorrow' ), 'https://developer.paytomorrow.com/' ),
//	),
	'debug' => array(
		'title'       => __( 'Debug Log', 'wc_paytomorrow' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'wc_paytomorrow' ),
		'default'     => 'no',
		'description' => sprintf( __( 'Log PayTomorrow events, such as IPN requests, inside <code>%s</code>', 'wc_paytomorrow' ), wc_get_log_file_path( 'paytomorrow' ) )
	),
	'receiver_email' => array(
		'title'       => __( 'Receiver Email', 'wc_paytomorrow' ),
		'type'        => 'email',
		'description' => __( 'If your main PayTomorrow email differs from the PayTomorrow email entered above, input your main receiver email for your PayTomorrow account here. This is used to validate IPN requests.', 'wc_paytomorrow' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => 'you@youremail.com'
	),
	'invoice_prefix' => array(
		'title'       => __( 'Invoice Prefix', 'wc_paytomorrow' ),
		'type'        => 'text',
		'description' => __( 'Please enter a prefix for your invoice numbers. If you use your PayTomorrow account for multiple stores ensure this prefix is unique as PayTomorrow will not allow orders with the same invoice number.', 'wc_paytomorrow' ),
		'default'     => 'WC-',
		'desc_tip'    => true,
	),
	'send_shipping' => array(
		'title'       => __( 'Shipping Details', 'wc_paytomorrow' ),
		'type'        => 'checkbox',
		'label'       => __( 'Send shipping details to PayTomorrow instead of billing.', 'wc_paytomorrow' ),
		'description' => __( 'PayTomorrow allows us to send one address. If you are using PayTomorrow for shipping labels you may prefer to send the shipping address rather than billing.', 'wc_paytomorrow' ),
		'default'     => 'no'
	),
    'api_url' => array(
    'title'       => __( 'API URL', 'wc_paytomorrow' ),
    'type'        => 'text',
    'desc_tip'    => true,
    'description' => __( 'Get your API url from PayTomorrow.', 'wc_paytomorrow' ),
    'default'     => 'http://www.paytomorrow.com'
    ),
	'api_username' => array(
		'title'       => __( 'API Username', 'wc_paytomorrow' ),
		'type'        => 'text',
		'description' => __( 'Get your API credentials from PayTomorrow.', 'wc_paytomorrow' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( '', 'wc_paytomorrow' )
	),
	'api_password' => array(
		'title'       => __( 'API Password', 'wc_paytomorrow' ),
		'type'        => 'password',
		'description' => __( 'Get your API credentials from PayTomorrow.', 'wc_paytomorrow' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( '', 'wc_paytomorrow' )
	),
	'api_signature' => array(
		'title'       => __( 'API Signature', 'wc_paytomorrow' ),
		'type'        => 'password',
		'description' => __( 'Get your API credentials from PayTomorrow.', 'wc_paytomorrow' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( '', 'wc_paytomorrow' )
	),
);
