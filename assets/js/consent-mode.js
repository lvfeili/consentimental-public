(function (document, window, config) {
	'use strict';

	window.dataLayer = window.dataLayer || [];

	function gtag() {
		window.dataLayer.push(arguments);
	}

	window.gtag = window.gtag || gtag;

	function buildConsentPayload() {
		var payload = { security_storage: 'granted' };

		Object.keys(config.categoryMap).forEach(function (category) {
			var granted = !!(window.CMP && window.CMP.hasConsent(category));

			config.categoryMap[category].forEach(function (param) {
				payload[param] = granted ? 'granted' : 'denied';
			});
		});

		var privacy = window.CMP && window.CMP.getPrivacyChoice && window.CMP.getPrivacyChoice();
		if (privacy && privacy.enabled && (privacy.optedOut || !privacy.ready)) {
			payload.ad_user_data = 'denied';
			payload.ad_personalization = 'denied';
		}
		return payload;
	}

	var defaults = buildConsentPayload();

	if (config.waitForUpdate) {
		defaults.wait_for_update = config.waitForUpdate;
	}

	window.gtag('consent', 'default', defaults);

	document.addEventListener('cmp:change', function () {
		window.gtag('consent', 'update', buildConsentPayload());
	});
})(document, window, cmpConsentModeConfig);
