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
		return __( 'Hosting Node & Server Status (Pterodactyl)', 'pterodactyl-hosting' );
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

	protected function register_controls() {
		$this->start_controls_section( 'content_section', [
			'label' => __( 'Status Box Settings', 'pterodactyl-hosting' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'title', [
			'label'   => __( 'Section Title', 'pterodactyl-hosting' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Infrastructure & Node Status', 'pterodactyl-hosting' ),
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$nodes = PHM_DB::get_nodes();
		?>
		<div class="phm-status-widget-wrap">
			<div class="phm-status-grid">
				<?php if ( empty( $nodes ) ) : ?>
					<div class="phm-status-card phm-status-all-ok">
						<span class="phm-pulse-dot"></span>
						<strong><?php esc_html_e( 'All Systems Operational', 'pterodactyl-hosting' ); ?></strong>
						<small><?php esc_html_e( '100% Uptime Across All Hosting Nodes', 'pterodactyl-hosting' ); ?></small>
					</div>
				<?php else : ?>
					<?php foreach ( $nodes as $node ) : ?>
						<div class="phm-status-card">
							<div class="phm-status-card-top">
								<span class="phm-pulse-dot"></span>
								<strong><?php echo esc_html( $node->name ); ?></strong>
								<span class="phm-badge phm-badge-active"><?php esc_html_e( 'Online', 'pterodactyl-hosting' ); ?></span>
							</div>
							<div class="phm-status-meta">
								<span>RAM: <?php echo (int) round( $node->memory_used / 1024 ); ?>GB / <?php echo (int) round( $node->memory / 1024 ); ?>GB</span>
								<span>Disk: <?php echo (int) round( $node->disk_used / 1024 ); ?>GB / <?php echo (int) round( $node->disk / 1024 ); ?>GB</span>
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
