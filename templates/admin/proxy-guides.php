<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
?>
<h2><?php esc_html_e('Proxy setup guides', 'consentimental'); ?></h2>
<p><?php esc_html_e('Endpoint configuration belongs to your integration. Vendor updates do not automatically expand your allowlist. Update the exact paths in Scripts, publish, and verify real traffic. These examples are starting points; consult the linked vendor documentation for current requirements.', 'consentimental'); ?></p>
<h3>Umami</h3>
<ol>
<li><?php esc_html_e('Add the external script URL, its consent category/service, and the data-website-id attribute. Enable proxying.', 'consentimental'); ?></li>
<li><?php esc_html_e('Allow POST /api/send, or the exact custom collection path configured on your Umami server. Set the runtime host if it differs from the script host.', 'consentimental'); ?></li>
<li><?php esc_html_e('The proxy supplies data-host-url unless you override it in Additional attributes. Publish, grant consent, and confirm real POST requests use your local proxy URL.', 'consentimental'); ?></li>
</ol>
<p><a href="https://docs.umami.is/docs/tracker-configuration" target="_blank" rel="noopener noreferrer">Umami tracker configuration</a> · <a href="https://docs.umami.is/docs/api/sending-stats" target="_blank" rel="noopener noreferrer">Umami collection API</a></p>
<h3>Matomo / Piwik</h3>
<p><?php esc_html_e('Allow GET and POST for the exact collector path, for example /matomo.php or /analytics/matomo.php for a subdirectory installation. In your consent-gated initialization code, point setTrackerUrl at the local proxy base URL followed by that path, and setSiteId to your site ID. Loading matomo.js through the proxy alone does not reroute collection. Keep initialization before the external tracker; remove duplicate theme/plugin installations.', 'consentimental'); ?></p>
<pre>_paq.push(['setTrackerUrl', 'YOUR_PROXY_BASE/analytics/matomo.php']);
_paq.push(['setSiteId', 'YOUR_SITE_ID']);</pre>
<p><a href="https://developer.matomo.org/guides/tracking-javascript-guide" target="_blank" rel="noopener noreferrer">Matomo JavaScript integration guide</a></p>
<h3>Google Analytics / Google Ads / Google Tag Manager</h3>
<p><?php esc_html_e('Use Google’s documented tag gateway or server-side tagging setup for first-party delivery. This generic WordPress proxy is not a replacement for a Google tagging server. If integrating an existing supported collection setup, configure only its verified paths and methods. A proxied gtag.js or GTM container does not automatically reroute its tags, and data-host-url is not a universal Google setting.', 'consentimental'); ?></p>
<p><a href="https://developers.google.com/tag-platform/tag-manager/gateway" target="_blank" rel="noopener noreferrer">Google tag gateway</a></p>
<h3><?php esc_html_e('Other services', 'consentimental'); ?></h3>
<p><?php esc_html_e('For Meta, LinkedIn, TikTok, Clarity, and Hotjar, use vendor-supported routing and inspect real network requests before configuring paths. No generic complete-proxy recipe is claimed. Keep YouTube, Vimeo, Maps, and CAPTCHA widgets directly loaded behind the appropriate consent gate unless a vendor documents a supported alternative. Do not proxy or cache Turnstile’s script.', 'consentimental'); ?></p>
<p><a href="https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/" target="_blank" rel="noopener noreferrer">Cloudflare Turnstile requirements</a></p>
<h3><?php esc_html_e('Verify after every setup change', 'consentimental'); ?></h3>
<ol>
<li><?php esc_html_e('Before consent, check that no optional tracker requests are made.', 'consentimental'); ?></li>
<li><?php esc_html_e('After consent, confirm script and event requests use the expected host/path, and check the vendor dashboard for the real test event.', 'consentimental'); ?></li>
<li><?php esc_html_e('Withdraw consent and confirm processing stops. Check Proxy diagnostics for recent transport results; unknown or stale endpoints still need verification.', 'consentimental'); ?></li>
</ol>
