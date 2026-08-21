<?php
/**
 * "My Services & Servers" Tab Template for [phm_dashboard].
 * Server IPs are never shown — hostname/subdomain only, otherwise
 * customers connect through the game panel.
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
			<h3><?php esc_html_e( "You don't have any services yet.", 'pterodactyl-hosting' ); ?></h3>
			<p><?php esc_html_e( 'Choose a plan to deploy a game server in seconds.', 'pterodactyl-hosting' ); ?></p>
			<?php $plans_url = PHM_Store::page_url( 'phm_plans' ); ?>
			<?php if ( $plans_url ) : ?>
				<a class="phm-btn phm-btn-primary phm-btn-lg" href="<?php echo esc_url( $plans_url ); ?>"><?php esc_html_e( 'Browse Server Plans →', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="phm-services-header">
			<div class="phm-services-summary">
				<h2><?php esc_html_e( 'My Services', 'pterodactyl-hosting' ); ?></h2>
				<span class="phm-services-counter"><?php echo esc_html( sprintf( _n( '%d service', '%d services', count( $orders ), 'pterodactyl-hosting' ), count( $orders ) ) ); ?></span>
			</div>
			<?php $plans_url = PHM_Store::page_url( 'phm_plans' ); ?>
			<?php if ( $plans_url ) : ?>
				<a class="phm-btn phm-btn-sm phm-btn-outline" href="<?php echo esc_url( $plans_url ); ?>">+ <?php esc_html_e( 'New Server', 'pterodactyl-hosting' ); ?></a>
			<?php endif; ?>
		</div>

		<div class="phm-server-grid">
			<?php foreach ( $orders as $i => $o ) :
				$address = PHM_Frontend::public_address( $o );
				$go_url  = ( 'active' === $o->status && $o->server_id ) ? PHM_Cookie_Login::url_for_current_user( $o->server_identifier ) : '';
				$product = $o->product_id ? PHM_DB::get_product( $o->product_id ) : null;
				$is_free = (float) $o->amount <= 0;
				?>
				<div class="phm-server-card phm-card-<?php echo esc_attr( $o->status ); ?>" style="animation-delay: <?php echo esc_attr( ( $i % 8 ) * 0.05 ); ?>s">
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

					<?php if ( $product ) : ?>
					<div class="phm-server-specs">
						<div class="phm-spec-item">
							<span class="phm-spec-label"><?php esc_html_e( 'RAM', 'pterodactyl-hosting' ); ?></span>
							<span class="phm-spec-val"><?php echo esc_html( PHM_Plans::format_memory( $product->memory ) ); ?></span>
						</div>
						<div class="phm-spec-item">
							<span class="phm-spec-label"><?php esc_html_e( 'Disk', 'pterodactyl-hosting' ); ?></span>
							<span class="phm-spec-val"><?php echo esc_html( PHM_Plans::format_memory( $product->disk ) ); ?></span>
						</div>
						<div class="phm-spec-item">
							<span class="phm-spec-label"><?php esc_html_e( 'CPU', 'pterodactyl-hosting' ); ?></span>
							<span class="phm-spec-val"><?php echo (int) $product->cpu; ?>%</span>
						</div>
					</div>
					<?php endif; ?>

					<?php if ( $address ) : ?>
						<div class="phm-address-box">
							<span class="phm-address-label"><?php esc_html_e( 'Hostname', 'pterodactyl-hosting' ); ?></span>
							<div class="phm-address-row">
								<code><?php echo esc_html( $address ); ?></code>
								<button type="button" class="phm-copy-btn" data-copy="<?php echo esc_attr( $address ); ?>" title="<?php esc_attr_e( 'Copy hostname', 'pterodactyl-hosting' ); ?>">
									📋
								</button>
							</div>
						</div>
					<?php elseif ( 'active' === $o->status ) : ?>
						<div class="phm-address-box">
							<span class="phm-address-label"><?php esc_html_e( 'Connection', 'pterodactyl-hosting' ); ?></span>
							<p class="phm-hostname-private" style="margin:4px 0 0;"><?php esc_html_e( 'Connect through the Game Panel — the node IP is private.', 'pterodactyl-hosting' ); ?></p>
						</div>
					<?php endif; ?>

					<div class="phm-server-meta-row">
						<span><?php esc_html_e( 'Plan', 'pterodactyl-hosting' ); ?></span>
						<strong><?php echo esc_html( $o->plan_name ); ?><?php echo $is_free ? ' · ' . esc_html__( 'Free', 'pterodactyl-hosting' ) : ''; ?></strong>
					</div>
					<div class="phm-server-meta-row">
						<span><?php esc_html_e( 'Service', 'pterodactyl-hosting' ); ?></span>
						<code><?php echo esc_html( $o->order_number ); ?></code>
					</div>

					<?php if ( $is_free ) : ?>
						<div class="phm-server-renewal">
							<span><?php esc_html_e( 'Billing', 'pterodactyl-hosting' ); ?></span>
							<strong class="phm-billing-free"><?php esc_html_e( 'Free — no renewal', 'pterodactyl-hosting' ); ?></strong>
						</div>
					<?php elseif ( ! empty( $o->next_due_at ) ) : ?>
						<div class="phm-server-renewal">
							<span><?php esc_html_e( 'Next due', 'pterodactyl-hosting' ); ?></span>
							<strong><?php echo esc_html( gmdate( 'M d, Y', strtotime( $o->next_due_at ) ) ); ?></strong>
						</div>
					<?php endif; ?>

					<?php if ( 'provisioning' === $o->status || 'paid' === $o->status ) : ?>
						<p class="phm-server-alert phm-alert-info">⚡ <?php esc_html_e( 'Your server is deploying… this page updates as soon as it is ready.', 'pterodactyl-hosting' ); ?></p>
					<?php elseif ( 'failed' === $o->status && $o->error_message ) : ?>
						<p class="phm-server-alert phm-alert-error">⚠️ <?php echo esc_html( $o->error_message ); ?></p>
					<?php elseif ( 'suspended' === $o->status ) : ?>
						<p class="phm-server-alert phm-alert-warning">⏸ <?php esc_html_e( 'Service suspended. Open a billing ticket or renew to unsuspend.', 'pterodactyl-hosting' ); ?></p>
					<?php endif; ?>

					<div class="phm-server-actions">
						<?php if ( $go_url ) : ?>
							<a class="phm-btn phm-btn-primary phm-btn-block" target="_blank" rel="noopener" href="<?php echo esc_url( $go_url ); ?>">
								<?php esc_html_e( 'Manage in Game Panel', 'pterodactyl-hosting' ); ?>
							</a>
						<?php endif; ?>
						<div class="phm-actions-subgroup">
							<a class="phm-btn phm-btn-muted phm-btn-sm" href="<?php echo esc_url( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_new_order_id' => $o->id ], remove_query_arg( [ 'phm_ticket', 'phm_msg' ] ) ) ); ?>">
								<?php esc_html_e( 'Support', 'pterodactyl-hosting' ); ?>
							</a>
							<?php if ( ! $is_free && in_array( $o->status, [ 'active', 'suspended' ], true ) ) : ?>
								<a class="phm-btn phm-btn-muted phm-btn-sm" href="<?php echo esc_url( add_query_arg( [ 'phm_tab' => 'tickets', 'phm_new_order_id' => $o->id ], remove_query_arg( [ 'phm_ticket', 'phm_msg' ] ) ) ); ?>">
									<?php esc_html_e( 'Renew / Billing', 'pterodactyl-hosting' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
