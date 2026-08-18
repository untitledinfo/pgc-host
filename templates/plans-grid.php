<?php if ( ! defined( 'ABSPATH' ) ) exit;
$plans = Ptero_Plans::get_active( 100 );
$cols = max( 1, (int) ( $atts['columns'] ?? 3 ) );
?>
<div class="ptero-plans-grid" style="--ptero-cols: <?php echo (int) $cols; ?>;">
	<?php if ( ! $plans ) : ?>
		<p><?php _e( 'No plans are available right now.', 'ptero-host' ); ?></p>
	<?php endif; ?>
	<?php foreach ( $plans as $plan ) : ?>
		<div class="ptero-plan-card <?php echo $plan->featured ? 'ptero-featured' : ''; ?>">
			<?php if ( $plan->featured ) : ?><span class="ptero-badge"><?php _e( 'Featured', 'ptero-host' ); ?></span><?php endif; ?>
			<?php if ( $plan->image_url ) : ?><img class="ptero-plan-img" src="<?php echo esc_url( $plan->image_url ); ?>" alt="<?php echo esc_attr( $plan->name ); ?>"><?php endif; ?>
			<h3><?php echo esc_html( $plan->name ); ?></h3>
			<?php if ( $plan->description ) : ?><p class="ptero-plan-desc"><?php echo esc_html( $plan->description ); ?></p><?php endif; ?>
			<ul class="ptero-plan-specs">
				<li><?php echo (int) $plan->cpu; ?>% CPU</li>
				<li><?php echo (int) $plan->ram; ?> MB RAM</li>
				<li><?php echo (int) $plan->disk; ?> MB Disk</li>
				<li><?php echo (int) $plan->backups; ?> Backups</li>
				<li><?php echo (int) $plan->databases; ?> Databases</li>
			</ul>
			<div class="ptero-plan-price">
				<select class="ptero-cycle-select">
					<?php foreach ( Ptero_Plans::$cycles as $key => $label ) :
						$price = Ptero_Plans::price_for_cycle( $plan, $key );
						if ( $price === null ) continue; ?>
						<option value="<?php echo esc_attr( $key ); ?>" data-price="<?php echo esc_attr( $price ); ?>"><?php echo esc_html( $label . ' — ' . $plan->currency . ' ' . number_format( $price, 2 ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<input type="text" class="ptero-server-name" placeholder="<?php esc_attr_e( 'Server name (optional)', 'ptero-host' ); ?>">
			<button class="ptero-btn ptero-add-to-cart" data-plan-id="<?php echo (int) $plan->id; ?>"><?php _e( 'Add to Cart', 'ptero-host' ); ?></button>
		</div>
	<?php endforeach; ?>
</div>
<div class="ptero-form-msg" id="ptero-plans-msg" style="display:none;"></div>
<script>
jQuery(function($){
	$('.ptero-add-to-cart').on('click', function(){
		var $card = $(this).closest('.ptero-plan-card');
		var planId = $(this).data('plan-id');
		var cycle = $card.find('.ptero-cycle-select').val();
		var name = $card.find('.ptero-server-name').val();
		var $msg = $('#ptero-plans-msg');
		$.post(PteroHost.ajax_url, { action: 'ptero_cart_add', nonce: PteroHost.nonce, plan_id: planId, billing_cycle: cycle, server_name: name }, function(res){
			$msg.show().text(res.data.message).css('color', res.success ? 'green' : 'red');
		});
	});
});
</script>
