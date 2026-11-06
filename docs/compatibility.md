# Compatibility

Last updated for version 0.3.0 (2026-09-25).

This page separates three kinds of evidence. Treat anything not listed under
**Automated** as unproven for your site, and run the browser check from the
[administrator guide](admin-guide.md#3-verifying-that-it-works) after every change.

- **Automated:** checked by the test suite on every CI run (`.github/workflows/ci.yml`).
  Each run uses a fresh, disposable WordPress site.
- **Manual:** checked by hand once, for the version stated. Not re-checked
  automatically, so later versions of either plugin may behave differently.
- **Untested:** no evidence either way.

## Supported platform

| | Minimum | Automated in CI |
| --- | --- | --- |
| WordPress | 6.4 | 6.4 and the current release (7.1.2 when this was written) |
| PHP | 8.1 | Unit tests on 8.1, 8.2, 8.3, 8.4. Integration and browser tests on 8.1 (with WordPress 6.4) and 8.4 (with the current WordPress) |
| Browsers | Current Chrome/Edge, Firefox, Safari | Chromium, Firefox and WebKit (desktop), Chromium on a Pixel 7 profile and WebKit on an iPhone 13 profile |

The plugin needs no Composer, Node or build step to install; CI tests the
distributable zip, not a development checkout.

## What the automated browser tests cover

Every combination above runs `tests/e2e`. The tests observe network requests (every
request not for the test site is intercepted; no real vendor is contacted) and
browser storage:

- first visit before a decision; reject all; accept all; one category only;
- one service only, then withdrawing it;
- withdrawal after scripts and embeds have run (cookies and storage cleaned, embeds
  re-blocked, page reloaded when a script had run);
- an expired decision and a decision for an outdated configuration version;
- a new service published after visitors decided, and a service moved to another
  category;
- reloads, and decisions or withdrawals made in another open tab;
- keyboard access and focus handling, labels, text contrast, 320px-wide screens,
  long strings, right-to-left layout, reopening the privacy settings;
- the setup flow, preview, publish, consent log, snapshot and export for
  administrators (Chromium only);
- no PHP errors, warnings or deprecation notices from the plugin during the run.

`tests/Integration` additionally checks security rules, disclosure snapshots and the
upgrade from the 0.2 database schema against real WordPress.

## Third-party plugins

| Plugin | Category | Evidence | Version | What was checked |
| --- | --- | --- | --- | --- |
| WooCommerce | Shop | **Automated** (CI, with the others below) | 11.1.2 | Shop and product pages: nothing before consent, services load after Accept all and after a reload, nothing after Reject all |
| Elementor (free) | Page builder | **Automated** | 4.3.2 | An embed inside an Elementor HTML widget is blocked, and released after consent |
| Autoptimize | Script/CSS optimisation | **Automated** | 3.1.15.1 | JS aggregation, CSS and HTML optimisation on: blocking and release unaffected, consent engine still runs first |
| Polylang | Multilingual | **Automated** | 3.8.9 | Translated banner title per language; a separate disclosure snapshot per language; the decision records the language shown |
| WP Super Cache | Page cache | **Automated** | 3.1.3 | Cached pages still enforce consent; publishing purges the cache so the new configuration version is served |
| LiteSpeed Cache | Page cache | Manual (0.1.x, 2026-09) | — | Installs and activates; consent flow worked on WooCommerce shop, cart and checkout. On Apache it does not cache pages, so purging was not verified. |
| WPML | Multilingual | Untested | — | Paid; not available to the project. The integration uses WPML's documented string translation hooks. |
| TranslatePress | Multilingual | Untested | — | Expected to translate the server-rendered banner like other content. |
| WP Rocket | Page cache / optimisation | Untested | — | Paid. The plugin calls its documented purge function, but that call has not been observed working. Its "Delay JavaScript" feature must exclude `cmp-bootstrap`. |
| W3 Total Cache | Page cache | Untested | — | Purge call exists, not observed working. |
| Cloudflare, Varnish, nginx and other server or CDN caches | Cache | Not supported automatically | — | Must be purged by you, or through the `cmp_config_published` action. |
| Perfmatters, Flying Scripts, WP Meteor and other "delay JavaScript" tools | Optimisation | Untested | — | Anything that delays the inline `cmp-bootstrap` script breaks ordering; exclude it. |
| Divi, Beaver Builder, Bricks, Oxygen | Page builders | Untested | — | |
| Google Site Kit, MonsterInsights and other plugins that add tags | Tag loaders | Untested | — | Tags matching a built-in service's domains should be blocked; not verified. |
| Other consent plugins | — | Not supported | — | Do not run two consent plugins at once. |

Versions are the ones installed when this page was written. CI installs the current
release from wordpress.org on every run, so the table can lag behind what CI tests.

Combinations: the automated plugin run activates WooCommerce, Elementor,
Autoptimize, Polylang and WP Super Cache **together** on the current WordPress and
PHP 8.4. It does not test each plugin alone, or with WordPress 6.4.

## Hosting

| Environment | Evidence |
| --- | --- |
| Apache + PHP 8.1/8.4, MariaDB 11 (official WordPress Docker images) | Automated |
| nginx, LiteSpeed server, managed hosts with their own page cache | Untested |
| Multisite | Untested; network-level management is not supported |
| Persistent object cache (Redis, Memcached) | Untested |
