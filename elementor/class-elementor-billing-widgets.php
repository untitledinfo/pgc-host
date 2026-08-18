<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/** Elementor: drag-and-drop grid of plans with images, specs, and add-to-cart. */
class Ptero_Elementor_Plans_Widget extends Widget_Base {
	public function get_name() { return 'ptero_plans_grid'; }
	public function get_title() { return __( 'Plans Grid (Pricing)', 'ptero-host' ); }
	public function get_icon() { return 'eicon-price-table'; }
	public function get_categories() { return array( 'ptero-host' ); }
	public function get_keywords() { return array( 'plans', 'pricing', 'billing', 'products' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'ptero-host' ), 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'columns', array( 'label' => __( 'Columns', 'ptero-host' ), 'type' => Controls_Manager::SELECT,
			'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ), 'default' => '3' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_section', array( 'label' => __( 'Style', 'ptero-host' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'accent_color', array( 'label' => __( 'Accent Color', 'ptero-host' ), 'type' => Controls_Manager::COLOR, 'default' => '#6c5ce7',
			'selectors' => array( '{{WRAPPER}} .ptero-btn' => 'background-color: {{VALUE}};', '{{WRAPPER}} .ptero-featured' => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo Ptero_Plans::instance()->render_plans_shortcode( array( 'columns' => $settings['columns'] ) );
	}
}

/** Elementor: monthly/yearly pricing table toggle (uses the same plans data). */
class Ptero_Elementor_Pricing_Table_Widget extends Widget_Base {
	public function get_name() { return 'ptero_pricing_table'; }
	public function get_title() { return __( 'Billing Pricing Table', 'ptero-host' ); }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return array( 'ptero-host' ); }
	public function get_keywords() { return array( 'pricing', 'billing', 'table' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'ptero-host' ), 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'default_cycle', array( 'label' => __( 'Default Billing Cycle', 'ptero-host' ), 'type' => Controls_Manager::SELECT,
			'options' => Ptero_Plans::$cycles, 'default' => 'monthly' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$plans = Ptero_Plans::get_active( 50 );
		$settings = $this->get_settings_for_display();
		?>
		<div class="ptero-pricing-table">
			<?php foreach ( $plans as $plan ) :
				$price = Ptero_Plans::price_for_cycle( $plan, $settings['default_cycle'] );
				if ( $price === null ) continue; ?>
				<div class="ptero-price-col <?php echo $plan->featured ? 'ptero-featured' : ''; ?>">
					<?php if ( $plan->thumbnail_url ) : ?><img src="<?php echo esc_url( $plan->thumbnail_url ); ?>" class="ptero-plan-thumb"><?php endif; ?>
					<h4><?php echo esc_html( $plan->name ); ?></h4>
					<div class="ptero-price-amount"><?php echo esc_html( $plan->currency . ' ' . number_format( $price, 2 ) ); ?><span>/<?php echo esc_html( $settings['default_cycle'] ); ?></span></div>
					<ul><li><?php echo (int) $plan->cpu; ?>% CPU</li><li><?php echo (int) $plan->ram; ?>MB RAM</li><li><?php echo (int) $plan->disk; ?>MB Disk</li></ul>
					<button class="ptero-btn ptero-add-to-cart" data-plan-id="<?php echo (int) $plan->id; ?>"><?php _e( 'Order Now', 'ptero-host' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
		<script>
		jQuery(function($){
			$('.ptero-pricing-table .ptero-add-to-cart').on('click', function(){
				var planId = $(this).data('plan-id');
				$.post(PteroHost.ajax_url, { action: 'ptero_cart_add', nonce: PteroHost.nonce, plan_id: planId, billing_cycle: '<?php echo esc_js( $settings['default_cycle'] ); ?>' }, function(res){
					alert(res.data.message);
				});
			});
		});
		</script>
		<?php
	}
}

/** Elementor: support ticket submission form. */
class Ptero_Elementor_Ticket_Widget extends Widget_Base {
	public function get_name() { return 'ptero_ticket_form'; }
	public function get_title() { return __( 'Support Ticket Form', 'ptero-host' ); }
	public function get_icon() { return 'eicon-envelope'; }
	public function get_categories() { return array( 'ptero-host' ); }
	public function get_keywords() { return array( 'ticket', 'support', 'help desk' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'ptero-host' ), 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->end_controls_section();
	}

	protected function render() {
		echo Ptero_Tickets::instance()->render_tickets( array() );
	}
}

/** Elementor: latest blog/news posts styled as billing announcements. */
class Ptero_Elementor_Blog_Widget extends Widget_Base {
	public function get_name() { return 'ptero_blog_posts'; }
	public function get_title() { return __( 'Blog / News Posts', 'ptero-host' ); }
	public function get_icon() { return 'eicon-post-list'; }
	public function get_categories() { return array( 'ptero-host' ); }
	public function get_keywords() { return array( 'blog', 'news', 'posts' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Content', 'ptero-host' ), 'tab' => Controls_Manager::TAB_CONTENT ) );
		$this->add_control( 'count', array( 'label' => __( 'Number of Posts', 'ptero-host' ), 'type' => Controls_Manager::NUMBER, 'default' => 3 ) );
		$this->add_control( 'columns', array( 'label' => __( 'Columns', 'ptero-host' ), 'type' => Controls_Manager::SELECT,
			'options' => array( '1' => '1', '2' => '2', '3' => '3' ), 'default' => '3' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => (int) $settings['count'], 'post_status' => 'publish' ) );
		?>
		<div class="ptero-blog-grid" style="--ptero-cols: <?php echo (int) $settings['columns']; ?>;">
			<?php while ( $q->have_posts() ) : $q->the_post(); ?>
				<a class="ptero-blog-card" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?><div class="ptero-blog-thumb"><?php the_post_thumbnail( 'medium' ); ?></div><?php endif; ?>
					<h4><?php the_title(); ?></h4>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
				</a>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
		<?php
	}
}
