<?php
/**
 * Elementor "Server & Node Status" Widget.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) :

class PHM_Widget_Status extends \Elementor\Widget_Base {

	public function get_name() {
		return 'phm_status';
	}

	public function get_title() {
		return __( 'Node Status', 'pterodactyl-hosting' );
	}

	public function get_icon() {
		return 'eicon-pulse';
	}

	public function get_categories() {
		return [ 'phm-hosting' ];
	}

	public function get_keywords() {
		return [ 'status', 'uptime', 'node', 'pterodactyl', 'ping' ];
	}

	public function get_style_depends() {
		return [ 'phm-frontend' ];
	}

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Status', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'title', [
			'label'   => __( 'Section Title', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Infrastructure status', 'pterodactyl-hosting' ),
		] );

		$this->end_controls_section();

		PHM_Elementor::add_theme_style_controls( $this );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		PHM_Frontend::enqueue_and_localize();
		$nodes    = PHM_DB::get_nodes();
		$title    = isset( $settings['title'] ) ? $settings['title'] : '';
		?>
		<div class="phm-status-widget-wrap">
			<?php if ( $title ) : ?>
				<h3 class="phm-status-heading"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<div class="phm-status-grid">
				<?php if ( empty( $nodes ) ) : ?>
					<div class="phm-status-card phm-status-all-ok phm-anim">
						<span class="phm-pulse-dot"></span>
						<strong><?php esc_html_e( 'All systems operational', 'pterodactyl-hosting' ); ?></strong>
						<small><?php esc_html_e( 'Nodes will appear here after the first panel sync.', 'pterodactyl-hosting' ); ?></small>
					</div>
				<?php else : ?>
					<?php foreach ( $nodes as $i => $node ) : ?>
						<div class="phm-status-card phm-anim" style="animation-delay: <?php echo esc_attr( ( $i % 8 ) * 0.06 ); ?>s">
							<div class="phm-status-card-top">
								<span class="phm-pulse-dot"></span>
								<strong><?php echo esc_html( $node->name ); ?></strong>
								<span class="phm-badge phm-badge-active"><?php esc_html_e( 'Online', 'pterodactyl-hosting' ); ?></span>
							</div>
							<div class="phm-status-meta">
								<span><?php echo esc_html( sprintf( 'RAM %s / %s', PHM_Plans::format_memory( $node->memory_used ), PHM_Plans::format_memory( $node->memory ) ) ); ?></span>
								<span><?php echo esc_html( sprintf( 'Disk %s / %s', PHM_Plans::format_memory( $node->disk_used ), PHM_Plans::format_memory( $node->disk ) ) ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

endif;
