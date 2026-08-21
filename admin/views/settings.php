<?php
/** @package Pterodactyl_Hosting */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHM_Admin::render_msg();
$s = PHM_Settings::get();
$app_key = PHM_Settings::app_key();
$cf_key  = PHM_Settings::cf_token();
?>
<div class="wrap phm-admin">
	<h1><?php esc_html_e( 'Settings', 'pterodactyl-hosting' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Enter your Pterodactyl Application API key below, click “Save, Test & Auto-Sync” — the connection is tested and all nests, eggs, locations and nodes are pulled into the database and reloaded on screen automatically.', 'pterodactyl-hosting' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="phm-settings-form">
		<input type="hidden" name="action" value="phm_save_settings">
		<?php wp_nonce_field( 'phm_save_settings' ); ?>

		<div class="phm-grid-2">
			<div class="phm-panel">
				<h2><?php esc_html_e( 'Pterodactyl panel', 'pterodactyl-hosting' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="phm_panel_url"><?php esc_html_e( 'Panel URL', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="phm_panel_url" name="phm[panel_url]" value="<?php echo esc_attr( $s['panel_url'] ); ?>" placeholder="https://panel.example.com" <?php echo defined( 'PHM_PANEL_URL' ) ? 'readonly' : ''; ?>>
							<?php if ( defined( 'PHM_PANEL_URL' ) ) : ?><p class="description"><?php esc_html_e( 'Defined in wp-config.php', 'pterodactyl-hosting' ); ?></p><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="phm_app_key"><?php esc_html_e( 'Application API key', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input type="<?php echo defined( 'PHM_APP_KEY' ) ? 'password' : 'text'; ?>" class="regular-text" id="phm_app_key" name="phm[app_api_key]" value="<?php echo esc_attr( PHM_Settings::mask( $app_key ) ); ?>" placeholder="ptla_xxxxxxxxxxxx" <?php echo defined( 'PHM_APP_KEY' ) ? 'readonly' : ''; ?> autocomplete="off">
							<p class="description"><?php esc_html_e( 'Panel → Admin → Application API → create key with ALL read + write scopes. Shown masked; enter a new key to replace.', 'pterodactyl-hosting' ); ?></p>
							<?php if ( defined( 'PHM_APP_KEY' ) ) : ?><p class="description"><?php esc_html_e( 'Defined in wp-config.php', 'pterodactyl-hosting' ); ?></p><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Auto sync', 'pterodactyl-hosting' ); ?></th>
						<td>
							<select name="phm[auto_sync]">
								<?php foreach ( [ 'phm_15min' => __( 'Every 15 minutes', 'pterodactyl-hosting' ), 'hourly' => __( 'Hourly', 'pterodactyl-hosting' ), 'twicedaily' => __( 'Twice daily', 'pterodactyl-hosting' ), 'daily' => __( 'Daily', 'pterodactyl-hosting' ), 'off' => __( 'Off (manual only)', 'pterodactyl-hosting' ) ] as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['auto_sync'], $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Background auto-reload of nests/eggs/locations/nodes.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
				</table>

				<div class="phm-actions-row">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save, Test & Auto-Sync', 'pterodactyl-hosting' ); ?></button>
					<button type="button" class="button" id="phm-test-connection"><?php esc_html_e( 'Test connection (AJAX)', 'pterodactyl-hosting' ); ?></button>
					<button type="button" class="button" id="phm-sync-now"><?php esc_html_e( 'Sync now', 'pterodactyl-hosting' ); ?></button>
				</div>
				<div id="phm-test-result" class="phm-test-result" aria-live="polite"></div>
			</div>

			<div class="phm-panel">
				<h2><?php esc_html_e( 'Cloudflare (subdomain cart)', 'pterodactyl-hosting' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable subdomains', 'pterodactyl-hosting' ); ?></th>
						<td><label><input type="checkbox" name="phm[cf_enabled]" value="1" <?php checked( $s['cf_enabled'] ); ?>> <?php esc_html_e( 'Offer & auto-create customer subdomains at checkout', 'pterodactyl-hosting' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="phm_cf_auth_type"><?php esc_html_e( 'API credential type', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<select id="phm_cf_auth_type" name="phm[cf_auth_type]">
								<option value="token" <?php selected( $s['cf_auth_type'], 'token' ); ?>><?php esc_html_e( 'API Token (recommended)', 'pterodactyl-hosting' ); ?></option>
								<option value="global" <?php selected( $s['cf_auth_type'], 'global' ); ?>><?php esc_html_e( 'Global API Key (email + key)', 'pterodactyl-hosting' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="phm-cf-email-row" <?php echo 'global' === $s['cf_auth_type'] ? '' : 'style="display:none"'; ?>>
						<th><label for="phm_cf_email"><?php esc_html_e( 'Account email', 'pterodactyl-hosting' ); ?></label></th>
						<td><input type="email" class="regular-text" id="phm_cf_email" name="phm[cf_api_email]" value="<?php echo esc_attr( $s['cf_api_email'] ); ?>" placeholder="you@cloudflare-account.com"></td>
					</tr>
					<tr>
						<th><label for="phm_cf_token"><?php esc_html_e( 'API key / token', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="phm_cf_token" name="phm[cf_api_token]" value="<?php echo esc_attr( PHM_Settings::mask( $cf_key ) ); ?>" autocomplete="off" <?php echo defined( 'PHM_CF_TOKEN' ) ? 'readonly' : ''; ?>>
							<p class="description"><?php esc_html_e( 'API Token: My Profile → API Tokens → create with Zone → DNS → Edit. Global key: My Profile → API Tokens → Global API Key.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="phm_cf_zone"><?php esc_html_e( 'Zone ID', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="phm_cf_zone" name="phm[cf_zone_id]" value="<?php echo esc_attr( $s['cf_zone_id'] ); ?>" placeholder="(auto-resolved from base domain)">
							<button type="button" class="button" id="phm-cf-resolve-zone"><?php esc_html_e( 'Find zone ID', 'pterodactyl-hosting' ); ?></button>
							<p class="description"><?php esc_html_e( 'Optional — leave empty and it is resolved & saved automatically from the base domain.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="phm_cf_domain"><?php esc_html_e( 'Base domain', 'pterodactyl-hosting' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="phm_cf_domain" name="phm[cf_base_domain]" value="<?php echo esc_attr( $s['cf_base_domain'] ); ?>" placeholder="pgcmc.fun">
							<p class="description"><?php esc_html_e( 'Customer picks “name” → name.basedomain is created automatically.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Proxy mode', 'pterodactyl-hosting' ); ?></th>
						<td>
							<select name="phm[cf_proxied]">
								<option value="0" <?php selected( (int) $s['cf_proxied'], 0 ); ?>><?php esc_html_e( 'DNS only — grey cloud (required for Minecraft/game traffic)', 'pterodactyl-hosting' ); ?></option>
								<option value="1" <?php selected( (int) $s['cf_proxied'], 1 ); ?>><?php esc_html_e( 'Proxied — orange cloud (HTTP/web panels only)', 'pterodactyl-hosting' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Minecraft SRV record', 'pterodactyl-hosting' ); ?></th>
						<td><label><input type="checkbox" name="phm[cf_create_srv]" value="1" <?php checked( $s['cf_create_srv'] ); ?>> <?php esc_html_e( 'Create _minecraft._tcp SRV record so players connect without a port', 'pterodactyl-hosting' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Required', 'pterodactyl-hosting' ); ?></th>
						<td><label><input type="checkbox" name="phm[cf_subdomain_required]" value="1" <?php checked( $s['cf_subdomain_required'] ); ?>> <?php esc_html_e( 'Every order must choose a subdomain', 'pterodactyl-hosting' ); ?></label></td>
					</tr>
				</table>
			</div>
		</div>

		<div class="phm-grid-2">
			<div class="phm-panel">
				<h2><?php esc_html_e( 'Payments (Paymenter-style manual gateways)', 'pterodactyl-hosting' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Currency', 'pterodactyl-hosting' ); ?></th>
						<td><input type="text" class="small-text" name="phm[default_currency]" value="<?php echo esc_attr( $s['default_currency'] ); ?>" placeholder="USD / PKR"></td>
					</tr>
					<?php
					$gateway_rows = [
						'easypaisa' => __( 'EasyPaisa', 'pterodactyl-hosting' ),
						'jazzcash'  => __( 'JazzCash', 'pterodactyl-hosting' ),
						'bank'      => __( 'Bank transfer', 'pterodactyl-hosting' ),
						'card'      => __( 'Card / international', 'pterodactyl-hosting' ),
					];
					foreach ( $gateway_rows as $gw => $label ) :
						$en = 'pay_' . $gw . '_enabled';
						$dt = 'pay_' . $gw . '_details';
						?>
					<tr>
						<th><label><input type="checkbox" name="phm[<?php echo esc_attr( $en ); ?>]" value="1" <?php checked( $s[ $en ] ); ?>> <?php echo esc_html( $label ); ?></label></th>
						<td><textarea rows="2" class="large-text" name="phm[<?php echo esc_attr( $dt ); ?>]" placeholder="<?php esc_attr_e( 'Account number / IBAN / instructions shown after checkout…', 'pterodactyl-hosting' ); ?>"><?php echo esc_textarea( $s[ $dt ] ); ?></textarea></td>
					</tr>
					<?php endforeach; ?>
				</table>
			</div>

			<div class="phm-panel">
				<h2><?php esc_html_e( 'Billing / renewals', 'pterodactyl-hosting' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Billing period', 'pterodactyl-hosting' ); ?></th>
						<td>
							<select name="phm[billing_period_months]">
								<?php foreach ( [ 1 => __( 'Monthly', 'pterodactyl-hosting' ), 3 => __( 'Quarterly', 'pterodactyl-hosting' ), 6 => __( 'Semi-annual', 'pterodactyl-hosting' ), 12 => __( 'Yearly', 'pterodactyl-hosting' ) ] as $m => $label ) : ?>
									<option value="<?php echo (int) $m; ?>" <?php selected( (int) $s['billing_period_months'], $m ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Auto-suspend overdue', 'pterodactyl-hosting' ); ?></th>
						<td><label><input type="checkbox" name="phm[billing_auto_suspend]" value="1" <?php checked( $s['billing_auto_suspend'] ); ?>> <?php esc_html_e( 'Suspend the server on the panel when a renewal is overdue (daily check)', 'pterodactyl-hosting' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Reminder email', 'pterodactyl-hosting' ); ?></th>
						<td>
							<input type="number" class="small-text" min="0" max="14" name="phm[billing_reminder_days]" value="<?php echo (int) $s['billing_reminder_days']; ?>">
							<p class="description"><?php esc_html_e( 'Days before the due date — 0 disables reminders.', 'pterodactyl-hosting' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="phm-panel">
				<h2><?php esc_html_e( 'Automation & notifications', 'pterodactyl-hosting' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Auto deploy on payment', 'pterodactyl-hosting' ); ?></th>
						<td><label><input type="checkbox" name="phm[auto_deploy_on_paid]" value="1" <?php checked( $s['auto_deploy_on_paid'] ); ?>> <?php esc_html_e( 'Create the server automatically as soon as an order is marked paid', 'pterodactyl-hosting' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Instant deploy (no payment)', 'pterodactyl-hosting' ); ?></th>
						<td><label><input type="checkbox" name="phm[auto_deploy_on_order]" value="1" <?php checked( $s['auto_deploy_on_order'] ); ?>> <?php esc_html_e( 'Deploy immediately when the order is placed (testing / free servers)', 'pterodactyl-hosting' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="phm_webhook"><?php esc_html_e( 'Discord webhook', 'pterodactyl-hosting' ); ?></label></th>
						<td><input type="url" class="regular-text" id="phm_webhook" name="phm[discord_webhook]" value="<?php echo esc_attr( $s['discord_webhook'] ); ?>" placeholder="https://discord.com/api/webhooks/…"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Emails', 'pterodactyl-hosting' ); ?></th>
						<td>
							<label><input type="checkbox" name="phm[notify_email_admin]" value="1" <?php checked( $s['notify_email_admin'] ); ?>> <?php esc_html_e( 'Email admin on new order', 'pterodactyl-hosting' ); ?></label><br>
							<label><input type="checkbox" name="phm[notify_email_customer]" value="1" <?php checked( $s['notify_email_customer'] ); ?>> <?php esc_html_e( 'Email customer (order + server details)', 'pterodactyl-hosting' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="phm_success"><?php esc_html_e( 'Order success text', 'pterodactyl-hosting' ); ?></label></th>
						<td><textarea rows="3" class="large-text" id="phm_success" name="phm[success_page_text]" placeholder="<?php esc_attr_e( 'Optional custom message shown after checkout…', 'pterodactyl-hosting' ); ?>"><?php echo esc_textarea( $s['success_page_text'] ); ?></textarea></td>
					</tr>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Save, Test & Auto-Sync', 'pterodactyl-hosting' ) ); ?>
	</form>

	<div class="phm-panel" style="margin-top:24px">
		<h2><?php esc_html_e( 'Synced database data', 'pterodactyl-hosting' ); ?> <span id="phm-last-sync" class="description">(<?php echo esc_html( PHM_Sync::last_sync_human() ); ?>)</span></h2>
		<div id="phm-db-data">
			<?php require PHM_PATH . 'admin/views/database.php'; ?>
		</div>
	</div>
</div>
<?php
