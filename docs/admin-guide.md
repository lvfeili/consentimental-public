# Consentimental administrator guide

This guide is for the people who install and run Consentimental on a WordPress site.
It covers setup, checking that it works, what the diagnostics can and cannot tell
you, the optional proxy, troubleshooting, and how consent records are kept.

Consentimental helps you ask for consent and hold back technologies until visitors
give it. It does not make a site legally compliant by itself, and it cannot detect
every tracking technology. You stay responsible for what your site loads, what the
banner says, and whether your setup meets the law that applies to you.

## Contents

1. [Before you start](#1-before-you-start)
2. [Guided setup](#2-guided-setup)
3. [Verifying that it works](#3-verifying-that-it-works)
4. [The Status page](#4-the-status-page)
5. [Proxy configuration (optional)](#5-proxy-configuration-optional)
6. [Troubleshooting](#6-troubleshooting)
7. [Consent records, disclosure snapshots and data retention](#7-consent-records-disclosure-snapshots-and-data-retention)
8. [Multilingual sites](#8-multilingual-sites)
9. [Caching and optimisation plugins](#9-caching-and-optimisation-plugins)
10. [Limits and what is not supported](#10-limits-and-what-is-not-supported)

## 1. Before you start

- WordPress 6.4 or newer, PHP 8.1 or newer.
- Install the plugin zip under **Plugins → Add New → Upload Plugin**. It does not
  need Composer or any other tool.
- Know which tracking, advertising, embedding and chat tools your site uses. The
  scanner helps you find them, but you know your site best.
- Have a published privacy policy page. The banner links to it.

### Legal framework and international visitors

Under **Consentimental → Legal framework**, optionally select **EU / Germany —
GDPR and TDDDG consent safeguards**. The default is **Custom (no legal profile)**,
which preserves the existing configurable behavior. The profile applies to every
visitor; it does not look up their IP address or choose a law automatically.

The profile enforces Basic Google Consent Mode, a visible privacy settings button,
browsing without first deciding, and a reload when an executed script loses consent.
Only the `necessary` category may be required. All other categories are optional
and start unchecked. Optional services require an explicit service grant; a bare
category grant through the JavaScript API does not release them. The banner's
category toggles and Accept all already record individual service choices.

These are conservative technical safeguards, not a complete implementation or
certification of either law. Administrators must still classify necessary services
correctly, disclose providers and purposes, assess transfers, configure unknown
integrations, and verify actual browser behavior. Custom code and CSS can also
undermine consent controls. California sale/sharing opt-outs and Global Privacy
Control can be enabled separately below. Data-subject request handling remains
the site's responsibility.

Profile changes take effect on save and invalidate earlier consent. The selected
profile is included in new disclosure snapshots. Common WordPress page caches are
purged automatically; purge external/CDN caches yourself. Conflicting controls are
disabled while the profile is active. Their prior custom preferences are retained.

Several laws can apply at once. GDPR can apply to processing in the context of an
EU establishment even for visitors elsewhere, or to a non-EU provider offering
goods/services to people in the EU or monitoring their behavior there. Merely
being accessible from Europe does not by itself establish targeting. See the
[EDPB territorial-scope guidelines](https://www.edpb.europa.eu/system/files/documents/files/file1/edpb_guidelines_3_2018_territorial_scope_after_public_consultation_en_1.pdf).
CCPA, as amended by CPRA, gives California residents rights against businesses
within its scope; residence is not the same as current IP location. It includes
sale/sharing opt-outs and honoring Global Privacy Control, which prior opt-in
alone does not replace. See the [California Attorney General's CCPA guidance](https://oag.ca.gov/privacy/ccpa).
Germany also has separate device-storage/access consent rules with narrow
exceptions in [TDDDG §25](https://www.gesetze-im-internet.de/ttdsg/__25.html).
Have the site's actual legal scope assessed; a worldwide consent baseline can
simplify delivery but does not replace additional applicable obligations.

### Enable California sale/sharing controls

1. In **Services → Edit**, classify each service as **Sale/sharing involved**,
   **No sale/sharing**, or **Unreviewed**. Assess the actual configuration and
   contractual arrangements, not just the vendor's name or its consent category.
   Assign every managed script to a service. Save and **Publish** the review.
2. On the Consentimental overview, enable **California sale/sharing controls for
   all visitors**. Optionally select a published **Privacy rights request page**
   describing your access, deletion and correction process. Save.
3. Purge external page caches. Exclude `/wp-json/consentimental/v1/privacy-choice`
   (and the equivalent `?rest_route=` URL on sites without pretty permalinks) from
   CDN/service-worker caching. Verify both ordinary visitors and GPC browsers.

This switch works alongside either consent baseline and does not geolocate
visitors. It enforces Basic Consent Mode, a visible settings button, access to the
page before a cookie decision, and reloads on withdrawal of an executed script.
The footer and privacy dialog expose **Do Not Sell or Share My Personal
Information** with immediate confirmation. GPC receives a visible acknowledgment.

Unreviewed services remain blocked even after Accept all, including services
labelled Necessary; first-party Consentimental and WordPress services are reviewed
as no sale/sharing by default. Elements gated only by category must be assigned a
reviewed service. The Status page identifies unreviewed services and unassigned
managed scripts. Reviewed services involving sale/sharing need both the usual
consent and absence of a sale/sharing opt-out; other reviewed services can still
run with the required consent.

The browser checks `navigator.globalPrivacyControl` before releasing content and
makes an uncached first-party request to check `Sec-GPC` as well. Involved services
remain blocked until that check succeeds. A failed request does not become
permission. PHP consent helpers also honor the header and stored preference.

Opt-out is stored independently in the first-party `cmp_sale_opt_out` cookie
(400 days, refreshed on visits) and a localStorage entry of the same name (until
cleared). Cookie-consent expiry, configuration changes, Accept all, provider
activation and `CMP.reset()` do not clear it. There is no opt-back-in prompt or
control in this implementation. Switching GPC off does not reverse a remembered
opt-out. Browser storage limits or deletion can remove saved choices; this is a
browser/site preference, not automatic account-wide or cross-device enforcement.

An opt-out unloads affected iframes, runs registered service cleanup and reloads
when an affected script has already executed. Other open tabs adopt the preference.
Google Consent Mode denies ad-user-data and ad-personalization while opted out or
while the header check is pending. Opt-outs are re-sent to the server on visits;
failures are shown with a retry control while local blocking remains effective.

Backend sales, server-side tracking and account-wide preferences require your own
integration with the PHP helper and opt-out hook described in the developer guide.
Requests remain anonymous; the plugin does not verify identity, automatically
update customer accounts, or implement access/deletion/correction workflows.
The consent log is not a separate ledger of California requests; use the hook to
record and route these requests in your rights-management system. Notices,
sensitive-information restrictions, minors' rules and other applicable obligations
must be assessed separately. These are sale/sharing controls, not certification of
CCPA compliance. Regulatory reference: [California's approved regulations](https://www.cppa.ca.gov/regulations/pdf/ccpa_updates_cyber_risk_admt_appr_text.pdf),
especially sections 7025–7026.

### Embedded content

Confirm YouTube, Vimeo or another recognized provider under **Services**, then
publish. Recognized iframe URLs, images and responsive image sources are held
in inert attributes before HTML reaches the browser. The placeholder identifies
the provider, explains the connection, links its privacy policy and offers
**Allow [service] and load content**. This grants only that service, across the
site until consent expires or is withdrawn. Content loads immediately without
reloading the page. Withdrawal unloads the iframe and restores its placeholder.
Unconfirmed services remain blocked and cannot be activated from the placeholder.

The rewriter also gates recognized remote content inside iframe `srcdoc`, including
multiple known providers, and removes matching HTML connection/preload/prefetch
hints. It does not recreate those optional hints after consent. YouTube's
`youtube-nocookie.com` embeds are gated too. No remote preview is needed for the
placeholder.

This is recognition-based protection, not a network firewall. Unknown providers,
HTTP `Link`/Early Hints headers, arbitrary JavaScript-created requests, lazy-load
plugins that restore URLs, and CSS backgrounds need separate integration and
verification. A `srcdoc` document may contain unknown providers too; review it.
With JavaScript disabled, rewritten embeds stay inert.

## 2. Guided setup

Open **Consentimental → Guided setup**. There are six steps. You can leave at any
time with **Skip to all settings**; every step also links to the full settings screen
it summarises.

Nothing you change in steps 1–3 reaches visitors until you publish in step 5.

### Step 1: Discover

**Scan my site** fetches up to 40 of your published pages the way a logged-out
visitor gets them, and looks for known services and external hosts in the HTML.

The scan only reads the HTML the server sends. It does not see:

- scripts that other scripts add later in the browser, including tags that a tag
  manager loads;
- pages behind a login, the checkout, or the account area;
- anything that differs by visitor, device or country.

A scan finds *likely* services. It is not proof that tracking is blocked. Step 6
checks that.

### Step 2: Review services

The site's own cookies are already listed under Necessary: the consent cookie
(`cmp_consent`), WordPress's login and preference cookies (only set for people who
log in), the protected-post cookie, and, while comments can remember a commenter's
details, the comment cookies. Add any other first-party cookies your theme or
plugins set as custom services.

Each row shows what is known about a service:

| Finding | Meaning |
| --- | --- |
| Detected by scan, confirmed by you | Found on your pages, and you marked it as in use. |
| Detected by scan, not confirmed | Found on your pages, but not yet in the banner. It stays blocked, and visitors cannot allow it. |
| Confirmed by you, not detected by scan (unverified) | You marked it as in use, but the scan did not see it. It may be loaded by JavaScript, or it may no longer be on the site. |

For each service, check the purpose and choose the category. The category decides
which banner switch covers it. Tick **In use** for everything the site really uses.
To edit purposes, cookies or domains, use **Services**.

**Unrecognized external hosts** are loaded by your pages, but no service matches
them, so the plugin does not block them. For each one, decide whether it needs
consent. If it does, create a service with its domain.

### Step 3: Resolve problems

This step checks the configuration you are about to publish. Each finding says
what is wrong, what it means for visitors, and what to do next. See
[section 4](#4-the-status-page) for the list of checks.

### Step 4: Banner and policy

Set the banner title and text. Leave them empty to use the built-in wording, which
is translated where a translation is available. Choose the privacy policy page
that the banner should link to. By default it uses the page set under
**Settings → Privacy**. If you generate a cookie policy page (under **Services**),
the banner links to that too.

Banner wording takes effect as soon as you save. Colours, position, logo and custom
CSS are on the **Banner** screen.
The floating button's side and its distances from the side and bottom edges can be
set separately for desktop and for screens 600px wide or narrower on that screen.

### Step 5: Preview and publish

**Preview the banner** opens your home page in a new tab with the banner shown,
and your *staged* services and categories in the settings dialog. Choices made in
the preview are not saved. Blocking on that page still follows the published
configuration.

**Publish configuration** makes the staged configuration live. If you added,
removed, activated or moved services or categories, visitors who already chose will
be asked again. The screen tells you before you publish.

### Step 6: Verify

Follow the browser and vendor checks in [section 3](#3-verifying-that-it-works)
and record the results. **Finish and go to Status** takes you to the Status page.

## 3. Verifying that it works

Only a check in a real browser shows whether trackers wait for consent. Repeat it
after every publish, and after installing or updating themes and plugins.

### Browser check

Use a private window, with the browser developer tools open on the **Network** tab
and the **Storage** (Firefox, Safari) or **Application** (Chrome, Edge) tab.

1. **Before any choice:** load a page. No requests to optional vendors appear in
   the Network tab, and no optional cookies or storage entries exist.
2. **Reject all:** click it, then reload. Still no optional requests, cookies or
   storage entries.
3. **Accept all, or allow one service:** the allowed service's scripts and embeds
   load, and nothing you did not allow loads.
4. **Withdraw:** reopen the privacy settings (floating button or a
   `[cmp_privacy_settings]` link) and switch the service off. The service stops;
   by default the page reloads if one of its scripts had already run. Its known
   cookies are removed.
5. **Keyboard:** the privacy settings can be reopened with the keyboard alone, on
   each page template you use (home, posts, shop, checkout, landing pages).

Check a few different page types, not just the home page.

While you are logged in, a **CMP Debug** button appears on your site. It shows the
consent state and which elements were blocked or released on that page. Use it to
understand what you see, but always do the check itself in a private window as a
logged-out visitor.

### Vendor check

In each vendor's own dashboard or debug view (for example Google Analytics
DebugView, Matomo's visitor log, Meta Events Manager's test events):

1. After allowing the service, a test page view or event arrives.
2. A visit that rejected consent produces no page view or event.

### Recording the result

Record both checks on the Status page (or in setup step 6). Tick the steps you
completed and say whether you found a problem. The result is stored with your user
name, the time and the configuration version. It stops counting as current
evidence when you publish again, or after 90 days.

## 4. The Status page

**Consentimental → Status** shows five separate areas. Each proves something
different, and **a passing result in one area is not evidence for another.** In
particular, a successful HTTP response from the proxy says nothing about whether
the browser waited for consent, or whether the vendor accepted the data.

| Area | How it is checked | What it can show |
| --- | --- | --- |
| 1. Configuration validation | Automatically, each time the page loads | Whether the published settings are consistent and complete |
| 2. Discovery | The last site scan | What the scan found in your pages' HTML |
| 3. Proxy and upstream availability | Hourly server checks and real proxied traffic | Whether this server could reach the vendor |
| 4. Consent enforcement in the browser | Your manual check | Whether trackers waited for consent |
| 5. Vendor-side receipt | Your manual check in the vendor's dashboard | Whether data arrived after consent, and not without it |

Every result shows its state, when it was checked, what it covered (scope), and how
it was obtained: an automated check on the server, a report by an administrator,
or no evidence.

| State | Meaning |
| --- | --- |
| Passed | Checked, and nothing wrong was found within the stated scope. |
| Failed | Checked, and something is wrong. The finding says what to do. |
| Warning | Works, but something is incomplete or risky. |
| Not checked | No current result: never checked, too old, or the configuration changed since. |
| Not applicable | Nothing to check, for example no proxied scripts. |

The summary at the top never says everything passed while an applicable check is
missing. Because browser and vendor checks need you, it stays "incomplete" until
you record them.

### Configuration checks

- **Active services in a category that does not exist:** they are not listed in the
  banner, so visitors cannot allow them.
- **Scripts that can never run:** assigned to a missing category, or to a service
  that is inactive or missing.
- **Scripts not assigned to a service:** the banner cannot name the vendor, and
  visitors can only allow the whole category.
- **Scripts that run without consent:** assigned to an always-active category.
  Only appropriate for strictly necessary technology.
- **Services without a purpose or privacy policy link.** Each named service links to
  its edit screen. For services defined in code (built-ins, or added by a theme or
  plugin) that is the **Edit** form reached from **Services**, where purpose and
  privacy policy URL can be set. The finding clears once you publish.
- **Custom services with no domains or patterns:** the plugin cannot recognise and
  block their markup.
- **Invalid proxy configuration:** non-HTTPS URLs or runtime hosts, or allowed
  endpoints that cannot be parsed.
- **Possible duplicate integrations:** two managed scripts load the same URL or the
  same Google tag ID. Tags hard-coded in themes or other plugins are not compared.
- **No policy link**, and **unpublished changes**.

## 5. Proxy configuration (optional)

Most sites do not need the proxy. It lets a script you manage under **Scripts** be
loaded through your own domain, and optionally forwards the script's runtime
requests (for example an analytics collection endpoint) to the vendor.

**Proxying does not change consent requirements.** A proxied script is blocked
until consent like any other. Routing traffic through your domain does not make
tracking lawful without consent, and it must not be used to get around ad or
tracker blockers against a visitor's wishes.

### Setting it up

1. Under **Scripts**, add the vendor's script URL (it must be `https://`), choose its
   category and service, and tick **Proxy**.
2. If the vendor's runtime requests go to another host than the script file, enter
   that host as **Runtime host** (again `https://`).
3. Under **Allowed runtime endpoints**, list each method and exact path the script
   needs, one per line, for example `POST /api/send`. Only GET, HEAD and POST are
   allowed; no wildcards, queries or `..`. The script file itself is always allowed.
   Leave it empty to proxy only the script file.
4. Configure the vendor's script to send its requests to the proxy. The proxy does
   not reroute requests by itself. **Proxy diagnostics** has setup guides for
   Umami, Matomo and Google tags, with links to the vendor documentation.
5. Publish, then do the browser and vendor checks.

Vendors change their endpoints. When they do, update the allowed endpoints and
publish; no plugin update is needed. Older integrations keep the endpoints they
were saved with; review them before relying on them.

Limits: responses up to 2 MiB, request bodies up to 64 KiB, queries up to 8 KiB,
120 uncached upstream requests per minute per script. Redirects are rejected:
configure the final URL.

### Proxy diagnostics and alerts

**Consentimental → Proxy diagnostics** lists every monitored endpoint of every
published proxied script:

| State | Meaning |
| --- | --- |
| Not yet verified | No request has been observed or checked yet. |
| HTTP checks passing | The last check or real request succeeded. |
| Failure observed — confirming | One or two consecutive failures. |
| Failing | Three or more consecutive failures. |
| No recent evidence | The last success is more than 24 hours old. |

Script files are fetched fresh by an hourly WordPress cron job (five scripts per
run). Runtime endpoints are only observed in real, consented visitor traffic; the
plugin never sends fake tracking events. Most 4xx answers to visitor events are
ignored, so malformed events cannot cause alerts; 404, 410, 429 and connection
failures count. Changing an integration resets its history.

WordPress cron only runs when the site gets traffic. On quiet sites, set up a real
system cron job if regular checks matter to you.

**Email alerts are off by default.** When you turn them on, the WordPress
administration email address gets a message only when an endpoint that previously
worked has failed three times in a row. Each incident is reported once, and at
most one message is sent per day. Recovery allows a future alert. Messages contain
the script name and endpoint only, never visitor data or request contents. If
WordPress cannot send mail, the page says so. WordPress accepting a message does
not mean it was delivered.

Monitoring requests come from your server and carry no visitor cookies, IP
addresses or tracking data.

## 6. Troubleshooting

| Symptom | Likely cause | What to do |
| --- | --- | --- |
| A service never loads, even after Accept all | The service is not confirmed (not **In use**), or its script is assigned to an inactive service | Confirm it in Guided setup or Services, publish. Check the Status page for "Scripts can never run". |
| A tracker loads before consent | It is loaded by something the plugin does not recognise: a tag manager, an unknown host, a script added by JavaScript | Scan, check unrecognized hosts, add a service with the vendor's domain. Load tags through Scripts or gate them in your tag manager with Consent Mode. |
| The banner shows old wording or services | A page cache or CDN is serving an old copy | Purge the cache. Supported cache plugins are purged automatically on publish; CDNs and server caches need their own purge (see section 9). |
| Visitors see the banner again | You published a meaningful change, or their decision expired | Expected. The expiry is under **Overview → General settings**. |
| The page reloads when someone withdraws consent | A script that had already run was switched off; reloading is the only reliable way to stop it | Expected. Turn off **Reload on withdrawal** only if every vendor has a working shutdown integration. |
| An embed shows a placeholder instead of the video or map | Its service is not allowed yet | Expected. The placeholder has an Enable button and a link to the privacy settings. |
| A proxied integration shows "Failing" | The vendor URL changed, the vendor is down, or your server cannot reach it | Open Proxy diagnostics for the error. Check the URL and endpoints; ask your host about outbound connections. |
| No consent records appear | Recording is off, or a caching/security layer blocks the plugin's REST endpoint (`/wp-json/consentimental/v1/consent`) | Check **Consent log → Record consent decisions**. Make sure the endpoint is not cached or blocked. Consent itself still works without it. |
| Two page views per visit in analytics | The same tag is loaded twice, for example by a theme and by Scripts | Check the Status page for duplicate integrations; remove the tag from one place. |

## 7. Consent records, disclosure snapshots and data retention

### What is recorded

When recording is on (**Consent log → Record consent decisions**, on by default),
each decision a visitor makes is stored with:

- an anonymous consent ID (also stored in the visitor's `cmp_consent` cookie);
- time (UTC), action, the chosen categories and services, configuration version;
- **which disclosure the visitor was shown** (a snapshot identifier) and **the
  language it was shown in**;
- an anonymised IP address (last part removed), the browser's user agent, and the
  page address without query string.

Consent IDs are not linked to WordPress user accounts. WordPress's own
**Tools → Export/Erase Personal Data** cannot find them; search the consent log by
consent ID instead.

**Records are what the visitor's browser reported to an anonymous endpoint.** They
are client assertions, not authenticated proof of identity: anyone can submit a
decision under an ID they make up. They show what was recorded, not who a person is.

### Disclosure snapshots

A snapshot is a stored, unchangeable copy of what the banner disclosed: title and
text, button labels, categories and their descriptions, services with provider,
purpose, cookies and privacy links, the policy links, the consent duration, the
Consent Mode setting, and the language.

- A new snapshot is stored whenever any of that changes, including a new or changed
  translation. An existing snapshot is never modified. Each one is identified by a
  SHA-256 hash of its content.
- Snapshots are stored when you publish, when banner settings change (in the site's
  default language, as entered), and the first time a page shows a disclosure in a
  language (as the visitor saw it, including translations).
- A consent record points at a snapshot only if the visitor's page named a stored
  snapshot for the same configuration version. Otherwise the record shows
  "Unknown". Records made before version 0.3.0 all show "Unknown". **The plugin
  never fills in a missing snapshot from the current settings.**
- The snapshot view shows whether the stored content still matches its identifier.
  This detects accidental or partial changes; it does not protect against someone
  with database access rewriting both.
- The configuration version's publication time is recorded from version 0.3.0 on.
  For versions published earlier it shows as unknown.

Open a snapshot from the **Disclosure shown** column or the snapshot list on the
**Consent log** screen.

### Export

On **Consent log**, administrators can export all records, or the records of one
consent ID, as CSV, or as JSON together with every snapshot they reference. The
JSON file starts with notes that explain these limits. CSV values that a
spreadsheet would treat as formulas are prefixed with an apostrophe.

### Retention and deletion

| Data | Kept | Deleted |
| --- | --- | --- |
| Consent records | For the retention period (default 1,095 days, **Consent log → Retention**) | Daily by WordPress cron once older than the retention period; immediately with **Delete all records for this consent ID** |
| Disclosure snapshots | While any retained record references them | Daily, when older than the retention period and no record references them. The newest snapshot for each language is always kept. |
| Verification results | The last 20 | Replaced by newer ones |
| Proxy diagnostics | While the integration exists | When the script is deleted or changed |
| Visitor's `cmp_consent` cookie | In the visitor's browser until the consent expiry | By the visitor, or when it expires |

Turning recording off stops new records; existing ones stay until retention removes
them. Deactivating the plugin deletes nothing. Deleting the plugin deletes nothing
either, unless you turned on **Overview → General settings → Delete all plugin data
when the plugin is deleted**; then its settings, consent log and snapshots are
removed. Export the log first if you might need it.

## 8. Multilingual sites

With WPML or Polylang, banner wording, category names and descriptions, and service
names and purposes can be translated through the translation plugin's string
translation screen (group "consentimental"). The policy links point to the
translated page when one exists. TranslatePress translates the banner like any
other page content.

Each language's disclosure gets its own snapshot, and the consent log records the
language shown. Changing a translation creates a new snapshot the next time a page
is shown in that language; older records keep pointing at the wording they were
shown.

The plugin's own interface text uses normal WordPress language files. A German
translation ships for the visitor-facing banner, embed placeholders, built-in
service descriptions and the `[cmp_services]` cookie table. The administration
screens are only partly translated. The translation template is in
`languages/consentimental.pot`; tools such as Loco Translate can fill remaining
strings or create translations for other languages.

## 9. Caching and optimisation plugins

The banner and blocking are identical for every visitor, so full-page caching is
safe. When you publish, or change banner settings, the plugin purges WP Rocket,
LiteSpeed Cache, W3 Total Cache and WP Super Cache. Other caches, CDNs and server
caches (Cloudflare, Varnish, nginx) are not purged automatically; purge them
yourself, or hook the `cmp_config_published` action.

Do not let optimisation plugins defer, delay, combine or move the inline script with
the ID `cmp-bootstrap`. It must run first. Exclude it if your optimisation plugin
delays inline scripts. Autoptimize with default settings is compatible (tested).

Security plugins and CDNs must not block or cache `POST /wp-json/consentimental/v1/consent`.

See [compatibility.md](compatibility.md) for what has been tested.

## 10. Limits and what is not supported

- **No automatic legal compliance.** The plugin gives you tools. Whether your
  banner text, categories and setup are lawful depends on your site and jurisdiction.
- **Detection is not universal.** Only technologies matching a configured service
  (by domain, script pattern or iframe pattern) are blocked automatically. Scripts
  created by other JavaScript at runtime are only handled if they carry the plugin's
  markers or go through your tag manager's consent settings.
- **Google Consent Mode** passes consent signals to Google tags. It is not IAB TCF
  support and not a Google-certified CMP. Publishers that need TCF or a certified
  CMP (for example for Google AdSense/Ad Manager in the EEA) need another solution.
- **Not yet supported:** regional configurations (different rules per country),
  Global Privacy Control, validated import/export of configurations, multisite
  network management, agency workflows, and maintained translations of the
  plugin's interface.
