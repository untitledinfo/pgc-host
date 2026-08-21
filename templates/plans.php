<?php
/**
 * Plans grid template. Variables from PHM_Frontend::render_plans():
 *   $products (array)  $nests (array)  $locations (array)  $args (array)
 *
 * @package Pterodactyl_Hosting
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Order page URL: auto-created store page first (activation creates one),
// then any existing page with the shortcode, then the site root as a last
// resort. Cached so we don't scan pages on every request.
$order_url = PHM_Store::page_url( 'phm_order' );
if ( ! $order_url ) {
	$order_url = get_transient( 'phm_order_page_url' );
	if ( false === $order_url ) {
		$order_url = '';
		foreach ( get_posts( [ 'post_type' => 'page', 'posts_per_page' => 100, 'fields' => 'ids', 'post_status' => 'publish' ] ) as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'phm_order' ) ) {
				$order_url = get_permalink( $page_id );
				break;
			}
		}
		set_transient( 'phm_order_page_url', $order_url, DAY_IN_SECONDS );
	}
}
if ( ! $order_url ) {
	$order_url = home_url( '/' );
}

$egg_names = [];
foreach ( PHM_DB::get_eggs() as $e ) {
	$egg_names[ (int) $e->egg_id ] = $e->name;
}

$nest_names = [];
foreach ( $nests as $n ) {
	$nest_names[ (int) $n->nest_id ] = $n->name;
}
?>
<div class="phm-store" data-columns="<?php echo (int) $args['columns']; ?>">
	<?php if ( ! $products ) : ?>
		<p class="phm-empty"><?php esc_html_e( 'Plans are being prepared — please check back soon.', 'pterodactyl-hosting' ); ?></p>
	<?php else : ?>
	<?php
	// "Best Value" plans float to the top of the grid.
	usort( $products, function ( $a, $b ) {
		$fa = ! empty( $a->featured ) ? 1 : 0;
		$fb = ! empty( $b->featured ) ? 1 : 0;
		if ( $fa === $fb ) {
			return ( $a->sort_order <=> $b->sort_order ) ?: ( $a->price <=> $b->price );
		}
		return $fb - $fa;
	} );
	?>
	<div class="phm-plan-grid">
		<?php foreach ( $products as $p ) :
			$is_free  = ( (float) $p->price + (float) $p->setup_fee ) <= 0;
			$nest     = isset( $nest_names[ (int) $p->nest_id ] ) ? $nest_names[ (int) $p->nest_id ] : '';
			$egg      = isset( $egg_names[ (int) $p->egg_id ] ) ? $egg_names[ (int) $p->egg_id ] : '';
			$location = ( $p->location_id && isset( $locations[ (int) $p->location_id ] ) ) ? $locations[ (int) $p->location_id ]->short : __( 'Any region', 'pterodactyl-hosting' );
			$out      = ( 0 === (int) $p->stock );
			$cta      = ! empty( $args['button_text'] ) ? $args['button_text'] : ( $is_free ? __( 'Deploy Free Server', 'pterodactyl-hosting' ) : __( 'Configure & Order', 'pterodactyl-hosting' ) );
			?>
			<article class="phm-plan <?php echo $out ? 'is-out' : ''; ?> <?php echo ! empty( $p->featured ) ? 'is-featured' : ''; ?>" data-product="<?php echo (int) $p->id; ?>">
				<?php if ( ! empty( $p->featured ) ) : ?><span class="phm-best-badge">★ <?php esc_html_e( 'Best Value', 'pterodactyl-hosting' ); ?></span><?php endif; ?>
				<header>
					<?php if ( $nest ) : ?><span class="phm-plan-game"><?php echo esc_html( $nest ); ?></span><?php endif; ?>
					<?php if ( $is_free ) : ?><span class="phm-plan-free-tag"><?php esc_html_e( 'Free', 'pterodactyl-hosting' ); ?></span><?php endif; ?>
					<h3><?php echo esc_html( $p->name ); ?></h3>
					<div class="phm-plan-price">
						<strong><?php echo $is_free ? esc_html__( 'Free', 'pterodactyl-hosting' ) : esc_html( PHM_Plans::format_price( $p->price, $p->currency ) ); ?></strong><?php if ( ! $is_free ) : ?><span>/<?php esc_html_e( 'mo', 'pterodactyl-hosting' ); ?></span><?php endif; ?>
						<?php if ( (float) $p->setup_fee > 0 ) : ?><small>+ <?php echo esc_html( PHM_Plans::format_price( $p->setup_fee, $p->currency ) ); ?> <?php esc_html_e( 'setup', 'pterodactyl-hosting' ); ?></small><?php endif; ?>
					</div>
				</header>
				<?php if ( $p->description ) : ?><p class="phm-plan-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $p->description ), 18 ) ); ?></p><?php endif; ?>
				<ul>
					<li><strong><?php echo esc_html( PHM_Plans::format_memory( $p->memory ) ); ?></strong> <?php esc_html_e( 'RAM', 'pterodactyl-hosting' ); ?></li>
					<li><strong><?php echo (int) $p->cpu; ?>%</strong> <?php esc_html_e( 'CPU', 'pterodactyl-hosting' ); ?></li>
					<li><strong><?php echo esc_html( PHM_Plans::format_memory( $p->disk ) ); ?></strong> <?php esc_html_e( 'NVMe', 'pterodactyl-hosting' ); ?></li>
					<li><strong><?php echo (int) $p->backups; ?></strong> <?php esc_html_e( 'backups', 'pterodactyl-hosting' ); ?></li>
					<li><strong><?php echo (int) $p->databases; ?></strong> <?php esc_html_e( 'database(s)', 'pterodactyl-hosting' ); ?></li>
					<?php if ( $egg ) : ?><li><?php esc_html_e( 'Server type:', 'pterodactyl-hosting' ); ?> <strong><?php echo esc_html( $egg ); ?></strong></li><?php endif; ?>
					<li><?php echo esc_html( $location ); ?></li>
					<?php if ( $p->stock > 0 && $p->stock <= 3 ) : ?><li class="phm-low-stock"><?php printf( esc_html__( 'Only %d left', 'pterodactyl-hosting' ), (int) $p->stock ); ?></li><?php endif; ?>
				</ul>
				<?php if ( $out ) : ?>
					<button class="phm-btn phm-btn-muted" disabled><?php esc_html_e( 'Out of stock', 'pterodactyl-hosting' ); ?></button>
				<?php else : ?>
					<a class="phm-btn phm-btn-primary" href="<?php echo esc_url( add_query_arg( 'plan', (int) $p->id, $order_url ) ); ?>"><?php echo esc_html( $cta ); ?></a>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>
<?php
