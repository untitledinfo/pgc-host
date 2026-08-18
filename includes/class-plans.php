<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plans / Products — the "config options" builder: name, description, image,
 * thumbnail, CPU/RAM/disk/backups/databases/allocations, and per-cycle
 * pricing (hourly/daily/weekly/monthly/quarterly/yearly), similar to
 * Paymenter's product config options (https://paymenter.org/docs/guides/products/config-options).
 */
class Ptero_Plans {

	private static $instance = null;
	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	public static $cycles = array(
		'hourly'    => 'Hourly',
		'daily'     => 'Daily',
		'weekly'    => 'Weekly',
		'monthly'   => 'Monthly',
		'quarterly' => 'Quarterly',
		'yearly'    => 'Yearly',
	);

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_ptero_save_plan', array( $this, 'save_plan' ) );
		add_action( 'admin_post_ptero_delete_plan', array( $this, 'delete_plan' ) );
		add_shortcode( 'ptero_plans', array( $this, 'render_plans_shortcode' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_media' ) );
	}

	public function menu() {
		add_submenu_page( 'ptero-host', 'Plans', 'Plans', 'manage_options', 'ptero-host-plans', array( $this, 'render_list' ) );
		add_submenu_page( 'ptero-host', 'Services', 'Services', 'manage_options', 'ptero-host-services', array( $this, 'render_list' ) );
	}

	/**
	 * Loads the WP media uploader (wp.media JS frame) only on our own admin
	 * pages, where the plan/service image + thumbnail picker needs it.
	 */
	public function maybe_enqueue_media( $hook_suffix ) {
		if ( strpos( (string) ( $_GET['page'] ?? '' ), 'ptero-host' ) !== false ) {
			wp_enqueue_media();
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ptero_plans';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	public static function get_active( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . " WHERE status = 'active' ORDER BY sort_order ASC, id DESC LIMIT %d", $limit
		) );
	}

	public static function price_for_cycle( $plan, $cycle ) {
		$field = 'price_' . $cycle;
		return isset( $plan->$field ) && $plan->$field !== null ? floatval( $plan->$field ) : null;
	}

	// ---------------------------------------------------------------- Admin

	public function render_list() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		if ( isset( $_GET['edit'] ) ) { $this->render_form( self::get( (int) $_GET['edit'] ) ); return; }
		if ( isset( $_GET['new'] ) ) { $this->render_form( null ); return; }

		global $wpdb;
		$plans = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, id DESC' );
		?>
		<div class="wrap">
			<h1>Plans <a href="<?php echo esc_url( admin_url( 'admin.php?page=ptero-host-plans&new=1' ) ); ?>" class="page-title-action">Add New</a></h1>
			<table class="widefat striped">
				<thead><tr><th>Image</th><th>Name</th><th>CPU/RAM/Disk</th><th>Monthly</th><th>Yearly</th><th>Status</th><th>Actions</th></tr></thead>
				<tbody>
				<?php if ( $plans ) : foreach ( $plans as $p ) : ?>
					<tr>
						<td><?php if ( $p->thumbnail_url ) : ?><img src="<?php echo esc_url( $p->thumbnail_url ); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px;"><?php endif; ?></td>
						<td><strong><?php echo esc_html( $p->name ); ?></strong></td>
						<td><?php echo (int) $p->cpu; ?>% / <?php echo (int) $p->ram; ?>MB / <?php echo (int) $p->disk; ?>MB</td>
						<td><?php echo $p->price_monthly !== null ? esc_html( $p->currency . ' ' . number_format( (float) $p->price_monthly, 2 ) ) : '—'; ?></td>
						<td><?php echo $p->price_yearly !== null ? esc_html( $p->currency . ' ' . number_format( (float) $p->price_yearly, 2 ) ) : '—'; ?></td>
						<td><?php echo esc_html( ucfirst( $p->status ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ptero-host-plans&edit=' . $p->id ) ); ?>">Edit</a> |
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ptero_delete_plan&id=' . $p->id ), 'ptero_delete_plan_' . $p->id ) ); ?>" onclick="return confirm('Delete this plan?');" style="color:#b32d2e;">Delete</a>
						</td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="7">No plans yet. Click "Add New" to create your first hosting plan.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<p style="margin-top:10px;color:#666;">Shortcode: <code>[ptero_plans]</code> — or use the "Plans Grid" / "Pricing Table" Elementor widgets.</p>
		</div>
		<?php
	}

	public function render_form( $plan ) {
		wp_enqueue_media();
		$api = class_exists( 'Ptero_API' ) ? new Ptero_API() : null;
		$locations = array(); $nests = array();
		if ( $api && $api->is_configured() ) {
			$locations = $api->get_locations() ?: array();
			$nests     = $api->get_nests() ?: array();
		}
		$is_edit = (bool) $plan;
		$v = function ( $field, $default = '' ) use ( $plan ) {
			return $plan && isset( $plan->$field ) ? $plan->$field : $default;
		};
		?>
		<div class="wrap">
			<h1><?php echo $is_edit ? 'Edit Plan' : 'Add New Plan'; ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ptero_save_plan' ); ?>
				<input type="hidden" name="action" value="ptero_save_plan">
				<input type="hidden" name="id" value="<?php echo (int) $v( 'id' ); ?>">
				<table class="form-table">
					<tr><th>Name</th><td><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr( $v( 'name' ) ); ?>"></td></tr>
					<tr><th>Description</th><td><textarea name="description" rows="3" class="large-text"><?php echo esc_textarea( $v( 'description' ) ); ?></textarea></td></tr>
					<tr>
						<th>Image</th>
						<td>
							<input type="text" name="image_url" id="ptero_image_url" class="regular-text" value="<?php echo esc_attr( $v( 'image_url' ) ); ?>">
							<button type="button" class="button ptero-media-btn" data-target="ptero_image_url">Choose Image</button>
							<div id="ptero_image_preview"><?php if ( $v( 'image_url' ) ) echo '<img src="' . esc_url( $v( 'image_url' ) ) . '" style="max-width:200px;margin-top:8px;display:block;">'; ?></div>
						</td>
					</tr>
					<tr>
						<th>Thumbnail</th>
						<td>
							<input type="text" name="thumbnail_url" id="ptero_thumb_url" class="regular-text" value="<?php echo esc_attr( $v( 'thumbnail_url' ) ); ?>">
							<button type="button" class="button ptero-media-btn" data-target="ptero_thumb_url">Choose Thumbnail</button>
						</td>
					</tr>
					<tr><th>Nest (game)</th><td>
						<select name="nest_id">
							<option value="">—</option>
							<?php foreach ( $nests as $n ) : ?>
								<option value="<?php echo (int) $n['id']; ?>" <?php selected( $v( 'nest_id' ), $n['id'] ); ?>><?php echo esc_html( $n['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th>Egg</th><td><input type="number" name="egg_id" value="<?php echo (int) $v( 'egg_id' ); ?>" placeholder="Egg ID"></td></tr>
					<tr><th>Location</th><td>
						<select name="location_id">
							<option value="">Auto (best available)</option>
							<?php foreach ( $locations as $l ) : ?>
								<option value="<?php echo (int) $l['id']; ?>" <?php selected( $v( 'location_id' ), $l['id'] ); ?>><?php echo esc_html( $l['short'] . ' — ' . $l['long'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th>CPU (%)</th><td><input type="number" name="cpu" value="<?php echo (int) $v( 'cpu', 100 ); ?>"></td></tr>
					<tr><th>RAM (MB)</th><td><input type="number" name="ram" value="<?php echo (int) $v( 'ram', 1024 ); ?>"></td></tr>
					<tr><th>Disk (MB)</th><td><input type="number" name="disk" value="<?php echo (int) $v( 'disk', 5120 ); ?>"></td></tr>
					<tr><th>Backups</th><td><input type="number" name="backups" value="<?php echo (int) $v( 'backups', 1 ); ?>"></td></tr>
					<tr><th>Databases</th><td><input type="number" name="databases" value="<?php echo (int) $v( 'databases', 1 ); ?>"></td></tr>
					<tr><th>Allocations (ports)</th><td><input type="number" name="allocations" value="<?php echo (int) $v( 'allocations', 1 ); ?>"></td></tr>
					<tr><th>Swap (MB)</th><td><input type="number" name="swap" value="<?php echo (int) $v( 'swap', 0 ); ?>"></td></tr>
					<tr><th colspan="2"><h3>Pricing (leave blank to hide a cycle)</h3></th></tr>
					<?php foreach ( self::$cycles as $key => $label ) : $field = 'price_' . $key; ?>
					<tr><th><?php echo esc_html( $label ); ?> price</th><td><input type="number" step="0.01" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $v( $field ) ); ?>"></td></tr>
					<?php endforeach; ?>
					<tr><th>Setup fee</th><td><input type="number" step="0.01" name="setup_fee" value="<?php echo esc_attr( $v( 'setup_fee', 0 ) ); ?>"></td></tr>
					<tr><th>Currency</th><td><input type="text" name="currency" value="<?php echo esc_attr( $v( 'currency', get_option( 'ptero_currency', 'PKR' ) ) ); ?>" style="width:80px;"></td></tr>
					<tr><th>Stock (blank = unlimited)</th><td><input type="number" name="stock" value="<?php echo esc_attr( $v( 'stock' ) ); ?>"></td></tr>
					<tr><th>Featured</th><td><label><input type="checkbox" name="featured" value="1" <?php checked( $v( 'featured' ), 1 ); ?>> Show as featured/highlighted plan</label></td></tr>
					<tr><th>Sort order</th><td><input type="number" name="sort_order" value="<?php echo (int) $v( 'sort_order', 0 ); ?>"></td></tr>
					<tr><th>Status</th><td>
						<select name="status">
							<option value="active" <?php selected( $v( 'status', 'active' ), 'active' ); ?>>Active</option>
							<option value="hidden" <?php selected( $v( 'status' ), 'hidden' ); ?>>Hidden</option>
						</select>
					</td></tr>
				</table>
				<?php submit_button( $is_edit ? 'Update Plan' : 'Create Plan' ); ?>
			</form>
		</div>
		<script>
		jQuery(function($){
			$('.ptero-media-btn').on('click', function(e){
				e.preventDefault();
				var target = $(this).data('target');
				var frame = wp.media({ title: 'Select Image', multiple: false });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#' + target).val(att.url);
					if (target === 'ptero_image_url') { $('#ptero_image_preview').html('<img src="'+att.url+'" style="max-width:200px;margin-top:8px;display:block;">'); }
				});
				frame.open();
			});
		});
		</script>
		<?php
	}

	public function save_plan() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'ptero_save_plan' );
		global $wpdb;

		$id = (int) ( $_POST['id'] ?? 0 );
		$data = array(
			'name'            => sanitize_text_field( $_POST['name'] ?? '' ),
			'slug'            => sanitize_title( $_POST['name'] ?? '' ) . ( $id ? '' : '-' . wp_rand( 100, 999 ) ),
			'description'     => wp_kses_post( $_POST['description'] ?? '' ),
			'image_url'       => esc_url_raw( $_POST['image_url'] ?? '' ),
			'thumbnail_url'   => esc_url_raw( $_POST['thumbnail_url'] ?? '' ),
			'nest_id'         => ( $_POST['nest_id'] ?? '' ) !== '' ? (int) $_POST['nest_id'] : null,
			'egg_id'          => ( $_POST['egg_id'] ?? '' ) !== '' ? (int) $_POST['egg_id'] : null,
			'location_id'     => ( $_POST['location_id'] ?? '' ) !== '' ? (int) $_POST['location_id'] : null,
			'cpu'             => (int) ( $_POST['cpu'] ?? 100 ),
			'ram'             => (int) ( $_POST['ram'] ?? 1024 ),
			'disk'            => (int) ( $_POST['disk'] ?? 5120 ),
			'backups'         => (int) ( $_POST['backups'] ?? 1 ),
			'databases'       => (int) ( $_POST['databases'] ?? 1 ),
			'allocations'     => (int) ( $_POST['allocations'] ?? 1 ),
			'swap'            => (int) ( $_POST['swap'] ?? 0 ),
			'setup_fee'       => (float) ( $_POST['setup_fee'] ?? 0 ),
			'currency'        => sanitize_text_field( $_POST['currency'] ?? 'PKR' ),
			'stock'           => ( $_POST['stock'] ?? '' ) !== '' ? (int) $_POST['stock'] : null,
			'featured'        => ! empty( $_POST['featured'] ) ? 1 : 0,
			'sort_order'      => (int) ( $_POST['sort_order'] ?? 0 ),
			'status'          => sanitize_text_field( $_POST['status'] ?? 'active' ),
		);
		foreach ( array_keys( self::$cycles ) as $cycle ) {
			$field = 'price_' . $cycle;
			$data[ $field ] = ( $_POST[ $field ] ?? '' ) !== '' ? (float) $_POST[ $field ] : null;
		}

		if ( $id ) {
			unset( $data['slug'] );
			$wpdb->update( self::table(), $data, array( 'id' => $id ) );
		} else {
			$wpdb->insert( self::table(), $data );
			$id = $wpdb->insert_id;
		}

		wp_redirect( admin_url( 'admin.php?page=ptero-host-plans&edit=' . $id . '&saved=1' ) );
		exit;
	}

	public function delete_plan() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'ptero_delete_plan_' . $id );
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ) );
		wp_redirect( admin_url( 'admin.php?page=ptero-host-plans&deleted=1' ) );
		exit;
	}

	// ------------------------------------------------------------- Public

	public function render_plans_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'columns' => 3 ), $atts );
		ob_start();
		include PTEROHOST_PATH . 'templates/plans-grid.php';
		return ob_get_clean();
	}
}
