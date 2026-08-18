<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Admin_Settings {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_ptero_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ptero_admin_manage_order', array( $this, 'ajax_manage_order' ) );
	}

	public function menu() {
		add_menu_page( 'Pterodactyl Hosting', 'Ptero Hosting', 'manage_options', 'ptero-host', array( $this, 'render_orders_page' ), 'dashicons-server', 58 );
		add_submenu_page( 'ptero-host', 'Orders', 'Orders', 'manage_options', 'ptero-host', array( $this, 'render_orders_page' ) );
		add_submenu_page( 'ptero-host', 'Settings', 'Settings', 'manage_options', 'ptero-host-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'ptero-host', 'Pricing / Plans', 'Pricing / Plans', 'manage_options', 'ptero-host-pricing', array( $this, 'render_pricing_page' ) );
		add_submenu_page( 'ptero-host', 'Coupons', 'Coupons', 'manage_options', 'ptero-host-coupons', array( $this, 'render_coupons_page' ) );
	}

	public function register_settings() {
		register_setting( 'ptero_host_settings', 'ptero_panel_url' );
		register_setting( 'ptero_host_settings', 'ptero_app_api_key' );
		register_setting( 'ptero_host_settings', 'ptero_client_api_key' );
		register_setting( 'ptero_host_settings', 'ptero_currency' );
		register_setting( 'ptero_host_settings', 'ptero_default_nest' );
		register_setting( 'ptero_host_settings', 'ptero_payment_mode' ); // manual | woocommerce
		register_setting( 'ptero_host_settings', 'ptero_manual_instructions' );
		register_setting( 'ptero_host_settings', 'ptero_recaptcha_site_key' );
		register_setting( 'ptero_host_settings', 'ptero_recaptcha_secret_key' );
		register_setting( 'ptero_host_settings', 'ptero_grace_period_days' );
	}

	// -------------------------------------------------------------- Orders
	public function render_orders_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_servers';
		$orders = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100" );
		?>
		<div class="wrap">
			<h1><?php _e( 'Pterodactyl Hosting — Orders', 'ptero-host' ); ?></h1>
			<table class="widefat striped">
				<thead><tr>
					<th>ID</th><th>User</th><th>Server</th><th>Plan</th><th>Location</th>
					<th>IP:Port</th><th>Price</th><th>Status</th><th>Expires</th><th>Actions</th>
				</tr></thead>
				<tbody>
				<?php if ( $orders ) : foreach ( $orders as $o ) :
					$user = get_user_by( 'id', $o->user_id ); ?>
					<tr>
						<td>#<?php echo (int) $o->id; ?></td>
						<td><?php echo esc_html( $user ? $user->user_login : '—' ); ?></td>
						<td><?php echo esc_html( $o->server_name ); ?></td>
						<td><?php echo (int) $o->ram; ?>MB / <?php echo (int) $o->cpu; ?>% / <?php echo (int) $o->disk; ?>MB</td>
						<td><?php echo (int) $o->location_id; ?></td>
						<td><?php echo esc_html( $o->ip_address ? $o->ip_address . ':' . $o->port : '—' ); ?></td>
						<td><?php echo esc_html( $o->price . ' ' . $o->currency ); ?></td>
						<td><span class="ptero-status ptero-status-<?php echo esc_attr( $o->status ); ?>"><?php echo esc_html( ucfirst( $o->status ) ); ?></span></td>
						<td><?php echo esc_html( $o->expires_at ?: '—' ); ?></td>
						<td>
							<?php if ( $o->status === 'pending' ) : ?>
								<button class="button button-primary ptero-order-action" data-id="<?php echo (int) $o->id; ?>" data-action="approve">Approve &amp; Create</button>
							<?php endif; ?>
							<?php if ( $o->status === 'active' ) : ?>
								<button class="button ptero-order-action" data-id="<?php echo (int) $o->id; ?>" data-action="suspend">Suspend</button>
							<?php endif; ?>
							<?php if ( $o->status === 'suspended' ) : ?>
								<button class="button ptero-order-action" data-id="<?php echo (int) $o->id; ?>" data-action="unsuspend">Unsuspend</button>
							<?php endif; ?>
							<button class="button button-link-delete ptero-order-action" data-id="<?php echo (int) $o->id; ?>" data-action="delete">Delete</button>
						</td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="10"><?php _e( 'No orders yet.', 'ptero-host' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<script>
		jQuery(function($){
			$('.ptero-order-action').on('click', function(){
				var btn = $(this), id = btn.data('id'), action = btn.data('action');
				if (action === 'delete' && !confirm('Delete this server permanently?')) return;
				btn.prop('disabled', true).text('Working...');
				$.post(ajaxurl, { action: 'ptero_admin_manage_order', order_id: id, do: action, _wpnonce: '<?php echo wp_create_nonce( "ptero_admin_order" ); ?>' })
					.done(function(res){ location.reload(); })
					.fail(function(xhr){ alert('Error: ' + xhr.responseText); btn.prop('disabled', false); });
			});
		});
		</script>
		<?php
	}

	public function ajax_manage_order() {
		check_ajax_referer( 'ptero_admin_order' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'no', 403 );

		$order_id = intval( $_POST['order_id'] ?? 0 );
		$do       = sanitize_key( $_POST['do'] ?? '' );

		$result = Ptero_Order_Handler::instance()->handle_admin_action( $order_id, $do );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 400 );
		}
		wp_send_json_success();
	}

	// ------------------------------------------------------------- Settings
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'Pterodactyl Hosting — Settings', 'ptero-host' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ptero_host_settings' ); ?>
				<table class="form-table">
					<tr><th>Panel URL</th><td><input type="url" name="ptero_panel_url" class="regular-text" placeholder="https://panel.yourdomain.com" value="<?php echo esc_attr( get_option( 'ptero_panel_url' ) ); ?>"></td></tr>
					<tr><th>Application API Key</th><td><input type="password" name="ptero_app_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_app_api_key' ) ); ?>"> <span class="description">ptla_... — used to create/manage servers server-side.</span></td></tr>
					<tr><th>Client API Key (optional)</th><td><input type="password" name="ptero_client_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_client_api_key' ) ); ?>"> <span class="description">ptlc_... — enables live resource usage &amp; power actions from the client dashboard.</span></td></tr>
					<tr><th>Currency</th><td>
						<select name="ptero_currency">
							<?php foreach ( array( 'PKR', 'USD', 'EUR', 'GBP', 'INR' ) as $c ) : ?>
								<option value="<?php echo esc_attr( $c ); ?>" <?php selected( get_option( 'ptero_currency', 'PKR' ), $c ); ?>><?php echo esc_html( $c ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th>Payment Mode</th><td>
						<select name="ptero_payment_mode">
							<option value="manual" <?php selected( get_option( 'ptero_payment_mode', 'manual' ), 'manual' ); ?>>Manual (bank transfer / Easypaisa / JazzCash — admin approves)</option>
							<option value="woocommerce" <?php selected( get_option( 'ptero_payment_mode' ), 'woocommerce' ); ?>>WooCommerce checkout (auto-provision on paid order)</option>
						</select>
					</td></tr>
					<tr><th>Manual Payment Instructions</th><td><textarea name="ptero_manual_instructions" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'ptero_manual_instructions', "Bank Transfer / Easypaisa / JazzCash\nAccount: 0000-0000-0000\nSend proof to WhatsApp/Discord after ordering." ) ); ?></textarea></td></tr>
					<tr><th>Grace Period (days before auto-suspend)</th><td><input type="number" name="ptero_grace_period_days" value="<?php echo esc_attr( get_option( 'ptero_grace_period_days', 3 ) ); ?>" min="0"></td></tr>
					<tr><th>reCAPTCHA v2 Site Key</th><td><input type="text" name="ptero_recaptcha_site_key" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_recaptcha_site_key' ) ); ?>"></td></tr>
					<tr><th>reCAPTCHA v2 Secret Key</th><td><input type="text" name="ptero_recaptcha_secret_key" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_recaptcha_secret_key' ) ); ?>"></td></tr>

					<tr><th colspan="2"><h2>Billing System</h2></th></tr>
					<tr><th>Auto-show Locations</th><td><label><input type="checkbox" name="ptero_auto_show_locations" value="1" <?php checked( get_option( 'ptero_auto_show_locations', '1' ), '1' ); ?>> Automatically pull &amp; display live locations from the panel on the order form / plans grid</label></td></tr>
					<tr><th>Sync WordPress Users</th><td><label><input type="checkbox" name="ptero_sync_wp_user" value="1" <?php checked( get_option( 'ptero_sync_wp_user', '0' ), '1' ); ?>> When a client registers via <code>[ptero_register]</code>, also create/update a matching WordPress user (same email &amp; password)</label></td></tr>
					<tr><th>Client Dashboard Page URL</th><td><input type="url" name="ptero_dashboard_page_url" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_dashboard_page_url' ) ); ?>"> <span class="description">Where clients land after login/register.</span></td></tr>
					<tr><th>Checkout Page URL</th><td><input type="url" name="ptero_checkout_page_url" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_checkout_page_url' ) ); ?>"> <span class="description">Page containing the <code>[ptero_checkout]</code> shortcode.</span></td></tr>
					<tr><th>Invoice Page URL</th><td><input type="url" name="ptero_invoice_page_url" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_invoice_page_url' ) ); ?>"> <span class="description">Page containing the <code>[ptero_invoices]</code> shortcode — invoice ID is appended as <code>?invoice=ID</code>.</span></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p><button class="button" id="ptero-test-connection">Test Panel Connection</button> <span id="ptero-test-result"></span></p>
		</div>
		<script>
		jQuery(function($){
			$('#ptero-test-connection').on('click', function(){
				$('#ptero-test-result').text('Testing...');
				$.post(ajaxurl, {action:'ptero_test_connection', _wpnonce: '<?php echo wp_create_nonce( "ptero_test_conn" ); ?>'})
					.done(function(r){ $('#ptero-test-result').css('color','green').text('✔ Connected successfully'); })
					.fail(function(x){ $('#ptero-test-result').css('color','red').text('✘ ' + x.responseText); });
			});
		});
		</script>
		<?php
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'ptero_test_conn' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'no', 403 );
		$api = new Ptero_API();
		$res = $api->test_connection();
		if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message(), 400 );
		wp_send_json_success();
	}

	// -------------------------------------------------------------- Pricing
	public function render_pricing_page() {
		if ( isset( $_POST['ptero_save_pricing'] ) && check_admin_referer( 'ptero_pricing_save' ) ) {
			update_option( 'ptero_price_per_ram_mb', floatval( $_POST['price_ram'] ?? 0 ) );
			update_option( 'ptero_price_per_cpu_pct', floatval( $_POST['price_cpu'] ?? 0 ) );
			update_option( 'ptero_price_per_disk_mb', floatval( $_POST['price_disk'] ?? 0 ) );
			update_option( 'ptero_price_dedicated_ip', floatval( $_POST['price_ip'] ?? 0 ) );
			update_option( 'ptero_price_backup', floatval( $_POST['price_backup'] ?? 0 ) );
			update_option( 'ptero_price_database', floatval( $_POST['price_db'] ?? 0 ) );
			update_option( 'ptero_price_base', floatval( $_POST['price_base'] ?? 0 ) );
			echo '<div class="updated"><p>Saved.</p></div>';
		}
		$get = function( $k, $d ) { return esc_attr( get_option( $k, $d ) ); };
		?>
		<div class="wrap">
			<h1><?php _e( 'Pricing (per unit, per month)', 'ptero-host' ); ?></h1>
			<p>The order form calculates live cost as: <code>base + (ram×price) + (cpu×price) + (disk×price) + addons</code>.</p>
			<form method="post">
				<?php wp_nonce_field( 'ptero_pricing_save' ); ?>
				<table class="form-table">
					<tr><th>Base price (server setup)</th><td><input type="number" step="0.01" name="price_base" value="<?php echo $get('ptero_price_base', 0); ?>"></td></tr>
					<tr><th>Price per 1MB RAM</th><td><input type="number" step="0.0001" name="price_ram" value="<?php echo $get('ptero_price_per_ram_mb', 0.05); ?>"></td></tr>
					<tr><th>Price per 1% vCPU</th><td><input type="number" step="0.0001" name="price_cpu" value="<?php echo $get('ptero_price_per_cpu_pct', 0.8); ?>"></td></tr>
					<tr><th>Price per 1MB Disk</th><td><input type="number" step="0.0001" name="price_disk" value="<?php echo $get('ptero_price_per_disk_mb', 0.01); ?>"></td></tr>
					<tr><th>Dedicated IP add-on</th><td><input type="number" step="0.01" name="price_ip" value="<?php echo $get('ptero_price_dedicated_ip', 300); ?>"></td></tr>
					<tr><th>Per backup slot</th><td><input type="number" step="0.01" name="price_backup" value="<?php echo $get('ptero_price_backup', 20); ?>"></td></tr>
					<tr><th>Per database</th><td><input type="number" step="0.01" name="price_db" value="<?php echo $get('ptero_price_database', 20); ?>"></td></tr>
				</table>
				<?php submit_button( 'Save Pricing', 'primary', 'ptero_save_pricing' ); ?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------- Coupons
	public function render_coupons_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'ptero_coupons';

		if ( isset( $_POST['ptero_add_coupon'] ) && check_admin_referer( 'ptero_coupon_save' ) ) {
			$wpdb->insert( $table, array(
				'code'       => sanitize_text_field( strtoupper( $_POST['code'] ?? '' ) ),
				'type'       => sanitize_key( $_POST['type'] ?? 'percent' ),
				'amount'     => floatval( $_POST['amount'] ?? 0 ),
				'max_uses'   => ( isset( $_POST['max_uses'] ) && $_POST['max_uses'] !== '' ) ? intval( $_POST['max_uses'] ) : null,
				'expires_at' => ! empty( $_POST['expires_at'] ) ? sanitize_text_field( $_POST['expires_at'] ) : null,
			) );
			echo '<div class="updated"><p>Coupon added.</p></div>';
		}

		$coupons = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
		?>
		<div class="wrap">
			<h1><?php _e( 'Coupons', 'ptero-host' ); ?></h1>
			<form method="post" style="margin-bottom:20px;">
				<?php wp_nonce_field( 'ptero_coupon_save' ); ?>
				<input type="text" name="code" placeholder="CODE" required>
				<select name="type"><option value="percent">% off</option><option value="fixed">Fixed amount off</option></select>
				<input type="number" step="0.01" name="amount" placeholder="Amount" required>
				<input type="number" name="max_uses" placeholder="Max uses (blank=unlimited)">
				<input type="date" name="expires_at">
				<?php submit_button( 'Add Coupon', 'secondary', 'ptero_add_coupon', false ); ?>
			</form>
			<table class="widefat striped">
				<thead><tr><th>Code</th><th>Type</th><th>Amount</th><th>Used</th><th>Max</th><th>Expires</th></tr></thead>
				<tbody>
				<?php foreach ( $coupons as $c ) : ?>
					<tr><td><?php echo esc_html( $c->code ); ?></td><td><?php echo esc_html( $c->type ); ?></td><td><?php echo esc_html( $c->amount ); ?></td><td><?php echo esc_html( $c->used ); ?></td><td><?php echo esc_html( $c->max_uses ?: '∞' ); ?></td><td><?php echo esc_html( $c->expires_at ?: '—' ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
