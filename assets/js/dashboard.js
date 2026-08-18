(function ($) {
	'use strict';

	function fetchUsage($card) {
		var orderId = $card.data('order-id');
		$.post(PteroHost.ajax_url, { action: 'ptero_get_usage', order_id: orderId, nonce: PteroHost.nonce })
			.done(function (res) {
				if (!res.success) return;
				var r = res.data.resources || {};
				var cpu = Math.round(r.cpu_absolute || 0);
				var ram = Math.round((r.memory_bytes || 0) / 1024 / 1024);

				$card.find('.ptero-cpu-bar').val(cpu);
				$card.find('.ptero-cpu-text').text(cpu + '%');
				$card.find('.ptero-ram-bar').val(ram);
				$card.find('.ptero-ram-text').text(ram + ' MB');
			});
	}

	$(function () {
		$('.ptero-usage-bars').each(function () {
			var $card = $(this).closest('.ptero-server-card');
			fetchUsage($card);
			setInterval(function () { fetchUsage($card); }, 15000);
		});

		$('.ptero-power-actions button').on('click', function () {
			var $btn = $(this);
			var $card = $btn.closest('.ptero-server-card');
			var orderId = $card.data('order-id');
			var signal = $btn.data('signal');

			$btn.prop('disabled', true);
			$.post(PteroHost.ajax_url, { action: 'ptero_power_action', order_id: orderId, signal: signal, nonce: PteroHost.nonce })
				.done(function (res) {
					if (!res.success) alert(res.data);
				})
				.fail(function (xhr) {
					alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Action failed.');
				})
				.always(function () {
					$btn.prop('disabled', false);
					setTimeout(function () { fetchUsage($card); }, 2000);
				});
		});
	});
})(jQuery);
