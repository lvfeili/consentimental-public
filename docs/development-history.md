# Development and audit history

This is the historical development log that used to be the README, kept as-is for
audit purposes. It describes how the plugin got to its current design, including
approaches that were later replaced (for example page-presence filtering of the
service list). It is **not** user documentation and parts of it are out of date.

- Administrators: [admin-guide.md](admin-guide.md)
- Developers: [development.md](development.md)
- What changed in each release: [../CHANGELOG.md](../CHANGELOG.md)

---

### Proxy infrastructure and diagnostics

The plugin supplies transport infrastructure; site operators own endpoint configuration.
Setup guides for Umami, Matomo, Google tagging, and other services are included under
**Consentimental → Proxy diagnostics**. They link to current vendor documentation;
vendor endpoint changes can be handled by editing the integration and publishing,
without waiting for a plugin release. Allowlisting alone does not reroute a tracker.

Diagnostics monitor **published** proxied scripts and provide per-endpoint states:
not yet verified, HTTP checks passing, failure observed, failing, and no recent evidence.
Hourly WordPress cron jobs fetch up to five script assets with fresh GETs, rotating
through larger catalogs. They use the same safe transport, response-size, and MIME
checks as the public proxy. Runtime endpoints are observed during real forwarded
requests; no synthetic tracking events are generated. Cached successes cannot mask
a fresh upstream failure. Configuration changes reset the baseline; deleted scripts
are removed from the report.

Three consecutive failures produce a dashboard error notice. Email alerts are
**off by default** and can be enabled on that screen. They go only to the WordPress
administration email address, only after an endpoint previously succeeded, once per
incident, with an aggregate limit of one digest per day. Recovery permits a future
alert. Mail failures are shown in the UI and retried after the cooldown; WordPress
accepting mail does not establish delivery. No real email is sent by the test suite.

These checks establish HTTP transport health, not JavaScript execution, collection
semantics, consent compliance, or visibility in a vendor dashboard. Most runtime
4xx responses caused by invalid visitor payloads are ignored to avoid manufactured
alerts; 404, 410, 429 and upstream availability failures remain monitored. Rejected
local paths/queries do not alter health. No observed traffic is not a success; healthy
observations older than 24 hours become stale. Check the browser Network tab and
vendor dashboard when setting up an integration. WordPress cron depends on traffic;
use a system cron when regular checks are important. A monitoring GET contacts the
vendor from the server, but includes no visitor cookies or tracking-event payload.

References: [Umami tracker configuration](https://docs.umami.is/docs/tracker-configuration),
[Matomo JavaScript guide](https://developer.matomo.org/guides/tracking-javascript-guide),
[Google tag gateway](https://developers.google.com/tag-platform/tag-manager/gateway),
[WordPress cron](https://developer.wordpress.org/plugins/cron/),
[WordPress mail](https://developer.wordpress.org/reference/functions/wp_mail/).

### Security and enforcement regression fixes

Withdrawal now compares each service's effective permission, including category
fallback, and cleans a revoked service once. Revoked embeds are reblocked. Reloading
after revoking an executed script defaults to enabled; explicitly disabling it
requires vendor shutdown integrations to stop existing timers and listeners.
Consent recording remains a self-reported client assertion, not authenticated proof.
Immediate identical log submissions are deduplicated for five seconds; later
identical decisions retain their own timestamps.

Scripts are released sequentially: external loads finish before following inline
initializers. A failed or timed-out library stops the remaining script release chain
and emits `cmp:script-error`; reload to retry. This prevents dependent initializers
from executing without their library. Inline modules receive a completion marker;
use nonce-based CSP for these modules rather than hashes of their original source.
Unknown JavaScript-created requests still
require explicit gating/integration; the DOM observer only handles tagged elements.

The proxy now exposes exact paths and methods. The configured script path permits
GET/HEAD with exactly the configured query parameters. New scripts default to
script-only proxying. Configure exact runtime methods/paths in **Scripts → Allowed
runtime endpoints** (for example `POST /api/send`). Older saved scripts retain their
previous rules as editable data; new integrations never infer routes from filenames.
Code integrations can also extend the map:

```php
add_filter('cmp_proxy_allowed_paths', function (array $paths, $script): array {
    if ('your-script-id' === $script->id) {
        $paths['custom/collect'] = ['POST'];
    }
    return $paths;
}, 10, 2);
```

Redirects are rejected: configure the final URL. Proxy responses are limited to
2 MiB, request bodies to 64 KiB, and queries to 8 KiB. Static caching uses one slot
per script, and a coarse aggregate limit permits 120 uncached upstream requests
per minute per script (not a substitute for infrastructure-level rate limiting).
Live settings changes and configuration publishes invalidate supported page caches;
external CDNs still need a purge integration.

Run the regressions without deleting development-site settings:

```bash
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/consentimental/vendor/bin/phpunit --configuration /var/www/html/wp-content/plugins/consentimental/phpunit.xml.dist --do-not-cache-result
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/consentimental/tests/Integration/security.php
cd tests/Browser
## Set CMP_CHROMIUM to an installed Chromium executable if Playwright's browser is unavailable.
CMP_CHROMIUM=/snap/bin/chromium npx playwright test --config isolated.config.js
```

The integration checks use real WordPress APIs, intercept upstream HTTP, and roll
back database writes. They require InnoDB tables and no persistent object cache.
The isolated browser checks use synthetic pages and intercepted tracker requests.
Read-only admin UI checks can be run with `CMP_CHROMIUM=/snap/bin/chromium npx
playwright test --config admin-readonly.config.js` from `tests/Browser`. Set
`CMP_TEST_URL`, `CMP_TEST_ADMIN`, and `CMP_TEST_PASSWORD` for a different test site.

### Status

**Post-V1: server-side consent recording (proof of consent), per brief section 31/32.**
The banner and cookie were already sufficient to *enforce* consent, but gave a site
owner no way to prove after the fact what a specific visitor agreed to — the cookie
can expire, be cleared, or simply not be trusted by a third party. `Consent\
ConsentLogRepository` adds a dedicated table (`wp_cmp_consent_log`, created via
`dbDelta` on activation and self-healing on version bump — the first custom table in
the plugin, exactly the exception the brief anticipated) and `Consent\ConsentRecorder`
listens for the existing `cmp_consent_changed` hook (already fired by `Api\
ConsentController` on every decision) to append a row: `consent_id`, timestamp,
`config_version`, the full categories/services state, plus an anonymized IP (last
octet/64 bits zeroed — enough for origin plausibility, not directly identifying) and
truncated user agent/referring page. This stays opt-in in spirit, not mandatory
bulk collection: nothing is recorded until a visitor actually makes a decision, and
records auto-expire (`consent_log_retention_days`, default 1095/~3 years) via a daily
`wp_cron` cleanup. A new **Consent log** admin screen (`Admin\ConsentLogPage`) lets an
admin search by a visitor's own `consent_id` — the same anonymous id already exposed
to the visitor via the `cmp_consent` cookie and `GET /consent` — and see that id's
full history as proof.

**Post-V1: collapsed-by-default individual services, to keep the banner shorter.**
Each category's list of individual service toggles (`.cmp-customize__services`) is
now wrapped in a native `<details>`/`<summary>`, closed by default — a category with
several active services no longer forces the whole Customize dialog taller just to
show them all at once. No JS needed for the collapse/expand itself (native browser
behavior, so it's keyboard/screen-reader accessible for free); `assets/js/banner.js`
didn't need any changes, since collapsing is purely visual — the checkboxes stay in
the DOM and keep working exactly the same whether their `<details>` is open or not.
Existing Playwright specs that clicked a service checkbox directly
(`granular-consent.spec.js`) now expand that category's `<details>` first, the same
way a real visitor would need to.

**Post-V1: an explicit activation flag, plus a site scanner to help populate it.**
Removing presence-based filtering (the entry below this one) fixed a real
disclosure bug, but traded it for a noisier one: every one of the 14+ built-in
services showed up in every banner regardless of whether the site actually used
it. Neither "hide based on this one page" nor "show everything registered
in code" is the right list — the right list is "what an admin has confirmed is
actually here," which is a new explicit flag, not a detection heuristic:

1. **`Services\Service::$active`.** Deliberately separate from blocking: `Blocking\
   DomRewriter` and `Scripts\ScriptGate` neutralize a matching service regardless of
   this flag (an unconfirmed technology is still safely blocked), but
   `Frontend\Banner`'s listing and `Frontend\ConsentBootstrap`'s `services` config
   (which is what `CMP.hasServiceConsent()`'s category fallback and
   `CMP.acceptAll()`/`rejectAll()` actually iterate) both filter to active-only. A
   freshly-registered built-in defaults inactive; a custom service an admin
   deliberately created defaults active, since there's no "unreviewed" state to
   start from the way a built-in has. Toggling it (`wp-admin → Consentimental →
   Services`, a one-click Active/Inactive button per row, or the full edit/override
   form) bumps the config version like any other consent-structure change.
2. **`Diagnostics\SiteScanner`** (`wp-admin → Consentimental → Scan site`): fetches
   the site's own published pages/posts (`wp_remote_get()`, up to 40 — a bounded,
   synchronous, single-click scan, not a background crawler) and checks each page's
   HTML against every registered service's domains/patterns — the same detection
   `Blocking\DomRewriter` does live, run here for discovery instead of enforcement.
   Won't catch something injected purely by client-side JS after page load, the
   same limitation `DomRewriter` itself has. Results (matched-but-inactive services,
   plus any unrecognized external `<script>`/`<iframe>`/`<img>` host found) are
   cached in a transient and reviewed on their own screen — activating one, or
   adding a custom service for an unrecognized domain, works directly from the
   results without re-scanning. Deliberately read-only: nothing is activated or
   created without an admin confirming it.

This changed what several existing Playwright fixtures assume: a fixture that
registers its own test service (`blocking-test-plugin.php`) now passes
`'active' => true` in that registration; a fixture that instead embeds markup for
an *existing* built-in (`gtm-test-plugin.php`, `definition-of-done-plugin.php`) now
explicitly activates that built-in via `ServiceRegistry::apply_override()` in its
own `cmp_register_services` hook — both simulate a site where that vendor's usage
is already confirmed, which is the fixture's whole point.

**Post-V1: removing presence-based filtering — a transparency gap, and a sticky
actions row.** Two real-world reports after using the granular banner further:

1. **The Customize dialog now always lists the full backend-configured catalog**
   (every registered category/service — `Frontend\Banner` already passed
   `ServiceRegistry::all()` to the template unconditionally; only the client-side
   hiding logic below has been removed). The previous behavior — hiding a
   category/service with nothing gated on the *current* page (see the superseded
   entry below) — created a real consent-transparency problem: "Accept all" and a
   category-level toggle set every service in that category site-wide
   (`CMP.acceptAll()`/a category checkbox cascade, both in `assets/js/consent.js`
   and `assets/js/banner.js`), including ones never shown on the page the visitor
   actually decided on. Concretely: a page without a Cloudflare Turnstile widget
   never listed it under Functional, so accepting Functional there silently
   covered Turnstile too, without the visitor ever seeing it existed. `banner.js`'s
   `detectPresence()`/`applyPresenceFiltering()` are removed entirely; every row is
   now always visible, and the category-checkbox cascade (`serviceTogglesFor()`)
   applies to every registered service in that category, not just previously-visible
   ones. This also means `banner.spec.js` and `granular-consent.spec.js` no longer
   need `definition-of-done-plugin.php` deployed first for their listing assertions
   — built-in services show regardless of page content now.
2. **The Customize dialog's action buttons (Reject all / Accept all / Save
   settings) now stay visible without scrolling**, however long the category/
   service list gets. `.cmp-customize__panel` is a flex column with only
   `.cmp-customize__categories` scrolling internally (`min-height: 0` overrides a
   flex item's default min-height, which is otherwise its own content height and
   would let the panel grow past `max-height` instead of scrolling); `.cmp-
   customize__actions` stays pinned below it, with a border to make the scroll
   boundary visually clear.

A backend-configured, admin-curated service list (rather than only what's
auto-detected) and a site-scanning discovery tool to help populate it are being
scoped separately — the fix above addresses the disclosure gap using what the
registry already had; scanning is a larger, distinct feature.

**Post-V1: user feedback on the granular banner — category overrides, a banner logo,
and only listing what's actually here.** Three follow-ups after using the granular
per-service consent UI for real:

1. **A built-in's category can be reassigned.** GTM defaulting to "Marketing" (its
   built-in default) is a defensible default, but not everyone agrees a given built-in
   belongs in the category it ships with. The existing "Edit blocking" override screen
   (`wp-admin → Consentimental → Services → Edit blocking`) now has a Category dropdown
   alongside the domains/patterns fields — `ServiceRegistry::apply_override()` already
   generically merges whatever fields are passed onto a built-in, so this needed no new
   mechanism, only exposing `category` as one of the overridable fields. Reassigning a
   category bumps the config version, same as any other category change.
2. **Optional banner logo.** `wp-admin → Consentimental → Banner` gained a Media
   Library picker (`wp.media`, the same picker used for a WordPress site icon/featured
   image); the chosen image URL is stored in a new `banner_logo_url` setting and
   rendered at the top of both the banner and the Customize dialog when set.
3. **Only a service/category actually gated on the current page is listed in the
   Customize dialog**, not the full site-wide catalogue. A site with no video embeds
   has no reason to ask about YouTube. This is enforced client-side in
   `assets/js/banner.js`: `detectPresence()` scans the rendered page for
   `[data-cmp-category]` (still-blocked) and `[data-cmp-released]` (already-released —
   `blocking.js`'s `releaseScript()` deliberately strips every other `data-cmp-*`
   attribute when it clones a released element live, so a released element only still
   matches via this one) to build the actual set of service ids/category slugs present
   on this exact page, then hides every other row in the Customize dialog. Two
   exceptions, both deliberate: a **required** category (Necessary) always shows,
   since it represents essential site cookies never modeled as individual services and
   so would never show up as "present" on its own; and a **category** (not an
   individual service — see the code comment in `applyPresenceFiltering()` for why the
   asymmetry is intentional) the visitor has already explicitly decided on always
   shows too, so there is still a way to review or withdraw a choice made on a
   different page (the consent cookie, and so the choice, is site-wide). Filtering
   re-runs every time the dialog opens, not just once at load, since content can load
   after the initial page render.

   This changed what four existing Playwright spec files can assume: `banner.spec.js`
   and `granular-consent.spec.js` previously ran against a vanilla homepage and relied
   on every built-in category/service always being listed regardless of page content;
   both now require `tests/Browser/fixtures/definition-of-done-plugin.php` deployed
   first (same fixture Milestone 7 already uses) so Functional/Analytics/Marketing
   actually have something present to list. `cleanup.spec.js` and `scripts-admin.spec.js`
   needed no change: the former grants via Accept All before reopening the dialog (the
   "already decided" category exemption covers it), and the latter's own custom script
   is itself what makes its category present.

**Post-V1: closing gaps found in a full brief-compliance audit.** After granular
per-service consent shipped, every one of the brief's 49 sections was checked against
the actual code (not memory) rather than the milestone list, which surfaced seven real
gaps. All seven are now closed and verified (87 PHPUnit tests, 46 Playwright tests, a
live Elementor compatibility check — see below):

1. **No admin UI for categories** (brief section 4) — `CategoryRepository::save()` had
   no caller. Added `wp-admin → Consentimental → Categories`: rename/describe/reorder
   existing categories, add a new one, delete a non-protected one. The four seeded
   categories (`necessary`/`functional`/`analytics`/`marketing`) can't be deleted since
   every built-in service is registered against one of them.
2. **Consent versioning had no admin trigger** (brief sections 7 & 22) —
   `Settings::bump_config_version()` existed but nothing called it. Now: adding/removing
   a category, service, or script bumps the version (visitors are asked to decide
   again); renaming/re-describing/reordering does not, since that's cosmetic, not
   structural. Each admin screen shows a warning notice distinguishing the two.
3. **No admin UI for `consent_expiry_days` / `floating_button_enabled`** — both settings
   existed but were only reachable by editing `wp_options` directly. Added a "General
   settings" form on the Overview page.
4. **No banner content editor** (brief section 12) — banner/customize-dialog text was
   hardcoded. Added `wp-admin → Consentimental → Banner`: title, banner text, and
   customize-dialog text, each falling back to the built-in translated copy when left
   blank.
5. **Vimeo and reCAPTCHA had no browser test coverage** even though brief section 33
   names both explicitly — `definition-of-done.spec.js` and its fixture now embed real
   Vimeo/reCAPTCHA markup and assert both are blocked before consent, released by
   Accept All, and stay blocked when their category isn't granted.
6. **The Elementor/WP Rocket compatibility claim was false** — a prior README revision
   claimed both had been "spot-checked by hand," which never happened. That claim was
   corrected mid-audit; this pass then actually installed and exercised Elementor (see
   Milestone 7 below) rather than leaving the correction as the final word. WP Rocket
   remains genuinely untested — it's a paid plugin with no free download.
7. **`storage_keys` was unused by every built-in service** — the field exists on
   `Service` but no entry in `BuiltInServiceLibrary` populates it, since no specific
   localStorage/sessionStorage key has actually been verified per vendor. Rather than
   fabricate plausible-looking key names, added a docblock note: an empty
   `storage_keys` means "not catalogued," not "confirmed no storage use" — don't read it
   as a compliance guarantee.

**Post-V1: granular per-service consent in the banner.** Category-only toggles
("Marketing" as one on/off switch covering Meta Pixel, Google Ads, TikTok Pixel, ...)
bundle several distinct vendors/purposes under a single consent, which does not meet
GDPR/ePrivacy's "specific" and "granular" requirements on its own — EU regulators
(CNIL, the German DSK) increasingly expect per-vendor granularity, not just
per-purpose-category. The Customize dialog now lists every service under its category,
each with its own toggle. The category checkbox remains, acting as a standard
"select all" control — checked only if every service under it is on, otherwise
indeterminate (or unchecked if none are) — never the *only* way to grant or deny a
given service.

```js
CMP.setConsent({ marketing: false }, { 'google-tag-manager': true }); // GTM on, rest of marketing off
CMP.hasServiceConsent('meta-pixel'); // reads the explicit override if one exists, else falls back to the category
```

Withdrawing a whole category now also clears any service-level override within it —
without this, a service individually enabled inside a category the visitor then
rejects would keep silently running, which would have undermined the entire feature.
`CMP.acceptAll()`/`rejectAll()` now set every individual service explicitly too,
rather than leaving prior per-service overrides untouched.

**Post-V1: admin UI for services and scripts.** All seven planned milestones (below)
delivered the *enforcement mechanism* — detection, blocking, Consent Mode, cleanup —
but the only way to actually configure anything beyond the built-in signatures was
code (a filter, `cmp_register_script()`). That's a real gap for the common case of "I
need to add GTM/GA4/Matomo, and I want the plugin in control of it," the way e.g.
Complianz works. Two new admin screens close it:

* **Services** (`wp-admin → Consentimental → Services`) — lists every service, built-in
  and custom. A built-in's *detection* domains/patterns can be overridden (**Edit
  blocking**) without touching code — the concrete case this solves: a self-hosted
  Matomo has no fixed domain, so the built-in definition can't auto-detect it until an
  admin points it at the real one. Custom services can be added/edited/deleted outright
  (name, provider, purpose, category, privacy policy URL, domains/patterns, cookies),
  and immediately show up both in blocking and in the `[cmp_services]`
  documentation table — same registry, same data.
* **Scripts** (`wp-admin → Consentimental → Scripts`) — the brief's "script manager"
  (section 15): add a script (external URL or inline code), pick where it loads (head /
  body start / footer) and under what condition (whole site / specific URL / URL
  pattern / post type), and which consent category (optionally a specific service).
  The plugin outputs *and* consent-gates it itself, via the same neutralize-then-release
  mechanism as everything else — this is what actually answers "how do I get GA4/GTM
  onto the site with the plugin in control," for a site that doesn't already have a tag
  manager: no need to hand-edit theme files or reach for a separate tag-management
  plugin for something this simple.

```php
// what Services -> Add custom service / Scripts -> Add script do under the hood:
$registry->apply_override('matomo', ['domains' => ['analytics.mysite.test'], ...]);
$custom_services->save_one(Service::from_array([...]));
$custom_scripts->save(CustomScript::from_array([...]));
```

Two real bugs surfaced building this. The serious one: auto-generated ids
(`wp_generate_password()`, which includes uppercase) were compared against
`sanitize_key()`-processed form submissions (which lowercases) — the case mismatch
meant **Delete silently did nothing** for any id that happened to contain an uppercase
character, while still showing a "Saved" success notice and redirecting normally. Found
only by tracing actual HTTP requests/option state through a failing Playwright test that
looked, at first, like a stray "landed on the wrong page" issue; a raw `curl` replay of
the same request initially looked fine specifically *because* that replay's random id
happened to come out all-lowercase. Fixed at the source: generated ids are lowercased
before storage, not patched at every lookup site. Second: the debug panel's own CSS
z-index sat *below* the banner's backdrop (Milestone 7), fixed there.

### The seven milestones, in brief

* **Milestone 7 — Diagnostics and QA**: a frontend, admin-only debug panel (`CMP
  Debug`) showing live consent state, Google Consent Mode signals, and blocked/released
  elements, plus a self-test tool (`CMP.reset()`, simulate no/analytics/marketing/all
  consent, preview the banner). Verified the brief's own "Definition of Done for V1"
  (section 44) end to end against the real built-in services, inspecting actual browser
  behaviour throughout — see `tests/Browser/definition-of-done.spec.js`. WooCommerce and
  LiteSpeed Cache install/activate cleanly and the consent flow works on WooCommerce's
  Shop/Cart/Checkout. Elementor (4.3.0, free) was installed and activated alongside
  Consentimental in the Docker environment; a page built and rendered with it (a real
  `_elementor_data` widget tree, not just the plugin present-but-idle) loaded with no
  PHP fatals/warnings/deprecation notices in the response or the container log, the
  consent banner and bootstrap config rendered correctly, and every admin screen
  (Overview, Categories, Services, Scripts, Banner) still loaded cleanly with Elementor
  active. **WP Rocket was not tested** — it is a paid, commercial plugin with no free
  download available, so it cannot be installed and exercised without a purchased
  license; brief section 33's compatibility claim for it remains unverified. An earlier
  version of this section claimed a "spot-check by hand" for both plugins that never
  happened; that claim was removed rather than left to stand uncorrected, and this is
  the actual, verified state after closing that gap.
* **Milestone 6**: the rest of the built-in service library (14 services total with
  Milestone 5's Google set), `[cmp_services]` + a Gutenberg block for cookie
  documentation, and withdrawal cleanup (`assets/js/cleanup.js` — known
  cookies/localStorage/sessionStorage removed, wildcards like `_ga*` supported,
  `CMP.registerCleanup(serviceId, fn)` for vendor-specific opt-out calls).
* **Milestone 5**: GTM/GA4/Ads definitions and full Google Consent Mode v2 sequencing —
  the admin chooses Basic (GTM gated like any other service, no request reaches Google
  pre-consent) or Advanced (GTM loads immediately under Consent Mode defaults, which
  may send cookieless pings — the UI says this is not the same as complete blocking).
* **Milestone 4**: the blocking mechanism itself — `cmp_register_script()` for anything
  going through `wp_enqueue_script()`, and `Blocking\DomRewriter` for raw hardcoded
  `<script>`/`<iframe>`/`<img>` tags — both neutralizing unconditionally (identical
  output per visitor, cache-safe) and released client-side by `assets/js/blocking.js`
  the moment consent exists.

```php
cmp_register_script('my-analytics-script', 'analytics');
// or by service id, so a service-level override can apply too:
cmp_register_script('ga-tag', 'google-analytics');
```

```php
if ( cmp_has_consent( 'analytics' ) ) {
    // ...
}
```

```js
CMP.acceptAll();
CMP.hasConsent('analytics'); // true
CMP.withdraw('analytics');
document.addEventListener('cmp:withdraw', (e) => console.log(e.detail));
```

### Requirements

* WordPress 6.4+
* PHP 8.1+
* Docker (for the local dev environment)
* Composer

### Local development

```bash
composer install
docker compose up -d
```

Then finish the WordPress install and activate the plugin:

```bash
docker compose exec wpcli wp core install \
  --url=localhost:8080 \
  --title="Consentimental Dev" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.test \
  --path=/var/www/html

docker compose exec wpcli wp plugin activate consentimental --path=/var/www/html
```

The site is then reachable at http://localhost:8080, with wp-admin at
http://localhost:8080/wp-admin (admin / admin).

The plugin directory is bind-mounted into
`wp-content/plugins/consentimental`, so PHP changes are picked up immediately — no
rebuild needed.

Run WP-CLI at any time with:

```bash
docker compose exec wpcli wp <command> --path=/var/www/html
```

### Packaging a release

```bash
composer build   # or: bash bin/build.sh
```

Produces `dist/consentimental-<version>.zip` — the version is read from the plugin
header in `consentimental.php`, so it can't drift out of sync. The zip contains exactly
what a WordPress install needs (`consentimental.php`, `src/`, `templates/`, `assets/`,
`languages/`, and a *production* `vendor/` — the plugin has no runtime Composer
dependencies, so that's just the autoloader, not any actual packages) and nothing a
checkout has that it doesn't (`tests/`, Docker config, `brief.md`, PHPUnit). Your
working `vendor/` (with dev dependencies) is untouched; the script builds in a
temporary `build/` directory and removes it afterwards.

Install the resulting zip via wp-admin → Plugins → Add New → Upload Plugin, or unzip it
straight into `wp-content/plugins/`.

### Tests

PHP unit tests (pure logic — categories, services, consent state — no WordPress
bootstrap required):

```bash
composer install
composer test
```

Browser tests (Playwright — the banner UI against a real, running WordPress site):

```bash
docker compose up -d   # and install/activate the plugin, see above
cd tests/Browser
npm install
npx playwright install chromium   # first run only
npm test
```

The full WordPress PHPUnit test suite (for code that needs real WordPress, not just
these stubs) has not been needed so far — everything with real WordPress dependencies
(admin screens, shortcodes/blocks, REST) is instead covered by the Playwright suite
above, against an actual running WordPress site rather than a simulated one.

### Verifying Milestone 1

1. `docker compose up -d`, install WordPress and activate the plugin as above.
2. Open **wp-admin → Consentimental**.
3. Confirm the four default categories (Necessary, Functional, Analytics, Marketing)
   are listed, with Necessary marked required and default-enabled, and the others
   default-disabled.
4. `composer test` passes.

### Verifying Milestone 2

1. With the plugin active, view any frontend page's source: a `<script id="cmp-bootstrap">`
   should be the first script in `<head>`, before anything else.
2. Open the browser console on that page and run:
   ```js
   CMP.needsConsent();      // true
   CMP.acceptAll();
   CMP.hasConsent('analytics'); // true
   document.cookie;         // contains cmp_consent=...
   ```
3. Reload the page — `CMP.needsConsent()` should now be `false` (the cookie persisted).
4. Server-side, with that cookie sent along, `cmp_has_consent('analytics')` returns
   `true` and `cmp_needs_consent()` returns `false`.
5. Hooking `cmp_consent_granted` / `cmp_consent_withdrawn` / `cmp_consent_changed` and
   POSTing to `/wp-json/consentimental/v1/consent` fires them as categories change.
6. `composer test` passes (22 tests, including `ConsentState`/`ConsentManager` logic).

### Verifying Milestone 3

0. Copy `tests/Browser/fixtures/definition-of-done-plugin.php` into
   `wp-content/mu-plugins/` first (same fixture Milestone 7 uses) — since a
   category/service is only listed in the Customize dialog when something on the page
   actually needs it (post-V1, see "Status"), a vanilla homepage with nothing tracked
   would only show Necessary.
1. With the plugin active, visit the homepage as a fresh visitor (private/incognito
   window, or clear the `cmp_consent` cookie): a banner with **Reject all**,
   **Customize** and **Accept all** appears, none of the optional categories are
   pre-selected, and the reject option is exactly as easy to reach as accept.
2. Click **Customize**: a dialog opens with a toggle per category, Necessary shown as
   always-active and disabled, focus moves into the dialog and stays trapped there
   (Tab/Shift+Tab cycle within it), and Escape closes it without saving.
3. Make a choice (any button): the banner disappears, a floating button appears in the
   corner, and reloading the page does not show the banner again.
4. Click the floating button (or a `[cmp_privacy_settings]` shortcode link): the
   Customize dialog reopens pre-filled with your previous choices; changing and saving
   updates consent immediately.
5. `composer test` passes (25 tests). `npm test` in `tests/Browser` passes (8 tests).

### Verifying Milestone 4

1. Copy the test fixture into the dev site (registers two test services and gates one
   external script, one inline script, and a raw script+iframe pair — see the fixture's
   own docblock for exactly what it wires up):
   ```bash
   docker compose exec -T wordpress mkdir -p /var/www/html/wp-content/mu-plugins
   docker compose cp tests/Browser/fixtures/blocking-test-plugin.php wordpress:/var/www/html/wp-content/mu-plugins/blocking-test-plugin.php
   docker compose cp tests/Browser/fixtures/test-external.js wordpress:/var/www/html/wp-content/mu-plugins/test-external.js
   ```
2. Visit the homepage as a fresh visitor and view source: the gated `<script>` tags have
   `type="text/plain"` and a `data-cmp-category`/`data-cmp-service` attribute instead of
   executing; the gated `<iframe>` has its `src` renamed to `data-cmp-src` and shows a
   placeholder ("This content is provided by ... and requires ... consent", with
   Enable/Open privacy settings buttons) instead of loading.
3. Accept only Analytics: the analytics-gated script runs (check
   `window.cmpTestExternalRan` in the console); the marketing-gated inline script and
   iframe are still blocked.
4. Click the placeholder's Enable button: that iframe loads and the placeholder is
   removed, without a page reload.
5. `npx playwright test blocking.spec.js` in `tests/Browser` passes (8 tests) — remove
   the fixture files afterwards, they are test-only.
6. `composer test` passes (43 tests, including `ScriptGate`/`DomRewriter`/`PatternMatcher`).

### Verifying Milestone 5

1. Copy the GTM test fixture (simulates a hardcoded GTM snippet, the way most themes
   actually embed it):
   ```bash
   docker compose exec -T wordpress mkdir -p /var/www/html/wp-content/mu-plugins
   docker compose cp tests/Browser/fixtures/gtm-test-plugin.php wordpress:/var/www/html/wp-content/mu-plugins/gtm-test-plugin.php
   ```
2. Visit the homepage as a fresh visitor. In the console: `window.dataLayer[0]` is
   already `['consent', 'default', {...}]` with everything denied except
   `security_storage` — this exists before GTM's own script has had any chance to run.
   View source: the GTM `<script>` has `type="text/plain"` (Basic mode, the default) —
   `window.google_tag_manager` is `undefined`.
3. Accept Marketing: the GTM script releases, and `window.dataLayer` now also contains
   a `['consent', 'update', {ad_storage: 'granted', ...}]` entry.
4. In **wp-admin → Consentimental**, switch Consent Mode to **Advanced** and save.
   Reload the homepage: the GTM script is no longer gated (loads immediately), and the
   default call now includes `wait_for_update: 500`. Switch back to Basic afterwards.
5. `npx playwright test consent-mode.spec.js` in `tests/Browser` passes (3 tests) —
   remove the fixture file afterwards, it is test-only.
6. `composer test` passes (53 tests, including `ConsentMode`/`GoogleServices`).

### Verifying Milestone 6

1. In **wp-admin → Consentimental**, the services table lists 14 built-in services
   (Google Tag Manager through Matomo).
2. Create a page containing `[cmp_services]` (or insert the "Consent Services" block)
   and view it: a table with Service/Provider/Purpose/Category/Cookies/Duration/Privacy
   policy columns, populated from live configuration — e.g. Google Analytics 4 lists
   `_ga, _ga_*` under Cookies.
3. On a page *without* the shortcode/block, view source: `services-table.css` is not
   loaded (it's only enqueued on pages that actually use it).
4. As a fresh visitor, accept all consent, then open the browser console and run:
   ```js
   document.cookie = '_fbp=fb.1.123; path=/';
   CMP.registerCleanup('meta-pixel', () => console.log('vendor cleanup ran'));
   ```
   Reopen privacy settings and withdraw Marketing: `document.cookie` no longer contains
   `_fbp`, and "vendor cleanup ran" is logged.
5. `npx playwright test cleanup.spec.js services.spec.js` in `tests/Browser` passes
   (5 tests) — these need no fixture files, only a running site (services.spec.js
   creates and removes its own test page via wp-cli).
6. `composer test` passes (61 tests, including `BuiltInServiceLibrary` and regression
   tests for both bugs above).

### Verifying Milestone 7

1. As a logged-out visitor, confirm **no** "CMP Debug" button appears anywhere on the
   site.
2. Log into wp-admin, then visit the homepage: a "CMP Debug" button appears
   bottom-left — click it (it must open even while the consent banner is still up
   front-and-centre; that's the regression the z-index fix covers).
3. Confirm the panel shows: Necessary ACTIVE / everything else DENIED, the current and
   stored configuration version, an active-services list, and (if any Google service is
   present on the page) the current Consent Mode signals.
4. Use the self-test buttons: **Simulate all consent**, then **Reset consent** — the
   banner reappears and `needsConsent()` becomes true again. **Reopen banner** instead
   previews the banner without discarding whatever consent was actually recorded.
5. For the full "actual browser behaviour" verification (brief section 44), copy the
   fixture and run the dedicated spec:
   ```bash
   docker compose exec -T wordpress mkdir -p /var/www/html/wp-content/mu-plugins
   docker compose cp tests/Browser/fixtures/definition-of-done-plugin.php wordpress:/var/www/html/wp-content/mu-plugins/definition-of-done-plugin.php
   ```
   `npx playwright test definition-of-done.spec.js` in `tests/Browser` passes (5 tests,
   now also covering Vimeo and reCAPTCHA — see the audit gaps above) — remove the
   fixture afterwards, it is test-only.
6. `npx playwright test diagnostics.spec.js` passes (6 tests). The full suite — every
   spec file, with every fixture deployed at once (including `test-external.js`, not
   just the `*.php` fixtures) — passes together (46 tests; see each fixture's own
   docblock for what to copy where, or just deploy all of `tests/Browser/fixtures/*`).
7. `composer test` passes (87 tests). The Categories/Banner/General-settings admin
   screens added while closing the audit gaps above are WP-integration glue (form
   handling, nonces, redirects) with no isolated pure-logic to unit test — they're
   verified by direct use against the real Docker WordPress instance instead (see the
   audit gaps section above).

### Verifying the Services/Scripts admin UI

1. **wp-admin → Consentimental → Services**: lists 14 built-in services plus any
   custom ones. Click **Edit blocking** on Matomo, set Domains to a test value (e.g.
   `analytics.mysite.test`), Save — the list now shows that domain and an "overridden"
   marker, and matching markup on the frontend gets gated using it instead of the
   built-in default. **Reset to built-in default** removes the override.
2. **Add custom service**: fill in name/provider/category/domains/cookies, Save — it
   appears in the Services list *and* in a page's `[cmp_services]` table (same
   registry). **Delete** removes it from both.
3. **wp-admin → Consentimental → Scripts → Add script**: create an inline script
   (`window.foo = true;`), category Analytics, placement Footer, condition Whole site.
   View the homepage source: the script is present but neutralized
   (`type="text/plain" data-cmp-category="analytics"`) — `window.foo` is `undefined`
   until Analytics is accepted, at which point it executes, no reload needed.
4. Add a second script (external URL) scoped to **URL pattern** `*contact*` — it
   appears in the source of a URL containing "contact" and nowhere else.
5. `npx playwright test services-admin.spec.js scripts-admin.spec.js` in
   `tests/Browser` passes (7 tests) — no fixtures needed, they create and clean up
   their own test data via wp-cli.
6. `composer test` passes (87 tests, including `ServiceOverrideRepository`,
   `ServiceRegistry::apply_override()`, `CustomServiceRepository` CRUD, `CustomScript`,
   `CustomScriptRepository`, and `ScriptManager`'s condition-matching/rendering logic).

### Verifying granular per-service consent

0. Copy `tests/Browser/fixtures/definition-of-done-plugin.php` into
   `wp-content/mu-plugins/` first — see the note at the top of "Verifying Milestone 3".
1. As a fresh visitor, open **Customize**: each category with registered services (e.g.
   Marketing) lists them underneath with their own toggle, none pre-checked.
2. Check a single service (e.g. Google Tag Manager) without touching its category
   checkbox: the category checkbox becomes indeterminate (a dash, not a check).
3. Click the category checkbox itself: it cascades to check every service under it;
   click again to uncheck all of them.
4. Save with a mixed selection (e.g. only GTM checked under Marketing). In the console:
   `CMP.hasServiceConsent('google-tag-manager')` is `true`,
   `CMP.hasServiceConsent('meta-pixel')` is `false`, `CMP.hasConsent('marketing')` is
   `false` (the category itself was never fully granted).
5. Reopen settings: the dialog shows exactly that state back — GTM checked, Meta Pixel
   unchecked, Marketing indeterminate.
6. With GTM still individually granted, uncheck the whole Marketing category (two
   clicks, since the first click on an indeterminate checkbox resolves to fully-checked)
   and save: `CMP.hasServiceConsent('google-tag-manager')` is now also `false` — a
   category-level withdrawal clears service-level overrides within it, rather than
   leaving one silently active.
7. `npx playwright test granular-consent.spec.js` in `tests/Browser` passes (7 tests).
   The full suite — every spec file together — passes deterministically (46 tests).
8. `composer test` passes (87 tests — this was a JS/template-only change; the engine
   logic it depends on, service-level override precedence, was already covered by
   `ConsentManagerTest` since Milestone 2).

### Verifying category overrides, banner logo, and presence-only listing

1. **Category override**: `wp-admin → Consentimental → Services → Edit blocking` on
   Google Tag Manager, change Category to Functional, Save — a version-bump warning
   appears, the Services list now shows GTM under Functional, and on the frontend
   `window.cmpConfig.services['google-tag-manager'].category` is `'functional'`.
   **Reset to built-in default** (on the same screen) restores Marketing.
2. **Banner logo**: `wp-admin → Consentimental → Banner`, click **Choose logo**, pick an
   image from the Media Library, Save — the logo appears at the top of both the banner
   and the Customize dialog on the frontend. **Remove logo** clears it.
3. **Presence-only listing**: with no fixture deployed, visit the homepage as a fresh
   visitor and open **Customize** — only Necessary is listed (nothing else is
   configured, so nothing else has anything to gate). Copy
   `tests/Browser/fixtures/definition-of-done-plugin.php` into `wp-content/mu-plugins/`
   and reload: Functional, Analytics and Marketing now appear, each listing only the
   services that fixture actually embeds (e.g. Marketing lists GTM/Ads/Meta
   Pixel/YouTube/Vimeo, not TikTok Pixel or LinkedIn Insight, which the fixture doesn't
   embed). Grant Marketing via **Accept all**, reload the page (removing the fixture's
   markup would be needed to fully test this, but reopening on the same page is enough
   to observe): reopening **Customize** still shows Marketing as granted even if you
   later remove the fixture, since a category already explicitly decided always stays
   listed for review — see "Status" for why this exemption is category-level only.
4. The full suite — every spec file together, with every fixture deployed at once —
   passes (46 tests). `composer test` passes (88 tests — one new unit test,
   `test_override_can_reassign_category`, covers `apply_override()` accepting
   `category` as an overridable field; the rest of this work is JS/template-only).

### Architecture

```text
src/
    Admin/          admin screens: dashboard, Services (list/override/CRUD), Scripts (script manager CRUD)
    Consent/        categories, consent state/engine, settings/config version
    Services/       ServiceRegistry (built-in + custom) + BuiltInServiceLibrary + ServiceOverrideRepository
    Blocking/       DomRewriter (raw HTML) + OutputBuffer + PatternMatcher
    Scripts/        ScriptGate (wp_enqueue_script() integration) + ScriptManager/CustomScript (script manager, brief section 15)
    Cookies/        reserved — cookie/storage data lives on Services\Service; nothing PHP-side needed one yet
    Integrations/   ConsentMode (Google Consent Mode v2) + GoogleServices (GTM/GA4/Ads definitions)
    Api/            REST endpoint that persists consent and fires server-side hooks
    Frontend/       consent-engine bootstrap, banner, customize dialog, shortcodes, services table/block, blocking assets
    Diagnostics/    DebugPanel: admin-only frontend debug panel + consent self-test tool
    functions.php   public procedural PHP API (cmp_has_consent(), cmp_register_script(), ...)
assets/
    js/
        consent.js       the consent engine itself, inlined into <head>
        consent-mode.js  gtag/dataLayer bridge, inlined right after consent.js
        banner.js        banner/customize-dialog UI, enqueued normally
        blocking.js      release engine: scans + MutationObserver, releases gated elements
        cleanup.js       withdrawal cookie/storage cleanup + vendor callback registry
        diagnostics.js   populates/drives the debug panel and self-test buttons
        blocks/services-block.js  Gutenberg editor registration (no build step)
    css/
        banner.css
        blocking.css       embed placeholder styles
        services-table.css conditionally enqueued only on pages using [cmp_services]/the block
        diagnostics.css    debug panel styling
templates/
    frontend/
        banner.php
        services-table.php
        debug-panel.php
    admin/
        dashboard.php
        services.php, service-form.php, service-override-form.php
        scripts.php, script-form.php
languages/
tests/
    Unit/            PHPUnit (pure PHP logic)
    Browser/         Playwright (against a real WordPress site) + test fixtures
```

Configuration is stored WordPress-natively in `wp_options`
(`cmp_categories`, `cmp_services`, `cmp_settings`, `cmp_version`) — see brief section 31.
The visitor's own consent *decision*, by contrast, lives only in a first-party cookie
(`cmp_consent`) written by the browser — never server-side by default (brief section 32).
This is also what keeps the plugin compatible with full-page caching (brief section 29):
the HTML is identical for every visitor, and `assets/js/consent.js` is inlined into
`<head>` so it runs before anything else, reads that cookie, and enforces consent
client-side regardless of whether PHP ran for this particular request.
