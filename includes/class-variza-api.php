<?php

defined( 'ABSPATH' ) || exit;

class Variza_API_Client {

	private $token;

	public function __construct( $token ) {
		$this->token = $token;
	}

	/**
	 * @param string|null $card_last_4 4 digits of the destination card or 'random' for least-load selection.
	 * @return array{slug:string,pay_url:string,amount:int,return_url:string,expires_at:string|null}|WP_Error
	 */
	public function create_payment_link( $amount, $return_url, $title = '', $card_last_4 = null ) {
		$base_url = trailingslashit( apply_filters( 'variza_api_base_url', VARIZA_API_BASE_URL ) );
		$body     = array(
			'amount'     => (int) $amount,
			'return_url' => $return_url,
		);

		if ( '' !== $title ) {
			$body['title'] = mb_substr( $title, 0, 255 );
		}

		if ( null !== $card_last_4 && '' !== $card_last_4 ) {
			$body['card_last_4'] = $card_last_4;
		}

		$expires = apply_filters( 'variza_link_expires_in', '1h' );
		if ( is_string( $expires ) && '' !== $expires ) {
			$body['expires_in'] = $expires;
		}

		$response = wp_remote_post(
			$base_url . 'pay',
			array(
				'timeout' => apply_filters( 'variza_api_timeout', 30 ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 201 !== $status ) {
			$message = isset( $data['message'] ) ? (string) $data['message'] : '';
			return new WP_Error( 'variza_api_error', $message ? $message : sprintf( 'خطای سرور (%d)', $status ), $data );
		}

		if ( ! is_array( $data ) || empty( $data['pay_url'] ) ) {
			return new WP_Error( 'variza_invalid_response', 'پاسخ نامعتبر از سرور واریزا.' );
		}

		return array(
			'slug'       => isset( $data['slug'] ) ? (string) $data['slug'] : '',
			'pay_url'    => (string) $data['pay_url'],
			'amount'     => isset( $data['amount'] ) ? (int) $data['amount'] : (int) $amount,
			'return_url' => isset( $data['return_url'] ) ? (string) $data['return_url'] : $return_url,
			'expires_at' => isset( $data['expires_at'] ) ? (string) $data['expires_at'] : null,
		);
	}
}
