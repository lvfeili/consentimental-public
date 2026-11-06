(function (document, window) {
	'use strict';

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = String(text);

		return div.innerHTML;
	}

	function renderConsent() {
		var table = document.getElementById('cmp-debug-consent');
		var categories = (window.cmpConfig && window.cmpConfig.categories) || [];

		table.innerHTML = categories
			.map(function (category) {
				var active = window.CMP.hasConsent(category.slug);

				return (
					'<tr><td>' +
					escapeHtml(category.label) +
					'</td><td>' +
					(active ? 'ACTIVE' : 'DENIED') +
					'</td></tr>'
				);
			})
			.join('');
	}

	function renderConfig() {
		var dl = document.getElementById('cmp-debug-config');
		var state = window.CMP.getConsent();

		var rows = [
			['Current config version', window.cmpConfig.configVersion],
			['Stored config version', state ? state.config_version : 'n/a'],
			['Needs consent', window.CMP.needsConsent() ? 'yes' : 'no'],
			['Consent id', state ? state.consent_id : 'n/a'],
		];

		dl.innerHTML = rows
			.map(function (row) {
				return '<dt>' + escapeHtml(row[0]) + '</dt><dd>' + escapeHtml(row[1]) + '</dd>';
			})
			.join('');
	}

	function renderServices() {
		var ul = document.getElementById('cmp-debug-services');
		var services = (window.cmpConfig && window.cmpConfig.services) || {};
		var ids = Object.keys(services);

		if (0 === ids.length) {
			ul.innerHTML = '<li>(no services registered)</li>';

			return;
		}

		ul.innerHTML = ids
			.map(function (id) {
				var allowed = window.CMP.hasServiceConsent(id);

				return (
					'<li>' +
					(allowed ? '✅' : '⛔') +
					' ' +
					escapeHtml(services[id].name) +
					' <code>(' +
					escapeHtml(services[id].category) +
					')</code></li>'
				);
			})
			.join('');
	}

	function renderConsentMode() {
		var section = document.getElementById('cmp-debug-consent-mode-section');
		var table = document.getElementById('cmp-debug-consent-mode');

		if (!Array.isArray(window.dataLayer)) {
			section.hidden = true;

			return;
		}

		var current = {};

		window.dataLayer.forEach(function (entry) {
			var args = Array.prototype.slice.call(entry);

			if ('consent' === args[0] && ('default' === args[1] || 'update' === args[1]) && args[2]) {
				Object.keys(args[2]).forEach(function (key) {
					current[key] = args[2][key];
				});
			}
		});

		var keys = Object.keys(current);

		if (0 === keys.length) {
			section.hidden = true;

			return;
		}

		section.hidden = false;
		table.innerHTML = keys
			.map(function (key) {
				return '<tr><td>' + escapeHtml(key) + '</td><td>' + escapeHtml(current[key]) + '</td></tr>';
			})
			.join('');
	}

	function describeElement(el) {
		var label = el.getAttribute('data-cmp-service') || el.getAttribute('data-cmp-category') || el.getAttribute('data-cmp-released');
		var src = el.getAttribute('src') || el.getAttribute('data-cmp-src') || '';

		return el.tagName + ': ' + label + (src ? ' — ' + src : '');
	}

	function renderElementList(selector, targetId) {
		var ul = document.getElementById(targetId);
		var elements = document.querySelectorAll(selector);

		if (0 === elements.length) {
			ul.innerHTML = '<li>(none)</li>';

			return;
		}

		ul.innerHTML = Array.prototype.map
			.call(elements, function (el) {
				return '<li>' + escapeHtml(describeElement(el)) + '</li>';
			})
			.join('');
	}

	function renderAll() {
		renderConsent();
		renderConfig();
		renderServices();
		renderConsentMode();
		renderElementList('[data-cmp-category], [data-cmp-service]', 'cmp-debug-blocked');
		renderElementList('[data-cmp-released]', 'cmp-debug-released');
	}

	function runSelfTestAction(action) {
		switch (action) {
			case 'reset':
				window.CMP.reset();
				break;
			case 'none':
				window.CMP.rejectAll();
				break;
			case 'analytics':
				window.CMP.setConsent({ analytics: true });
				break;
			case 'marketing':
				window.CMP.setConsent({ marketing: true });
				break;
			case 'all':
				window.CMP.acceptAll();
				break;
			case 'reopen-banner': {
				var banner = document.getElementById('cmp-banner');

				if (banner) {
					banner.hidden = false;
				}

				break;
			}
			default:
				break;
		}
	}

	function init() {
		var toggle = document.getElementById('cmp-debug-toggle');
		var panel = document.getElementById('cmp-debug-panel');

		if (!toggle || !panel || !window.CMP) {
			return;
		}

		function open() {
			panel.hidden = false;
			toggle.setAttribute('aria-expanded', 'true');
			renderAll();
		}

		function close() {
			panel.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
		}

		toggle.addEventListener('click', function () {
			if (panel.hidden) {
				open();
			} else {
				close();
			}
		});

		var closeButton = panel.querySelector('.cmp-debug-panel__close');

		if (closeButton) {
			closeButton.addEventListener('click', close);
		}

		panel.querySelectorAll('[data-cmp-debug-action]').forEach(function (button) {
			button.addEventListener('click', function () {
				runSelfTestAction(button.getAttribute('data-cmp-debug-action'));
			});
		});

		document.addEventListener('cmp:change', function () {
			if (!panel.hidden) {
				renderAll();
			}
		});

		document.addEventListener('cmp:release', function () {
			if (!panel.hidden) {
				renderAll();
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(document, window);
