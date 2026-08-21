/**
 * Pterodactyl Hosting Manager — admin JS.
 *
 * Flow that powers "enter API key → auto reload of database data":
 *  1. "Test connection (AJAX)"  → test → on success AUTO-runs a full sync
 *  2. "Sync now"                → full sync
 *  Both replace the #phm-db-data panel in place (no page refresh) and
 *  update the "last sync" labels.
 */
(function () {
	'use strict';

	if (typeof PHM_ADMIN === 'undefined') return;

	function post(action, extra) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', PHM_ADMIN.nonce);
		if (extra) {
			Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
		}
		return fetch(PHM_ADMIN.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	function resultBox() {
		var el = document.getElementById('phm-test-result');
		return el;
	}

	function say(html, kind) {
		var el = resultBox();
		if (!el) return;
		el.className = 'phm-test-result phm-' + (kind || 'info');
		el.innerHTML = html;
		el.style.display = 'block';
	}

	function reloadDbData(html, lastSync) {
		var db = document.getElementById('phm-db-data');
		if (db && html) db.innerHTML = html;
		var labels = document.querySelectorAll('#phm-last-sync');
		labels.forEach(function (l) { l.textContent = lastSync || l.textContent; });
	}

	function runSync(triggeredByTest) {
		say('⏳ ' + PHM_ADMIN.i18n.syncing, 'info');
		post('phm_sync_now').then(function (res) {
			if (res && res.success) {
				var c = res.data.counts || {};
				say(
					'✅ ' + PHM_ADMIN.i18n.sync_ok + ' — ' +
					(c.locations || 0) + ' locations · ' +
					(c.nests || 0) + ' nests · ' +
					(c.eggs || 0) + ' eggs · ' +
					(c.nodes || 0) + ' nodes', 'success'
				);
				reloadDbData(res.data.db_html, res.data.last_sync);
			} else {
				say('❌ ' + (res && res.data && res.data.message ? res.data.message : PHM_ADMIN.i18n.failed), 'error');
			}
		}).catch(function () {
			say('❌ ' + PHM_ADMIN.i18n.failed, 'error');
		});
	}

	var testBtn = document.getElementById('phm-test-connection');
	if (testBtn) {
		testBtn.addEventListener('click', function () {
			testBtn.disabled = true;
			say('⏳ ' + PHM_ADMIN.i18n.testing, 'info');
			post('phm_test_connection').then(function (res) {
				testBtn.disabled = false;
				if (res && res.success) {
					var p = res.data.panel || {};
					var d = p.detail || {};
					var html = '✅ <strong>' + PHM_ADMIN.i18n.ok + '</strong> — ' + (p.message || '') + '<br>';
					html += 'Nodes: ' + (d.nodes >= 0 ? d.nodes : '?') + ' · Locations: ' + (d.locations >= 0 ? d.locations : '?') +
						' · Nests: ' + (d.nests >= 0 ? d.nests : '?') + ' · Servers: ' + (d.servers >= 0 ? d.servers : '?');
					if (res.data.cf) {
						html += '<br>☁️ Cloudflare: ' + (res.data.cf.ok ? '✅ ' : '❌ ') + (res.data.cf.message || '');
					}
					if (res.data.synced) {
						html += '<br>🔄 ' + PHM_ADMIN.i18n.sync_ok;
					} else if (res.data.sync_error) {
						html += '<br>⚠️ Sync: ' + res.data.sync_error;
					}
					say(html, 'success');
					reloadDbData(res.data.db_html, res.data.last_sync);
				} else {
					var msg = (res && res.data && res.data.panel && res.data.panel.message) || PHM_ADMIN.i18n.failed;
					say('❌ ' + msg, 'error');
				}
			}).catch(function () {
				testBtn.disabled = false;
				say('❌ ' + PHM_ADMIN.i18n.failed, 'error');
			});
		});
	}

	var syncBtn = document.getElementById('phm-sync-now');
	if (syncBtn) {
		syncBtn.addEventListener('click', function () {
			syncBtn.disabled = true;
			runSync();
			setTimeout(function () { syncBtn.disabled = false; }, 6000);
		});
	}

	// Auto-test + auto-sync right after settings were saved with a fresh key.
	if (/phm_msg=settings_saved$/.test(window.location.search)) {
		if (testBtn && document.getElementById('phm_app_key')) {
			testBtn.click();
		}
	}

	// Cloudflare: auth type toggle (show email field for Global API Key).
	var cfType = document.getElementById('phm_cf_auth_type');
	if (cfType) {
		cfType.addEventListener('change', function () {
			var row = document.getElementById('phm-cf-email-row');
			if (row) row.style.display = cfType.value === 'global' ? '' : 'none';
		});
	}

	// Cloudflare: "Find zone ID" — resolves the zone from the base domain
	// via the saved credentials and fills + saves the zone id automatically.
	var cfResolve = document.getElementById('phm-cf-resolve-zone');
	if (cfResolve) {
		cfResolve.addEventListener('click', function () {
			var zoneInput = document.getElementById('phm_cf_zone');
			var domainInput = document.getElementById('phm_cf_domain');
			cfResolve.disabled = true;
			post('phm_cf_resolve_zone', { domain: domainInput ? domainInput.value : '' }).then(function (res) {
				cfResolve.disabled = false;
				if (res && res.success) {
					if (zoneInput) zoneInput.value = res.data.zone_id;
					say('✅ ' + res.data.message, 'success');
				} else {
					say('❌ ' + (res && res.data && res.data.message ? res.data.message : PHM_ADMIN.i18n.failed) + ' — ' + 'save your credentials first, then retry.', 'error');
				}
			}).catch(function () {
				cfResolve.disabled = false;
				say('❌ ' + PHM_ADMIN.i18n.failed, 'error');
			});
		});
	}

	// Products: egg dropdown follows the selected nest (game).
	var nestSelect = document.getElementById('phm-plan-nest');
	var eggSelect = document.getElementById('phm-plan-egg');
	if (nestSelect && eggSelect && !eggSelect.dataset.bound) {
		eggSelect.dataset.bound = '1';
		var current = eggSelect.dataset.current || '';
		function filterEggs() {
			var nest = nestSelect.value;
			var firstVisible = null;
			Array.prototype.forEach.call(eggSelect.options, function (opt) {
				var show = !opt.dataset.nest || opt.dataset.nest === nest;
				opt.hidden = !show;
				if (show && !firstVisible) firstVisible = opt;
			});
			var keep = Array.prototype.some.call(eggSelect.options, function (opt) {
				return opt.value === eggSelect.value && !opt.hidden;
			});
			if (!keep) {
				if (Array.prototype.some.call(eggSelect.options, function (opt) { return opt.value === current && !opt.hidden; })) {
					eggSelect.value = current;
				} else if (firstVisible) {
					eggSelect.value = firstVisible.value;
				}
			}
		}
		nestSelect.addEventListener('change', function () {
			current = '';
			filterEggs();
		});
		filterEggs();
	}
})();
