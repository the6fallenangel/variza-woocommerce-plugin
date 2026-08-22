<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! class_exists( 'Variza_Blocks' ) ) {
	class Variza_Blocks extends AbstractPaymentMethodType {

		protected $name = 'variza';

		public function initialize() {
			$this->settings = get_option( 'woocommerce_variza_settings', array() );
		}

		public function is_active() {
			return isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
		}

		public function get_payment_method_script_handles() {
			wp_register_script(
				'variza-blocks',
				VARIZA_PLUGIN_URL . 'assets/js/variza-blocks.js',
				array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
				VARIZA_VERSION,
				true
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'variza-blocks', 'variza-for-woocommerce' );
			}

			return array( 'variza-blocks' );
		}

		public function get_payment_method_data() {
			$title = $this->get_setting( 'title' );
			if ( empty( $title ) || preg_match( '/^card-to-card/i', $title ) || preg_match( '/^variza\s*\(/i', $title ) ) {
				$title = 'واریزا';
			}

			return array(
				'title'       => $title,
				'description' => $this->get_setting( 'description' ),
				'supports'    => $this->get_supported_features(),
				'icon'        => VARIZA_ICON_URL,
			);
		}
	}
}