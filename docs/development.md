# Developing Consentimental

## Layout

```text
consentimental.php   bootstrap; Composer autoloader if present, else a built-in PSR-4 loader
uninstall.php        opt-in data removal on plugin deletion
src/
    Admin/           admin screens (Guided setup, Status, Services, Scripts, Banner, Consent log, Publish, Proxy diagnostics)
    Api/             REST endpoint receiving visitor decisions
    Blocking/        server-side neutralization of raw <script>/<iframe>/<img> markup
    Consent/         categories, settings, draft/publish, consent state, consent log, disclosure snapshots
    Diagnostics/     findings model, configuration checks, status report, scanner, proxy health, debug panel
    Frontend/        inline consent engine bootstrap, banner (BannerContent is its single source of wording)
    Integrations/    Google Consent Mode, WPML/Polylang strings
    Scripts/         script manager and proxy
    Services/        service registry, built-in library, overrides
templates/           admin and frontend templates
assets/js/           consent.js (inline engine), blocking.js, banner.js, cleanup.js, consent-mode.js
tests/
    Unit/            PHPUnit, pure logic with WordPress stubs
    Integration/     scripts run inside real WordPress; writes rolled back (upgrade.php only on the disposable site)
    e2e/             Playwright against a disposable WordPress (the CI suite)
    Browser/         older Playwright suite against the development site
```

## Development site

```bash
composer install          # PHPUnit only; the plugin has no runtime dependencies
docker compose up -d
docker compose exec wpcli wp core install --url=localhost:8080 --title="Consentimental Dev" \
  --admin_user=admin --admin_password=admin --admin_email=admin@example.test --path=/var/www/html
docker compose exec wpcli wp plugin activate consentimental --path=/var/www/html
```

The site runs at http://localhost:8080 (admin / admin) with the checkout bind-mounted.

## Tests

| Suite | Command | Touches |
| --- | --- | --- |
| Unit | `docker compose exec -T wordpress php /var/www/html/wp-content/plugins/consentimental/vendor/bin/phpunit -c /var/www/html/wp-content/plugins/consentimental/phpunit.xml.dist` (or `composer test` with PHP's dom and mbstring extensions) | Nothing |
| Integration | `docker compose exec -T wordpress php /var/www/html/wp-content/plugins/consentimental/tests/Integration/security.php`, `.../disclosure.php` and `.../first-party.php` | Dev site database, inside a transaction that is always rolled back. Needs InnoDB and no persistent object cache. |
| End-to-end | `bin/e2e-env.sh up`, then `bin/e2e-test.sh` | A separate, disposable site (see below) |
| Upgrade | `bin/e2e-env.sh php tests/Integration/upgrade.php` | Disposable site only; refuses to run elsewhere |
| Isolated browser | `cd tests/Browser && npx playwright test --config isolated.config.js` | Nothing (synthetic pages) |
| Legacy browser | `cd tests/Browser && npx playwright test` | Dev site; adds consent log rows. Specs that reset options are skipped unless `CMP_ALLOW_SITE_RESET=1`. |

### The disposable end-to-end site

`bin/e2e-env.sh up` builds the release zip, starts WordPress in a separate compose
project (`cmp-e2e`, port 8891, database on tmpfs), installs the built plugin
read-only, and adds two test-only must-use plugins from `tests/e2e/mu-plugins`:

- `cmp-e2e-safety.php` records `wp_mail()` calls instead of sending mail, blocks all
  server-side HTTP except to the site itself (WP-CLI may still download plugins from
  wordpress.org), and raises the consent rate limit.
- `cmp-e2e-fixtures.php` registers services on `*.cmp-e2e.test` (a non-resolving
  name), prints their markup, and exposes a header-protected control endpoint for
  fixture state. It only works where `cmp_e2e_disposable` is set.

In the browser, `tests/e2e/specs/helpers.js` intercepts every request not for the
test site: vendor hosts get small stub scripts that set a cookie and storage entry,
everything else is aborted and reported. No test contacts a real vendor.

`bin/e2e-test.sh` runs Playwright inside the official Playwright image, so WebKit
works on hosts whose system libraries it does not support. Pass Playwright
arguments through, for example `bin/e2e-test.sh --project=webkit consent-matrix`.
Without Docker for the browsers: `cd tests/e2e && npx playwright test`.

Other versions: `CMP_E2E_WP_VERSION=6.4 bin/e2e-env.sh up`, or
`CMP_E2E_WP_TAG=php8.4-apache CMP_E2E_CLI_TAG=cli-php8.4 bin/e2e-env.sh up`.
Third-party plugins: `bin/e2e-env.sh compat woocommerce elementor autoptimize polylang wp-super-cache`
(configured by `tests/e2e/compat-setup.php`; `specs/compat.spec.js` then runs).

After code changes, `bin/e2e-env.sh sync` rebuilds and refreshes the running site.
`bin/e2e-env.sh down` deletes it.

## Releasing

1. Update the version in the plugin header and `CMP_VERSION` (`consentimental.php`),
   and add a `CHANGELOG.md` entry.
2. Regenerate the translation template: `bin/make-pot.sh` (needs WP-CLI with PHP's
   mbstring), or `docker run --rm -v "$PWD:/app" wordpress:cli-php8.1 wp i18n make-pot /app /app/languages/consentimental.pot --domain=consentimental --exclude=tests,dist,build,vendor,node_modules,docs`.
3. `bin/release-check.sh`: versions, lint, direct-access guards, PSR-4 layout, text
   domain, zip contents. It builds `dist/consentimental-<version>.zip`.
4. Run the end-to-end suite, including the upgrade check and a compat run.

Database changes go through `dbDelta()` with a version option per table
(`ConsentLogRepository::DB_VERSION`, `DisclosureSnapshotRepository::DB_VERSION`),
checked on every boot so updates that skip the activation hook still migrate. Add an
upgrade case to `tests/Integration/upgrade.php` for every schema change. New
settings need a default in `Settings::defaults()`; stored settings are merged over
the defaults on read.

## Extension points

| Hook | Kind | Purpose |
| --- | --- | --- |
| `cmp_register_services` | action | Register or adjust services. Administrator overrides also apply to services added here. |
| `cmp_consent_changed` | action | `($current, $previous, $context)`: a decision was stored. `$context['disclosure']` is the verified snapshot reference or null. |
| `cmp_consent_granted` / `cmp_consent_withdrawn` | action | Category transitions. |
| `cmp_config_published` | action | `($meaningful, $version)`: after publishing. Use it to purge CDN caches. |
| `cmp_settings_changed` | action | Live settings changed or the version was bumped. |
| `cmp_sale_sharing_opt_out` | action | Request-scoped opt-out notification: context includes `source` (`manual`, `gpc`, `stored`), optional existing `consent_id`, and server `timestamp`. Repeated visits/retries repeat the notification; handlers must be idempotent. No verified account identity is asserted. |
| `cmp_consent_log_insert_failed` | action | A consent record could not be written. |
| `cmp_consent_rate_limit` | filter | Decisions per IP per minute (default 20). |
| `cmp_proxy_allowed_paths` | filter | Add exact proxy routes from code. |
| `cmp_diagnostics_enabled` | filter | Whether the frontend debug panel is shown. |

Front end: `window.CMP` (`hasConsent`, `hasServiceConsent`, `setConsent`,
`acceptAll`, `rejectAll`, `withdraw`, `enableService`, `openSettings`, `reset`,
`registerCleanup`, `sync`) and the `cmp:ready`, `cmp:change`, `cmp:consent`,
`cmp:withdraw`, `cmp:release`, `cmp:cleanup`, `cmp:script-error` events. A decision
made in one tab is adopted by other open tabs of the site immediately
(BroadcastChannel) and when a tab regains focus or is restored from the
back/forward cache.

California controls add `CMP.getPrivacyChoice()`, `CMP.optOutSaleSharing()` and
`CMP.isServicePrivacyBlocked(id)`, plus `cmp:privacy` events. The independent
opt-out cookie/localStorage key is `cmp_sale_opt_out`; it must never be erased by
vendor cleanup or ordinary consent reset. `CMP.getPrivacyChoice()` exposes
`enabled`, `optedOut`, `gpc`, `ready` (header check completed), and `syncFailed`.
An opt-out takes effect immediately, before its server notification completes.

For backend integrations use `cmp_sale_sharing_opted_out()` alongside
`cmp_has_service_consent($id)`; the latter also denies unreviewed services while
California controls are enabled. Category checks are conservatively denied when
any active service in that category is privacy-blocked (or none is classified).
Prefer service checks to avoid blocking an unrelated reviewed service. Service
definitions have `sale_sharing: unreviewed|involved|not_involved`; administrator
overrides and custom services persist this field through draft/publish.

`GET /consentimental/v1/privacy-choice` returns request-specific `enabled`, `gpc`
and `opted_out` with `Cache-Control: private, no-store` and `Vary: Sec-GPC, Cookie`.
The frontend does not embed a visitor's header into cacheable HTML. It keeps
sale/sharing services blocked until this check succeeds. Exclude this REST route
from all CDN/service-worker caches.

`POST /consentimental/v1/privacy-choice` only accepts JSON `opted_out: true` and
an optional source, applies the consent endpoint's rate limit and fires the
opt-out hook. It does not issue Set-Cookie headers or allow opt-in. The browser
has already stored the choice. It is an anonymous notification, not authority to
modify a supplied account: integrate account identification and authentication
separately. Retry-safe integration handlers should record the request and apply it
to relevant downstream systems. The plugin does not automatically stop backend
transfers or keep a separate California request ledger.

Additional checks: `php8.3 vendor/bin/phpunit --do-not-cache-result`, the isolated
browser suite, and `tests/Integration/california.php` in real WordPress. The latter
rolls back admin/option writes and disables external integration hooks.
