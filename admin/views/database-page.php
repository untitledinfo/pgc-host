<?php
/** @package Pterodactyl_Hosting */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHM_Admin::render_msg();
?>
<div class="wrap phm-admin">
	<h1>
		<?php esc_html_e( 'Database Data', 'pterodactyl-hosting' ); ?>
		<button type="button" class="button button-primary" id="phm-sync-now"><?php esc_html_e( 'Sync now', 'pterodactyl-hosting' ); ?></button>
	</h1>
	<p class="description">
		<?php esc_html_e( 'Everything below is pulled live from your Pterodactyl panel into the WordPress database (auto-reloads after every sync). Last sync:', 'pterodactyl-hosting' ); ?>
		<span id="phm-last-sync"><?php echo esc_html( PHM_Sync::last_sync_human() ); ?></span>
	</p>
	<div id="phm-test-result" class="phm-test-result" aria-live="polite"></div>
	<div id="phm-db-data">
		<?php require PHM_PATH . 'admin/views/database.php'; ?>
	</div>
</div>
<?php
