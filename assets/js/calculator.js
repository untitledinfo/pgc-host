(function ($) {
	'use strict';

	function debounce(fn, wait) {
		var t;
		return function () {
			clearTimeout(t);
			var args = arguments, ctx = this;
			t = setTimeout(function () { fn.apply(ctx, args); }, wait);
		};
	}

	function collectValues() {
		return {
			ram: $('#ptero-ram').val(),
			cpu: $('#ptero-cpu').val(),
			disk: $('#ptero-disk').val(),
			dedicated_ip: $('#ptero-dedicated-ip').is(':checked') ? 1 : 0,
			backups: $('#ptero-backups').val(),
			databases: $('#ptero-databases').val(),
			billing_cycle: $('#ptero-billing-cycle').val(),
			coupon: $('#ptero-coupon').val(),
			nonce: PteroHost.nonce
		};
	}

	function refreshPrice() {
		$.post(PteroHost.ajax_url, $.extend({ action: 'ptero_calculate_price' }, collectValues()))
			.done(function (res) {
				if (res.success) {
					$('#ptero-total-price').text(res.data.price + ' ' + res.data.currency);
				}
			});
	}

	function loadEggs(nestId) {
		var $egg = $('#ptero-egg');
		$egg.prop('disabled', true).html('<option>Loading...</option>');
		$.post(PteroHost.ajax_url, { action: 'ptero_get_eggs', nest_id: nestId, nonce: PteroHost.nonce })
			.done(function (res) {
				$egg.empty();
				if (res.success && res.data.length) {
					res.data.forEach(function (egg) {
						$egg.append($('<option>').val(egg.id).text(egg.name));
					});
					$egg.prop('disabled', false);
				} else {
					$egg.html('<option>No games found</option>');
				}
			});
	}

	$(function () {
		var debouncedRefresh = debounce(refreshPrice, 250);

		$('#ptero-ram').on('input', function () { $('#ptero-ram-val').text($(this).val()); debouncedRefresh(); });
		$('#ptero-cpu').on('input', function () { $('#ptero-cpu-val').text($(this).val()); debouncedRefresh(); });
		$('#ptero-disk').on('input', function () { $('#ptero-disk-val').text($(this).val()); debouncedRefresh(); });
		$('#ptero-backups, #ptero-databases, #ptero-billing-cycle, #ptero-dedicated-ip').on('change', debouncedRefresh);
		$('#ptero-coupon').on('blur', debouncedRefresh);
		$('#ptero-billing-cycle').on('change', function () { $('#ptero-cost-cycle').text($(this).find(':selected').text().toLowerCase()); });

		$('#ptero-nest').on('change', function () {
			var id = $(this).val();
			if (id) loadEggs(id);
		});

		if ($('#ptero-order-form').length) refreshPrice();

		$('#ptero-submit-order').on('click', function () {
			var $btn = $(this), $result = $('#ptero-order-result');
			var name = $('#ptero-server-name').val().trim();
			var location = $('#ptero-location').val();
			var egg = $('#ptero-egg').val();

			if (!name) { $result.html('<p class="ptero-error">Please name your server.</p>'); return; }
			if (!location) { $result.html('<p class="ptero-error">Please choose a location.</p>'); return; }
			if (!egg) { $result.html('<p class="ptero-error">Please choose a game.</p>'); return; }

			var recaptchaToken = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';

			$btn.prop('disabled', true).text('Placing order...');

			$.post(PteroHost.ajax_url, $.extend({
				action: 'ptero_submit_order',
				server_name: name,
				location_id: location,
				egg_id: egg,
				recaptcha_token: recaptchaToken
			}, collectValues()))
				.done(function (res) {
					if (res.success) {
						if (res.data.mode === 'woocommerce' && res.data.checkout_url) {
							window.location.href = res.data.checkout_url;
						} else {
							$result.html('<div class="ptero-success"><p><strong>Order #' + res.data.order_id + ' received!</strong></p><p>' + (res.data.message || '').replace(/\n/g, '<br>') + '</p></div>');
						}
					} else {
						$result.html('<p class="ptero-error">' + res.data + '</p>');
					}
				})
				.fail(function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : 'Something went wrong. Please try again.';
					$result.html('<p class="ptero-error">' + msg + '</p>');
				})
				.always(function () {
					$btn.prop('disabled', false).text('Place Order');
				});
		});
	});
})(jQuery);
