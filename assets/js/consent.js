(function (window, document, config) {
	'use strict';

	function readCookie(name) {
		var match = document.cookie.match('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)');

		try { return match ? decodeURIComponent(match[1]) : null; } catch (error) { return null; }
	}

	function writeCookie(name, value, expiresAt) {
		var maxAge = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
		var parts = [
			name + '=' + encodeURIComponent(value),
			'path=/',
			'max-age=' + maxAge,
			'samesite=lax',
		];

		if ('https:' === window.location.protocol) {
			parts.push('secure');
		}

		document.cookie = parts.join('; ');
	}

	function generateId() {
		if (window.crypto && window.crypto.randomUUID) {
			return window.crypto.randomUUID();
		}

		return 'cmp-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
	}

	function dispatch(name, detail) {
		document.dispatchEvent(new CustomEvent(name, { detail: detail }));
	}

	function readState() {
		var raw = readCookie(config.cookieName);

		if (!raw) {
			return null;
		}

		var parsed;

		try {
			parsed = JSON.parse(raw);
		} catch (error) {
			return null;
		}

		if (!parsed || 'object' !== typeof parsed || !parsed.categories) {
			return null;
		}

		if (parsed.expiry && parsed.expiry <= Math.floor(Date.now() / 1000)) {
			return null;
		}

		return parsed;
	}

	function isStale(candidate) {
		return !candidate || candidate.config_version !== config.configVersion;
	}

	function normalizeCategories(input) {
		var result = {};

		config.categories.forEach(function (category) {
			result[category.slug] = !categoryPrivacyBlocked(category.slug) && (category.required ? true : !!(input && input[category.slug]));
		});

		return result;
	}

	function categoryMeta(slug) {
		for (var i = 0; i < config.categories.length; i++) {
			if (config.categories[i].slug === slug) {
				return config.categories[i];
			}
		}

		return null;
	}

	function normalizeServices(input) {
		var result = {};

		Object.keys(config.services || {}).forEach(function (id) {
			if (input && Object.prototype.hasOwnProperty.call(input, id)) {
				result[id] = !!input[id] && !privacyBlocks(config.services[id]);
			}
		});

		return result;
	}

	function syncToServer(nextState, previousState) {
		if (!window.fetch) {
			return;
		}

		window
			.fetch(config.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				keepalive: true,
				body: JSON.stringify({
					consent_id: nextState.consent_id,
					categories: nextState.categories,
					services: nextState.services,
					config_version: nextState.config_version,
					previous_state: previousState || null,
					disclosure_id: (config.disclosure && config.disclosure.id) || null,
				}),
			})
			.catch(function () {
			});
	}

	var privacyCookie = 'cmp_sale_opt_out';
	var californiaReady = !config.californiaEnabled;
	var gpc = !!(window.navigator && window.navigator.globalPrivacyControl === true);
	var saleOptOut = readPrivacyChoice();
	var privacySyncFailed = false;

	function readPrivacyChoice() {
		var stored = false;
		try { stored = window.localStorage.getItem(privacyCookie) === '1'; } catch (error) {}
		return gpc || readCookie(privacyCookie) === '1' || stored;
	}

	function rememberPrivacyChoice() {
		if (config.preview) { return; }
		writeCookie(privacyCookie, '1', Math.floor(Date.now() / 1000) + 400 * 86400);
		try { window.localStorage.setItem(privacyCookie, '1'); } catch (error) {}
	}

	function privacyBlocks(meta) {
		if (!config.californiaEnabled) { return false; }
		if (meta.saleSharing === 'not_involved') { return false; }
		return meta.saleSharing !== 'involved' || saleOptOut || !californiaReady;
	}

	function categoryPrivacyBlocked(slug) {
		if (!config.californiaEnabled) { return false; }
		var ids = Object.keys(config.services || {}).filter(function (id) { return config.services[id].category === slug; });
		return !ids.length || ids.some(function (id) { return privacyBlocks(config.services[id]); });
	}

	function updatePrivacy(optedOut, ready, signal) {
		var previouslyAllowed = Object.keys(config.services || {}).filter(function (id) { return serviceConsent(state, id); });
		saleOptOut = saleOptOut || optedOut; // No implicit opt-in, including after GPC disappears.
		gpc = gpc || !!signal;
		californiaReady = ready;
		if (saleOptOut) { rememberPrivacyChoice(); }
		dispatch('cmp:change', state);
		dispatch('cmp:privacy', CMP.getPrivacyChoice());
		previouslyAllowed.forEach(function (id) {
			if (!serviceConsent(state, id)) { dispatch('cmp:withdraw', { serviceId: id, state: state }); }
		});
	}

	function postPrivacyChoice(source) {
		if (config.preview || !config.privacyUrl || !window.fetch) { return; }
		window.fetch(config.privacyUrl, {
			method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive: true,
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ opted_out: true, source: source })
		}).then(function (response) {
			if (!response.ok) { throw new Error('Privacy sync failed'); }
			privacySyncFailed = false;
			dispatch('cmp:privacy', CMP.getPrivacyChoice());
		}).catch(function () {
			privacySyncFailed = true;
			dispatch('cmp:privacy', CMP.getPrivacyChoice());
		});
	}

	var state = readState();
	var lastRawCookie = readCookie(config.cookieName);
	var channel = null;

	try {
		channel = window.BroadcastChannel ? new window.BroadcastChannel('cmp-consent') : null;
	} catch (error) {
		channel = null;
	}

	function announce() {
		lastRawCookie = readCookie(config.cookieName);

		if (channel) {
			try {
				channel.postMessage({ cookie: lastRawCookie, privacy: readCookie(privacyCookie) });
			} catch (error) {
			}
		}
	}

	function baseDecision() {
		if (!state || isStale(state)) {
			return { categories: {}, services: {} };
		}

		return { categories: state.categories, services: state.services || {} };
	}

	function serviceConsent(candidate, id) {
		var meta = config.services && config.services[id];
		if (!meta || privacyBlocks(meta)) { return false; }
		if (!isStale(candidate) && candidate.services && Object.prototype.hasOwnProperty.call(candidate.services, id)) {
			return !!candidate.services[id];
		}
		var category = categoryMeta(meta.category);
		if (config.explicitServiceConsent && !(category && category.required)) { return false; }
		return !!(category && category.required) || (!isStale(candidate) && !!candidate.categories[meta.category]);
	}

	function dispatchWithdrawals(previous, next) {
		var revoked = [];
		Object.keys(config.services || {}).forEach(function (id) {
			if (serviceConsent(previous, id) && !serviceConsent(next, id)) { revoked.push(id); }
		});
		config.categories.forEach(function (category) {
			if (!category.required && !isStale(previous) && previous.categories[category.slug] &&
				(isStale(next) || !next.categories[category.slug])) {
				dispatch('cmp:withdraw', { category: category.slug, services: revoked.filter(function (id) {
					return config.services[id].category === category.slug;
				}), state: next });
			}
		});
		revoked.forEach(function (id) {
			var category = config.services[id].category;
			if (!isStale(previous) && previous.categories[category] && (isStale(next) || !next.categories[category])) { return; }
			dispatch('cmp:withdraw', { serviceId: id, state: next });
		});
	}

	function persist(categories, services) {
		var now = Math.floor(Date.now() / 1000);
		var previous = state;

		var next = {
			schema_version: 1,
			consent_id: (previous && previous.consent_id) || generateId(),
			timestamp: now,
			expiry: now + config.expiryDays * 86400,
			config_version: config.configVersion,
			categories: normalizeCategories(categories),
			services: normalizeServices(services),
		};

		writeCookie(config.cookieName, JSON.stringify(next), next.expiry);
		state = next;
		announce();

		dispatch('cmp:change', next);

		if (isStale(previous)) {
			dispatch('cmp:consent', next);
		}

		dispatchWithdrawals(previous, next);

		syncToServer(next, previous);

		return next;
	}

	var CMP = {
		getPrivacyChoice: function () {
			return { enabled: !!config.californiaEnabled, optedOut: saleOptOut, gpc: gpc, ready: californiaReady, syncFailed: privacySyncFailed };
		},
		isServicePrivacyBlocked: function (id) {
			return !!(config.services && config.services[id] && privacyBlocks(config.services[id]));
		},
		optOutSaleSharing: function () {
			if (!config.californiaEnabled || config.preview) { return CMP.getPrivacyChoice(); }
			updatePrivacy(true, californiaReady, gpc);
			announce();
			postPrivacyChoice(gpc ? 'gpc' : 'manual');
			return CMP.getPrivacyChoice();
		},
		hasConsent: function (category) {
			if (categoryPrivacyBlocked(category)) { return false; }
			var meta = categoryMeta(category);

			if (meta && meta.required) {
				return true;
			}

			if (isStale(state)) {
				return false;
			}

			return !!state.categories[category];
		},

		hasServiceConsent: function (serviceId) {
			return serviceConsent(state, serviceId);
		},

		needsConsent: function () {
			return isStale(state);
		},

		getConsent: function () {
			return state ? JSON.parse(JSON.stringify(state)) : null;
		},

		setConsent: function (categories, services) {
			return persist(categories, services);
		},

		acceptAll: function () {
			var categories = {};
			var services = {};

			config.categories.forEach(function (category) {
				categories[category.slug] = true;
			});

			Object.keys(config.services || {}).forEach(function (id) {
				services[id] = true;
			});

			return persist(categories, services);
		},

		rejectAll: function () {
			var categories = {};
			var services = {};

			config.categories.forEach(function (category) {
				categories[category.slug] = !!category.required;
			});

			Object.keys(config.services || {}).forEach(function (id) {
				services[id] = false;
			});

			return persist(categories, services);
		},

		withdraw: function (categorySlugs) {
			var slugs = Array.isArray(categorySlugs) ? categorySlugs : [categorySlugs];
			var base = baseDecision();
			var next = {};

			config.categories.forEach(function (category) {
				next[category.slug] = !!base.categories[category.slug];
			});

			slugs.forEach(function (slug) {
				next[slug] = false;
			});

			var services = Object.assign({}, base.services);

			Object.keys(config.services || {}).forEach(function (id) {
				var meta = config.services[id];

				if (meta && -1 !== slugs.indexOf(meta.category)) {
					services[id] = false;
				}
			});

			return persist(next, services);
		},

		openSettings: function () {
			dispatch('cmp:open-settings', {});
		},

		enableService: function (serviceId) {
			if (!config.services || !Object.prototype.hasOwnProperty.call(config.services, serviceId)) {
				return CMP.getConsent();
			}

			var base = baseDecision();
			var services = Object.assign({}, base.services);
			services[serviceId] = true;

			return persist(base.categories, services);
		},

		reset: function () {
			var previous = state;

			writeCookie(config.cookieName, '', 0);
			state = null;
			announce();

			dispatch('cmp:change', null);

			dispatchWithdrawals(previous, null);

			return null;
		},
	};

	function syncFromCookie() {
		if (config.californiaEnabled) {
			var signal = !!(window.navigator && window.navigator.globalPrivacyControl === true);
			if ((!saleOptOut && (signal || readPrivacyChoice())) || (signal && !gpc)) {
				updatePrivacy(true, californiaReady, signal);
				if (signal) { postPrivacyChoice('gpc'); }
			}
		}
		var raw = readCookie(config.cookieName);

		if (raw === lastRawCookie) {
			return;
		}

		lastRawCookie = raw;

		var previous = state;
		var next = readState();

		state = next;
		dispatch('cmp:change', next);

		if (isStale(previous) && !isStale(next)) {
			dispatch('cmp:consent', next);
		}

		dispatchWithdrawals(previous, next);
	}

	if (channel) {
		channel.onmessage = function (event) {
			var expected = event && event.data ? event.data.cookie : undefined;
			var attempts = 0;

			(function attempt() {
				if (undefined === expected || (readCookie(config.cookieName) === expected && (!event.data.privacy || readCookie(privacyCookie) === event.data.privacy)) || ++attempts > 40) {
					syncFromCookie();

					return;
				}

				setTimeout(attempt, 50);
			})();
		};
	}

	document.addEventListener('visibilitychange', function () {
		if ('visible' === document.visibilityState) {
			syncFromCookie();
		}
	});
	window.addEventListener('storage', syncFromCookie);
	window.addEventListener('focus', syncFromCookie);
	window.addEventListener('pageshow', syncFromCookie);

	CMP.sync = syncFromCookie;

	window.CMP = CMP;

	if (config.californiaEnabled && !config.preview) {
		if (saleOptOut) { rememberPrivacyChoice(); }
		if (saleOptOut) { postPrivacyChoice(gpc ? 'gpc' : 'stored'); }
		if (window.fetch && config.privacyUrl) {
			window.fetch(config.privacyUrl, { credentials: 'same-origin', cache: 'no-store' })
				.then(function (response) { if (!response.ok) { throw new Error('Privacy check failed'); } return response.json(); })
				.then(function (data) {
					if (!data || data.enabled !== true || typeof data.gpc !== 'boolean' || typeof data.opted_out !== 'boolean') { throw new Error('Invalid privacy check'); }
					var newlyOptedOut = !saleOptOut && (data.gpc || data.opted_out);
					updatePrivacy(data.gpc || data.opted_out, true, data.gpc);
					if (newlyOptedOut) { announce(); postPrivacyChoice(data.gpc ? 'gpc' : 'manual'); }
				}).catch(function () {
					privacySyncFailed = true;
					dispatch('cmp:privacy', CMP.getPrivacyChoice());
				});
		}
	}

	dispatch('cmp:ready', { state: CMP.getConsent(), needsConsent: CMP.needsConsent() });
})(window, document, cmpConfig);
