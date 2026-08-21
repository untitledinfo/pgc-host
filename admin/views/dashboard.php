<?php
/** @package Pterodactyl_Hosting */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHM_Admin::render_msg();
$counts    = PHM_DB::counts();
$last_sync = PHM_Sync::last_sync_human();
$configured = PHM_Settings::is_configured();
$orders    = PHM_DB::get_orders();
$pending   = array_filter( $orders, function ( $o ) { return 'pending' === $o->status; } );
?>
<?php
$missing_tables = ( false === get_transient( 'phm_tables_ok' ) ) ? PHM_DB::tables_exist() : [];
?>
<div class="wrap phm-admin">
	<?php if ( $missing_tables ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Database tables are missing:', 'pterodactyl-hosting' ); ?></strong>
				<code><?php echo esc_html( implode( ', ', $missing_tables ) ); ?></code> —
				<?php esc_html_e( 'plans/orders cannot be saved until this is fixed.', 'pterodactyl-hosting' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_repair_db' ), 'phm_repair_db' ) ); ?>">
					🔧 <?php esc_html_e( 'Repair database tables', 'pterodactyl-hosting' ); ?>
				</a>
				<span class="description"><?php esc_html_e( 'If repair still fails, grant the WordPress DB user CREATE/ALTER privileges (cPanel → MySQL → user privileges), then run it again.', 'pterodactyl-hosting' ); ?></span>
			</p>
		</div>
	<?php endif; ?>
	<h1>
		<?php esc_html_e( 'PGC Hosting — Dashboard', 'pterodactyl-hosting' ); ?>
		<button type="button" class="button button-primary" id="phm-sync-now"><?php esc_html_e( 'Sync now', 'pterodactyl-hosting' ); ?></button>
		<?php
		$pages = PHM_Store::pages();
		if ( count( $pages ) < 3 ) :
			?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_create_pages' ), 'phm_create_pages' ) ); ?>"><?php esc_html_e( 'Create store pages', 'pterodactyl-hosting' ); ?></a>
		<?php endif; ?>
	</h1>
	<div id="phm-test-result" class="phm-test-result" aria-live="polite"></div>

	<?php
	$store_links = [];
	foreach ( [
		'phm_plans'         => __( 'Plans', 'pterodactyl-hosting' ),
		'phm_order'         => __( 'Order Cart', 'pterodactyl-hosting' ),
		'phm_dashboard'     => __( 'Client Dashboard', 'pterodactyl-hosting' ),
		'phm_ticket_create' => __( 'Support Tickets', 'pterodactyl-hosting' ),
		'phm_track'         => __( 'Tracking', 'pterodactyl-hosting' ),
	] as $sc => $label ) {
		$url = PHM_Store::page_url( $sc );
		if ( $url ) {
			$store_links[] = '<strong>' . esc_html( $label ) . ':</strong> <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>';
		}
	}
	if ( $store_links ) :
		?>
		<p class="description">🛒 <?php echo wp_kses_post( implode( ' · ', $store_links ) ); ?></p>
	<?php endif; ?>

	<div class="phm-cards">
		<div class="phm-card">
			<span class="phm-card-label"><?php esc_html_e( 'Panel connection', 'pterodactyl-hosting' ); ?></span>
			<strong class="<?php echo $configured ? 'phm-ok' : 'phm-bad'; ?>">
				<?php echo $configured ? esc_html__( 'Configured', 'pterodactyl-hosting' ) : esc_html__( 'Not configured', 'pterodactyl-hosting' ); ?>
			</strong>
			<small><?php echo esc_html( PHM_Settings::panel_url() ?: __( 'Enter your panel URL in Settings', 'pterodactyl-hosting' ) ); ?></small>
		</div>
		<div class="phm-card">
			<span class="phm-card-label"><?php esc_html_e( 'Synced data', 'pterodactyl-hosting' ); ?></span>
			<strong><?php echo (int) $counts['nests']; ?> <?php esc_html_e( 'nests', 'pterodactyl-hosting' ); ?> · <?php echo (int) $counts['eggs']; ?> <?php esc_html_e( 'eggs', 'pterodactyl-hosting' ); ?></strong>
			<small><?php echo (int) $counts['locations']; ?> <?php esc_html_e( 'locations', 'pterodactyl-hosting' ); ?> · <?php echo (int) $counts['nodes']; ?> <?php esc_html_e( 'nodes', 'pterodactyl-hosting' ); ?> · <?php esc_html_e( 'Last sync:', 'pterodactyl-hosting' ); ?> <span id="phm-last-sync"><?php echo esc_html( $last_sync ); ?></span></small>
		</div>
		<div class="phm-card">
			<span class="phm-card-label"><?php esc_html_e( 'Products', 'pterodactyl-hosting' ); ?></span>
			<strong><?php echo (int) $counts['products']; ?></strong>
			<small><a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-products' ) ); ?>"><?php esc_html_e( 'Manage plans', 'pterodactyl-hosting' ); ?></a></small>
		</div>
		<div class="phm-card">
			<span class="phm-card-label"><?php esc_html_e( 'Orders (pending)', 'pterodactyl-hosting' ); ?></span>
			<strong><?php echo (int) $counts['orders']; ?> (<?php echo count( $pending ); ?>)</strong>
			<small><a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-orders' ) ); ?>"><?php esc_html_e( 'Review orders', 'pterodactyl-hosting' ); ?></a></small>
		</div>
	</div>

	<div class="phm-grid-2">
		<div class="phm-panel">
			<h2><?php esc_html_e( 'Recent orders', 'pterodactyl-hosting' ); ?></h2>
			<?php if ( ! $orders ) : ?>
				<p><?php esc_html_e( 'No orders yet. Add [phm_plans] and [phm_order] shortcodes to a page to start selling.', 'pterodactyl-hosting' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Order', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Plan', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Customer', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $orders, 0, 8 ) as $o ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=phm-orders' ) ); ?>"><?php echo esc_html( $o->order_number ); ?></a></td>
							<td><?php echo esc_html( $o->plan_name ); ?></td>
							<td><?php echo esc_html( $o->customer_name ); ?></td>
							<td><span class="phm-status phm-status-<?php echo esc_attr( PHM_Orders::status_class( $o->status ) ); ?>"><?php echo esc_html( PHM_Orders::status_label( $o->status ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="phm-panel">
			<h2><?php esc_html_e( 'Activity log', 'pterodactyl-hosting' ); ?></h2>
			<ul class="phm-log">
				<?php foreach ( PHM_DB::get_logs( 8 ) as $log ) : ?>
					<li class="phm-log-<?php echo esc_attr( $log->level ); ?>">
						<small><?php echo esc_html( $log->created_at ); ?></small>
						<?php echo esc_html( $log->message ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<h2><?php esc_html_e( 'Shortcodes', 'pterodactyl-hosting' ); ?></h2>
			<p>
				<code>[phm_plans]</code> — <?php esc_html_e( 'pricing grid', 'pterodactyl-hosting' ); ?><br>
				<code>[phm_order]</code> — <?php esc_html_e( 'subdomain cart + checkout', 'pterodactyl-hosting' ); ?><br>
				<code>[phm_track]</code> — <?php esc_html_e( 'customer order tracking', 'pterodactyl-hosting' ); ?><br>
				<code>[phm_plans nest="1"]</code> — <?php esc_html_e( 'only plans for one game', 'pterodactyl-hosting' ); ?>
			</p>
		</div>
	</div>
</div>
<?php
