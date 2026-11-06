# WordPress Consent Management Plugin

## 1. Project objective

Build a modern WordPress consent management plugin whose primary job is not merely displaying a cookie banner, but **preventing consent-dependent technologies from executing before valid consent exists**.

The first release should focus on:

* GDPR/ePrivacy-compatible consent collection
* reliable prior blocking
* granular consent categories and services
* consent withdrawal
* WordPress-native integration
* Google Consent Mode v2
* transparent service/cookie documentation
* developer APIs
* basic diagnostics proving whether blocking actually works

The first release does **not** need multilingual support, IAB TCF, geo-targeting, agency management or sophisticated automated crawling.

The architecture should nevertheless make those features possible later without a rewrite.

The plugin must work as a standalone WordPress plugin without depending on a SaaS backend.

---

# 2. Core design principle

The plugin should be designed around this rule:

> Configuration is not proof of consent enforcement.

If the administrator configures Google Analytics as requiring Analytics consent, the important question is not whether the dashboard says that GA is blocked.

The important question is whether GA actually cannot execute before Analytics consent exists.

Consent enforcement therefore needs to be part of the plugin's architecture rather than simply a frontend banner layered on top of existing scripts.

---

# 3. Compliance target

The first release should be designed for normal commercial websites serving users in the EU/EEA.

The plugin must facilitate consent meeting the relevant GDPR requirements:

* freely given
* specific
* informed
* unambiguous
* expressed through affirmative action
* granular where different purposes exist
* no optional consent enabled by default
* refusal must not cause unnecessary detriment
* withdrawal must be possible at any time
* withdrawal must be as easy as giving consent
* proof of the user's consent state must be possible
* consent-dependent processing must not begin before valid consent exists

For cookies, browser storage, identifiers and similar technologies, implementation must also account for the EU ePrivacy rules applicable to terminal-device access.

The plugin should therefore assume:

**Strictly necessary technologies**

May operate without consent where genuinely necessary for the requested service.

**Everything else**

Must remain inactive until the required consent has been obtained.

The plugin itself must not claim that installing it automatically makes a website GDPR compliant. Compliance also depends on the website operator's configuration, privacy information, data processing and third parties.

---

# 4. Consent model

Use a two-level model:

## Categories

Default categories:

1. Necessary
2. Functional
3. Analytics
4. Marketing

Necessary is always active and cannot be deselected.

Administrators can:

* rename categories
* change descriptions
* create additional categories
* reorder categories

Optional categories must default to disabled.

## Services

Individual services exist inside categories.

Examples:

* Google Analytics
* Google Ads
* Google Tag Manager
* Meta Pixel
* Microsoft Clarity
* YouTube
* Vimeo
* Google Maps
* reCAPTCHA
* hCaptcha
* Cloudflare Turnstile
* Hotjar
* LinkedIn Insight Tag
* TikTok Pixel
* Matomo
* custom services

A service contains structured metadata such as:

* name
* provider
* purpose
* category
* privacy policy URL
* cookies/storage used
* cookie duration
* third-party domains
* known script patterns
* known iframe patterns
* known storage keys
* optional cleanup rules

Administrators must also be able to create arbitrary custom services.

---

# 5. Banner UX

The first-layer banner must support:

* Accept all
* Reject all
* Customize

Rejecting must be genuinely available and must not require significantly more interaction than accepting.

Do not:

* pre-enable optional categories
* treat scrolling as consent
* treat continued browsing as consent
* treat inactivity as consent
* use misleading button labels
* visually hide the reject option
* automatically load optional services while waiting for a decision

The Customize interface should show categories and optionally their contained services.

Users should be able to select individual categories.

Service-level consent should be architecturally supported, even if category-level consent remains the default UX.

---

# 6. Consent state

Store the user's consent state using a first-party mechanism.

The stored object should contain at least:

* schema version
* configuration/version identifier
* timestamp
* consent categories
* explicitly selected services where applicable
* consent expiry
* anonymous consent identifier

Example conceptual structure:

```json
{
    "version": 3,
    "consent_id": "random-id",
    "timestamp": 1780000000,
    "categories": {
        "necessary": true,
        "functional": false,
        "analytics": true,
        "marketing": false
    }
}
```

Do not store unnecessary personal information.

IP addresses should not be required.

Consent expiration must be configurable.

---

# 7. Consent versioning

Consent configuration must have a version.

Meaningful changes such as:

* adding a new purpose
* adding a new vendor
* materially changing a service purpose
* changing consent structure

should allow the administrator to require renewed consent.

Minor presentation changes should not necessarily invalidate existing consent.

The admin should be warned before publishing a configuration change that would trigger renewed consent.

---

# 8. Consent withdrawal

Users must always have an easy way to reopen consent settings.

Provide:

* footer-link shortcode/block
* JavaScript method
* PHP helper
* optional floating privacy button

Example:

```text
Privacy settings
```

When consent is withdrawn:

1. consent state changes immediately
2. affected scripts must no longer load on subsequent navigation
3. Google Consent Mode must be updated immediately
4. known cookies/storage for the withdrawn services should be removed where technically possible
5. vendor-specific cleanup callbacks should run
6. an optional reload can be triggered if required

Withdrawal must not require deleting browser cookies manually.

---

# 9. Prior blocking

This is one of the most important components.

The plugin must prevent consent-dependent resources from executing before consent.

Support blocking of:

* external scripts
* inline JavaScript
* iframes
* embedded videos
* maps
* tracking pixels/images
* known third-party requests
* scripts added dynamically after page load

Blocking must work both for plugin-managed services and common WordPress integrations.

---

# 10. WordPress script integration

Integrate with WordPress's script system wherever possible.

Support scripts registered using:

```php
wp_enqueue_script()
wp_register_script()
wp_add_inline_script()
```

The plugin should be able to associate a WordPress script handle with a service/category.

Example:

```php
cmp_register_script(
    'my-analytics-script',
    'analytics'
);
```

or equivalent API.

When consent does not exist, the corresponding script must not execute.

When consent is granted, it should be loaded without requiring unnecessary page reloads where technically feasible.

---

# 11. DOM-level blocking

Not everything enters WordPress through the enqueue system.

The plugin therefore needs mechanisms for blocking:

```html
<script>
<script src="">
<iframe>
<img>
```

where matching rules indicate a consent-controlled service.

Examples:

```text
googletagmanager.com
google-analytics.com
youtube.com/embed/
connect.facebook.net
```

Support:

* URL matching
* domain matching
* wildcard patterns
* script-content patterns where practical

For dynamic DOM changes, use a lightweight MutationObserver where necessary.

Performance must remain a priority.

---

# 12. Embed placeholders

Blocked embeds should not simply disappear.

For services such as:

* YouTube
* Vimeo
* Google Maps

display a placeholder.

Example:

> This content is provided by YouTube and requires Marketing consent.

Provide:

* Enable YouTube
* Open privacy settings

Optionally allow one-click service/category activation directly from the placeholder.

---

# 13. Google Consent Mode v2

Implement proper Google Consent Mode support.

Support at least:

* `analytics_storage`
* `ad_storage`
* `ad_user_data`
* `ad_personalization`
* `functionality_storage`
* `personalization_storage`
* `security_storage`

Consent defaults must be established **before Google tags can execute**.

This requirement is important.

Do not implement Consent Mode as:

1. load GTM
2. initialize CMP
3. eventually tell Google consent is denied

The correct default state needs to exist first.

Support:

* Basic Consent Mode
* Advanced Consent Mode

The website administrator chooses which mode to use.

The UI must explain that Advanced Consent Mode may still send cookieless signals to Google before consent and is therefore different from complete blocking.

---

# 14. Google Tag Manager

GTM should be treated carefully because it can load arbitrary technologies.

Support two modes.

## Strict blocking

GTM itself does not load until the required consent has been granted.

## Consent Mode integration

GTM may load under appropriate Consent Mode defaults, while individual tags respect consent signals.

The configuration screen must clearly distinguish the two approaches.

---

# 15. Script manager

Provide a simple script-management interface.

Administrators can create custom scripts containing:

* name
* code or external URL
* placement
* consent category
* service
* loading condition

Placement:

* head
* body start
* footer

Conditions may initially include:

* whole site
* specific URL
* URL pattern
* post type

This avoids forcing users to use GTM for simple tracking integrations.

---

# 16. Built-in service definitions

Ship with definitions for common services.

First release should include at least:

* Google Analytics 4
* Google Tag Manager
* Google Ads
* Meta Pixel
* Microsoft Clarity
* YouTube
* Vimeo
* Google Maps
* reCAPTCHA
* Cloudflare Turnstile
* Hotjar
* LinkedIn Insight Tag
* TikTok Pixel
* Matomo

Definitions should live in a clean, extendable service registry rather than being hardcoded throughout the application.

Example conceptual structure:

```php
ServiceRegistry::register([
    'id' => 'google-analytics',
    'category' => 'analytics',
    'domains' => [...],
    'scripts' => [...],
    'cookies' => [...],
]);
```

---

# 17. Cookie/storage inventory

Each service can define:

* cookie name
* wildcard cookie name
* purpose
* duration
* domain
* category

Also support:

* localStorage keys
* sessionStorage keys

Example wildcard:

```text
_ga*
```

This information is used both for documentation and consent withdrawal cleanup.

---

# 18. Cookie deletion

When consent is withdrawn, attempt to remove known cookies belonging to the affected service.

Support:

* cookie name
* wildcard names
* path variations
* known domain variations

Do not promise deletion where browser security rules make deletion impossible.

The system should report failures where useful.

---

# 19. Consent documentation

Provide a shortcode and Gutenberg block capable of outputting the site's current service/cookie inventory.

Example:

```text
[cmp_services]
```

The generated information can include:

* service
* provider
* purpose
* category
* cookies
* duration
* privacy-policy URL

This allows the privacy/cookie policy to reflect the active configuration.

---

# 20. Developer API

A proper developer API is required from the beginning.

## PHP

Conceptually:

```php
cmp_has_consent('analytics');
cmp_has_service_consent('google-analytics');
```

Hooks:

```text
cmp_consent_granted
cmp_consent_withdrawn
cmp_consent_changed
```

Filters should allow modification of:

* services
* categories
* detection rules
* cookie definitions
* script rules

## JavaScript

Provide an API such as:

```javascript
CMP.hasConsent('analytics');
CMP.openSettings();
CMP.getConsent();
```

Events:

```javascript
cmp:ready
cmp:consent
cmp:change
cmp:withdraw
```

The exact naming can differ, but the concepts must be stable and documented.

---

# 21. Admin interface

The initial administration workflow should be:

```text
Setup
  ↓
Categories
  ↓
Services
  ↓
Blocking
  ↓
Banner
  ↓
Review
  ↓
Publish
```

Avoid presenting the user with dozens of unrelated settings.

Use sensible defaults.

---

# 22. Draft/publish configuration

Consent configuration should preferably use a draft/publish concept.

The administrator can modify configuration without immediately changing the production consent behaviour.

When publishing:

* validate settings
* calculate whether renewed consent is required
* increment configuration version when needed
* clear relevant caches

This will become useful later for configuration history and rollback.

---

# 23. Diagnostics — MVP

The first release should already contain a small diagnostic system.

Display:

* current consent state
* current configuration version
* active categories
* active services
* Google Consent Mode state
* known scripts blocked
* known scripts released after consent

Provide a frontend debug mode available to administrators.

For example:

```text
CMP Debug

Consent:
Analytics      DENIED
Marketing      DENIED

Blocked:
Google Analytics
Meta Pixel
YouTube

Google Consent Mode:
analytics_storage = denied
ad_storage = denied
```

This will make implementation/testing dramatically easier.

---

# 24. Consent self-test

A basic test tool should exist in the first version.

The admin should be able to:

1. reset consent
2. simulate no consent
3. simulate analytics consent
4. simulate marketing consent
5. simulate all consent
6. reopen the banner

The frontend should clearly display which services are expected to run under each state.

A more advanced automated network scanner can come later.

---

# 25. Performance requirements

Consent management runs on every frontend request, so performance matters.

Requirements:

* no jQuery dependency
* minimal JavaScript bundle
* minimal CSS
* no external request to the CMP developer
* no mandatory SaaS API
* avoid frontend database queries wherever possible
* cache compiled configuration
* inline the minimum consent bootstrap state where useful
* CMP initialization should occur before controlled scripts
* compatible with full-page caching

The architecture must work with:

* Cloudflare
* nginx page caching
* LiteSpeed
* WP Rocket
* common WordPress hosting environments

---

# 26. Privacy of the CMP itself

The plugin should follow privacy-by-design principles.

By default it must:

* make no external telemetry request
* use no remote fonts
* use no tracking
* transmit no consent records to the plugin developer
* require no account
* require no cloud backend

If telemetry is ever added, it must be explicit opt-in.

---

# 27. Security

Follow standard WordPress development practices:

* capability checks
* nonces
* escaping
* sanitization
* prepared SQL
* REST API permission callbacks
* no arbitrary PHP execution
* carefully handle custom JavaScript input
* protect configuration endpoints
* validate imported configuration

Custom script functionality should only be available to trusted administrator-level capabilities.

---

# 28. Accessibility

The banner and settings dialog must be accessible.

Requirements include:

* keyboard navigation
* visible focus states
* sensible tab order
* focus trapping inside modal dialogs
* ESC behaviour where appropriate
* ARIA labels
* semantic buttons
* adequate contrast
* usable screen-reader structure

---

# 29. Caching compatibility

Consent must remain **client-specific**.

Do not generate different cached HTML pages for every consent combination.

A cached page should remain reusable between visitors.

Consent-dependent behaviour should normally be determined from the user's consent state on the client side or through cache-safe mechanisms.

Avoid architectures requiring:

```text
cache vary by consent cookie
```

unless absolutely necessary.

---

# 30. Plugin architecture

Use modern object-oriented PHP.

Suggested rough structure:

```text
src/
    Admin/
    Consent/
    Services/
    Blocking/
    Scripts/
    Cookies/
    Integrations/
    Api/
    Frontend/
    Diagnostics/

assets/
    js/
    css/

templates/

languages/
```

Use Composer autoloading.

Namespaces must be used consistently.

Frontend and administration code must remain clearly separated.

---

# 31. Data model

Prefer WordPress-native storage unless there is a strong reason not to.

Possible approach:

```text
wp_options
    cmp_settings
    cmp_categories
    cmp_services
    cmp_banner
    cmp_version
```

A dedicated table is not necessary merely for configuration.

If consent logging is later implemented, it may warrant its own database table.

---

# 32. No mandatory consent logging in V1

Do not create a giant server-side database containing every visitor's consent unless required.

The initial plugin should be capable of demonstrating the consent state and configuration version without unnecessarily collecting identifiable user data.

Optional server-side consent evidence can be implemented later.

Architecture should nevertheless reserve concepts such as:

```text
consent_id
configuration_version
timestamp
consent_state
```

---

# 33. Required V1 integrations

Prioritize generic mechanisms over hundreds of plugin-specific integrations.

Nevertheless, verify compatibility with:

* WordPress core
* Gutenberg
* WooCommerce
* Elementor
* WP Rocket
* LiteSpeed Cache

And explicitly test:

* GA4
* Google Tag Manager
* Google Ads
* Meta Pixel
* YouTube
* Vimeo
* Google Maps
* reCAPTCHA

---

# 34. WordPress development environment

The repository must include a reproducible local development setup.

Prefer Docker.

For example:

```text
docker-compose.yml

wordpress
php
mysql/mariadb
wp-cli
```

The development environment must make it easy to:

* install WordPress
* activate the plugin
* change PHP versions
* run WP-CLI
* inspect the database
* run automated tests

Document startup in the README.

Target:

```bash
docker compose up
```

followed by minimal setup.

---

# 35. Automated tests

Tests are especially important because consent bugs are often timing bugs.

Use:

* PHPUnit for PHP logic
* WordPress test suite where appropriate
* JavaScript unit tests
* Playwright or equivalent browser testing

Browser tests should cover at minimum:

### Test: fresh visitor

Expected:

```text
necessary services run
analytics does not run
marketing does not run
```

### Test: Accept all

Expected:

```text
analytics runs
marketing runs
consent persists
```

### Test: Reject all

Expected:

```text
analytics remains blocked
marketing remains blocked
necessary functionality works
```

### Test: Analytics only

Expected:

```text
GA allowed
Meta Pixel blocked
marketing embeds blocked
```

### Test: withdraw Analytics

Expected:

```text
future GA requests stop
known GA cookies removed where possible
Consent Mode becomes denied
```

### Test: reload

Consent state must persist correctly.

---

# 36. Race-condition testing

Explicitly test:

```text
CMP initialization
↓
Consent defaults
↓
GTM/GA initialization
↓
Consent update
```

There must never be a timing window in which a consent-dependent tracker executes before the plugin determines consent state.

This needs automated browser testing rather than visual testing alone.

---

# 37. Milestone 1 — Plugin foundation

Deliver:

* plugin bootstrap
* Composer structure
* Docker environment
* settings architecture
* service registry
* category model
* basic admin interface

At the end of this milestone the plugin must install and run, but no banner is required yet.

---

# 38. Milestone 2 — Consent engine

Deliver:

* consent state
* persistence
* expiry
* versioning
* category selection
* JS API
* PHP API
* consent events

A developer must already be able to write:

```php
if (cmp_has_consent('analytics')) {
    // ...
}
```

---

# 39. Milestone 3 — Banner

Deliver:

* first-layer banner
* Accept all
* Reject all
* Customize
* category configuration
* privacy settings reopening
* accessibility behaviour
* responsive layout

At this point consent collection itself should satisfy the project's compliance requirements.

---

# 40. Milestone 4 — Blocking

Deliver:

* WordPress enqueue blocking
* external script blocking
* inline-script handling
* iframe blocking
* placeholders
* dynamic-content handling
* release after consent

This is the milestone at which the plugin becomes genuinely useful.

---

# 41. Milestone 5 — Google ecosystem

Deliver:

* GA4 definition
* GTM definition
* Google Ads definition
* Consent Mode v2
* correct consent defaults
* correct consent updates
* strict GTM-blocking option

Automated timing tests are mandatory.

---

# 42. Milestone 6 — Services and cookie documentation

Deliver:

* initial built-in service library
* cookie inventory
* service metadata
* cookie/service shortcode
* Gutenberg block
* withdrawal cleanup

---

# 43. Milestone 7 — Diagnostics and QA

Deliver:

* debug panel
* reset consent
* simulate states
* blocked-service display
* Consent Mode display
* automated browser tests
* compatibility testing

After this milestone the plugin should be suitable for initial real-world testing.

---

# 44. Definition of done for V1

Version 1 is not considered complete merely because the UI is finished.

On a test WordPress site containing:

```text
GA4
GTM
Google Ads
Meta Pixel
YouTube
Google Maps
```

the following must be demonstrably true.

Before interaction:

```text
Necessary        ACTIVE
Functional       DENIED
Analytics        DENIED
Marketing        DENIED
```

No Analytics or Marketing technology requiring consent may execute.

After:

```text
Accept Analytics
```

analytics may execute while Marketing remains blocked.

After:

```text
Accept All
```

all configured services may execute.

After:

```text
Withdraw Marketing
```

marketing technologies stop executing and known removable storage is cleaned up.

After:

```text
Reject All
```

the site remains usable except for genuinely consent-dependent optional functionality.

The tests must inspect actual browser behaviour, not merely internal CMP state.

---

# 45. V2 — multilingual support

Multilingual support is explicitly **not required for V1**, but the data model must anticipate it.

Do not store human-readable labels in ways that make future localization difficult.

Later support should include:

* WordPress locale
* WPML
* Polylang
* TranslatePress
* per-language banner text
* per-language category descriptions
* per-language service descriptions
* fallback language

Core service identifiers must remain language-independent.

Example:

```text
google-analytics
```

not:

```text
google-analytics-de
```

---

# 46. Later roadmap

Potential later additions:

### Automated scanner

Crawl representative pages and detect:

* third-party requests
* scripts
* cookies
* localStorage
* iframes
* pixels
* unknown domains

### Consent Audit

Automate:

```text
clean browser
→ reject everything
→ inspect network/storage
→ accept Analytics
→ inspect delta
→ accept Marketing
→ inspect delta
```

Report services that execute in the wrong state.

This could eventually become one of the plugin's strongest differentiators.

### Multilingual

As described above.

### Consent logs

Optional server-side consent evidence.

### Configuration history

* revisions
* compare configurations
* rollback

### Import/export

JSON configuration portability.

### Multisite

Network-wide service definitions and defaults.

### Agency functionality

Central management across multiple WordPress installations.

### Geo rules

Different consent strategies by jurisdiction.

### IAB TCF

Only if there is a real use case for publisher/ad-tech sites.

---

# 47. Explicit non-goals for V1

Do not spend first-release development time on:

* IAB TCF
* US state privacy frameworks
* complex geo-detection
* SaaS dashboard
* cloud account system
* billing
* multilingual UI
* AI-generated legal text
* hundreds of vendor templates
* consent statistics dashboards
* A/B testing
* agency white-labeling

Get consent enforcement correct first.

---

# 48. Product philosophy

The plugin should distinguish itself from typical CMPs through technical reliability.

The core promise should effectively be:

> We do not merely record what the visitor clicked. We make sure the website behaves accordingly.

That means engineering effort should primarily go into:

1. correct initial consent state
2. reliable prior blocking
3. reliable withdrawal
4. correct Google Consent Mode sequencing
5. diagnostics
6. automated verification

rather than producing an enormous settings interface.

---

# 49. Development rule

Every milestone must result in something that can be inspected and tested on a running WordPress installation.

Do not build the entire backend architecture before exposing working functionality.

At the end of every milestone:

* plugin installs normally
* no fatal errors
* functionality is accessible through WordPress
* current milestone behaviour can be tested manually
* automated tests for that milestone pass
* README explains how to reproduce the test

Prefer incremental, inspectable development over a large one-shot implementation.
