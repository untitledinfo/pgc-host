<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="ptero-dashboard">
	<h2><?php _e( 'My Servers', 'ptero-host' ); ?></h2>

	<?php if ( ! $servers ) : ?>
		<p><?php _e( 'You don\'t have any servers yet.', 'ptero-host' ); ?></p>
	<?php else : ?>
		<div class="ptero-server-list">
			<?php foreach ( $servers as $s ) : ?>
				<div class="ptero-server-card" data-order-id="<?php echo (int) $s->id; ?>">
					<div class="ptero-server-head">
						<h3><?php echo esc_html( $s->server_name ); ?></h3>
						<span class="ptero-status ptero-status-<?php echo esc_attr( $s->status ); ?>"><?php echo esc_html( ucfirst( $s->status ) ); ?></span>
					</div>
					<div class="ptero-server-meta">
						<div><strong><?php _e( 'IP', 'ptero-host' ); ?>:</strong> <?php echo esc_html( $s->ip_address ? $s->ip_address . ':' . $s->port : __( 'Provisioning…', 'ptero-host' ) ); ?></div>
						<div><strong><?php _e( 'RAM', 'ptero-host' ); ?>:</strong> <?php echo (int) $s->ram; ?> MB</div>
						<div><strong><?php _e( 'CPU', 'ptero-host' ); ?>:</strong> <?php echo (int) $s->cpu; ?>%</div>
						<div><strong><?php _e( 'Disk', 'ptero-host' ); ?>:</strong> <?php echo (int) $s->disk; ?> MB</div>
						<div><strong><?php _e( 'Expires', 'ptero-host' ); ?>:</strong> <?php echo esc_html( $s->expires_at ?: '—' ); ?></div>
					</div>

					<div class="ptero-usage-bars" data-order-id="<?php echo (int) $s->id; ?>">
						<div class="ptero-bar"><label>CPU</label><progress class="ptero-cpu-bar" value="0" max="100"></progress><span class="ptero-cpu-text">—</span></div>
						<div class="ptero-bar"><label>RAM</label><progress class="ptero-ram-bar" value="0" max="<?php echo (int) $s->ram; ?>"></progress><span class="ptero-ram-text">—</span></div>
					</div>

					<?php if ( $s->status === 'active' && $s->ptero_identifier ) : ?>
						<div class="ptero-power-actions">
							<button class="ptero-btn-sm" data-signal="start">▶ Start</button>
							<button class="ptero-btn-sm" data-signal="restart">⟳ Restart</button>
							<button class="ptero-btn-sm ptero-btn-danger" data-signal="stop">■ Stop</button>
						</div>
						<a class="ptero-console-link" href="<?php echo esc_url( untrailingslashit( get_option( 'ptero_panel_url' ) ) . '/server/' . $s->ptero_identifier ); ?>" target="_blank" rel="noopener">
							<?php _e( 'Open Console →', 'ptero-host' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
