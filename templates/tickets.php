<?php if ( ! defined( 'ABSPATH' ) ) exit;
$client = Ptero_Client_Auth::instance()->current_client();
if ( ! $client ) { echo '<p>' . esc_html__( 'Please log in to view or open support tickets.', 'ptero-host' ) . '</p>'; return; }

$view_id = isset( $_GET['ticket'] ) ? (int) $_GET['ticket'] : 0;
$tickets_svc = Ptero_Tickets::instance();

if ( $view_id ) {
	$ticket = $tickets_svc->get_ticket( $view_id );
	if ( ! $ticket || (int) $ticket->client_id !== (int) $client->id ) { echo '<p>' . esc_html__( 'Ticket not found.', 'ptero-host' ) . '</p>'; return; }
	$replies = $tickets_svc->get_replies( $view_id );
	?>
	<div class="ptero-ticket-view">
		<h3><?php echo esc_html( $ticket->subject ); ?> — <?php echo esc_html( ucfirst( $ticket->status ) ); ?></h3>
		<div class="ptero-form-msg" style="display:none;"></div>
		<?php foreach ( $replies as $r ) : ?>
			<div class="ptero-ticket-msg ptero-msg-<?php echo esc_attr( $r->sender_type ); ?>">
				<strong><?php echo esc_html( $r->sender_name ); ?></strong> <span><?php echo esc_html( $r->created_at ); ?></span>
				<div><?php echo wp_kses_post( $r->message ); ?></div>
			</div>
		<?php endforeach; ?>
		<?php if ( $ticket->status !== 'closed' ) : ?>
			<form id="ptero-ticket-reply-form">
				<input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
				<textarea name="message" rows="3" style="width:100%;" required></textarea>
				<button type="submit" class="ptero-btn"><?php _e( 'Reply', 'ptero-host' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<script>
	jQuery(function($){
		$('#ptero-ticket-reply-form').on('submit', function(e){
			e.preventDefault();
			var $f = $(this), $msg = $('.ptero-form-msg');
			$.post(PteroHost.ajax_url, { action: 'ptero_ticket_reply', nonce: PteroHost.nonce, ticket_id: $f.find('[name=ticket_id]').val(), message: $f.find('[name=message]').val() }, function(res){
				$msg.show().text(res.data.message).css('color', res.success ? 'green' : 'red');
				if (res.success) setTimeout(function(){ window.location.reload(); }, 700);
			});
		});
	});
	</script>
	<?php
	return;
}

$tickets = $tickets_svc->get_client_tickets( $client->id );
?>
<div class="ptero-tickets-list">
	<h3><?php _e( 'Support Tickets', 'ptero-host' ); ?></h3>
	<?php if ( $tickets ) : ?>
		<ul>
			<?php foreach ( $tickets as $t ) : ?>
				<li><a href="<?php echo esc_url( add_query_arg( 'ticket', $t->id ) ); ?>"><?php echo esc_html( $t->subject ); ?></a> — <?php echo esc_html( ucfirst( $t->status ) ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p><?php _e( 'No tickets yet.', 'ptero-host' ); ?></p>
	<?php endif; ?>

	<h4><?php _e( 'Open a New Ticket', 'ptero-host' ); ?></h4>
	<div class="ptero-form-msg" style="display:none;"></div>
	<form id="ptero-ticket-create-form">
		<p><label><?php _e( 'Department', 'ptero-host' ); ?></label>
			<select name="department">
				<option value="general"><?php _e( 'General', 'ptero-host' ); ?></option>
				<option value="billing"><?php _e( 'Billing', 'ptero-host' ); ?></option>
				<option value="technical"><?php _e( 'Technical Support', 'ptero-host' ); ?></option>
			</select>
		</p>
		<p><label><?php _e( 'Subject', 'ptero-host' ); ?></label><input type="text" name="subject" required></p>
		<p><label><?php _e( 'Message', 'ptero-host' ); ?></label><textarea name="message" rows="4" style="width:100%;" required></textarea></p>
		<button type="submit" class="ptero-btn"><?php _e( 'Submit Ticket', 'ptero-host' ); ?></button>
	</form>
</div>
<script>
jQuery(function($){
	$('#ptero-ticket-create-form').on('submit', function(e){
		e.preventDefault();
		var $f = $(this), $msg = $('.ptero-form-msg');
		$.post(PteroHost.ajax_url, {
			action: 'ptero_ticket_create', nonce: PteroHost.nonce,
			department: $f.find('[name=department]').val(), subject: $f.find('[name=subject]').val(), message: $f.find('[name=message]').val()
		}, function(res){
			$msg.show().text(res.data.message).css('color', res.success ? 'green' : 'red');
			if (res.success) setTimeout(function(){ window.location.reload(); }, 700);
		});
	});
});
</script>
