<?php
/**
 * "My Services & Servers" Tab Template for [phm_dashboard].
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="phm-dash-panel">
	<?php if ( empty( $orders ) ) : ?>
		<div class="phm-empty-block">
			<div class="phm-empty-icon">🎮</div>
			<h3><?php esc_html_e( "You don't have any active servers yet.", 'pterodactyl-hosting' ); ?></h3>
			<p><?php esc_html_e( 'Choose a high-performance game server plan to deploy instantly in seconds.', 'pterodactyl-hosting' ); ?></p>
			<?php $plans_url = PHM_Store::page_url( 'phm_plans' ); ?>
			<?php if ( $plans_url ) : ?>
				<a class="phm-btn phm-btn-primary phm-btn-lg" href="<?php echo esc_url( $plans_url ); ?>"><?php esc_html_e( 'Browse Server Plans →', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="phm-services-header">
			<div class="phm-services-summary">
				<h2><?php esc_html_e( 'My Active Services', 'pterodactyl-hosting' ); ?></h2>
				<span class="phm-services-counter"><?php echo sprintf( esc_html__( '%d Servers Total', 'pterodactyl-hosting' ), count( $orders ) ); ?></span>
			</div>
			<?php $plans_url = PHM_Store::page_url( 'phm_plans' ); ?>
			<?php if ( $plans_url ) : ?>
				<a class="phm-btn phm-btn-sm phm-btn-outline" href="<?php echo esc_url( $plans_url ); ?>">+ <?php esc_html_e( 'Deploy New Server', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</div>

		<div class="phm-server-grid">
			<?php foreach ( $orders as $o ) :
				$address = $o->fqdn ? $o->fqdn : trim( $o->server_ip . ( $o->server_port && 25565 !== (int) $o->server_port ? ':' . $o->server_port : '' ), ':' );
				$go_url  = ( 'active' === $o->status && $o->server_id ) ? PHM_Cookie_Login::url_for_current_user( $o->server_identifier ) : '';
				$product = $o->product_id ? PHM_DB::get_product( $o->product_id ) : null;
				?>
				<div class="phm-server-card phm-card-<?php echo esc_attr( $o->status ); ?>">
					<!-- Card Header -->
					<div class="phm-server-card-top">
						<div class="phm-server-title-box">
							<span class="phm-server-egg-badge"><?php echo esc_html( $o->egg_name ? $o->egg_name : $o->plan_name ); ?></span>
							<h3><?php echo esc_html( $o->server_label ? $o->server_label : $o->plan_name ); ?></h3>
						</div>
						<span class="phm-status phm-status-<?php echo esc_attr( PHM_Orders::status_class( $o->status ) ); ?>">
							<span class="phm-status-dot"></span>
							<?php echo esc_html( PHM_Orders::status_label( $o->status ) ); ?>
						</span>
					</div>

					<!-- Server Specs Grid -->
					<?php if ( $product ) : ?>
					<div class="phm-server-specs">
						<div class="phm-spec-item">
							<span class="phm-spec-label"><?php esc_html_e( 'RAM', 'pterodactyl-hosting' ); ?></span>
							<span class="phm-spec-val"><?php echo (int) round( $product->memory / 1024 ); ?> GB</span>
						</div>
						<div class="phm-spec-item">
							<span class="phm-spec-label"><?php esc_html_e( 'Disk NVMe', 'pterodactyl-hosting' ); ?></span>
							<span class="phm-spec-val"><?php echo (int) round( $product->disk / 1024 ); ?> GB</span>
						</div>
						<div class="phm-spec-item">
							<span class="phm-spec-label"><?php esc_html_e( 'CPU', 'pterodactyl-hosting' ); ?></span>
							<span class="phm-spec-val"><?php echo (int) $product->cpu; ?>%</span>
						</div>
					</div>
					<?php endif; ?>

					<!-- Server Address Box -->
					<?php if ( $address ) : ?>
						<div class="phm-address-box">
							<span class="phm-address-label"><?php esc_html_e( 'Server IP / Address', 'pterodactyl-hosting' ); ?></span>
							<div class="phm-address-row">
								<code><?php echo esc_html( $address ); ?></code>
								<button type="button" class="phm-copy-btn" data-copy="<?php echo esc_attr( $address ); ?>" title="<?php esc_attr_e( 'Copy Address', 'pterodactyl-hosting' ); ?>">
									📋
								</button>
							</div>
						</div>
					<?php endif; ?>

					<!-- Account / Credentials Info -->
					<div class="phm-server-creds">
						<span class="phm-creds-title">🔑 <?php esc_html_e( 'Panel Login ID', 'pterodactyl-hosting' ); ?>:</span>
						<code><?php echo esc_html( $o->email ); ?></code>
					</div>

					<!-- Renewal Date -->
					<?php if ( ! empty( $o->next_due_at ) ) : ?>
						<div class="phm-server-renewal">
							<span><?php esc_html_e( 'Next Billing Due:', 'pterodactyl-hosting' ); ?></span>
							<strong><?php echo esc_html( gmdate( 'M d, Y', strtotime( $o->next_due_at ) ) ); ?></strong>
						</div>
					<?php endif; ?>

					<!-- Warnings / Alerts -->
					<?php if ( 'provisioning' === $o->status ) : ?>
						<p class="phm-server-alert phm-alert-info">⚡ <?php esc_html_e( 'Your server is deploying right now… Please refresh in a moment.', 'pterodactyl-hosting' ); ?></p>
					<?php elseif ( 'failed' === $o->status && $o->error_message ) : ?>
						<p class="phm-server-alert phm-alert-error">⚠️ <?php echo esc_html( $o->error_message ); ?></p>
					<?php elseif ( 'suspended' === $o->status ) : ?>
						<p class="phm-server-alert phm-alert-warning">⏸ <?php esc_html_e( 'Server suspended due to renewal. Contact support or renew.', 'pterodactyl-hosting' ); ?></p>
					<?php endif; ?>

					<!-- Action Buttons -->
					<div class="phm-server-actions">
						<?php if ( $go_url ) : ?>
							<a class="phm-btn phm-btn-primary phm-btn-block" target="_blank" rel="noopener" href="<?php echo esc_url( $go_url ); ?>">
								🚀 <?php esc_html_e( 'Open Game Panel Console', 'pterodactyl-hosting' ); ?>
							</a>
						<?php endif; ?>
						<div class="phm-actions-subgroup">
							<a class="phm-btn phm-btn-muted phm-btn-sm" href="<?php echo esc_url( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_new_order_id' => $o->id ], remove_query_arg( [ 'phm_ticket', 'phm_msg' ] ) ) ); ?>">
								🎫 <?php esc_html_e( 'Support Ticket', 'pterodactyl-hosting' ); ?>
							</a>
							<?php if ( $o->server_identifier ) : ?>
								<span class="phm-server-sid">ID: <code><?php echo esc_html( substr( $o->server_identifier, 0, 8 ) ); ?></code></span>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
