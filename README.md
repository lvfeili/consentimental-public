# Consentimental

By [Philipp Krueger](https://www.philippkrueger.com/).

A WordPress consent management plugin. It blocks consent-dependent scripts, embeds and pixels until a visitor allows them, and helps administrators check that this is really what happens on their site.

- Blocking before consent for known services and for scripts you manage in the plugin, released per category or per service.
- A banner with equally easy Accept all / Reject all, per-service choices, a floating button to change the decision, and a link to your privacy policy.
- Guided setup: scan the site, confirm services, fix configuration problems, preview, publish, verify.
- A Status page that keeps configuration checks, discovery, proxy availability, browser enforcement and vendor receipt apart, with explicit passed / failed / not checked states.
- Draft and publish for consent configuration; visitors are asked again when services or categories change.
- An optional consent log that records, for each decision, an immutable snapshot of what was disclosed and in which language, with CSV/JSON export and retention.
- Optional EU/Germany consent safeguards and additive California sale/sharing controls, including GPC recognition, service review, independent opt-outs and integration hooks for backend systems.
- Google Consent Mode v2 (Basic and Advanced). This is not IAB TCF support and not a certified CMP.
- An optional first-party proxy for scripts you manage, with diagnostics and opt-in email alerts.

It does not make a site legally compliant by itself, and it does not detect every tracking technology. See the limits in the administrator guide.

## Documentation

| For | Read |
| --- | --- |
| Site administrators | [docs/admin-guide.md](docs/admin-guide.md): setup, verification, diagnostics, proxy, troubleshooting, data retention |
| What has been tested with which plugins and versions | [docs/compatibility.md](docs/compatibility.md) |
| Developers | [docs/development.md](docs/development.md): environment, tests, release, hooks |
| Changes per release | [CHANGELOG.md](CHANGELOG.md) |
| How the design evolved (audit history) | [docs/development-history.md](docs/development-history.md) |
| Original product brief | [brief.md](brief.md) |

## Requirements and installation

WordPress 6.4+ and PHP 8.1+. Upload the release zip under **Plugins → Add New → Upload Plugin** and activate it, then open **Consentimental → Guided setup**. No Composer or build step is needed.

## License

GPL-2.0-or-later.
