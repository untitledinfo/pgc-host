<?php
/**
 * Rendered synced data tables (reused by the Settings screen, the Database
 * Data page and the AJAX auto-reload endpoint).
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$locations = PHM_DB::get_locations();
$nests     = PHM_DB::get_nests();
$eggs      = PHM_DB::get_eggs();
$nodes     = PHM_DB::get_nodes();
?>
<div class="phm-db-grid">
	<div class="phm-db-box">
		<h3><?php esc_html_e( 'Locations', 'pterodactyl-hosting' ); ?> <span class="phm-pill"><?php echo count( $locations ); ?></span></h3>
		<?php if ( ! $locations ) : ?><p class="description"><?php esc_html_e( 'No data — run a sync.', 'pterodactyl-hosting' ); ?></p><?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Short', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Description', 'pterodactyl-hosting' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $locations as $loc ) : ?>
				<tr><td><?php echo (int) $loc->location_id; ?></td><td><strong><?php echo esc_html( $loc->short ); ?></strong></td><td><?php echo esc_html( $loc->long_description ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<div class="phm-db-box">
		<h3><?php esc_html_e( 'Nests (games)', 'pterodactyl-hosting' ); ?> <span class="phm-pill"><?php echo count( $nests ); ?></span></h3>
		<?php if ( ! $nests ) : ?><p class="description"><?php esc_html_e( 'No data — run a sync.', 'pterodactyl-hosting' ); ?></p><?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Game', 'pterodactyl-hosting' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $nests as $nest ) : ?>
				<tr><td><?php echo (int) $nest->nest_id; ?></td><td><strong><?php echo esc_html( $nest->name ); ?></strong></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<div class="phm-db-box">
		<h3><?php esc_html_e( 'Eggs (server types — Minecraft, Paper…)', 'pterodactyl-hosting' ); ?> <span class="phm-pill"><?php echo count( $eggs ); ?></span></h3>
		<?php if ( ! $eggs ) : ?><p class="description"><?php esc_html_e( 'No data — run a sync.', 'pterodactyl-hosting' ); ?></p><?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Nest', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Egg', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Docker image', 'pterodactyl-hosting' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $eggs as $egg ) : ?>
				<tr>
					<td><?php echo (int) $egg->egg_id; ?></td>
					<td>#<?php echo (int) $egg->nest_id; ?></td>
					<td><strong><?php echo esc_html( $egg->name ); ?></strong></td>
					<td><code><?php echo esc_html( $egg->docker_image ); ?></code></td>
					<td><a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_import_plan&egg_id=' . (int) $egg->egg_id ), 'phm_import_plan' ) ); ?>"><?php esc_html_e( 'Create plan', 'pterodactyl-hosting' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<div class="phm-db-box">
		<h3><?php esc_html_e( 'Nodes', 'pterodactyl-hosting' ); ?> <span class="phm-pill"><?php echo count( $nodes ); ?></span></h3>
		<?php if ( ! $nodes ) : ?><p class="description"><?php esc_html_e( 'No data — run a sync.', 'pterodactyl-hosting' ); ?></p><?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Node', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Location', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Free RAM', 'pterodactyl-hosting' ); ?></th><th><?php esc_html_e( 'Free disk', 'pterodactyl-hosting' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $nodes as $node ) :
				$free_mem  = max( 0, ( $node->memory + max( 0, $node->memory_overallocate ) ) - $node->memory_used );
				$free_disk = max( 0, ( $node->disk + max( 0, $node->disk_overallocate ) ) - $node->disk_used );
				?>
				<tr>
					<td><?php echo (int) $node->node_id; ?></td>
					<td><strong><?php echo esc_html( $node->name ); ?></strong><br><small><?php echo esc_html( $node->fqdn ); ?></small></td>
					<td>#<?php echo (int) $node->location_id; ?></td>
					<td><?php echo esc_html( size_format( $free_mem * MB_IN_BYTES, 0 ) ); ?></td>
					<td><?php echo esc_html( size_format( $free_disk * MB_IN_BYTES, 0 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
</div>
<?php
