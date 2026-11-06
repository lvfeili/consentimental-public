(function (document, window, config) {
	'use strict';

	var cleanupCallbacks = {};

	function escapeRegExp(text) {
		return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	function matchesPattern(name, pattern) {
		if (-1 === pattern.indexOf('*')) {
			return name === pattern;
		}

		var regex = new RegExp('^' + pattern.split('*').map(escapeRegExp).join('.*') + '$');

		return regex.test(name);
	}

	function currentCookieNames() {
		return document.cookie
			.split(';')
			.map(function (part) {
				return part.split('=')[0].trim();
			})
			.filter(Boolean);
	}

	function domainVariants(host) {
		var labels = host.split('.');
		var variants = [''];

		for (var i = 0; i < labels.length - 1; i++) {
			var suffix = labels.slice(i).join('.');
			variants.push(suffix, '.' + suffix);
		}

		return variants;
	}

	function pathVariants(pathname) {
		var segments = pathname.split('/').filter(Boolean);
		var paths = ['/'];
		var current = '';

		segments.forEach(function (segment) {
			current += '/' + segment;
			paths.push(current, current + '/');
		});

		return paths;
	}

	function deleteCookie(name) {
		if (name === config.cookieName || name === 'cmp_sale_opt_out') { return; }
		var domains = domainVariants(window.location.hostname);
		var paths = pathVariants(window.location.pathname);

		domains.forEach(function (domain) {
			paths.forEach(function (path) {
				var cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + path;

				if (domain) {
					cookie += '; domain=' + domain;
				}

				document.cookie = cookie;
			});
		});
	}

	function cleanupStorage(storage, patterns) {
		if (!storage || !patterns || 0 === patterns.length) {
			return;
		}

		var keys = [];

		try {
			keys = Object.keys(storage);
		} catch (error) {
			return; // storage access can throw (e.g. disabled in this browsing context)
		}

		keys.forEach(function (key) {
			if (key === 'cmp_sale_opt_out') { return; }
			patterns.forEach(function (pattern) {
				if (matchesPattern(key, pattern)) {
					try {
						storage.removeItem(key);
					} catch (error) {
					}
				}
			});
		});
	}

	function cleanupService(serviceId) {
		var info = config.services && config.services[serviceId];

		if (!info) {
			return;
		}

		var cookiePatterns = (info.cookies || []).filter(function (pattern) {
			return pattern !== config.cookieName;
		});

		if (cookiePatterns.length > 0) {
			var before = currentCookieNames();

			before.forEach(function (name) {
				cookiePatterns.forEach(function (pattern) {
					if (matchesPattern(name, pattern)) {
						deleteCookie(name);
					}
				});
			});
		}

		cleanupStorage(window.localStorage, info.storageKeys);
		cleanupStorage(window.sessionStorage, info.storageKeys);

		(cleanupCallbacks[serviceId] || []).forEach(function (callback) {
			try {
				callback();
			} catch (error) {
			}
		});

		var remaining = currentCookieNames().filter(function (name) {
			return cookiePatterns.some(function (pattern) {
				return matchesPattern(name, pattern);
			});
		});

		document.dispatchEvent(
			new CustomEvent('cmp:cleanup', { detail: { serviceId: serviceId, remainingCookies: remaining } }),
		);
	}

	function servicesInCategory(category) {
		var ids = [];

		Object.keys(config.services || {}).forEach(function (id) {
			if (config.services[id].category === category) {
				ids.push(id);
			}
		});

		return ids;
	}

	document.addEventListener('cmp:withdraw', function (event) {
		var detail = event.detail || {};

		if (detail.serviceId) {
			cleanupService(detail.serviceId);

			return;
		}

		if (detail.category) {
			(detail.services || servicesInCategory(detail.category)).forEach(cleanupService);
		}
	});

	if (window.CMP) {
		window.CMP.registerCleanup = function (serviceId, callback) {
			cleanupCallbacks[serviceId] = cleanupCallbacks[serviceId] || [];
			cleanupCallbacks[serviceId].push(callback);
		};
	}
})(document, window, cmpConfig);
