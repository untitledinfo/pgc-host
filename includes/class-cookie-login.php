<?php
/**
 * Two related "stay logged in" features:
 *
 * 1. Longer "Remember Me" sessions — WordPress's own login form (and its
 *    "Remember Me" checkbox) already exists on wp-login.php; this just
 *    extends how long that cookie lasts, from core's 14-day default to a
 *    site-configurable number of days.
 *
 * 2. One-click panel access ("phm_panel_login") — a pseudo-SSO convenience
 *    link. Pterodactyl's Application API has no endpoint to open a login
 *    session on the panel for someone else (that's a deliberate limitation
 *    of the panel itself, not something a WordPress plugin can work
 *    around) — true cookie-based single sign-on would require a change on
 *    the panel side, outside this plugin.
 *
 *    What this DOES do, safely, from WordPress alone: confirm the visitor
 *    is logged in here, mint a fresh one-time panel password for their
 *    already-linked panel account via the Application API, and hand it to
 *    them on a short-lived reveal screen with a "Continue to panel login"
 *    link — one extra click instead of a password reset email, and no
 *    second password to look up. The one-time password is never written
 *    to a log or email, only held in a transient for a few minutes.
 *
 * @package Pterodactyl_Hosting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PHM_Cookie_Login {

	const REVEAL_TTL = 3 * MINUTE_IN_SECONDS;

	public static function init() {
		add_filter( 'auth_cookie_expiration', [ __CLASS__, 'extend_remember_me' ], 10, 3 );
		add_action( 'admin_post_phm_panel_login', [ __CLASS__, 'handle_panel_login' ] );
		// Without this, a logged-out visitor hitting the URL gets WordPress's
		// bare "0" instead of our friendly "please log in first" message.
		add_action( 'admin_post_nopriv_phm_panel_login', [ __CLASS__, 'handle_panel_login' ] );
		add_shortcode( 'phm_panel_login', [ __CLASS__, 'shortcode' ] );
	}

	/**
	 * Only lengthens sessions where the visitor actually ticked
	 * "Remember Me" — a normal (non-remembered) login is untouched.
	 */
	public static function extend_remember_me( $length, $user_id, $remember ) {
		if ( ! $remember ) {
			return $length;
		}
		/**
		 * Filter how many days a "Remember Me" session lasts (default 30,
		 * up from WordPress core's default 14).
		 */
		$days = (int) apply_filters( 'phm_remember_me_days', 30 );
		$days = max( 1, $days );
		return $days * DAY_IN_SECONDS;
	}

	/**
	 * URL for the "Open game panel" / "Go to Server" one-click link, or ''
	 * if this visitor isn't logged in / doesn't have a linked panel account
	 * yet.
	 *
	 * BUG FIX: this used to always land the customer on the bare panel
	 * homepage, forcing them to hunt through "My Servers" themselves even
	 * when they clicked "Go to Server" on one specific server. Pass that
	 * server's Pterodactyl identifier (order->server_identifier) and the
	 * link now deep-links straight to that server's console
	 * (panel_url/server/{identifier}) after the one-click login reveal.
	 *
	 * @param string $server_identifier Optional Pterodactyl server identifier to deep-link to.
	 */
	public static function url_for_current_user( $server_identifier = '' ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( ! PHM_DB::get_ptero_user_id_for_wp_user( get_current_user_id() ) ) {
			return '';
		}
		$args = [ 'action' => 'phm_panel_login' ];
		if ( $server_identifier ) {
			$args['sid'] = sanitize_text_field( $server_identifier );
		}
		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			'phm_panel_login_' . get_current_user_id()
		);
	}

	public static function shortcode() {
		$url = self::url_for_current_user();
		if ( ! $url ) {
			return '';
		}
		return '<a class="phm-btn phm-btn-primary" href="' . esc_url( $url ) . '">'
			. esc_html__( 'Open Game Panel', 'pterodactyl-hosting' ) . '</a>';
	}

	/**
	 * admin-post.php handler. Logged-in only (admin-post.php runs it for
	 * both nopriv and priv, but we require a real session here).
	 */
	public static function handle_panel_login() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in first.', 'pterodactyl-hosting' ) );
		}
		$user_id = get_current_user_id();
		check_admin_referer( 'phm_panel_login_' . $user_id );

		if ( ! PHM_Settings::is_configured() ) {
			wp_die( esc_html__( 'The hosting panel is not configured yet.', 'pterodactyl-hosting' ) );
		}

		$ptero_user_id = PHM_DB::get_ptero_user_id_for_wp_user( $user_id );
		if ( ! $ptero_user_id ) {
			wp_die( esc_html__( "We couldn't find a game panel account linked to your WordPress account yet — place an order first.", 'pterodactyl-hosting' ) );
		}

		$current = PHM_API::get_user( $ptero_user_id );
		if ( ! $current['ok'] || empty( $current['data']['attributes'] ) ) {
			PHM_DB::log( 'error', sprintf( 'One-click panel access: could not read panel user #%d — %s', $ptero_user_id, $current['error'] ) );
			wp_die( esc_html__( "Couldn't reach the game panel right now — please try again shortly.", 'pterodactyl-hosting' ) );
		}
		$attrs = $current['data']['attributes'];

		$password = wp_generate_password( 20, true, true );
		$result   = PHM_API::update_user_password( $ptero_user_id, $password );
		if ( is_wp_error( $result ) ) {
			PHM_DB::log( 'error', sprintf( 'One-click panel access: password mint failed for panel user #%d — %s', $ptero_user_id, $result->get_error_message() ) );
			wp_die( esc_html__( "Couldn't prepare panel access right now — please try again shortly.", 'pterodactyl-hosting' ) );
		}

		// Optional: which server this click was for, so the reveal screen's
		// "Continue to panel" button lands the customer straight on THAT
		// server's console page instead of the bare panel homepage.
		$sid = isset( $_GET['sid'] ) ? sanitize_text_field( wp_unslash( $_GET['sid'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$token = wp_generate_password( 32, false );
		set_transient( 'phm_panel_reveal_' . $token, [
			'username' => $attrs['username'],
			'email'    => $attrs['email'],
			'password' => $password,
			'user_id'  => $user_id,
			'sid'      => $sid,
		], self::REVEAL_TTL );

		PHM_DB::log( 'success', sprintf( 'One-click panel access used by WP user #%d (panel user #%d).', $user_id, $ptero_user_id ) );

		self::render_reveal( $token );
		exit;
	}

	/**
	 * Standalone reveal screen — deliberately not routed through a theme
	 * template, so it renders the same regardless of the active theme and
	 * never gets swept up by a page cache.
	 */
	private static function render_reveal( $token ) {
		$data = get_transient( 'phm_panel_reveal_' . $token );
		delete_transient( 'phm_panel_reveal_' . $token ); // one-time reveal — consumed on display.

		if ( ! $data || (int) $data['user_id'] !== get_current_user_id() ) {
			wp_die( esc_html__( 'This panel access link has expired. Please click "Open Game Panel" again.', 'pterodactyl-hosting' ) );
		}

		nocache_headers();
		// BUG FIX ("Go to Server" landed on the panel homepage): when this
		// reveal was reached via a specific server's button, send the
		// customer straight to that server's console instead of making them
		// hunt for it themselves.
		$panel_url = PHM_Settings::panel_url();
		$go_url    = ! empty( $data['sid'] ) ? $panel_url . '/server/' . rawurlencode( $data['sid'] ) : $panel_url;
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php esc_html_e( 'Game Panel Access', 'pterodactyl-hosting' ); ?></title>
<style>
	body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0f1115;color:#e6e8ec;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:20px}
	.card{background:#171a21;border:1px solid #262b36;border-radius:12px;padding:32px;max-width:420px;width:100%}
	h1{font-size:18px;margin:0 0 8px}
	p{color:#9aa2b1;font-size:14px;line-height:1.5}
	.row{display:flex;align-items:center;gap:8px;background:#0f1115;border:1px solid #262b36;border-radius:8px;padding:10px 12px;margin:10px 0;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px}
	.row span{flex:1;overflow:hidden;text-overflow:ellipsis}
	button.copy{background:#262b36;color:#e6e8ec;border:0;border-radius:6px;padding:6px 10px;cursor:pointer;font-size:12px}
	button.copy:hover{background:#323848}
	a.go{display:block;text-align:center;margin-top:18px;background:#5865f2;color:#fff;text-decoration:none;padding:12px;border-radius:8px;font-weight:600}
	a.go:hover{background:#4752c4}
	.hint{margin-top:14px;font-size:12px}
</style>
</head>
<body>
	<div class="card">
		<h1><?php esc_html_e( 'Your game panel login', 'pterodactyl-hosting' ); ?></h1>
		<p><?php esc_html_e( 'A one-time panel password was just set for you. Copy it, then continue to the panel — this reveal will not be shown again.', 'pterodactyl-hosting' ); ?></p>
		<div class="row"><span><?php echo esc_html( $data['email'] ); ?></span><button class="copy" data-copy="<?php echo esc_attr( $data['email'] ); ?>"><?php esc_html_e( 'Copy', 'pterodactyl-hosting' ); ?></button></div>
		<div class="row"><span id="phm-pw"><?php echo esc_html( $data['password'] ); ?></span><button class="copy" data-copy="<?php echo esc_attr( $data['password'] ); ?>"><?php esc_html_e( 'Copy', 'pterodactyl-hosting' ); ?></button></div>
		<a class="go" target="_blank" rel="noopener" href="<?php echo esc_url( $go_url ); ?>"><?php esc_html_e( 'Continue to panel login →', 'pterodactyl-hosting' ); ?></a>
		<p class="hint"><?php esc_html_e( 'Tip: setting a memorable panel password? Changing your WordPress password will automatically update this panel password too.', 'pterodactyl-hosting' ); ?></p>
	</div>
	<script>
	document.querySelectorAll('button.copy').forEach(function(btn){
		btn.addEventListener('click', function(){
			var v = btn.getAttribute('data-copy');
			navigator.clipboard && navigator.clipboard.writeText(v).then(function(){
				var old = btn.textContent; btn.textContent = '<?php echo esc_js( __( 'Copied', 'pterodactyl-hosting' ) ); ?>';
				setTimeout(function(){ btn.textContent = old; }, 1200);
			});
		});
	});
	</script>
</body>
</html>
		<?php
	}
}
