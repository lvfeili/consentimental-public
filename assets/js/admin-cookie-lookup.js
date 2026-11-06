(function (window, document) {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var domainsField = document.getElementById('cmp-domains');
		var cookiesField = document.getElementById('cmp-cookies');
		var database = window.cmpCookieDatabase || {};

		if (!domainsField || !cookiesField) {
			return;
		}

		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'button';
		button.textContent = (window.cmpCookieLookupL10n && window.cmpCookieLookupL10n.buttonLabel) || 'Suggest cookies from domains';
		button.addEventListener('click', suggestCookies);

		cookiesField.insertAdjacentElement('afterend', button);

		function existingCookieNames() {
			return cookiesField.value
				.split('\n')
				.map(function (line) {
					return line.split('|')[0].trim().toLowerCase();
				})
				.filter(Boolean);
		}

		function cookiesForDomain(domain) {
			domain = domain.replace(/^\.+/, '').toLowerCase();

			if (database[domain]) {
				return database[domain];
			}

			for (var key in database) {
				if (Object.prototype.hasOwnProperty.call(database, key) && domain.slice(-(key.length + 1)) === '.' + key) {
					return database[key];
				}
			}

			return [];
		}

		function suggestCookies() {
			var domains = domainsField.value.split('\n').map(function (line) {
				return line.trim();
			}).filter(Boolean);

			var known = existingCookieNames();
			var suggestions = [];

			domains.forEach(function (domain) {
				cookiesForDomain(domain).forEach(function (cookie) {
					if (known.indexOf(cookie.name.toLowerCase()) === -1) {
						known.push(cookie.name.toLowerCase());
						suggestions.push([cookie.name, cookie.duration || '', cookie.purpose || ''].join('|'));
					}
				});
			});

			if (0 === suggestions.length) {
				return;
			}

			var existing = cookiesField.value.replace(/\n+$/, '');
			cookiesField.value = (existing ? existing + '\n' : '') + suggestions.join('\n');
		}
	});
})(window, document);
