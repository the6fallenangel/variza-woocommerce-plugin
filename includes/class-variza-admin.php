<?php

defined( 'ABSPATH' ) || exit;

class Variza_Admin {

	public const OPTION = 'woocommerce_variza_settings';

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'variza-for-woocommerce' ) );
		}

		$settings   = self::settings();
		$enabled    = 'yes' === ( $settings['enabled'] ?? 'no' );
		$has_token  = ! empty( $settings['api_token'] ?? '' );
		$has_secret = ! empty( $settings['webhook_secret'] ?? '' );
		$unit       = $settings['currency_unit'] ?? 'toman';
		$gateway    = new Variza_Gateway();
		$webhook    = $gateway->webhook_url();
		$saved      = isset( $_GET['settings-updated'] ) && '1' === $_GET['settings-updated']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap variza-admin" dir="rtl">';
		echo '<div class="variza-admin__header">';
		echo '<a href="https://variza.ir" target="_blank" rel="noopener" class="variza-admin__brand variza-admin__brand--link"><span class="variza-admin__logo"><img src="' . esc_url( VARIZA_ICON_URL ) . '" alt="واریزا" width="32" height="32" /></span><span><span class="variza-admin__title">واریزا</span><span class="variza-admin__subtitle">درگاه کارت‌به‌کارت با تأیید خودکار برای ووکامرس</span></span></a>';
		echo '<div class="variza-admin__badge ' . ( $enabled ? 'is-on' : 'is-off' ) . '">' . ( $enabled ? 'فعال' : 'غیرفعال' ) . '</div>';
		echo '</div>';

		if ( $saved ) {
			echo '<div class="variza-admin__notice">✓ تنظیمات با موفقیت ذخیره شد.</div>';
		}

		self::render_steps( ! $has_token && ! $has_secret );

		echo '<div class="variza-admin__grid">';
		echo '<div class="variza-admin__col variza-admin__col--main">';
		self::render_settings_form( $enabled, $unit, $settings );
		self::render_faq();
		echo '</div>';

		echo '<div class="variza-admin__col variza-admin__col--side">';
		self::render_webhook_card( $webhook, $has_secret );
		self::render_status_card( $enabled, $has_token, $has_secret );
		self::render_guide_links();
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	private static function render_steps( $open_by_default = false ) {
		$is_open  = (bool) $open_by_default;
		$classes  = 'variza-admin__card variza-admin__steps' . ( $is_open ? ' is-open' : ' is-collapsed' );
		$expanded = $is_open ? 'true' : 'false';

		echo '<div class="' . esc_attr( $classes ) . '" id="variza-steps">';
		echo '<button type="button" class="variza-admin__steps-toggle" aria-expanded="' . esc_attr( $expanded ) . '" aria-controls="variza-steps-body">';
		echo '<span class="variza-admin__steps-title">🚀 راه‌اندازی در ۴ گام ساده</span>';
		echo '<span class="variza-admin__steps-chevron" aria-hidden="true">‹</span>';
		echo '</button>';
		echo '<div class="variza-admin__steps-body" id="variza-steps-body"' . ( $is_open ? '' : ' hidden' ) . '>';
		echo '<div class="variza-admin__steps-list">';
		self::step( 1, 'حساب واریزا بسازید', 'در <a href="https://variza.ir" target="_blank" rel="noopener">variza.ir</a> ثبت‌نام کنید؛ پلن رایگان ۷ روزه با ۱۰ اعتبار به‌صورت خودکار برای شما فعال می‌شود.' );
		self::step( 2, 'کلیدها را بگیرید', 'از پنل واریزا (پروفایل ← کلید API و وب‌هوک) کلید API و کلید امضای تأیید خودکار را کپی کنید و در فرم روبه‌رو وارد و ذخیره کنید.' );
		self::step( 3, 'آدرس وب‌هوک را ثبت کنید', 'آدرس وب‌هوک این صفحه را کپی و در پنل واریزا (پروفایل ← وب‌هوک) در فیلد «آدرس وب‌هوک» قرار دهید — بدون این مرحله، تأیید خودکار کار نمی‌کند.' );
		self::step( 4, 'فعال و تست کنید', 'درگاه را فعال کنید، یک سفارش آزمایشی با مبلغ کم ثبت کنید و پس از واریز، ببینید سفارش بدون هیچ بررسی دستی خودکار «پرداخت‌شده» می‌شود.' );
		echo '</div></div></div>';
	}

	private static function step( $no, $title, $body ) {
		echo '<div class="variza-admin__step">';
		echo '<span class="variza-admin__step-no">' . esc_html( $no ) . '</span>';
		echo '<div><h3>' . esc_html( $title ) . '</h3><p>' . wp_kses_post( $body ) . '</p></div>';
		echo '</div>';
	}

	private static function render_settings_form( $enabled, $unit, $settings ) {
		$token  = $settings['api_token'] ?? '';
		$secret = $settings['webhook_secret'] ?? '';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="variza-admin__card variza-admin__form" data-enhanced="true">';
		echo '<input type="hidden" name="action" value="variza_save_settings" />';
		wp_nonce_field( 'variza_settings_nonce' );
		echo '<h2>تنظیمات درگاه تأیید خودکار</h2>';

		echo '<label class="variza-admin__switch">';
		echo '<input type="checkbox" name="variza_enabled" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo '<span class="variza-admin__switch-track"></span>';
		echo '<span class="variza-admin__switch-label">فعال‌سازی درگاه واریزا</span>';
		echo '</label>';

		echo '<div class="variza-admin__field">';
		echo '<label for="variza_api_token">کلید API</label>';
		echo '<input type="password" id="variza_api_token" name="variza_api_token" value="' . esc_attr( $token ) . '" autocomplete="off" placeholder="کلید API پنل واریزا" />';
		echo '<p class="variza-admin__hint">از پنل واریزا (پروفایل ← کلید API) کپی کنید.</p>';
		echo '</div>';

		echo '<div class="variza-admin__field">';
		echo '<label for="variza_webhook_secret">کلید امضای وب‌هوک</label>';
		echo '<input type="password" id="variza_webhook_secret" name="variza_webhook_secret" value="' . esc_attr( $secret ) . '" autocomplete="off" placeholder="کلید امضای تأیید خودکار" />';
		echo '<p class="variza-admin__hint">از پنل واریزا (پروفایل ← وب‌هوک) کپی کنید — برای تأیید خودکار امن استفاده می‌شود.</p>';
		echo '</div>';

		echo '<div class="variza-admin__field">';
		echo '<span class="variza-admin__label">واحد پول فروشگاه</span>';
		echo '<div class="variza-admin__seg">';
		echo '<input type="radio" id="unit_toman" name="variza_currency_unit" value="toman" ' . checked( $unit, 'toman', false ) . ' />';
		echo '<label for="unit_toman">تومان</label>';
		echo '<input type="radio" id="unit_rial" name="variza_currency_unit" value="rial" ' . checked( $unit, 'rial', false ) . ' />';
		echo '<label for="unit_rial">ریال</label>';
		echo '</div>';
		echo '<p class="variza-admin__hint">واریزا فقط تومان می‌پذیرد؛ ریال خودکار به تومان تبدیل می‌شود (÷۱۰).</p>';
		echo '</div>';

		submit_button( 'ذخیره', 'primary', 'submit', true );
		echo '</form>';
	}

	private static function render_webhook_card( $webhook, $has_secret ) {
		echo '<div class="variza-admin__card variza-admin__webhook">';
		echo '<h2>آدرس وب‌هوک تأیید خودکار</h2>';
		echo '<p class="variza-admin__hint">این آدرس را در پنل واریزا (پروفایل ← وب‌هوک) ثبت کنید تا تأیید خودکار فعال شود.</p>';
		echo '<div class="variza-admin__copy">';
		echo '<input type="text" id="variza_webhook_url" readonly value="' . esc_attr( $webhook ) . '" />';
		echo '<button type="button" class="button" data-copy="#variza_webhook_url">کپی</button>';
		echo '</div>';
		echo '<p class="variza-admin__hint" style="margin-bottom:0">' . ( $has_secret ? 'تأیید خودکار آماده است.' : 'برای فعال‌سازی تأیید خودکار، کلید امضا را ثبت کنید.' ) . '</p>';
		echo '</div>';
	}

	private static function render_status_card( $enabled, $has_token, $has_secret ) {
		echo '<div class="variza-admin__card variza-admin__status">';
		echo '<h2>وضعیت اتصال</h2>';
		echo '<ul>';
		self::status_item( 'درگاه فعال است', $enabled );
		self::status_item( 'کلید API ثبت شده', $has_token );
		self::status_item( 'کلید امضا ثبت شده', $has_secret );
		echo '</ul>';
		echo '</div>';
	}

	private static function status_item( $label, $ok ) {
		echo '<li class="' . ( $ok ? 'is-ok' : 'is-no' ) . '"><span class="variza-admin__dot"></span>' . esc_html( $label ) . '</li>';
	}

	private static function faq( $q, $a ) {
		echo '<details class="variza-admin__faq-item"><summary>' . esc_html( $q ) . '</summary><p>' . wp_kses_post( $a ) . '</p></details>';
	}

	private static function render_faq() {
		echo '<div class="variza-admin__card variza-admin__faq">';
		echo '<h2>سوالات متداول</h2>';
		self::faq( 'واریزا چطور پرداخت را تأیید می‌کند؟', 'واریزا پیامک بانکی واریز را روی گوشی متصل می‌خواند و در چند ثانیه، بدون دخالت انسانی، پرداخت را با سفارش تطبیق می‌دهد و وب‌هوک تأیید را ارسال می‌کند.' );
		self::faq( 'بدون نصب اپلیکیشن روی گوشی هم کار می‌کند؟', 'لینک پرداخت بدون آن هم ساخته می‌شود، اما تأیید <b>خودکار</b> فقط زمانی فعال است که پیامک بانکی از طریق اپلیکیشن اندروید یا شورت‌کات آیفون به واریزا برسد.' );
		self::faq( 'اعتبار حساب واریزا چیست؟', 'به ازای هر پرداخت که خودکار تأیید می‌شود، یک واحد از اعتبار حساب شما در واریزا کم می‌شود؛ صرفاً ساختن لینک بدون پرداخت، اعتباری مصرف نمی‌کند.' );
		self::faq( 'چرا سفارش با وجود واریز، خودکار تأیید نشد؟', 'معمولاً یعنی آدرس وب‌هوک در پنل واریزا ثبت نشده یا گوشی متصل پیامک را دریافت نکرده. وضعیت اتصال کنار همین صفحه و لاگ «واریزا» در «ووکامرس ← وضعیت ← لاگ‌ها» را بررسی کنید.' );
		echo '<div class="variza-admin__faq-more"><a href="https://variza.ir/faq" target="_blank" rel="noopener">مشاهده همه سوالات متداول ←</a></div>';
		echo '</div>';
	}

	private static function render_guide_links() {
		echo '<div class="variza-admin__card variza-admin__guides">';
		echo '<h2>راهنمای اتصال گوشی</h2>';
		echo '<p class="variza-admin__hint">تأیید خودکار با خواندن پیامک واریز روی گوشی فروشنده انجام می‌شود.</p>';
		echo '<div class="variza-admin__guide-grid">';
		self::guide_link( 'https://variza.ir/blog/android-app-tutorial', 'اندروید', 'نصب اپلیکیشن' );
		self::guide_link( 'https://variza.ir/blog/iphone-shortcut-tutorial', 'آیفون', 'نصب شورت‌کات' );
		echo '</div>';
		echo '</div>';
	}

	private static function guide_link( $url, $title, $sub ) {
		echo '<a class="variza-admin__guide" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">';
		echo '<b>' . esc_html( $title ) . '</b>';
		echo '<span>' . esc_html( $sub ) . '</span>';
		echo '</a>';
	}

	private static function settings() {
		$defaults = array(
			'enabled'        => 'no',
			'title'          => 'کارت‌به‌کارت خودکار',
			'description'    => '',
			'api_token'      => '',
			'webhook_secret' => '',
			'currency_unit'  => 'toman',
		);

		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( $defaults, $saved );
	}

	public static function save() {
		check_admin_referer( 'variza_settings_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'variza-for-woocommerce' ) );
		}

		$settings                   = self::settings();
		$settings['enabled']        = isset( $_POST['variza_enabled'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['api_token']      = isset( $_POST['variza_api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['variza_api_token'] ) ) : '';
		$settings['webhook_secret'] = isset( $_POST['variza_webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['variza_webhook_secret'] ) ) : '';

		$unit                      = isset( $_POST['variza_currency_unit'] ) ? sanitize_key( $_POST['variza_currency_unit'] ) : 'toman';
		$settings['currency_unit'] = in_array( $unit, array( 'toman', 'rial' ), true ) ? $unit : 'toman';

		update_option( self::OPTION, $settings );

		wp_safe_redirect( add_query_arg( 'settings-updated', '1', admin_url( 'admin.php?page=variza' ) ) );
		exit;
	}

	/**
	 * Returns the settings-page CSS as a string, for use with wp_add_inline_style().
	 * Uses the system/Tahoma fallback stack only — no remote font is loaded.
	 */
	public static function css() {
		return '
		.wrap.variza-admin{max-width:none !important;width:100% !important;margin:16px 0 0 0 !important;padding-left:20px;box-sizing:border-box;font-family:Tahoma,Arial,sans-serif;}
		.variza-admin .notice, .variza-admin .updated, .variza-admin .error, .variza-admin .is-dismissible, .variza-admin + .notice, .variza-admin + .updated{ display:none !important; }
		.variza-admin__form[data-enhanced] .notice, .variza-admin__form[data-enhanced] .updated, .variza-admin__form[data-enhanced] .error { display:none !important; }
		.variza-admin *{box-sizing:border-box;font-family:inherit;}
		.variza-admin h1{margin:0;font-size:22px;color:#fff;}
		.variza-admin h2{font-size:15px;margin:0 0 14px;color:#0a1b15;font-weight:800;}
		.variza-admin p{line-height:1.9;color:#3c4a46;}
		.variza-admin a{color:#059669;}
		.variza-admin__header{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:22px 24px;border-radius:18px;background:linear-gradient(120deg,#0a1b15 0%,#0b3d2e 55%,#0f6b4b 100%);color:#fff;box-shadow:0 10px 30px rgba(10,27,21,.25);overflow:hidden;}
		.variza-admin__header::before{content:"";position:absolute;inset:0;background:radial-gradient(600px circle at 85% -20%,rgba(255,255,255,.16),transparent 55%);pointer-events:none;}
		.variza-admin__header .variza-admin__brand{color:#fff;}
		.variza-admin__header .variza-admin__brand:hover{color:#fff;}
		.variza-admin__brand{display:flex;align-items:center;gap:14px;text-decoration:none;}
		.variza-admin__brand--link:hover{opacity:.9;}
		.variza-admin__title{font-size:22px;font-weight:800;color:#fff;display:block;}
		.variza-admin__subtitle{margin:4px 0 0;color:#b5e6d3;font-size:13px;display:block;}
		.variza-admin__header-actions{display:flex;align-items:center;gap:12px;}
		.variza-admin__header-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:#b5e6d3;text-decoration:none;}
		.variza-admin__header-link:hover{color:#fff;}
		.variza-admin__logo{width:32px;height:32px;border-radius:9px;display:block;flex:0 0 32px;background:#fff;padding:4px;box-shadow:0 2px 10px rgba(16,185,129,.35);overflow:hidden;}
		.variza-admin__logo img{width:100%;height:100%;object-fit:contain;display:block;}
		.variza-admin__badge{padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;}
		.variza-admin__badge.is-on{background:#10b981;color:#052e1a;}
		.variza-admin__badge.is-off{background:#7f1d1d;color:#fecaca;}
		.variza-admin__notice{margin:16px 0 0;padding:12px 16px;border-radius:12px;background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;font-weight:600;}
		.variza-admin{background:linear-gradient(180deg,#f4fbf8 0%,#f7f8fa 220px);}
		.variza-admin__card{background:rgba(255,255,255,.9);backdrop-filter:saturate(160%) blur(6px);border:1px solid #e5e7eb;border-radius:16px;padding:20px 22px;margin-top:16px;box-shadow:0 2px 14px rgba(6,60,40,.05);}
		.variza-admin__grid{display:grid;grid-template-columns:1fr 500px;gap:16px;align-items:start;}
		.variza-admin__col{min-width:0;}
		.variza-admin__steps-toggle{width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;background:none;border:none;padding:0;cursor:pointer;text-align:right;}
		.variza-admin__steps-title{font-size:15px;font-weight:800;color:#0a1b15;}
		.variza-admin__steps-chevron{width:28px;height:28px;border-radius:999px;background:#f3f4f6;color:#6b7280;display:flex;align-items:center;justify-content:center;font-size:18px;transform:rotate(-90deg);transition:.2s;}
		.variza-admin__steps.is-open .variza-admin__steps-chevron{transform:rotate(90deg);background:#ecfdf5;color:#059669;}
		.variza-admin__steps-body{margin-top:14px;}
		.variza-admin__step{display:flex;gap:14px;padding:12px 0;border-bottom:1px dashed #e5e7eb;}
		.variza-admin__step:last-child{border-bottom:none;}
		.variza-admin__step-no{flex:0 0 32px;height:32px;border-radius:50%;background:#10b981;color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;font-size:14px;}
		.variza-admin__step h3{margin:0 0 4px;font-size:13px;color:#0a1b15;}
		.variza-admin__step p{margin:0;font-size:12px;}
		.variza-admin__field{margin:14px 0;}
		.variza-admin__field label,.variza-admin__label{display:block;font-weight:700;font-size:13px;color:#1f2937;margin-bottom:6px;}
		.variza-admin__field input[type=password],.variza-admin__copy input[type=text]{width:100%;padding:11px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;direction:ltr;text-align:left;background:#f9fafb;}
		.variza-admin__field input[type=password]:focus,.variza-admin__copy input[type=text]:focus{border-color:#10b981;outline:none;background:#fff;box-shadow:0 0 0 3px rgba(16,185,129,.15);}
		.variza-admin__hint{font-size:12px;color:#6b7280;margin:6px 0 0;}
		.variza-admin__hint b{color:#065f46;}
		.variza-admin__switch{position:relative;display:flex;align-items:center;gap:10px;cursor:pointer;padding:12px 14px;border-radius:12px;background:#f9fafb;border:1px solid #e5e7eb;margin-bottom:6px;}
		.variza-admin__switch input{position:absolute;opacity:0;}
		.variza-admin__switch-track{width:46px;height:26px;border-radius:999px;background:#d1d5db;transition:.2s;position:relative;flex:0 0 46px;}
		.variza-admin__switch-track::after{content:"";position:absolute;top:3px;right:3px;width:20px;height:20px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2);}
		.variza-admin__switch input:checked + .variza-admin__switch-track{background:#10b981;}
		.variza-admin__switch input:checked + .variza-admin__switch-track::after{transform:translateX(-20px);}
		.variza-admin__switch-label{font-weight:700;font-size:14px;color:#0a1b15;}
		.variza-admin__seg{display:flex;gap:8px;}
		.variza-admin__seg input{position:absolute;opacity:0;}
		.variza-admin__seg label{flex:1;text-align:center;padding:11px;border:2px solid #e5e7eb;border-radius:12px;cursor:pointer;font-weight:700;color:#6b7280;background:#f9fafb;margin:0;}
		.variza-admin__seg input:checked + label{border-color:#10b981;color:#065f46;background:#ecfdf5;}
		.variza-admin__form .button-primary{background:#10b981;border-color:#10b981;}
		.variza-admin__form .button-primary:hover{background:#059669;border-color:#059669;}
		.variza-admin__copy{display:flex;gap:8px;}
		.variza-admin__copy input{font-family:monospace;font-size:13px;}
		.variza-admin__copy .button{white-space:nowrap;border-radius:10px;}
		.variza-admin__external-link{margin-top:12px;display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:#059669;text-decoration:none;}
		.variza-admin__external-link:hover{color:#047857;}
		.variza-admin__external-icon{font-size:14px;line-height:1;}
		.variza-admin__status ul{list-style:none;margin:0;padding:0;}
		.variza-admin__status li{display:flex;align-items:center;gap:10px;padding:8px 0;font-size:13px;color:#374151;}
		.variza-admin__dot{width:10px;height:10px;border-radius:50%;background:#d1d5db;flex:0 0 10px;}
		.variza-admin__status li.is-ok .variza-admin__dot{background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.2);}
		.variza-admin__status li.is-no .variza-admin__dot{background:#9ca3af;}
		.variza-admin__faq-item{border-bottom:1px dashed #e5e7eb;padding:12px 0;}
		.variza-admin__faq-item:last-of-type{border-bottom:none;}
		.variza-admin__faq-item summary{cursor:pointer;font-weight:700;font-size:13px;color:#0a1b15;list-style:none;display:flex;align-items:center;gap:8px;}
		.variza-admin__faq-item summary::-webkit-details-marker{display:none;}
		.variza-admin__faq-item summary::before{content:"+";flex:0 0 18px;height:18px;border-radius:50%;background:#ecfdf5;color:#059669;font-weight:800;font-size:13px;display:flex;align-items:center;justify-content:center;}
		.variza-admin__faq-item[open] summary::before{content:"−";}
		.variza-admin__faq-item p{margin:8px 0 0 26px;font-size:13px;}
		.variza-admin__faq-more{margin-top:14px;text-align:center;}
		.variza-admin__faq-more a{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:999px;background:#f9fafb;border:1px solid #e5e7eb;color:#059669;font-weight:700;font-size:13px;text-decoration:none;}
		.variza-admin__faq-more a:hover{background:#ecfdf5;border-color:#10b981;}
		.variza-admin__guides-bullets{list-style:none;margin:8px 0 0;padding:0;}
		.variza-admin__guides-bullets li{position:relative;padding-right:16px;font-size:12px;line-height:1.9;color:#374151;margin:6px 0;}
		.variza-admin__guides-bullets li::before{content:"";position:absolute;right:0;top:9px;width:6px;height:6px;border-radius:50%;background:#10b981;}
		.variza-admin__guide-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;}
		.variza-admin__guide{display:flex;flex-direction:column;gap:4px;padding:16px;border-radius:14px;background:#f9fafb;border:1px solid #e5e7eb;text-decoration:none;transition:.15s;}
		.variza-admin__guide:hover{border-color:#10b981;box-shadow:0 4px 14px rgba(16,185,129,.12);}
		.variza-admin__guide b{font-size:13px;color:#0a1b15;}
		.variza-admin__guide span{font-size:12px;color:#6b7280;}
		@media (max-width: 1100px){
			.variza-admin__grid{grid-template-columns:1fr;}
			.variza-admin__guide-grid{grid-template-columns:1fr 1fr;}
		}
		@media (max-width: 782px){
			.wrap.variza-admin{padding-left:10px !important;padding-right:10px;}
			.variza-admin__header{padding:18px;}
			.variza-admin__guide-grid{grid-template-columns:1fr;}
		}
		';
	}

	/**
	 * Returns the settings-page JS as a string, for use with wp_add_inline_script().
	 */
	public static function js() {
		return '
		(function () {
			var card = document.getElementById("variza-steps");
			var btn = card ? card.querySelector(".variza-admin__steps-toggle") : null;
			var body = document.getElementById("variza-steps-body");
			if (!card || !btn || !body) return;
			btn.addEventListener("click", function () {
				var open = card.classList.toggle("is-open");
				btn.setAttribute("aria-expanded", open ? "true" : "false");
				body.hidden = !open;
			});
			var copyBtn = document.querySelector(".variza-admin__copy button");
			if (copyBtn) {
				copyBtn.addEventListener("click", function () {
					var el = document.querySelector(copyBtn.getAttribute("data-copy"));
					if (!el) return;
					var done = function (ok) {
						copyBtn.textContent = ok ? "کپی شد" : "کپی";
						window.setTimeout(function () { copyBtn.textContent = "کپی"; }, 1500);
					};
					if (navigator.clipboard && window.isSecureContext) {
						navigator.clipboard.writeText(el.value).then(function(){ done(true); }, function(){ done(false); });
					} else {
						el.focus(); el.select();
						try { done(document.execCommand("copy")); } catch (e) { done(false); }
					}
				});
			}
		})();
		';
	}
}
