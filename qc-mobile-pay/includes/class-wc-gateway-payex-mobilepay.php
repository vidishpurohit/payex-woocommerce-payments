<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Payex_Psp_MobilePay extends WC_Gateway_Payex_Cc
	implements WC_Payment_Gateway_Payex_Interface {

	/**
	 * Merchant Token
	 * @var string
	 */
	public $merchant_token = '';

	/**
	 * Payee Id
	 * @var string
	 */
	public $payee_id = '';

	/**
	 * Test Mode
	 * @var string
	 */
	public $testmode = 'yes';

	/**
	 * Debug Mode
	 * @var string
	 */
	public $debug = 'yes';

	/**
	 * Locale
	 * @var string
	 */
	public $culture = 'en-US';

	/**
	 * Checkout Method
	 * @var string
	 */
	public $method = 'redirect';

	/**
	 * Init
	 */
	public function __construct() {
		$this->transactions = WC_Payex_Transactions::instance();

		$this->id           = 'payex_psp_mobilepay';
		$this->has_fields   = true;
		$this->method_title = __( 'Mobilepay', 'payex-woocommerce-payments' );
		$this->icon         = apply_filters( 'woocommerce_payex_mobilepay_icon', plugins_url( '/assets/images/mobilepay_online.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled        = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title          = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description    = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_token = isset( $this->settings['merchant_token'] ) ? $this->settings['merchant_token'] : $this->merchant_token;
		$this->payee_id       = isset( $this->settings['payee_id'] ) ? $this->settings['payee_id'] : $this->payee_id;
		$this->testmode       = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
		$this->debug          = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->culture        = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->method         = isset( $this->settings['method'] ) ? $this->settings['method'] : $this->method;
		$this->terms_url      = isset( $this->settings['terms_url'] ) ? $this->settings['terms_url'] : get_site_url();

		// TermsOfServiceUrl contains unsupported scheme value http in Only https supported.
		if ( ! filter_var( $this->terms_url, FILTER_VALIDATE_URL ) ) {
			$this->terms_url = '';
		} elseif ( 'https' !== parse_url( $this->terms_url, PHP_URL_SCHEME ) ) {
			$this->terms_url = '';
		}

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Pending Cancel
		add_action( 'woocommerce_order_status_pending_to_cancelled', array(
			$this,
			'cancel_pending'
		), 10, 2 );

	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'payex-woocommerce-payments' ),
				'default' => 'no'
			),
			'title'          => array(
				'title'       => __( 'Title', 'payex-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'payex-woocommerce-payments' ),
				'default'     => __( 'MobilePay payment', 'payex-woocommerce-payments' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'payex-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'payex-woocommerce-payments' ),
				'default'     => __( 'Mobilepay', 'payex-woocommerce-payments' ),
			),
			'merchant_token' => array(
				'title'       => __( 'Merchant Token', 'payex-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Merchant Token', 'payex-woocommerce-payments' ),
				'default'     => $this->merchant_token
			),
			'payee_id'       => array(
				'title'       => __( 'Payee Id', 'payex-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Payee Id', 'payex-woocommerce-payments' ),
				'default'     => $this->payee_id
			),
			'testmode'       => array(
				'title'   => __( 'Test Mode', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable PayEx Test Mode', 'payex-woocommerce-payments' ),
				'default' => $this->testmode
			),
			'debug'          => array(
				'title'   => __( 'Debug', 'payex-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'payex-woocommerce-payments' ),
				'default' => $this->debug
			),
			'culture'        => array(
				'title'       => __( 'Language', 'payex-woocommerce-payments' ),
				'type'        => 'select',
				'options'     => array(
					'en-US' => 'English',
					'sv-SE' => 'Swedish',
					'nb-NO' => 'Norway',
					'dk-DK'	=> 'Danish'
				),
				'description' => __( 'Language of pages displayed by PayEx during payment.', 'payex-woocommerce-payments' ),
				'default'     => $this->culture
			),
			'method'         => array(
				'title'       => __( 'Checkout Method', 'payex-woocommerce-payments' ),
				'type'        => 'select',
				'options'     => array(
					'redirect' => __( 'Redirect', 'payex-woocommerce-payments' ),
				),
				'description' => __( 'Checkout Method', 'payex-woocommerce-payments' ),
				'default'     => $this->method
			),
			'terms_url'      => array(
				'title'       => __( 'Terms & Conditions Url', 'payex-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Terms & Conditions Url', 'payex-woocommerce-payments' ),
				'default'     => get_site_url()
			),
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		parent::payment_fields();
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		//
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$amount   = $order->get_total();
		$currency = px_obj_prop( $order, 'order_currency' );
		$email    = px_obj_prop( $order, 'billing_email' );
		$phone    = px_obj_prop( $order, 'billing_phone' );

		$user_id = $order->get_customer_id();

		// Get Customer UUID
		if ( $user_id > 0 ) {
			$customer_uuid = get_user_meta( $user_id, '_payex_customer_uuid', true );
			if ( empty( $customer_uuid ) ) {
				$customer_uuid = px_uuid( $user_id );
				update_user_meta( $user_id, '_payex_customer_uuid', $customer_uuid );
			}
		} else {
			$customer_uuid = px_uuid( uniqid( $email ) );
		}

		// Get Order UUID
		$order_uuid = mb_strimwidth( px_uuid( $order_id ), 0, 30, '', 'UTF-8' );

		$params = [
			'payment' => [
				'operation'      => 'Purchase',
				'intent'         => 'Authorization',
				'currency'       => $currency,
				'prices'         => [
					[
						'type'      => 'Visa',
						'amount'    => round( $amount * 100 ),
						'vatAmount' => '0',
						'FeeAmount'			=>	'5'
					],
					[
						'type'      => 'MasterCard',
						'amount'    => round( $amount * 100 ),
						'vatAmount' => '0',
						'FeeAmount'			=>	'10'
					]
				],
				'description'    => sprintf( __( 'Order #%s', 'payex-woocommerce-payments' ), $order->get_order_number() ),
				'payerReference' => $customer_uuid,
				'userAgent'      => $_SERVER['HTTP_USER_AGENT'],
				'language'       => $this->culture,
				'urls'           => [
					'completeUrl'       => html_entity_decode( $this->get_return_url( $order ) ),
					'cancelUrl'         => $order->get_cancel_order_url_raw(),
					'callbackUrl'       => WC()->api_request_url( __CLASS__ ),
					// 50px height and 400px width. Require https.
					//'logoUrl'     => "https://example.com/logo.png",// @todo
					'termsOfServiceUrl' => $this->terms_url
				],
				'payeeInfo'      => [
					'payeeId'        => $this->payee_id,
					'payeeReference' => str_replace( '-', '', $order_uuid ),
					'orderReference' => $order->get_order_number()
				],
			],
			'mobilepay'          => [
				'shoplogoUrl' => home_url('wp-content/uploads/cropped-Clearblueshop-logo.png')
			]
		];

		try {
			$result = $this->request( 'POST', '/psp/mobilepay/payments', $params );
		} catch ( \Exception $e ) {
			$this->log( sprintf( '[ERROR] Process payment: %s', $e->getMessage() ) );
			wc_add_notice( $e->getMessage(), 'error' );

			return false;
		}

		// Save payment ID
		update_post_meta( $order_id, '_payex_payment_id', $result['payment']['id'] );
		//print('<pre>');print_r($result);print('</pre>');
		//die($this->method);
		switch ( $this->method ) {
			case 'redirect':
				// Get Redirect
				$redirect = $result['operations'][1]['href'];

				return array(
					'result'   => 'success',
					'redirect' => $redirect
				);
				break;
			case 'direct':
				// Sale payment
				$sale = self::get_operation( $result['operations'], 'create-sale' );

				try {
					$params = [
						'transaction' => [
							'msisdn' => apply_filters( 'payex_mobilepay_phone_format', $phone, $order )
						]
					];

					$result = $this->request( 'POST', $sale, $params );
				} catch ( \Exception $e ) {
					$this->log( sprintf( '[ERROR] Create Sale: %s', $e->getMessage() ) );
					wc_add_notice( $e->getMessage(), 'error' );

					return false;
				}

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);

				break;

			default:
				wc_add_notice( __( 'Wrong method', 'payex-woocommerce-payments' ), 'error' );

				return false;
		}

	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param bool $amount
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function capture_payment( $order, $amount = false ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		// @todo Improve feature
		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		$order_id   = px_obj_prop( $order, 'id' );
		$payment_id = get_post_meta( $order_id, '_payex_payment_id', true );
		if ( empty( $payment_id ) ) {
			throw new \Exception( 'Unable to get payment ID' );
		}

		try {
			$result = $this->request( 'GET', $payment_id );
		} catch ( \Exception $e ) {
			throw new \Exception( sprintf( 'API Error: %s', $e->getMessage() ) );
		}
		//print('<pre>');print_r($result);print('</pre>');
		$capture_href = self::get_operation( $result['operations'], 'create-capture' );
		if ( empty( $capture_href ) ) {
			throw new \Exception( __( 'Capture unavailable', 'payex-woocommerce-payments' ) );
		}

		// Order Info
		$info = $this->get_order_info( $order );

		// Get Order UUID
		$payeeReference = mb_strimwidth( px_uuid( uniqid( $order_id ) ), 0, 30, '', 'UTF-8' );

		$params = array(
			'transaction' => array(
				'amount'         => (int) round( $amount * 100 ),
				'vatAmount'      => (int) round( $info['vat_amount'] * 100 ),
				'description'    => sprintf( 'Capture for Order #%s', $order_id ),
				'payeeReference' => str_replace( '-', '', $payeeReference )
			)
		);
		$result = $this->request( 'POST', $capture_href, $params );

		// Save transaction
		$transaction = $result['capture']['transaction'];
		$this->transactions->import( $transaction, $order_id );

		switch ( $transaction['state'] ) {
			case 'Completed':
				update_post_meta( $order_id, '_payex_payment_state', 'Captured' );
				update_post_meta( $order_id, '_payex_transaction_capture', $transaction['id'] );

				$order->add_order_note( __( 'Transaction captured.', 'payex-woocommerce-payments' ) );
				$order->payment_complete( $transaction['number'] );

				break;
			case 'Initialized':
				$order->add_order_note( sprintf( __( 'Transaction capture status: %s.', 'payex-woocommerce-payments' ), $transaction['state'] ) );
				break;
			case 'Failed':
			default:
				$message = isset( $transaction['failedReason'] ) ? $transaction['failedReason'] : __( 'Capture failed.', 'payex-woocommerce-payments' );
				throw new \Exception( $message );
				break;
		}
	}
}

// Register Gateway
WC_Payex_Psp::register_gateway( 'WC_Gateway_Payex_Psp_MobilePay' );
