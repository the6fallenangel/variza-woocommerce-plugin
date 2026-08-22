<?php

defined( 'ABSPATH' ) || exit;

class Variza_Gateway extends WC_Payment_Gateway {

	private static $logger = null;

	public function __construct() {
		$this->id                 = 'variza';
		$this->icon               = apply_filters( 'variza_gateway_icon', VARIZA_ICON_URL );
		$this->has_fields         = false;
		$this->method_title       = __( 'واریزا', 'variza-for-woocommerce' );
		$this->method_description = __( 'درگاه تأیید خودکار کارت‌به‌کارت واریزا، بدون بررسی دستی و ارسال رسید.', 'variza-for-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'فعال / غیرفعال', 'variza-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی درگاه پرداخت واریزا', 'variza-for-woocommerce' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'       => __( 'عنوان روش پرداخت', 'variza-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'نامی که خریدار در بخش انتخاب روش پرداخت در صفحه‌ی تسویه‌حساب می‌بیند.', 'variza-for-woocommerce' ),
				'default'     => __( 'واریزا', 'variza-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'    => array(
				'title'       => __( 'توضیحات روش پرداخت', 'variza-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'توضیح کوتاهی که زیر نام روش پرداخت در صفحه‌ی تسویه‌حساب نشان داده می‌شود.', 'variza-for-woocommerce' ),
				'default'     => __( 'به‌صورت کارت‌به‌کارت پرداخت کنید؛ پس از واریز، سفارش شما به‌صورت خودکار تأیید می‌شود.', 'variza-for-woocommerce' ),
			),
			'api_token'      => array(
				'title'       => __( 'کلید API', 'variza-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'کلید API حساب واریزا شما؛ از پنل واریزا (پروفایل ← کلید API) دریافت می‌شود.', 'variza-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_secret' => array(
				'title'       => __( 'کلید امضای وب‌هوک', 'variza-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'کلید امضای وب‌هوک؛ برای اطمینان از اینکه خبر پرداخت واقعاً از واریزا رسیده است.', 'variza-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_url'    => array(
				'title'       => __( 'آدرس وب‌هوک', 'variza-for-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf(
					'<code>%s</code><br />%s',
					esc_html( $this->webhook_url() ),
					__( 'این آدرس را در پنل واریزا (پروفایل ← بخش «وب‌هوک») در فیلد «آدرس وب‌هوک» ثبت کنید تا پرداخت‌ها به‌صورت خودکار تأیید شوند.', 'variza-for-woocommerce' )
				),
			),
			'currency_unit'  => array(
				'title'       => __( 'واحد پول فروشگاه', 'variza-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'toman' => __( 'تومان', 'variza-for-woocommerce' ),
					'rial'  => __( 'ریال', 'variza-for-woocommerce' ),
				),
				'default'     => 'toman',
				'description' => __( 'قیمت‌های فروشگاه شما با کدام واحد نمایش داده می‌شود؟ واریزا فقط تومان می‌پذیرد؛ مبالغ ریالی به‌صورت خودکار به تومان تبدیل می‌شوند (÷۱۰).', 'variza-for-woocommerce' ),
				'desc_tip'    => true,
			),
		);
	}

	public function webhook_url() {
		return rest_url( trailingslashit( Variza_Plugin::WEBHOOK_NAMESPACE ) . 'webhook' );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'result'   => 'failure',
				'messages' => __( 'سفارش یافت نشد.', 'variza-for-woocommerce' ),
			);
		}

		$token = $this->get_option( 'api_token' );
		if ( empty( $token ) ) {
			return $this->fail_payment(
				$order,
				__( 'درگاه واریزا تنظیم نشده است. لطفاً با فروشگاه تماس بگیرید.', 'variza-for-woocommerce' )
			);
		}

		$amount = $this->normalize_amount( $order->get_total() );
		if ( $amount < 1000 ) {
			return $this->fail_payment(
				$order,
				__( 'حداقل مبلغ قابل پرداخت با این درگاه ۱۰۰۰ تومان است.', 'variza-for-woocommerce' )
			);
		}

		$return_url = $this->get_return_url( $order );
		$title      = sprintf( __( 'سفارش #%s', 'variza-for-woocommerce' ), $order->get_order_number() );

		$client = new Variza_API_Client( $token );
		$link   = $client->create_payment_link( $amount, $return_url, $title );

		if ( is_wp_error( $link ) ) {
			self::log(
				sprintf(
					'Order %d: failed to create Variza payment link. Reason: %s',
					$order_id,
					$link->get_error_message()
				)
			);

			$order->add_order_note(
				sprintf(
					/* translators: %s: raw error message returned by the Variza API. */
					__( 'ساخت لینک پرداخت واریزا با خطا مواجه شد: %s', 'variza-for-woocommerce' ),
					$link->get_error_message()
				)
			);

			return $this->fail_payment(
				$order,
				__( 'ایجاد درخواست پرداخت ممکن نشد. لطفاً دوباره تلاش کنید یا روش پرداخت دیگری انتخاب کنید.', 'variza-for-woocommerce' )
			);
		}

		$order->update_meta_data( Variza_Plugin::META_SLUG, $link['slug'] );
		$order->update_meta_data( Variza_Plugin::META_AMOUNT, $link['amount'] );
		$order->add_order_note(
			sprintf(
				__( 'لینک پرداخت واریزا ساخته شد (%1$s) به مبلغ %2$s تومان. در انتظار تأیید پرداخت.', 'variza-for-woocommerce' ),
				$link['slug'],
				number_format_i18n( $link['amount'] )
			)
		);

		wc_reduce_stock_levels( $order_id );
		$order->update_status( 'pending', __( 'در انتظار تأیید پرداخت واریزا.', 'variza-for-woocommerce' ) );

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $link['pay_url'],
		);
	}

	private function fail_payment( $order, $message ) {
		wc_add_notice( $message, 'error' );
		$order->update_status( 'failed', $message );
		return array(
			'result'   => 'failure',
			'messages' => $message,
		);
	}

	private function normalize_amount( $total ) {
		$unit   = $this->get_option( 'currency_unit', 'toman' );
		$factor = ( 'rial' === $unit ) ? 0.1 : 1;
		$toman  = (float) $total * $factor;
		$toman  = (float) apply_filters( 'variza_amount', $toman, $total, $unit );
		return (int) round( $toman, 0 );
	}

	public static function log( $message ) {
		if ( null === self::$logger ) {
			self::$logger = wc_get_logger();
		}
		self::$logger->info( $message, array( 'source' => 'variza' ) );
	}
}