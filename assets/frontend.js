/**
 * Pterodactyl Hosting Manager — Modern Storefront JS.
 * Handles dependent dropdowns, live subdomain check, coupon validation,
 * seamless checkout, live deployment progress, credentials display, and 1-click copy.
 */
(function () {
	'use strict';

	if (typeof PHM_PUBLIC === 'undefined') return;

	function post(action, fields) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', PHM_PUBLIC.nonce);
		Object.keys(fields || {}).forEach(function (k) {
			if (fields[k] !== undefined && fields[k] !== null) body.append(k, fields[k]);
		});
		return fetch(PHM_PUBLIC.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json && json.data && json.data.refreshed_nonce) {
					PHM_PUBLIC.nonce = json.data.refreshed_nonce;
				}
				return json;
			});
	}

	/* ---------- Clipboard copy helper ---------- */
	var clipboardBound = false;
	function initClipboard() {
		if (clipboardBound) return;
		clipboardBound = true;
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-copy]');
			if (!btn) return;
			e.preventDefault();
			var text = btn.getAttribute('data-copy');
			if (!text) return;

			function flash() {
				var orig = btn.innerHTML;
				btn.innerHTML = '✓ ' + (PHM_PUBLIC.i18n.copied || 'Copied!');
				setTimeout(function () { btn.innerHTML = orig; }, 1500);
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(flash).catch(function () {
					fallbackCopy(text); flash();
				});
			} else {
				fallbackCopy(text); flash();
			}
		});
	}

	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (err) { /* ignore */ }
		document.body.removeChild(ta);
	}

	/* ---------- dependent egg dropdown & price calculation ---------- */
	function initEggSelect(form) {
		var product = form.querySelector('#phm-product');
		var egg = form.querySelector('#phm-egg');
		var desc = form.querySelector('#phm-egg-desc');
		var subtotalEl = form.querySelector('#phm-subtotal');
		var totalEl = form.querySelector('#phm-total');
		var paySection = form.querySelector('#phm-payment-section');
		var freeNote = form.querySelector('#phm-free-note');
		var submitBtn = form.querySelector('#phm-submit');
		var discountLine = form.querySelector('#phm-discount-line');

		if (!product || !egg) return;
		if (submitBtn && !submitBtn.dataset.defaultLabel) submitBtn.dataset.defaultLabel = submitBtn.textContent;

		form.currentCoupon = null;

		function recalculatePrice() {
			var opt = product.options[product.selectedIndex];
			if (!opt) return;

			var rawAmount = parseFloat(opt.dataset.amount || '0');
			var priceStr = opt.dataset.price || '—';
			var isFree = rawAmount <= 0;

			if (subtotalEl) subtotalEl.textContent = priceStr;

			var finalAmount = rawAmount;
			if (form.currentCoupon && form.currentCoupon.valid) {
				var disc = form.currentCoupon.discount_amount || 0;
				finalAmount = Math.max(0, rawAmount - disc);
				if (discountLine) discountLine.style.display = 'flex';
				var discEl = form.querySelector('#phm-discount-amount');
				if (discEl) discEl.textContent = '-$' + parseFloat(disc).toFixed(2);
			} else {
				if (discountLine) discountLine.style.display = 'none';
			}

			if (totalEl) {
				totalEl.textContent = finalAmount <= 0 ? 'FREE' : '$' + parseFloat(finalAmount).toFixed(2);
			}

			var radios = paySection ? paySection.querySelectorAll('input[name="payment_method"]') : [];
			Array.prototype.forEach.call(radios, function (r) { r.required = finalAmount > 0; });
			if (paySection) paySection.hidden = finalAmount <= 0;
			if (freeNote) freeNote.hidden = finalAmount > 0;
			if (submitBtn) {
				submitBtn.textContent = finalAmount <= 0
					? (PHM_PUBLIC.i18n.getFree || 'Deploy Free Server')
					: (submitBtn.dataset.defaultLabel || 'Complete Order & Deploy Server →');
			}
		}

		function refresh() {
			var opt = product.options[product.selectedIndex];
			if (!opt) return;
			var nest = String(opt.dataset.nest || '0');
			var wanted = String(opt.dataset.egg || '');
			var eggs = PHM_PUBLIC.eggsByNest[nest] || [];

			egg.innerHTML = '';
			if (!eggs.length) {
				var o = document.createElement('option');
				o.value = wanted;
				o.textContent = 'Default server software';
				egg.appendChild(o);
			} else {
				eggs.forEach(function (e) {
					var o = document.createElement('option');
					o.value = e.id;
					o.textContent = e.name;
					if (String(e.id) === wanted) o.selected = true;
					egg.appendChild(o);
				});
			}
			updateDesc();
			recalculatePrice();
		}

		function updateDesc() {
			if (!desc) return;
			var opt = egg.options[egg.selectedIndex];
			if (!opt) { desc.textContent = ''; return; }
			var nest = String(product.options[product.selectedIndex].dataset.nest || '0');
			var eggs = PHM_PUBLIC.eggsByNest[nest] || [];
			var found = eggs.filter(function (e) { return String(e.id) === egg.value; })[0];
			desc.textContent = found && found.description ? found.description : '';
		}

		product.addEventListener('change', refresh);
		egg.addEventListener('change', updateDesc);
		form.recalculatePrice = recalculatePrice;
		refresh();
	}

	/* ---------- live coupon code validation ---------- */
	function initCoupon(form) {
		var input = form.querySelector('#phm-coupon-code');
		var btn = form.querySelector('#phm-apply-coupon');
		var status = form.querySelector('#phm-coupon-status');
		var product = form.querySelector('#phm-product');
		if (!input || !btn) return;

		btn.addEventListener('click', function () {
			var code = input.value.trim().toUpperCase();
			if (!code) {
				if (status) {
					status.textContent = 'Please enter a coupon code.';
					status.className = 'phm-hint phm-sub-bad';
				}
				return;
			}

			var opt = product ? product.options[product.selectedIndex] : null;
			var amount = opt ? parseFloat(opt.dataset.amount || '0') : 0;
			var productId = opt ? parseInt(opt.value, 10) : 0;

			btn.disabled = true;
			btn.textContent = '…';

			post('phm_apply_coupon', {
				coupon_code: code,
				product_id: productId,
				amount: amount
			}).then(function (res) {
				if (res && res.success && res.data) {
					form.currentCoupon = res.data;
					if (status) {
						status.textContent = '✅ ' + res.data.message;
						status.className = 'phm-hint phm-sub-good';
					}
					if (form.recalculatePrice) form.recalculatePrice();
				} else {
					form.currentCoupon = null;
					if (status) {
						status.textContent = '❌ ' + (res && res.data && res.data.message ? res.data.message : 'Invalid coupon');
						status.className = 'phm-hint phm-sub-bad';
					}
					if (form.recalculatePrice) form.recalculatePrice();
				}
			}).catch(function () {
				if (status) {
					status.textContent = '❌ ' + (PHM_PUBLIC.i18n.error || 'Could not verify coupon.');
					status.className = 'phm-hint phm-sub-bad';
				}
			}).finally(function () {
				btn.disabled = false;
				btn.textContent = 'Apply';
			});
		});

		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				btn.click();
			}
		});
	}

	/* ---------- live subdomain availability check ---------- */
	function initSubdomain(form) {
		var input = form.querySelector('#phm-subdomain');
		var status = form.querySelector('#phm-subdomain-status');
		if (!input || !status) return;
		var timer;
		var ok = false;

		function state(msg, kind) {
			status.textContent = msg;
			status.className = 'phm-hint phm-sub-' + kind;
		}

		input.addEventListener('input', function () {
			var value = input.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
			if (value !== input.value) input.value = value;
			ok = false;
			if (!value) {
				if (PHM_PUBLIC.subdomainOn && PHM_PUBLIC.subdomainRequired) {
					state(PHM_PUBLIC.i18n.invalid, 'bad');
				} else {
					state('', 'idle');
					ok = true;
				}
				return;
			}
			if (value.length < 3) {
				state(PHM_PUBLIC.i18n.invalid, 'bad');
				return;
			}
			state(PHM_PUBLIC.i18n.checking, 'idle');
			clearTimeout(timer);
			timer = setTimeout(function () {
				post('phm_check_subdomain', { subdomain: value }).then(function (res) {
					if (res && res.success) {
						ok = true;
						state('✅ ' + res.data.fqdn + ' — ' + PHM_PUBLIC.i18n.available, 'good');
					} else {
						var reason = res && res.data && res.data.code === 'invalid' ? PHM_PUBLIC.i18n.invalid : PHM_PUBLIC.i18n.taken;
						state('❌ ' + value + '.' + PHM_PUBLIC.baseDomain + ' — ' + reason, 'bad');
					}
				}).catch(function () {
					state(PHM_PUBLIC.i18n.error, 'bad');
				});
			}, 450);
		});

		form.subdomainOk = function () { return ok || !input.value && !PHM_PUBLIC.subdomainRequired; };
	}

	/* ---------- checkout submit & auto-provisioning ---------- */
	function initCheckout() {
		initClipboard();

		var form = document.getElementById('phm-order-form');
		if (!form || form.dataset.bound) return;
		form.dataset.bound = '1';

		initEggSelect(form);
		initCoupon(form);
		initSubdomain(form);

		var btn = form.querySelector('#phm-submit');
		var wrap = form.closest('.phm-checkout-wrap');
		var result = (wrap && wrap.querySelector('#phm-order-result')) || document.getElementById('phm-order-result');

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();

			if (form.subdomainOk && !form.subdomainOk()) {
				var st = form.querySelector('#phm-subdomain-status');
				if (st && !st.textContent) {
					st.textContent = PHM_PUBLIC.i18n.invalid;
					st.className = 'phm-hint phm-sub-bad';
				}
				return;
			}

			var fields = {};
			Array.prototype.forEach.call(new FormData(form).entries(), function (pair) {
				fields[pair[0]] = pair[1];
			});

			var productEl = form.querySelector('#phm-product');
			var eggEl = form.querySelector('#phm-egg');
			if (productEl) fields.product_id = productEl.value;
			if (eggEl) fields.egg_id = eggEl.value;

			if (!fields.product_id) {
				alert(PHM_PUBLIC.i18n.choosePlan || 'Please choose a plan.');
				return;
			}

			if (btn) {
				btn.disabled = true;
				btn.dataset.label = btn.textContent;
				btn.textContent = '🚀 Deploying…';
			}

			post('phm_place_order', fields).then(function (res) {
				if (res && res.success) {
					if (res.data.redirect) {
						window.location.href = res.data.redirect;
						return;
					}
					var o = res.data.order;
					form.hidden = true;
					if (result && res.data.deploying) {
						startProgress(result, o, !!res.data.already_active);
					} else if (result) {
						result.hidden = false;
						result.className = 'phm-result phm-result-ok';
						result.innerHTML =
							'<div class="phm-deploy-success-card">' +
							'<h3>🎉 Order #' + esc(o.number) + ' Created!</h3>' +
							'<div class="phm-order-details-grid">' +
							'<div><strong>Server:</strong> ' + esc(o.server_name || o.plan) + '</div>' +
							'<div><strong>Plan:</strong> ' + esc(o.plan) + ' (' + esc(o.egg) + ')</div>' +
							'<div><strong>Total:</strong> ' + esc(o.amount) + '</div>' +
							'<div><strong>Status:</strong> ' + esc(o.status_label) + '</div>' +
							'</div>' +
							'<p>Payment instructions have been prepared. Once confirmed, your server will deploy automatically!</p>' +
							'</div>';
						result.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}
				} else if (res && res.data && res.data.code === 'nonce_expired') {
					// Auto retry once with refreshed nonce.
					btn.click();
				} else {
					alert(res && res.data && res.data.message ? res.data.message : PHM_PUBLIC.i18n.error);
				}
			}).catch(function () {
				alert(PHM_PUBLIC.i18n.error);
			}).finally(function () {
				if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Complete Order & Deploy Server →'; }
			});
		});
	}

	/* ---------- live deploy progress bar & credentials modal ---------- */
	function startProgress(result, order, alreadyActive) {
		result.hidden = false;
		result.className = 'phm-result phm-result-ok';
		render(alreadyActive ? 100 : 5, PHM_PUBLIC.i18n.deployTitle || 'Deploying your game server…');
		result.scrollIntoView({ behavior: 'smooth', block: 'center' });

		function render(percent, label) {
			result.innerHTML =
				'<div class="phm-deploy-card">' +
				'<div class="phm-deploy-header">' +
				'<span class="phm-deploy-spinner">⚙️</span>' +
				'<h3>Order #' + esc(order.number) + ' — Deploying Server</h3>' +
				'</div>' +
				'<p class="phm-progress-label">' + esc(label) + ' (' + percent + '%)</p>' +
				'<div class="phm-progress-track"><div class="phm-progress-fill" style="width:' + percent + '%"></div></div>' +
				'<small class="phm-hint">Creating panel container, allocating RAM, and configuring DNS…</small>' +
				'</div>';
		}

		function done(o) {
			var panelBtn = '';
			if (o.panel_login_url) {
				panelBtn = '<a class="phm-btn phm-btn-primary phm-btn-lg phm-btn-block" href="' + esc(o.panel_login_url) + '">' + (PHM_PUBLIC.i18n.openPanel || 'Open Game Panel Console →') + '</a>';
			} else if (o.panel_url) {
				panelBtn = '<a class="phm-btn phm-btn-primary phm-btn-lg phm-btn-block" href="' + esc(o.panel_url) + '" target="_blank" rel="noopener">' + (PHM_PUBLIC.i18n.openPanel || 'Open Game Panel Console →') + '</a>';
			}

			result.innerHTML =
				'<div class="phm-deploy-success-card">' +
				'<div class="phm-success-badge">✓</div>' +
				'<h2>' + (PHM_PUBLIC.i18n.deploySuccess || 'Server Successfully Deployed!') + '</h2>' +
				'<p class="phm-hint">Your game server is online, configured, and ready to play.</p>' +

				'<div class="phm-creds-box">' +
				'<div class="phm-cred-row">' +
				'<span>Server Name:</span>' +
				'<strong>' + esc(o.server_name || o.plan) + '</strong>' +
				'</div>' +
				(o.server_address ?
					'<div class="phm-cred-row">' +
					'<span>Hostname:</span>' +
					'<code>' + esc(o.server_address) + '</code>' +
					'<button type="button" class="phm-copy-btn" data-copy="' + esc(o.server_address) + '">📋 Copy</button>' +
					'</div>' :
					'<div class="phm-cred-row"><span class="phm-hostname-private">' + esc(PHM_PUBLIC.i18n.connectViaPanel || 'Connect through the Game Panel — the server address is private.') + '</span></div>') +
				'<div class="phm-cred-row">' +
				'<span>Panel Username/Email:</span>' +
				'<code>' + esc(o.email) + '</code>' +
				'<button type="button" class="phm-copy-btn" data-copy="' + esc(o.email) + '">📋 Copy</button>' +
				'</div>' +
				'</div>' +

				'<p class="phm-credential-hint">🔑 ' + esc(o.credential_note) + '</p>' +
				panelBtn +
				'</div>';

			result.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		function failed(o, message) {
			result.className = 'phm-result phm-result-bad';
			result.innerHTML =
				'<div class="phm-deploy-failed-card">' +
				'<h3>⚠️ ' + esc(PHM_PUBLIC.i18n.deployFailedTitle || 'Deployment Failed') + '</h3>' +
				'<p><strong>Order:</strong> #' + esc(o.number) + '</p>' +
				(message ? '<p class="phm-hint phm-sub-bad">' + esc(message) + '</p>' : '') +
				'<p>' + esc(PHM_PUBLIC.i18n.deployFailedHint || 'Our team has been notified. Open a support ticket to get immediate assistance.') + '</p>' +
				'</div>';
		}

		var tries = 0;
		(function poll() {
			tries++;
			post('phm_order_status', { order_id: order.id }).then(function (res) {
				if (!res || !res.success) {
					if (tries < 60) { setTimeout(poll, 2500); }
					return;
				}
				var d = res.data;
				if ('active' === d.status) {
					done(d.order);
					return;
				}
				if ('failed' === d.status) {
					failed(d.order, d.error);
					return;
				}
				render(d.percent, d.stage_label);
				if (tries < 120) { setTimeout(poll, 2500); }
			}).catch(function () {
				if (tries < 60) { setTimeout(poll, 2500); }
			});
		})();
	}

	function esc(v) {
		var d = document.createElement('div');
		d.textContent = String(v == null ? '' : v);
		return d.innerHTML;
	}

	var dashRefreshQueued = false;
	function boot() {
		initCheckout();
		initClipboard();
		if (!dashRefreshQueued && document.querySelector('.phm-card-provisioning, .phm-card-paid')) {
			dashRefreshQueued = true;
			setTimeout(function () { window.location.reload(); }, 8000);
		}
	}

	function refreshNonce() {
		post('phm_refresh_nonce', {}).then(function (res) {
			if (res && res.success && res.data && res.data.nonce) {
				PHM_PUBLIC.nonce = res.data.nonce;
			}
		}).catch(function () { /* ignore */ });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			boot();
			refreshNonce();
		});
	} else {
		boot();
		refreshNonce();
	}

	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', function () {
			if (window.elementorFrontend && window.elementorFrontend.hooks) {
				window.elementorFrontend.hooks.addAction('frontend/element_ready/global', boot);
			}
		});
	}
	document.addEventListener('elementor/popup/show', boot);
})();
