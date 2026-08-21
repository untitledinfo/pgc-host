<?php
/** Products list + add/edit form. @package Pterodactyl_Hosting */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHM_Admin::render_msg();

$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$edit   = ( 'edit' === $action && ! empty( $_GET['id'] ) ) ? PHM_DB::get_product( (int) $_GET['id'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification

if ( 'new' === $action || $edit ) :
	$product   = $edit ?: (object) [
		'id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'nest_id' => 0, 'egg_id' => 0,
		'location_id' => 0, 'memory' => 2048, 'swap' => 0, 'disk' => 10240, 'io' => 500, 'cpu' => 100,
		'databases' => 1, 'extra_allocations' => 0, 'backups' => 1, 'price' => 0, 'setup_fee' => 0,
		'currency' => PHM_Settings::get()['default_currency'], 'stock' => -1, 'featured' => 0, 'sort_order' => 0, 'active' => 1,
	];
	$nests     = PHM_DB::get_nests();
	$eggs      = PHM_DB::get_eggs();
	$locations = PHM_DB::get_locations();
	?>
	<div class="wrap phm-admin">
		<h1><?php echo $edit ? esc_html__( 'Edit plan', 'pterodactyl-hosting' ) : esc_html__( 'New plan', 'pterodactyl-hosting' ); ?></h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="phm-settings-form">
			<input type="hidden" name="action" value="phm_save_plan">
			<input type="hidden" name="id" value="<?php echo (int) $product->id; ?>">
			<?php wp_nonce_field( 'phm_save_plan' ); ?>

			<div class="phm-grid-2">
				<div class="phm-panel">
					<h2><?php esc_html_e( 'Basics', 'pterodactyl-hosting' ); ?></h2>
					<table class="form-table">
						<tr><th><label for="phm_plan_name"><?php esc_html_e( 'Name', 'pterodactyl-hosting' ); ?></label></th>
							<td><input required class="regular-text" id="phm_plan_name" name="name" value="<?php echo esc_attr( $product->name ); ?>" placeholder="Minecraft — 4 GB"></td></tr>
						<tr><th><?php esc_html_e( 'Slug', 'pterodactyl-hosting' ); ?></th>
							<td><input class="regular-text" name="slug" value="<?php echo esc_attr( $product->slug ); ?>" placeholder="(auto)"></td></tr>
						<tr><th><?php esc_html_e( 'Description', 'pterodactyl-hosting' ); ?></th>
							<td><textarea rows="3" class="large-text" name="description"><?php echo esc_textarea( $product->description ); ?></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Game / nest', 'pterodactyl-hosting' ); ?></th>
							<td>
								<select name="nest_id" id="phm-plan-nest">
									<?php foreach ( $nests as $nest ) : ?>
										<option value="<?php echo (int) $nest->nest_id; ?>" <?php selected( (int) $product->nest_id, (int) $nest->nest_id ); ?>><?php echo esc_html( $nest->name ); ?> (#<?php echo (int) $nest->nest_id; ?>)</option>
									<?php endforeach; ?>
								</select>
							</td></tr>
						<tr><th><?php esc_html_e( 'Egg / server type', 'pterodactyl-hosting' ); ?></th>
							<td>
								<select name="egg_id" id="phm-plan-egg" data-current="<?php echo (int) $product->egg_id; ?>">
									<?php foreach ( $eggs as $egg ) : ?>
										<option data-nest="<?php echo (int) $egg->nest_id; ?>" value="<?php echo (int) $egg->egg_id; ?>" <?php selected( (int) $product->egg_id, (int) $egg->egg_id ); ?>><?php echo esc_html( $egg->name ); ?> (#<?php echo (int) $egg->egg_id; ?>)</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'e.g. Minecraft nest → Paper / Vanilla / Forge / Fabric egg.', 'pterodactyl-hosting' ); ?></p>
							</td></tr>
						<tr><th><?php esc_html_e( 'Location', 'pterodactyl-hosting' ); ?></th>
							<td>
								<select name="location_id">
									<option value="0"><?php esc_html_e( 'Any location', 'pterodactyl-hosting' ); ?></option>
									<?php foreach ( $locations as $loc ) : ?>
										<option value="<?php echo (int) $loc->location_id; ?>" <?php selected( (int) $product->location_id, (int) $loc->location_id ); ?>><?php echo esc_html( $loc->short ); ?> — <?php echo esc_html( $loc->long_description ); ?></option>
									<?php endforeach; ?>
								</select>
							</td></tr>
						<tr><th><?php esc_html_e( 'Active', 'pterodactyl-hosting' ); ?></th>
							<td><label><input type="checkbox" name="active" value="1" <?php checked( $product->active ); ?>> <?php esc_html_e( 'Visible in the store', 'pterodactyl-hosting' ); ?></label></td></tr>
						<tr><th><?php esc_html_e( 'Best Value badge', 'pterodactyl-hosting' ); ?></th>
							<td><label><input type="checkbox" name="featured" value="1" <?php checked( ! empty( $product->featured ) ); ?>> <?php esc_html_e( 'Show the “Best Value” ribbon + gold highlight on this plan in the storefront', 'pterodactyl-hosting' ); ?></label></td></tr>
					</table>
				</div>

				<div class="phm-panel">
					<h2><?php esc_html_e( 'Resources & price', 'pterodactyl-hosting' ); ?></h2>
					<table class="form-table phm-mini-table">
						<tr><th><?php esc_html_e( 'RAM (MB)', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="memory" value="<?php echo (int) $product->memory; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'CPU (%)', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="cpu" value="<?php echo (int) $product->cpu; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Disk (MB)', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="disk" value="<?php echo (int) $product->disk; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Swap (MB)', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="swap" value="<?php echo (int) $product->swap; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'IO weight', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="io" value="<?php echo (int) $product->io; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Databases', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="databases" value="<?php echo (int) $product->databases; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Extra ports', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="extra_allocations" value="<?php echo (int) $product->extra_allocations; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Backups', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="backups" value="<?php echo (int) $product->backups; ?>"></td></tr>
					</table>
					<table class="form-table phm-mini-table">
						<tr><th><?php esc_html_e( 'Price / month', 'pterodactyl-hosting' ); ?></th><td><input type="number" step="0.01" name="price" value="<?php echo esc_attr( $product->price ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Setup fee', 'pterodactyl-hosting' ); ?></th><td><input type="number" step="0.01" name="setup_fee" value="<?php echo esc_attr( $product->setup_fee ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Currency', 'pterodactyl-hosting' ); ?></th><td><input class="small-text" name="currency" value="<?php echo esc_attr( $product->currency ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Stock (−1 = ∞)', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="stock" value="<?php echo (int) $product->stock; ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Sort', 'pterodactyl-hosting' ); ?></th><td><input type="number" name="sort_order" value="<?php echo (int) $product->sort_order; ?>"></td></tr>
					</table>
				</div>
			</div>
			<?php submit_button( $edit ? __( 'Save plan', 'pterodactyl-hosting' ) : __( 'Create plan', 'pterodactyl-hosting' ) ); ?>
		</form>
	</div>
	<?php
	return;
endif;

$products  = PHM_DB::get_products();
$nests_map = [];
foreach ( PHM_DB::get_nests() as $n ) {
	$nests_map[ (int) $n->nest_id ] = $n->name;
}
$eggs_map = [];
foreach ( PHM_DB::get_eggs() as $e ) {
	$eggs_map[ (int) $e->egg_id ] = $e->name;
}
$loc_map = [ 0 => __( 'Any', 'pterodactyl-hosting' ) ];
foreach ( PHM_DB::get_locations() as $l ) {
	$loc_map[ (int) $l->location_id ] = $l->short;
}
?>
<div class="wrap phm-admin">
	<h1>
		<?php esc_html_e( 'Products / Plans', 'pterodactyl-hosting' ); ?>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=phm-products&action=new' ) ); ?>"><?php esc_html_e( 'Add new', 'pterodactyl-hosting' ); ?></a>
	</h1>
	<p class="description"><?php esc_html_e( 'Tip: on the Database Data screen every synced egg has a “Create plan” shortcut that imports the nest/egg metadata for you.', 'pterodactyl-hosting' ); ?></p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Plan', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Game / egg', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Location', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Resources', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Price', 'pterodactyl-hosting' ); ?></th>
				<th><?php esc_html_e( 'Status', 'pterodactyl-hosting' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $products ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No plans yet — add one, or use “Create plan” next to an egg in Database Data.', 'pterodactyl-hosting' ); ?></td></tr>
		<?php else : foreach ( $products as $p ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $p->name ); ?></strong>
					<?php if ( ! empty( $p->featured ) ) : ?><span class="phm-status phm-status-review" style="background:#fdf3d8;color:#996800">★ <?php esc_html_e( 'Best Value', 'pterodactyl-hosting' ); ?></span><?php endif; ?>
					<br><small><?php echo esc_html( $p->slug ); ?></small></td>
				<td><?php echo esc_html( isset( $nests_map[ (int) $p->nest_id ] ) ? $nests_map[ (int) $p->nest_id ] : '—' ); ?> /
					<?php echo esc_html( isset( $eggs_map[ (int) $p->egg_id ] ) ? $eggs_map[ (int) $p->egg_id ] : '—' ); ?></td>
				<td><?php echo esc_html( isset( $loc_map[ (int) $p->location_id ] ) ? $loc_map[ (int) $p->location_id ] : '—' ); ?></td>
				<td><?php echo (int) $p->memory; ?> MB · <?php echo (int) $p->cpu; ?>% · <?php echo (int) $p->disk; ?> MB</td>
				<td><?php echo esc_html( PHM_Plans::format_price( $p->price, $p->currency ) ); ?>/mo</td>
				<td><span class="phm-status phm-status-<?php echo $p->active ? 'success' : 'muted'; ?>"><?php echo $p->active ? esc_html__( 'Active', 'pterodactyl-hosting' ) : esc_html__( 'Hidden', 'pterodactyl-hosting' ); ?></span></td>
				<td class="phm-row-actions">
					<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=phm-products&action=edit&id=' . (int) $p->id ) ); ?>"><?php esc_html_e( 'Edit', 'pterodactyl-hosting' ); ?></a>
					<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_toggle_plan&id=' . (int) $p->id ), 'phm_toggle_plan' ) ); ?>"><?php echo $p->active ? esc_html__( 'Hide', 'pterodactyl-hosting' ) : esc_html__( 'Show', 'pterodactyl-hosting' ); ?></a>
					<a class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Delete this plan?', 'pterodactyl-hosting' ); ?>')" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=phm_delete_plan&id=' . (int) $p->id ), 'phm_delete_plan' ) ); ?>"><?php esc_html_e( 'Delete', 'pterodactyl-hosting' ); ?></a>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>
</div>
<?php
