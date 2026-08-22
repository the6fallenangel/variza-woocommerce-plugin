<?php

defined( 'ABSPATH' ) || exit;

class Variza_Webhook {

	public static function handle( $request ) {
		$gateway = self::gateway();
		if ( ! $gateway ) {
			return self::response( 500, 'gateway_not_found' );
		}

		$secret = $gateway->get_option( 'webhook_secret' );
		if ( empty( $secret ) ) {
			Variza_Gateway::log( 'Webhook received but webhook_secret is not configured.' );
			return self::response( 500, 'not_configured' );
		}

		$body      = (string) $request->get_body();
		$signature = (string) $request->get_header( 'X-Webhook-Signature' );

		if ( ! self::verify_signature( $body, $signature, $secret ) ) {
			Variza_Gateway::log( 'Webhook signature verification failed.' );
			return self::response( 400, 'invalid_signature' );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			Variza_Gateway::log( 'Webhook body is not valid JSON.' );
			return self::response( 400, 'invalid_json' );
		}

		$event = isset( $data['event'] ) ? (string) $data['event'] : '';
		if ( 'payment.paid' !== $event ) {
			return self::response( 200, 'ignored' );
		}

		$slug = isset( $data['slug'] ) ? (string) $data['slug'] : '';
		if ( '' === $slug ) {
			Variza_Gateway::log( 'Webhook received without a payment link slug.' );
			return self::response( 400, 'missing_slug' );
		}

		$attempt_code = isset( $data['attempt_code'] ) ? (string) $data['attempt_code'] : '';
		$amount       = isset( $data['amount'] ) ? (int) $data['amount'] : 0;

		$order = self::find_order_by_slug( $slug );
		if ( ! $order ) {
			Variza_Gateway::log( 'Webhook received for unknown payment link slug: ' . $slug );
			return self::response( 404, 'order_not_found' );
		}

		if ( ! $order->needs_payment() ) {
			return self::response( 200, 'already_processed' );
		}

		$order->update_meta_data( Variza_Plugin::META_ATTEMPT_CODE, $attempt_code );

		$note = sprintf(
			__( 'پرداخت توسط واریزا تأیید شد: %1$s تومان (کد پیگیری %2$s).', 'variza-for-woocommerce' ),
			number_format_i18n( $amount ),
			$attempt_code
		);

		$order->add_order_note( $note, false );
		$order->set_transaction_id( $attempt_code );
		$order->payment_complete( $attempt_code );

		Variza_Gateway::log( sprintf( 'Order %d marked paid via Variza webhook (slug %s).', $order->get_id(), $slug ) );

		return self::response( 200, 'ok' );
	}

	private static function find_order_by_slug( $slug ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_key'   => Variza_Plugin::META_SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $orders ? $orders[0] : null;
	}

	private static function verify_signature( $body, $signature, $secret ) {
		$provided = $signature;

		if ( 0 === strpos( $provided, 'sha256=' ) ) {
			$provided = substr( $provided, 7 );
		}

		$expected = hash_hmac( 'sha256', $body, $secret );
		return hash_equals( $expected, $provided );
	}

	private static function gateway() {
		if ( ! class_exists( 'Variza_Gateway' ) ) {
			return null;
		}
		return new Variza_Gateway();
	}

	private static function response( $code, $message ) {
		return new WP_REST_Response( array( 'status' => $message ), $code );
	}
}