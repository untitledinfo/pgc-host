# Pterodactyl Hosting Manager (v3.1.0)

## What's new in 3.1.0 — deploy reliability, theme-aware UI, no public IPs, Elementor Theme Builder

- **Deploy actually runs.** Free (and auto-deploy) orders no longer sit on “Queued” forever when WP-Cron is disabled. Deploy now runs in the checkout request, on shutdown, *and* is kicked again from the live progress poll if it still has no server. A lock prevents double-create.
- **Free server create fixed.** $0 plans deploy immediately, skip payment emails, never get a due date, and are never auto-suspended. RAM/disk under 1 GB no longer render as “0 GB”.
- **Node capacity math fixed.** Pterodactyl’s `memory_overallocate` / `disk_overallocate` are percents, not extra MB — this was rejecting valid nodes with “No node has enough free RAM/disk”. Public-only node filtering also no longer blocks panels that mark every node as not-public.
- **Egg variables actually sync.** Pagination now keeps API `relationships`, so Paper/Minecraft env vars (and a live egg fetch at deploy time) are sent with `create server`. Docker image / startup are pulled live if the local cache is empty.
- **Node IPs are never shown to customers.** Dashboard, track page, deploy success, REST API, and customer emails show hostname/subdomain only (or “connect via Game Panel”). Staff still see IPs in wp-admin.
- **Theme matches WordPress / Elementor Theme Builder.** Storefront CSS inherits Elementor global colors, WordPress theme presets, and the page’s own font/color instead of forcing a dark cyberpunk skin. Cards, forms, and buttons work on light *and* dark themes.
- **Animations.** Plan/service cards fade up, status dots pulse, deploy progress shimmers, success check pops. Respects `prefers-reduced-motion`.
- **Elementor addons.** Seven widgets in the **PGC Hosting** category (Plans, Checkout, Dashboard, Tickets, Node Status, Track Order, Open Game Panel), each with Theme Builder style controls (global primary/text/card colors, typography, buttons, radius). Assets load in the editor and preview.
- **Full hosting dashboard.** My Services + Billing + Support tabs: specs, hostname, plan, next due / “Free — no renewal”, manage-in-panel, tickets. Dashboard auto-refreshes while a server is deploying.

## What's new in 2.8.0 — customer dashboard, support tickets, "Go to Server" fixed

- **New customer dashboard (`[phm_dashboard]`, auto-created as "My Account").**
  Logged-in customers now get one page with two tabs:
  - **My Servers** — every server tied to their account, with status,
    address, renewal date, and a **Go to Server** button per server.
  - **Support Tickets** — open a new ticket (optionally linked to one of
    their servers), see all their tickets, and reply in a full
    message thread.
- **"Go to Server" bug fixed.** The one-click panel button (order
  tracking, deploy-success screen, and now the dashboard) used to always
  land customers on the bare panel homepage, no matter which server they
  clicked from. It now deep-links straight to that specific server's
  console (`panel_url/server/{identifier}`) via `PHM_Cookie_Login`.
- **New support ticket system**, end to end:
  - Customers open/reply from the dashboard; status flows
    open → answered → customer-reply → closed automatically based on
    who replied last.
  - Staff manage tickets from a new **PGC Hosting → Support Tickets**
    admin screen (list with status filters + unread badge in the admin
    menu, full thread view, reply-and-close in one action).
  - Email + Discord notifications on new tickets and every reply, reusing
    the existing `notify_email_admin` / `notify_email_customer` /
    `discord_webhook` settings.

## What's new in 2.7.0 — password stays in sync, one-click panel access, longer logins

Three additions, all building on the existing "one password for WordPress
and the panel" idea from 2.4.0:

- **Panel password now stays in sync after the fact.** 2.4.0 only matched
  the panel password at the moment a panel account was first created —
  if the customer changed their WordPress password afterwards (via
  **Forgot password**, or from their WordPress profile screen), the panel
  password silently stopped matching. `PHM_Password_Sync` now hooks both
  of those and pushes the new password onto their already-linked panel
  account automatically, so "one login for both" stays true indefinitely,
  not just on day one. No-ops entirely for anyone who has never ordered.
- **"Open Game Panel" one-click access.** Logged-in customers with a
  linked panel account now get a real button (order confirmation, order
  tracking, and the `[phm_panel_login]` shortcode) instead of a bare link
  to the panel's own login page. Clicking it mints a fresh one-time panel
  password via the API and hands it to them on a short reveal screen with
  copy buttons and a "Continue to panel" link.
  ⚠️ **This is not true SSO** — Pterodactyl's API has no endpoint to open a
  login session on the panel for someone else, so it can't skip the
  panel's own login form entirely. That would need a change on the panel
  side, which is outside this plugin. What it removes is the password
  *reset* step: no email round-trip, no separate "forgot password" flow.
  The one-time password is shown once, never logged, never emailed, and
  the reveal transient is deleted the instant it's displayed.
- **Longer "Remember Me" sessions.** WordPress's login form already has a
  built-in "Remember Me" checkbox (core, not new) — checking it now keeps
  you signed in for 30 days instead of core's default 14. Configurable:
  ```php
  add_filter( 'phm_remember_me_days', function () { return 45; } );
  ```

## What's new in 2.4.1 — free plans skip payment entirely

Plans priced at $0 (price + setup fee) no longer show the "Payment method"
step at all, and no longer require picking one:

- The Payment method section hides itself live as soon as a $0 plan is
  selected (and reappears if you switch to a paid one) — no page reload.
- The submit button changes to "Get free server".
- On submit, the order is created, immediately marked paid, and deployed
  right away — regardless of the site's normal manual-payment / auto-deploy
  setting, since there's nothing to confirm.

## What's new in 2.4.0 — order with your WordPress account

Checkout no longer asks for a name/email (or a second password) — it now
requires you to be logged into WordPress, reads your name + email straight
from your WP account, and the Pterodactyl panel account we create for you
reuses your **WordPress password**, so there's one login for both the
website and the game panel.

- **Login required to order.** `[phm_order]` now shows a "please log in /
  create an account" screen for logged-out visitors instead of the form.
- **"Your account" section** replaces the old name/email fields — it just
  confirms who you're ordering as, with a "Not you?" logout link.
- **Password bridge** (`PHM_Password_Bridge`): captures your plaintext
  password for ~15 minutes at the moment you log in or register (the only
  moment WordPress ever has it), stores it only long enough for the very
  next server deployment to use it as the panel password, then deletes it.
  If that window has lapsed by the time your order deploys (e.g. you
  ordered a while after logging in, or payment took a while to confirm),
  it falls back to the previous behavior — an auto-generated panel
  password, with "use Reset Password on first login" in the deploy email.
- **"Name your server"** field replaces "Full name" when subdomains aren't
  enabled — the plan/subdomain already carries a name when they are.

  ⚠️ **Security trade-off, please read**: the captured password sits in a
  WordPress transient (`wp_options`/object cache) for up to 15 minutes,
  unencrypted. That's the only way to know a plaintext password WordPress
  itself never stores. If that's not an acceptable trade-off for your site,
  say so and I'll switch this back to the previous behavior (random panel
  password, emailed reset link) with the account auto-fill kept.

## What's new in 2.3.3 — "Please enter a valid name and email" with no indication which field

The checkout `<form>` had `novalidate` on it, which turns off the browser's
built-in required-field/email-format checking. That meant tapping *Place
order* with an empty "Full name" field skipped straight past any inline
warning, round-tripped to the server, and only then showed a generic alert
— with nothing pointing at the actual empty field.

Fix:

- Removed `novalidate` from the order form, so the browser now stops
  immediately, scrolls to, and highlights the specific empty/invalid field
  (native "Please fill out this field" / "Please include an '@'…" UI) before
  anything is sent to the server.
- Added a matching JS safety-net check right before submit as defense in
  depth.
- Scoped `#phm-total` and `#phm-order-result` lookups to the actual form
  instance instead of a bare `document.getElementById`, so totals/results
  stay correct even if the `[phm_order]` shortcode is ever placed more than
  once on the same page.

## What's new in 2.3.2 — "Please choose a plan." with a plan clearly selected

The 2.3.1 fix made the underlying failure visible instead of hiding it
behind a generic message — and that surfaced the *real* bug: the checkout
form was reading `product_id` from a generic `FormData(form)` walk keyed
only by field name. If the order page ever renders more than once on the
same view (a shortcode used twice, a theme/Elementor duplicate, a builder
preview, etc.), a later `product_id`/`egg_id` field can silently overwrite
the real, visibly-selected one with an empty value — the customer sees a
plan and a correct total, but the value that reaches the server is blank.

Fix: `frontend.js` no longer trusts `FormData` for `product_id`/`egg_id`.
It now reads both values directly off the actual `<select>` elements inside
the bound form immediately before submitting, and stops with a clear inline
message if a plan genuinely isn't selected — instead of a confusing round
trip to the server.

## What's new in 2.3.1 — "This plan is not available" at checkout

Root cause: the storefront (`[phm_plans]`) and checkout (`[phm_order]`) pages
show live plan data (price, active/hidden, stock), but on hosts running a
page cache (LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache, or a
host-level cache like the one used on shared cPanel/EasyPaisa-style hosts)
a visitor could be served a **stale, cached copy** of the checkout page that
still lists a plan the admin has since hidden, edited, or that just sold
out. Submitting that stale form correctly gets rejected server-side, but the
customer just saw an unhelpful `This plan is not available.` alert with no
way to recover — and stock wasn't even re-checked at order time, so a sold
out plan could still be purchased if the page *wasn't* stale.

Fixes:

- **No more caching of storefront/checkout pages**: both shortcodes now send
  `nocache_headers()` and set `DONOTCACHEPAGE` / `DONOTCACHEOBJECT`, which
  every major caching plugin respects, so visitors always see live plans.
- **Stock is now re-checked at order time** (`phm_place_order`), not just on
  page load — closes an overselling gap where a plan showing 0 stock could
  still be ordered from a stale page.
- **Self-healing checkout**: if a plan turns out to be stale/sold-out/hidden
  by the time of submit, the customer now gets a clear explanation and the
  page auto-reloads (cache-busted) to show current plans, instead of a dead
  end.

## What's new in 2.3.0 — "Table wp_phm_products doesn't exist"

Root cause: the version option could say "installed" while the custom tables
were never actually created (silent activation failure, file replacement
without reactivation, or missing DB privileges) — so every plan save died
with `Table 'wp_phm_products' doesn't exist`.

Fixes:

- **Existence-based upgrade**: `maybe_upgrade()` no longer trusts the version
  number alone — in wp-admin it checks that all 7 tables actually exist
  (cached 5 min) and rebuilds anything missing automatically.
- **Self-healing writes**: a failed save caused by a missing table/column
  now triggers an automatic schema repair + one retry — the plan saves
  without you touching anything. Other failures still surface the real
  `$wpdb->last_error` (never hidden behind "Saved.").
- **"Repair database tables" button**: on the Dashboard (shown automatically
  when a table is missing) and inside every database error banner. It
  verifies the result and tells you exactly which tables are still missing.
- **Install verification + logging**: `PHM_DB::install()` now checks its own
  result and logs it — if your DB user lacks CREATE/ALTER privileges you'll
  see that in the sync log immediately instead of finding out at order time.

If the repair button reports tables still missing: cPanel → MySQL Databases →
your DB user → enable **CREATE / ALTER / DROP** for the `pgcmc-host` database,
then click repair again.

## What's new in 2.2.0

- **Fix: silent plan-save failure.** A failed DB insert previously returned
  `id=0`, still redirected with a fake "Saved." banner, then PHP's
  `empty('0')` bounced you back to an empty products list — the plan never
  existed and no error was shown. `PHM_DB::save_product()` now returns a
  `WP_Error` carrying the real `$wpdb->last_error`, the save/import handlers
  redirect to a banner that **prints the actual database error** (with a hint
  to reactivate the plugin to rebuild tables when a column is missing), and
  the failure is recorded in the sync log.
- **"Best Value" badge**: a new featured flag on plans — gold ribbon +
  highlighted card in the storefront, featured plans float to the top of the
  grid, and the admin list shows a ★ Best Value pill. The `featured` column
  is added automatically via the version-gated dbDelta upgrade.

## What's new in 2.1.0

- **Panel compatibility fix**: the connection test no longer depends on
  `GET /api/application/account`, which does not exist on some panels
  ("The route api/application/account could not be found"). It now probes the
  core list endpoints (`users`, `nodes`, `locations`, `nests`, `servers`)
  that every 1.x-compatible panel has, tries the account route as a bonus,
  and reports exactly how many scopes your key can read.
- **Cloudflare credential types**: select between *API Token (Bearer)* and
  *Global API Key (email + key)*; zone ID can be auto-resolved from the base
  domain (**Find zone ID** button, or automatic on first use); proxy-mode
  select (DNS-only default, required for game traffic); record type
  auto-select — IPv4 targets get an A record, hostnames get a CNAME.
- **Plan save hardening**: egg↔game consistency is enforced (wrong egg is
  auto-corrected with a warning, nests without synced eggs are rejected with
  guidance), numeric limits are clamped to sane ranges.
- **Auto-created store pages**: activation publishes *Game Server Plans*,
  *Order a Server* and *Track My Order* pages so the storefront works
  immediately; dashboard shows their links + a "Create store pages" button.
- **Billing / renewals**: monthly (or quarterly/…) cycles — deploy sets the
  due date, daily cron emails a reminder before expiry, overdue servers are
  auto-suspended on the panel, **Renew +1 period** / Suspend / Unsuspend
  actions in the Orders screen.
- **Stock management**: finite stock decrements on order and is restored on
  cancel/delete; storefront shows "Only N left" for low stock.

## Previously fixed (2.0.x)

A complete WordPress plugin that turns a Pterodactyl panel into a billing
storefront — a Paymenter-style flow: **products → cart with subdomain picker →
payment → automatic server deployment**, with Cloudflare DNS automation.

## Fatal errors fixed

| Error | Cause | Fix |
|---|---|---|
| `maybe_load_elementor: Class "Elementor\Widget_Base" not found (class-elementor-widget.php:7)` | The widget class extended `Elementor\Widget_Base` at file load, via an unconditional `init` include | `class-elementor-widget.php` now only hooks Elementor's own `elementor/widgets/register` action and the widget file is wrapped in a `class_exists('\Elementor\Widget_Base')` guard. Without Elementor the site keeps working (use the `[phm_plans]` shortcode instead). |
| `init: Too few arguments to function add_action(), 1 passed ... class-plans.php on line 32 and at least 2 expected` | `add_action( 'init' )` was called with no callback | `class-plans.php` now uses `add_action( 'init', [ __CLASS__, 'register_shortcodes' ] )` (and every other hook passes both args). |

## Features

- **API key entry + auto reload**: paste panel URL + Application API key →
  *Save, Test & Auto-Sync* → connection is validated and all nests, eggs,
  locations, nodes are pulled into the DB and re-rendered on screen without a
  page refresh. Scheduled background auto-sync too (15 min → daily).
- **Database Data screen**: live tables of synced locations, nests (games),
  eggs (Minecraft, Paper, Vanilla, Forge, Fabric, BungeeCord…), and node free
  RAM/disk — with a **Create plan** shortcut per egg.
- **Plans**: full CRUD — nest, egg, location, RAM/CPU/disk/swap/IO, databases,
  extra ports, backups, price, setup fee, currency, stock.
- **Subdomain cart**: at checkout the customer picks `name.yourdomain.com`;
  availability is checked live (reserved list + orders + Cloudflare zone),
  and on deployment an **A record** (DNS-only) plus optional
  **`_minecraft._tcp` SRV record** (connect without `:port`) are created
  automatically.
- **Auto provisioning**: creates/links the panel user, picks a node with
  capacity in the plan's location, reserves a free allocation, fills every
  egg variable with sane defaults (Paper/Minecraft versions = `latest`),
  creates + starts the server, emails panel login + IP/subdomain, posts to
  Discord webhook.
- **Payments**: built-in manual gateways (EasyPaisa, JazzCash, bank, card)
  with per-gateway instructions, *Mark paid + deploy* in Orders, optional
  WooCommerce checkout bridge, optional instant-deploy mode for testing.
- **Shortcodes**: `[phm_plans]` `[phm_order]` `[phm_track]` `[phm_panel_login]`
  (`[phm_plans nest="1"]` filters by game).
- **Password sync + one-click panel access**: a WordPress password change
  (forgot-password reset or profile edit) automatically updates the linked
  panel password too; a one-click "Open Game Panel" button mints a fresh
  one-time panel password and hands it over on a short reveal screen.

## Install

1. Zip the `pterodactyl-hosting` folder and upload via *Plugins → Add New →
   Upload* (or copy it into `wp-content/plugins/` — it is a drop-in
   replacement for your existing broken `pterodactyl-hosting` folder).
2. Activate. Go to **PGC Hosting → Settings**.
3. Enter panel URL + an **Application API key** (Panel → Admin →
   *Application API*, create a key with **all** read & write scopes) and save
   — the page will test the key and auto-sync nests/eggs/locations/nodes.
4. (Subdomains) Enable Cloudflare, paste a DNS-edit token + zone ID + base
   domain.
5. **Products → Add new** (or use *Create plan* next to an egg in Database
   Data), set price + resources, activate.
6. Create a page with `[phm_plans]` and one with `[phm_order]`, optionally
   `[phm_track]`. Elementor users also get a **Hosting Plans** widget.

## Secrets in wp-config.php (recommended)

```php
define( 'PHM_PANEL_URL', 'https://panel.example.com' );
define( 'PHM_APP_KEY',   'ptla_...' );
define( 'PHM_CF_TOKEN',  '...' );
```

## Requirements

- WordPress 5.8+, PHP 7.4+
- Pterodactyl 1.x panel reachable over HTTPS from the WordPress host
- Application API key with full scope
- (optional) Cloudflare zone for automatic subdomains

## Hooks for developers

- `phm_server_deployed( $order_id, $server_id )` — fires after auto-deploy.
- `phm_admin_capability` — change which capability unlocks the admin screens
  (default `manage_options`).
- `phm_panel_password_synced( $wp_user_id, $ptero_user_id )` — fires after a
  changed WordPress password has been pushed to the linked panel account.
- `phm_remember_me_days` — change how long a "Remember Me" login lasts
  (default `30`).

## Troubleshooting

**"Sorry, you are not allowed to access this page."**

1. Make sure you are logged in as a full **Administrator** (the plugin menu
   requires the `manage_options` capability). Custom roles (e.g. Shop Manager,
   membership roles) do not have it by default — remap with:
   ```php
   add_filter( 'phm_admin_capability', function () { return 'manage_woocommerce'; } );
   ```
2. Old bookmarks/links to the previous plugin build (`pterodactyl-*` slugs)
   redirect automatically as of v2.0.1 — re-save any custom bookmarks.
3. If you get that screen right after *activating*: deactivate another plugin
   at a time — a security/role plugin filtering `user_has_cap` is the usual
   cause.
4. The plugin also catches the access-denied hook for its own screens and
   shows the exact capability + fix instead of the bare core message.
