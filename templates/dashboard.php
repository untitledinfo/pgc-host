<?php
/**
 * [phm_dashboard] wrapper. Variables set by PHM_Dashboard::shortcode():
 * $tab, $orders, $tickets, $ticket, $ticket_replies, $msg.
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url     = remove_query_arg( [ 'phm_tab', 'phm_ticket', 'phm_msg' ] );
$servers_url  = $base_url;
$tickets_url  = add_query_arg( [ 'phm_tab' => 'tickets' ], $base_url );
$banner       = $msg ? PHM_Dashboard::message( $msg ) : null;
?>
<div class="phm-dashboard-wrap">

	<?php if ( $banner ) : ?>
		<p class="phm-alert phm-alert-<?php echo esc_attr( 'good' === $banner[0] ? 'success' : 'error' ); ?>"><?php echo esc_html( $banner[1] ); ?></p>
	<?php endif; ?>

	<nav class="phm-dash-tabs">
		<a href="<?php echo esc_url( $servers_url ); ?>" class="<?php echo 'servers' === $tab ? 'is-active' : ''; ?>">
			<?php esc_html_e( 'My Servers', 'pterodactyl-hosting' ); ?>
			<span class="phm-tab-count"><?php echo (int) count( $orders ); ?></span>
		</a>
		<a href="<?php echo esc_url( $tickets_url ); ?>" class="<?php echo 'tickets' === $tab ? 'is-active' : ''; ?>">
			<?php esc_html_e( 'Support Tickets', 'pterodactyl-hosting' ); ?>
			<?php if ( $awaiting_count ) : ?><span class="phm-tab-badge"><?php echo (int) $awaiting_count; ?></span><?php endif; ?>
		</a>
	</nav>

	<?php if ( 'servers' === $tab ) : ?>
		<?php require PHM_PATH . 'templates/dashboard-servers.php'; ?>
	<?php elseif ( $ticket ) : ?>
		<?php require PHM_PATH . 'templates/ticket-view.php'; ?>
	<?php else : ?>
		<?php require PHM_PATH . 'templates/dashboard-tickets.php'; ?>
	<?php endif; ?>

</div>
