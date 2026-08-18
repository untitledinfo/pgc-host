<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Invoices, transactions, wallet balance, and payment gateway routing
 * (manual / wallet / WooCommerce / Stripe-Paypal placeholders — enable
 * whichever you have keys for under Ptero Hosting → Gateways).
 */
class Ptero_Billing {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 21 );
		add_shortcode( 'ptero_invoices', array( $this, 'render_invoices' ) );
		add_shortcode( 'ptero_add_funds', array( $this, 'render_add_funds' ) );

		add_action( 'wp_ajax_ptero_pay_invoice', array( $this, 'ajax_pay_invoice' ) );
		add_action( 'wp_ajax_ptero_add_funds', array( $this, 'ajax_add_funds' ) );
		add_action( 'admin_post_ptero_admin_mark_paid', array( $this, 'admin_mark_paid' ) );
		add_action( 'admin_post_ptero_save_gateways', array( $this, 'save_gateways' ) );
	}

	public function menu() {
		add_submenu_page( 'ptero-host', 'Invoices', 'Invoices', 'manage_options', 'ptero-host-invoices', array( $this, 'render_admin_invoices' ) );
		add_submenu_page( 'ptero-host', 'Clients', 'Clients', 'manage_options', 'ptero-host-clients', array( $this, 'render_admin_clients' ) );
		add_submenu_page( 'ptero-host', 'Gateways', 'Gateways', 'manage_options', 'ptero-host-gateways', array( $this, 'render_admin_gateways' ) );
	}

	private function invoices_table() { global $wpdb; return $wpdb->prefix . 'ptero_invoices'; }
	private function items_table()    { global $wpdb; return $wpdb->prefix . 'ptero_invoice_items'; }
	private function tx_table()       { global $wpdb; return $wpdb->prefix . 'ptero_transactions'; }
	private function clients_table()  { global $wpdb; return $wpdb->prefix . 'ptero_clients'; }

	// -------------------------------------------------------------- Create

	public static function create_invoice_from_cart( $client, $items, $cart ) {
		global $wpdb;
		$self = self::instance();

		$subtotal = 0;
		foreach ( $items as $item ) $subtotal += $cart->line_total( $item );

		$wpdb->insert( $self->invoices_table(), array(
			'client_id'      => $client->id,
			'invoice_number' => 'INV-' . strtoupper( wp_generate_password( 8, false ) ),
			'subtotal'       => $subtotal,
			'total'          => $subtotal,
			'currency'       => $items[0]->currency ?? get_option( 'ptero_currency', 'PKR' ),
			'status'         => 'unpaid',
			'due_at'         => date( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ),
		) );
		$invoice_id = $wpdb->insert_id;

		foreach ( $items as $item ) {
			$wpdb->insert( $self->items_table(), array(
				'invoice_id'    => $invoice_id,
				'plan_id'       => $item->plan_id,
				'description'   => $item->plan_name . ' — ' . $item->server_name . ' (' . $item->billing_cycle . ')',
				'billing_cycle' => $item->billing_cycle,
				'quantity'      => $item->quantity,
				'unit_price'    => $cart->line_total( $item ) / max( 1, $item->quantity ),
				'total'         => $cart->line_total( $item ),
			) );
		}

		return $invoice_id;
	}

	public function get_invoice( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->invoices_table()} WHERE id = %d", $id ) );
	}

	public function get_invoice_items( $id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->items_table()} WHERE invoice_id = %d", $id ) );
	}

	public function get_client_invoices( $client_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->invoices_table()} WHERE client_id = %d ORDER BY created_at DESC", $client_id ) );
	}

	// -------------------------------------------------------------- Pay

	public function mark_paid( $invoice_id, $gateway = 'manual', $ref = '' ) {
		global $wpdb;
		$invoice = $this->get_invoice( $invoice_id );
		if ( ! $invoice || $invoice->status === 'paid' ) return false;

		$wpdb->update( $this->invoices_table(), array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ), 'gateway' => $gateway ), array( 'id' => $invoice_id ) );
		$wpdb->insert( $this->tx_table(), array(
			'client_id'   => $invoice->client_id,
			'invoice_id'  => $invoice_id,
			'type'        => 'payment',
			'gateway'     => $gateway,
			'gateway_ref' => $ref,
			'amount'      => $invoice->total,
			'currency'    => $invoice->currency,
			'status'      => 'completed',
		) );

		do_action( 'ptero_invoice_paid', $invoice_id, $invoice );
		return true;
	}

	public function ajax_pay_invoice() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		$client = Ptero_Client_Auth::instance()->current_client();
		if ( ! $client ) wp_send_json_error( array( 'message' => __( 'Please log in.', 'ptero-host' ) ) );

		$invoice_id = (int) ( $_POST['invoice_id'] ?? 0 );
		$invoice = $this->get_invoice( $invoice_id );
		if ( ! $invoice || (int) $invoice->client_id !== (int) $client->id ) {
			wp_send_json_error( array( 'message' => __( 'Invoice not found.', 'ptero-host' ) ) );
		}
		if ( $invoice->status === 'paid' ) wp_send_json_error( array( 'message' => __( 'Already paid.', 'ptero-host' ) ) );

		$method = sanitize_text_field( $_POST['method'] ?? 'wallet' );

		if ( $method === 'wallet' ) {
			global $wpdb;
			if ( (float) $client->balance < (float) $invoice->total ) {
				wp_send_json_error( array( 'message' => __( 'Insufficient wallet balance. Please add funds.', 'ptero-host' ) ) );
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$this->clients_table()} SET balance = balance - %f WHERE id = %d", $invoice->total, $client->id ) );
			$this->mark_paid( $invoice_id, 'wallet' );
			wp_send_json_success( array( 'message' => __( 'Paid from wallet balance!', 'ptero-host' ) ) );
		}

		if ( get_option( 'ptero_gateway_manual_enabled', '1' ) === '1' ) {
			wp_send_json_success( array(
				'message'      => __( 'Manual payment selected. An admin will confirm payment and activate your service.', 'ptero-host' ),
				'instructions' => wpautop( wp_kses_post( get_option( 'ptero_manual_instructions', '' ) ) ),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'No payment method is currently available for this invoice.', 'ptero-host' ) ) );
	}

	public function ajax_add_funds() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		$client = Ptero_Client_Auth::instance()->current_client();
		if ( ! $client ) wp_send_json_error( array( 'message' => __( 'Please log in.', 'ptero-host' ) ) );

		$amount = (float) ( $_POST['amount'] ?? 0 );
		if ( $amount <= 0 ) wp_send_json_error( array( 'message' => __( 'Enter a valid amount.', 'ptero-host' ) ) );

		global $wpdb;
		// Manual/offline top-up flow: recorded as pending until an admin approves it.
		$wpdb->insert( $this->tx_table(), array(
			'client_id' => $client->id,
			'type'      => 'topup',
			'gateway'   => 'manual',
			'amount'    => $amount,
			'currency'  => $client->currency,
			'status'    => 'pending',
		) );

		wp_send_json_success( array( 'message' => __( 'Top-up request submitted. It will be added to your balance once confirmed by an admin.', 'ptero-host' ) ) );
	}

	public function admin_mark_paid() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'ptero_mark_paid_' . $id );
		$this->mark_paid( $id, 'manual-admin' );
		wp_redirect( admin_url( 'admin.php?page=ptero-host-invoices&paid=1' ) );
		exit;
	}

	// ------------------------------------------------------------- Admin UI

	public function render_admin_invoices() {
		global $wpdb;
		$invoices = $wpdb->get_results( "SELECT i.*, c.name AS client_name, c.email FROM {$this->invoices_table()} i LEFT JOIN {$this->clients_table()} c ON c.id = i.client_id ORDER BY i.created_at DESC LIMIT 200" );
		?>
		<div class="wrap">
			<h1>Invoices</h1>
			<table class="widefat striped">
				<thead><tr><th>#</th><th>Client</th><th>Total</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
				<tbody>
				<?php if ( $invoices ) : foreach ( $invoices as $i ) : ?>
					<tr>
						<td><?php echo esc_html( $i->invoice_number ); ?></td>
						<td><?php echo esc_html( $i->client_name . ' (' . $i->email . ')' ); ?></td>
						<td><?php echo esc_html( $i->currency . ' ' . number_format( (float) $i->total, 2 ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $i->status ) ); ?></td>
						<td><?php echo esc_html( $i->created_at ); ?></td>
						<td><?php if ( $i->status !== 'paid' ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ptero_admin_mark_paid&id=' . $i->id ), 'ptero_mark_paid_' . $i->id ) ); ?>">Mark Paid</a>
						<?php else : echo '—'; endif; ?></td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="6">No invoices yet.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_admin_clients() {
		global $wpdb;
		$clients = $wpdb->get_results( "SELECT * FROM {$this->clients_table()} ORDER BY created_at DESC LIMIT 200" );
		?>
		<div class="wrap">
			<h1>Clients</h1>
			<table class="widefat striped">
				<thead><tr><th>Name</th><th>Email</th><th>Balance</th><th>Status</th><th>Joined</th></tr></thead>
				<tbody>
				<?php if ( $clients ) : foreach ( $clients as $c ) : ?>
					<tr>
						<td><?php echo esc_html( $c->name ); ?></td>
						<td><?php echo esc_html( $c->email ); ?></td>
						<td><?php echo esc_html( $c->currency . ' ' . number_format( (float) $c->balance, 2 ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $c->status ) ); ?></td>
						<td><?php echo esc_html( $c->created_at ); ?></td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="5">No client accounts yet — they'll appear here once someone registers via <code>[ptero_register]</code>.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_admin_gateways() {
		?>
		<div class="wrap">
			<h1>Payment Gateways</h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ptero_save_gateways' ); ?>
				<input type="hidden" name="action" value="ptero_save_gateways">
				<table class="form-table">
					<tr><th colspan="2"><h2>Manual Payment</h2></th></tr>
					<tr><th>Enabled</th><td><label><input type="checkbox" name="manual_enabled" value="1" <?php checked( get_option( 'ptero_gateway_manual_enabled', '1' ), '1' ); ?>> Enable</label></td></tr>
					<tr><th>Instructions</th><td><textarea name="manual_instructions" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'ptero_manual_instructions', '' ) ); ?></textarea></td></tr>

					<tr><th colspan="2"><h2>Wallet</h2></th></tr>
					<tr><th>Enabled</th><td><label><input type="checkbox" name="wallet_enabled" value="1" <?php checked( get_option( 'ptero_gateway_wallet_enabled', '1' ), '1' ); ?>> Allow paying invoices from wallet balance</label></td></tr>

					<tr><th colspan="2"><h2>Stripe (optional)</h2></th></tr>
					<tr><th>Enabled</th><td><label><input type="checkbox" name="stripe_enabled" value="1" <?php checked( get_option( 'ptero_gateway_stripe_enabled', '0' ), '1' ); ?>> Enable</label></td></tr>
					<tr><th>Publishable key</th><td><input type="text" name="stripe_pub_key" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_stripe_pub_key', '' ) ); ?>"></td></tr>
					<tr><th>Secret key</th><td><input type="password" name="stripe_secret_key" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_stripe_secret_key', '' ) ); ?>"></td></tr>

					<tr><th colspan="2"><h2>PayPal (optional)</h2></th></tr>
					<tr><th>Enabled</th><td><label><input type="checkbox" name="paypal_enabled" value="1" <?php checked( get_option( 'ptero_gateway_paypal_enabled', '0' ), '1' ); ?>> Enable</label></td></tr>
					<tr><th>Client ID</th><td><input type="text" name="paypal_client_id" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_paypal_client_id', '' ) ); ?>"></td></tr>
					<tr><th>Secret</th><td><input type="password" name="paypal_secret" class="regular-text" value="<?php echo esc_attr( get_option( 'ptero_paypal_secret', '' ) ); ?>"></td></tr>
				</table>
				<?php submit_button( 'Save Gateways' ); ?>
			</form>
			<p style="color:#666;">Stripe/PayPal fields are stored ready for you to wire up their SDKs — flip "Enabled" once you've added real API keys. Manual and Wallet work out of the box.</p>
		</div>
		<?php
	}

	public function save_gateways() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'ptero_save_gateways' );

		update_option( 'ptero_gateway_manual_enabled', ! empty( $_POST['manual_enabled'] ) ? '1' : '0' );
		update_option( 'ptero_manual_instructions', wp_kses_post( $_POST['manual_instructions'] ?? '' ) );
		update_option( 'ptero_gateway_wallet_enabled', ! empty( $_POST['wallet_enabled'] ) ? '1' : '0' );
		update_option( 'ptero_gateway_stripe_enabled', ! empty( $_POST['stripe_enabled'] ) ? '1' : '0' );
		update_option( 'ptero_stripe_pub_key', sanitize_text_field( $_POST['stripe_pub_key'] ?? '' ) );
		update_option( 'ptero_stripe_secret_key', sanitize_text_field( $_POST['stripe_secret_key'] ?? '' ) );
		update_option( 'ptero_gateway_paypal_enabled', ! empty( $_POST['paypal_enabled'] ) ? '1' : '0' );
		update_option( 'ptero_paypal_client_id', sanitize_text_field( $_POST['paypal_client_id'] ?? '' ) );
		update_option( 'ptero_paypal_secret', sanitize_text_field( $_POST['paypal_secret'] ?? '' ) );

		wp_redirect( admin_url( 'admin.php?page=ptero-host-gateways&saved=1' ) );
		exit;
	}

	// ------------------------------------------------------------- Public

	public function render_invoices( $atts ) {
		ob_start();
		include PTEROHOST_PATH . 'templates/invoices.php';
		return ob_get_clean();
	}

	public function render_add_funds( $atts ) {
		ob_start();
		include PTEROHOST_PATH . 'templates/add-funds.php';
		return ob_get_clean();
	}
}
