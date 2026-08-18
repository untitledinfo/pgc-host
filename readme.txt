=== Pterodactyl Hosting Manager ===
Contributors: firepdx
Tags: pterodactyl, game server, minecraft, hosting, elementor
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Sell and manage Pterodactyl game servers directly from WordPress, with a live
order form, Elementor widget, client dashboard, and automated provisioning.

== Setup ==

1. Upload and activate the plugin.
2. Go to **Ptero Hosting → Settings** and enter:
   - Panel URL (e.g. `https://panel.yourdomain.com`)
   - Application API key (`ptla_...`, create it under Panel → Admin → API)
   - Client API key (`ptlc_...`, optional, needed for live usage stats + power buttons)
3. Go to **Ptero Hosting → Pricing / Plans** and set your per-MB RAM, per-% CPU,
   per-MB disk, dedicated IP, backup, and database prices.
4. Add the order form to any page:
   - Shortcode: `[ptero_order_form title="Order a Minecraft Server" min_ram="1024" max_ram="8192"]`
   - Or drag the **"Game Server Order Form"** widget in Elementor.
5. Add the client dashboard to a "My Servers" page: `[ptero_dashboard]`
6. Choose a payment mode in Settings:
   - **Manual** — order goes to "pending"; you approve & provision from
     **Ptero Hosting → Orders** after confirming payment (bank transfer /
     Easypaisa / JazzCash / etc.)
   - **WooCommerce** — a hidden product is created per order and the customer
     is sent to checkout; the server auto-provisions the moment WooCommerce
     marks the order paid.
7. Map your eggs to Docker images/startup commands using the
   `ptero_host_docker_image_for_egg`, `ptero_host_startup_for_egg`, and
   `ptero_host_environment_for_egg` filters (see functions.php example below).

```php
add_filter( 'ptero_host_docker_image_for_egg', function( $image, $egg_id ) {
    $map = array( 5 => 'ghcr.io/pterodactyl/yolks:java_17' ); // Minecraft egg id => image
    return $map[ $egg_id ] ?? $image;
}, 10, 2 );
```

== 25 Key Features ==

1. Full Pterodactyl **Application API** integration (create/suspend/unsuspend/delete servers)
2. Live **order form** with real-time cost calculator (AJAX, no page reload)
3. **Elementor widget** — drag-and-drop the order form into any page/template
4. **Location selector** pulled live from your panel's node locations
5. **RAM slider** with configurable min/max per form instance
6. **CPU slider** (%) with configurable min/max
7. **Disk size slider**
8. **Dedicated IP add-on** toggle with its own price
9. **Game/egg selector** (nest → egg cascading dropdown), pulled live from panel
10. **Automatic node capacity check** — never oversells a node's free RAM
11. **Auto free-allocation finder** — picks an open IP:port automatically
12. **WooCommerce payment integration** (optional) — auto-provisions on paid order
13. **Manual payment mode** — bank transfer / Easypaisa / JazzCash / custom instructions, admin-approved
14. **Coupon system** — percentage or fixed discounts, usage limits, expiry dates
15. **Multi-currency pricing** (PKR, USD, EUR, GBP, INR)
16. **Billing cycles** — monthly, quarterly (5% off), yearly (15% off)
17. **Client dashboard** shortcode — lists all of a user's servers with live status
18. **Live resource usage bars** (CPU % and RAM MB) polled from the Client API
19. **Power controls** — Start / Restart / Stop buttons right from the dashboard
20. **One-click console link** straight into the Pterodactyl panel
21. **Admin order management screen** — approve & provision, suspend, unsuspend, delete
22. **Automatic expiry + renewal reminder emails** (3 days before expiry)
23. **Automatic suspension after grace period** for unpaid renewals (daily cron)
24. **Order & server-ready email notifications** to both customer and admin
25. **REST API endpoints** (`/wp-json/ptero-host/v1/servers`, `/locations`, `/estimate`) for headless/mobile apps
26. **Google reCAPTCHA v2** support on the order form
27. **Referral/affiliate tracking** (`?ref=USERID` cookie, credits stored per user)
28. Backups & databases as **paid add-ons** with their own quantity selectors
29. Fully **filterable egg → Docker image/startup/environment mapping**
30. Clean, responsive, theme-agnostic **CSS** that adapts to your site's accent color (Elementor style tab)

== v1.1.0 — Full Billing System (Paymenter-style) ==

31. **Standalone client accounts** — separate email + password login (`[ptero_login]`, `[ptero_register]`), own table, 30-day auth token cookie
32. Optional **WordPress user sync** — toggle in Settings to mirror each client account (email + password) into a real WP user
33. **Plans / Products builder** (Ptero Hosting → Plans) — name, description, image, thumbnail (WP Media Library picker), CPU/RAM/disk/backups/databases/allocations/swap, nest/egg/location, stock, featured flag
34. **Per-cycle pricing** on every plan — hourly, daily, weekly, monthly, quarterly, yearly, plus a one-time setup fee (leave a cycle blank to hide it) — modeled on Paymenter's product config options
35. **Cart system** (`[ptero_cart]`) — add/remove plans with a chosen billing cycle and server name, session-based for guests
36. **Checkout** (`[ptero_checkout]`) — turns the cart into an invoice once the client is logged in
37. **Invoices** (`[ptero_invoices]`) — itemized, per-client, payable by wallet balance or manual/bank transfer
38. **Wallet / Add Funds** (`[ptero_add_funds]`) — clients submit top-up requests, admin approves from Clients screen
39. **Gateways settings page** (Ptero Hosting → Gateways) — Manual, Wallet enabled out of the box; Stripe & PayPal key fields ready to wire up
40. **Support ticket system** — clients open/reply via `[ptero_tickets]`, admins manage from Ptero Hosting → Tickets, with department + priority + status
41. **Services** admin page — quick view of all sellable plans (Paymenter-style "services" list)
42. **Auto-show Locations** toggle — turn the live panel location list on/off for the order form in one click
43. **Elementor widgets**: Plans Grid, Billing Pricing Table (with cycle switch), Support Ticket Form, Blog/News Posts
44. **Blog/news shortcode** (`[ptero_blog_posts]`) — pulls your latest WordPress posts as styled cards for announcements/billing news
45. **REST API v2** (`/wp-json/ptero-host/v1/...`) — `/register`, `/login`, `/me`, `/plans`, `/locations`, `/cart`, `/checkout`, `/invoices`, `/tickets` for headless apps, mobile apps, or other WordPress sites to integrate with

=== Quick setup for the billing system ===

1. Create three pages: "Client Dashboard/Login", "Checkout", "Invoices" — add `[ptero_login]` + `[ptero_register]` to the first, `[ptero_checkout]` to the second, `[ptero_invoices]` to the third.
2. Go to **Ptero Hosting → Settings** and paste those page URLs into "Client Dashboard Page URL" / "Checkout Page URL" / "Invoice Page URL" so redirects work.
3. Go to **Ptero Hosting → Plans → Add New** and create your first plan (set image, CPU/RAM/disk, and at least a monthly price).
4. Add `[ptero_plans]` to any page, or drag the **Plans Grid** / **Billing Pricing Table** Elementor widget onto it.
5. Go to **Ptero Hosting → Gateways** to review/enable Manual, Wallet, or add Stripe/PayPal keys.
6. Add `[ptero_tickets]` to a "Support" page for the ticket system.

== Notes ==

- This plugin ships with core logic wired end-to-end (API calls, DB schema,
  AJAX handlers, cron jobs) so it works out of the box once you add your
  panel URL/API key and egg → Docker image mapping.
- WooCommerce integration activates automatically if WooCommerce is active
  and "WooCommerce" is selected as the payment mode.
- Elementor widget only registers if Elementor is active — no hard dependency.
