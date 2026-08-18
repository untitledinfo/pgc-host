<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ptero_Tickets {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 22 );
		add_shortcode( 'ptero_tickets', array( $this, 'render_tickets' ) );

		add_action( 'wp_ajax_ptero_ticket_create', array( $this, 'ajax_create' ) );
		add_action( 'wp_ajax_ptero_ticket_reply', array( $this, 'ajax_reply' ) );
		add_action( 'admin_post_ptero_admin_ticket_reply', array( $this, 'admin_reply' ) );
		add_action( 'admin_post_ptero_admin_ticket_status', array( $this, 'admin_set_status' ) );
	}

	public function menu() {
		add_submenu_page( 'ptero-host', 'Tickets', 'Tickets', 'manage_options', 'ptero-host-tickets', array( $this, 'render_admin' ) );
	}

	private function t()  { global $wpdb; return $wpdb->prefix . 'ptero_tickets'; }
	private function tr() { global $wpdb; return $wpdb->prefix . 'ptero_ticket_replies'; }

	public function get_client_tickets( $client_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->t()} WHERE client_id = %d ORDER BY updated_at DESC", $client_id ) );
	}

	public function get_ticket( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->t()} WHERE id = %d", $id ) );
	}

	public function get_replies( $ticket_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->tr()} WHERE ticket_id = %d ORDER BY created_at ASC", $ticket_id ) );
	}

	public function ajax_create() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		$client = Ptero_Client_Auth::instance()->current_client();
		if ( ! $client ) wp_send_json_error( array( 'message' => __( 'Please log in.', 'ptero-host' ) ) );

		$subject = sanitize_text_field( $_POST['subject'] ?? '' );
		$message = wp_kses_post( $_POST['message'] ?? '' );
		$dept    = sanitize_text_field( $_POST['department'] ?? 'general' );
		if ( ! $subject || ! $message ) wp_send_json_error( array( 'message' => __( 'Subject and message are required.', 'ptero-host' ) ) );

		global $wpdb;
		$wpdb->insert( $this->t(), array(
			'client_id'  => $client->id,
			'subject'    => $subject,
			'department' => $dept,
			'server_id'  => ( $_POST['server_id'] ?? '' ) !== '' ? (int) $_POST['server_id'] : null,
		) );
		$ticket_id = $wpdb->insert_id;
		$wpdb->insert( $this->tr(), array(
			'ticket_id'   => $ticket_id,
			'sender_type' => 'client',
			'sender_name' => $client->name,
			'message'     => $message,
		) );

		do_action( 'ptero_ticket_created', $ticket_id, $client );
		wp_send_json_success( array( 'message' => __( 'Ticket submitted!', 'ptero-host' ), 'ticket_id' => $ticket_id ) );
	}

	public function ajax_reply() {
		check_ajax_referer( 'ptero_host_nonce', 'nonce' );
		$client = Ptero_Client_Auth::instance()->current_client();
		if ( ! $client ) wp_send_json_error( array( 'message' => __( 'Please log in.', 'ptero-host' ) ) );

		$ticket_id = (int) ( $_POST['ticket_id'] ?? 0 );
		$ticket = $this->get_ticket( $ticket_id );
		if ( ! $ticket || (int) $ticket->client_id !== (int) $client->id ) wp_send_json_error( array( 'message' => __( 'Ticket not found.', 'ptero-host' ) ) );

		$message = wp_kses_post( $_POST['message'] ?? '' );
		if ( ! $message ) wp_send_json_error( array( 'message' => __( 'Message cannot be empty.', 'ptero-host' ) ) );

		global $wpdb;
		$wpdb->insert( $this->tr(), array( 'ticket_id' => $ticket_id, 'sender_type' => 'client', 'sender_name' => $client->name, 'message' => $message ) );
		$wpdb->update( $this->t(), array( 'status' => 'open', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $ticket_id ) );

		wp_send_json_success( array( 'message' => __( 'Reply sent.', 'ptero-host' ) ) );
	}

	public function admin_reply() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		$ticket_id = (int) ( $_POST['ticket_id'] ?? 0 );
		check_admin_referer( 'ptero_ticket_reply_' . $ticket_id );

		$message = wp_kses_post( $_POST['message'] ?? '' );
		if ( $message ) {
			global $wpdb;
			$user = wp_get_current_user();
			$wpdb->insert( $this->tr(), array( 'ticket_id' => $ticket_id, 'sender_type' => 'admin', 'sender_name' => $user->display_name, 'message' => $message ) );
			$wpdb->update( $this->t(), array( 'status' => 'answered', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $ticket_id ) );
		}
		wp_redirect( admin_url( 'admin.php?page=ptero-host-tickets&view=' . $ticket_id ) );
		exit;
	}

	public function admin_set_status() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		$ticket_id = (int) ( $_GET['ticket_id'] ?? 0 );
		check_admin_referer( 'ptero_ticket_status_' . $ticket_id );
		global $wpdb;
		$wpdb->update( $this->t(), array( 'status' => sanitize_text_field( $_GET['status'] ?? 'closed' ) ), array( 'id' => $ticket_id ) );
		wp_redirect( admin_url( 'admin.php?page=ptero-host-tickets&view=' . $ticket_id ) );
		exit;
	}

	public function render_admin() {
		global $wpdb;
		if ( isset( $_GET['view'] ) ) {
			$ticket = $this->get_ticket( (int) $_GET['view'] );
			$replies = $this->get_replies( (int) $_GET['view'] );
			?>
			<div class="wrap">
				<h1>Ticket: <?php echo esc_html( $ticket->subject ?? '' ); ?></h1>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=ptero-host-tickets' ) ); ?>">&larr; Back to all tickets</a></p>
				<?php foreach ( $replies as $r ) : ?>
					<div style="background:<?php echo $r->sender_type === 'admin' ? '#e7f3ff' : '#f6f7f7'; ?>;padding:12px;margin-bottom:8px;border-radius:6px;">
						<strong><?php echo esc_html( $r->sender_name ); ?></strong> — <?php echo esc_html( $r->created_at ); ?>
						<div><?php echo wp_kses_post( $r->message ); ?></div>
					</div>
				<?php endforeach; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ptero_ticket_reply_' . $ticket->id ); ?>
					<input type="hidden" name="action" value="ptero_admin_ticket_reply">
					<input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
					<textarea name="message" rows="4" class="large-text"></textarea>
					<?php submit_button( 'Reply' ); ?>
				</form>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ptero_admin_ticket_status&status=closed&ticket_id=' . $ticket->id ), 'ptero_ticket_status_' . $ticket->id ) ); ?>" class="button">Close Ticket</a>
			</div>
			<?php return;
		}

		$tickets = $wpdb->get_results( "SELECT t.*, c.name AS client_name FROM {$this->t()} t LEFT JOIN {$wpdb->prefix}ptero_clients c ON c.id = t.client_id ORDER BY t.updated_at DESC LIMIT 200" );
		?>
		<div class="wrap">
			<h1>Support Tickets</h1>
			<table class="widefat striped">
				<thead><tr><th>#</th><th>Client</th><th>Subject</th><th>Department</th><th>Status</th><th>Updated</th></tr></thead>
				<tbody>
				<?php if ( $tickets ) : foreach ( $tickets as $t ) : ?>
					<tr>
						<td>#<?php echo (int) $t->id; ?></td>
						<td><?php echo esc_html( $t->client_name ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=ptero-host-tickets&view=' . $t->id ) ); ?>"><?php echo esc_html( $t->subject ); ?></a></td>
						<td><?php echo esc_html( $t->department ); ?></td>
						<td><?php echo esc_html( ucfirst( $t->status ) ); ?></td>
						<td><?php echo esc_html( $t->updated_at ); ?></td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="6">No tickets yet.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_tickets( $atts ) {
		ob_start();
		include PTEROHOST_PATH . 'templates/tickets.php';
		return ob_get_clean();
	}
}
