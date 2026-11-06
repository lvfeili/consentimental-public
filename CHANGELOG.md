# Changelog

## 0.3.5 — 2026-10-30

- Reset theme button padding on the floating launcher so its 24px custom image
  cannot be squeezed inside the 48px circle. Prevent the icon from flexing smaller.

## 0.3.4 — 2026-10-30

- Keep the floating fingerprint and emoji at their chosen mobile sizes when a
  stylesheet loaded later overrides the icon's dimensions or transform.

## 0.3.3 — 2026-10-30

- Enlarge the floating launcher icon on phones and keep its size when mobile theme
  styles override SVG or emoji sizing.

## 0.3.2 — 2026-10-30

- Keep the floating launcher icon at its intended size on mobile devices.
- Add separate desktop and mobile controls for the launcher's left or right side,
  distance from that edge, and distance from the bottom edge.

## 0.3.1 — 2026-10-16

- Fix fatal errors when opening the custom service and service override forms.

- Add compiled German translations for visitor-facing consent controls, built-in
  service descriptions and the service/cookie table. Show translated category
  names and cookie durations in the table, including on sites whose default
  categories were stored before the locale changed. Hide the consent-log notice
  when recording is disabled.
- Add independent California sale/sharing controls: service classifications in
  draft/publish, automatic GPC recognition (browser and request header), persistent
  opt-out UI, server-side consent enforcement, backend integration hook and helper.
  Unreviewed services and category-only managed elements stay blocked. Cookie
  consent cannot reverse an opt-out; cross-tab withdrawal unloads affected embeds.
  Provide an optional privacy-rights page link and classification diagnostics.
  Account-wide enforcement and downstream transfers require site integration.

- Optional EU / Germany consent profile, applied worldwide: runtime safeguards
  for Basic Consent Mode, withdrawal access, non-blocking browsing, script
  withdrawal reloads, optional categories and explicit optional-service choices.
  Changing profiles renews consent; disclosure snapshots record the profile.
- Remove recognized providers' speculative resource hints before browser delivery,
  gate iframe `srcdoc`, and require all recognized providers for mixed embeds.
  Include `youtube-nocookie.com` in YouTube domain detection.
- Explain provider connections and site-wide consent in embed placeholders. Hide
  activation buttons for unconfirmed services; align the PHP consent API with
  the browser's refusal to grant unconfirmed services.
- Add isolated browser coverage for provider activation, remembered consent,
  withdrawal and multi-provider `srcdoc`.

## 0.3.0 — 2026-09-25

### Added

- **Guided setup** (Consentimental → Guided setup): scan, review services with
  detected / confirmed / unverified states, resolve problems, banner wording and
  privacy policy link with the same live preview as the Banner screen, preview with staged services (nothing saved), publish,
  verification procedure.
- **Status & verification** page with five separate areas (configuration, discovery,
  proxy availability, browser enforcement, vendor receipt), explicit passed /
  failed / warning / not checked / not applicable states, timestamps, scope and next
  steps. The overall summary is never "passed" while an applicable check is missing.
  Administrators can record manual browser and vendor checks; these are labelled as
  administrator-reported and expire on the next publish or after 90 days.
- New configuration checks: services in missing categories, scripts that can never
  run, scripts without a service, scripts that run without consent, incomplete
  service disclosures, undetectable custom services, invalid proxy configuration,
  duplicate managed scripts, missing policy link, unpublished changes.
- **Disclosure snapshots**: an immutable, content-addressed copy of the banner
  wording, choices, categories, services, links and language, stored at publish,
  on banner changes, and per language at first render. Consent records now store the
  snapshot and language shown. Snapshot viewer, CSV and JSON export (administrators,
  nonce-protected), retention cleanup of unreferenced snapshots.
- The site's own cookies are disclosed by default under Necessary: the consent
  cookie (`cmp_consent`, with the configured duration), WordPress login,
  admin-preference, protected-post and login-language cookies, and comment cookies
  when comments with the "save my details" checkbox are possible. They appear in the
  banner, the `[cmp_services]` table and disclosure snapshots, and can be
  deactivated like any built-in service.
- The banner and settings dialog link to the privacy policy (chosen page, else
  Settings → Privacy) and the cookie policy page.
- Opt-in deletion of all plugin data on uninstall (off by default).
- `cmp_consent_rate_limit` filter.
- Disposable end-to-end environment, cross-browser suite (Chromium, Firefox, WebKit,
  mobile profiles), third-party compatibility run, upgrade test, release checks,
  GitHub Actions CI. Administrator guide and compatibility matrix.

### Fixed

- **First decisions were rejected by the REST endpoint** (HTTP 400: the schema did
  not allow `previous_state: null`, which the consent engine sends for a first
  decision). Consent itself was still stored in the cookie and enforced, but first
  decisions were not logged and `cmp_consent_*` hooks did not fire for them. Consent
  logs from earlier versions therefore lack visitors' first decisions.
- A withdrawal in one tab did not stop trackers already running in other open tabs,
  or on pages restored from the back/forward cache, until they were reloaded.
- Activation and category overrides saved for services registered through the
  `cmp_register_services` hook were never applied.
- The blocking banner was announced as a modal dialog but did not move focus into
  it or keep keyboard focus inside; a non-blocking banner was wrongly announced as
  modal.
- Right-to-left layout of the settings dialog and floating button; long words could
  force horizontal scrolling on small screens.
- The Overview screen claimed most sites need no configuration.
- Services defined in code (built-ins, or registered by a theme or plugin) had no way
  to set a purpose or privacy policy URL, so the Status finding asking for them could
  not be resolved. The override form now has both fields, and findings link each
  named service straight to its edit screen.
- The Banner screen's live preview now shows the saved colours and the policy links,
  and clearing a text field previews the built-in wording instead of the old text.

### Changed

- The plugin runs without Composer: a built-in autoloader is used when `vendor/` is
  absent, and the release zip no longer contains `vendor/`.
- Site scan results are kept until the next scan (previously one hour).
- The legacy development-site browser suite no longer contacts real vendors and skips
  specs that reset site options unless `CMP_ALLOW_SITE_RESET=1`.

### Upgrade notes

- Database: the consent log gains `disclosure_hash` and `language` columns and a new
  `wp_cmp_disclosure_snapshots` table is created, automatically on the first request
  after updating. Existing records are kept and show the disclosure as unknown; they
  are not back-filled.
- The publication time of configuration versions is recorded from now on; earlier
  versions show it as unknown.
- New settings (`privacy_policy_page_id`, `delete_data_on_uninstall`) default to the
  previous behaviour, except that the banner now links to the Settings → Privacy
  page when one is published.
- Frontend: the inline consent engine sends a `disclosure_id` with each decision;
  cached pages from before the update simply record the disclosure as unknown.
