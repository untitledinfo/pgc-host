/**
 * Shared front-end helpers for the billing system (plans, cart, checkout,
 * tickets, invoices, auth). Individual shortcodes/widgets add their own
 * small inline scripts that talk to the same window.PteroHost object
 * (ajax_url + nonce), localized in pterodactyl-hosting.php.
 */
(function ($) {
	'use strict';
	if (typeof PteroHost === 'undefined') return;

	// Simple helper other scripts can reuse for consistent AJAX error display.
	window.PteroHostShowMsg = function ($el, res) {
		if (!$el || !$el.length) return;
		$el.show().text(res && res.data && res.data.message ? res.data.message : '').css('color', res && res.success ? 'green' : 'red');
	};
})(jQuery);
