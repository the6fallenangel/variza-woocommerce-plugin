<?php

defined( 'ABSPATH' ) || exit;

class Variza_Plugin {

	private static $instance = null;

	public const WEBHOOK_NAMESPACE = 'variza/v1';
	public const WEBHOOK_ROUTE     = '/webhook';

	public const META_SLUG         = '_variza_slug';
	public const META_ATTEMPT_CODE = '_variza_attempt_code';
	public const META_AMOUNT       = '_variza_amount';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_classes();
		$this->register_hooks();
	}

	private function load_classes() {
		$always = array(
			'class-variza-api.php',
			'class-variza-webhook.php',
			'class-variza-admin.php',
		);

		foreach ( $always as $file ) {
			$path = VARIZA_PLUGIN_DIR . 'includes/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		// WC_Payment_Gateway must exist before we can extend it.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once VARIZA_PLUGIN_DIR . 'includes/class-variza-gateway.php';
		}

		// The Blocks payment integration base class is only available when
		// WooCommerce Blocks (bundled with modern WooCommerce) has loaded.
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once VARIZA_PLUGIN_DIR . 'includes/class-variza-blocks.php';
		}
	}

	public static function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private function register_hooks() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks' ) );
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wc_ajax_variza_check_order', array( $this, 'ajax_check_order' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_post_variza_save_settings', array( $this, 'save_admin_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_styles' ) );
	}

	public function register_gateway( $gateways ) {
		if ( class_exists( 'WooCommerce' ) && class_exists( 'Variza_Gateway' ) ) {
			$gateways[] = 'Variza_Gateway';
		}
		return $gateways;
	}

	public function register_blocks() {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		if ( ! class_exists( 'Variza_Blocks' ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
				$registry->register( new Variza_Blocks() );
			}
		);
	}

	public function register_webhook_route() {
		if ( ! class_exists( 'Variza_Webhook' ) ) {
			return;
		}

		register_rest_route(
			self::WEBHOOK_NAMESPACE,
			self::WEBHOOK_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( 'Variza_Webhook', 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function register_admin_menu() {
		if ( ! class_exists( 'Variza_Admin' ) ) {
			return;
		}

		add_menu_page(
			'واریزا',
			'واریزا',
			'manage_woocommerce',
			'variza',
			array( 'Variza_Admin', 'render' ),
			VARIZA_ICON_URL,
			56
		);

	}

	/**
	 * Registers and enqueues all admin-side CSS/JS for this plugin.
	 * - A small, always-loaded style that fixes the admin-menu icon sizing on every admin screen.
	 * - The full settings-page style/script, only loaded on the plugin's own settings screen.
	 */
	public function admin_styles( $hook ) {
		wp_register_style( 'variza-admin-menu', false, array(), VARIZA_VERSION );
		wp_enqueue_style( 'variza-admin-menu' );
		wp_add_inline_style(
			'variza-admin-menu',
			'#toplevel_page_variza .wp-menu-image img{width:20px !important;height:20px !important;object-fit:contain;padding:7px 0 !important;display:block;margin:0 auto;}'
		);

		if ( 'toplevel_page_variza' !== $hook || ! class_exists( 'Variza_Admin' ) ) {
			return;
		}

		wp_register_style( 'variza-admin', false, array(), VARIZA_VERSION );
		wp_enqueue_style( 'variza-admin' );
		wp_add_inline_style( 'variza-admin', Variza_Admin::css() );

		wp_register_script( 'variza-admin', false, array(), VARIZA_VERSION, true );
		wp_enqueue_script( 'variza-admin' );
		wp_add_inline_script( 'variza-admin', Variza_Admin::js() );
	}

	public function checkout_styles() {
		wp_register_style( 'variza-checkout', false, array(), VARIZA_VERSION );
		wp_enqueue_style( 'variza-checkout' );
		$css = '
			.wc_payment_method.payment_method_variza label{display:inline-flex !important;align-items:center;gap:8px;}
			.wc_payment_method.payment_method_variza label img{height:22px !important;width:22px !important;max-height:22px !important;border-radius:6px;background:#ecfdf5;padding:3px;object-fit:contain;vertical-align:middle;}
			.wc-block-components-radio-control__label img,
			.wc-block-components-payment-method-label img{height:22px !important;width:22px !important;max-height:22px !important;border-radius:6px;background:#ecfdf5;padding:3px;object-fit:contain;}
			.wc_payment_method.payment_method_variza .payment_box{border-top-color:#10b981;}
		';
		wp_add_inline_style( 'variza-checkout', $css );
	}

	public function save_admin_settings() {
		if ( ! class_exists( 'Variza_Admin' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=variza' ) );
			exit;
		}

		Variza_Admin::save();
	}

	public function enqueue_scripts() {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$order = self::get_order_from_request();
		if ( ! $order ) {
			return;
		}

		if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return;
		}

		wp_enqueue_script( 'variza-checkout', VARIZA_PLUGIN_URL . 'assets/js/variza-checkout.js', array(), VARIZA_VERSION, true );
		wp_localize_script(
			'variza-checkout',
			'varizaCheckout',
			array(
				'ajaxUrl'  => WC_AJAX::get_endpoint( 'variza_check_order' ),
				'orderId'  => (int) $order->get_id(),
				'nonce'    => wp_create_nonce( 'variza_check_order_' . $order->get_id() ),
				'poll'     => (int) apply_filters( 'variza_poll_interval_ms', 5000 ),
				'maxTries' => (int) apply_filters( 'variza_max_poll_tries', 60 ),
			)
		);
	}

	public function ajax_check_order() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$nonce    = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! $order_id || ! wp_verify_nonce( $nonce, 'variza_check_order_' . $order_id ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}

		wp_send_json_success(
			array(
				'status'        => $order->get_status(),
				'needs_payment' => $order->needs_payment(),
			)
		);
	}

	private static function get_order_from_request() {
		if ( ! isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id  = wc_get_order_id_by_order_key( $order_key );

		return $order_id ? wc_get_order( $order_id ) : null;
	}
}