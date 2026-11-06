(function (document) {
	'use strict';

	var SELECTOR = '[data-cmp-category], [data-cmp-service]';
	var l10n = window.cmpBlockingL10n || {};

	function format(str, args) {
		return (str || '').replace(/%(\d)\$s/g, function (match, index) {
			return args[Number(index) - 1];
		});
	}

	function categoryLabel(slug) {
		var categories = (window.cmpConfig && window.cmpConfig.categories) || [];

		for (var i = 0; i < categories.length; i++) {
			if (categories[i].slug === slug) {
				return categories[i].label || slug;
			}
		}

		return slug;
	}

	function serviceInfo(serviceId) {
		return (window.cmpConfig && window.cmpConfig.services && window.cmpConfig.services[serviceId]) || null;
	}

	function mayRelease(el) {
		if (el.hasAttribute('data-cmp-services')) {
			try {
				var ids = JSON.parse(el.getAttribute('data-cmp-services'));
				return Array.isArray(ids) && ids.length > 0 && ids.every(function (id) { return window.CMP.hasServiceConsent(id); });
			} catch (error) { return false; }
		}
		var serviceId = el.getAttribute('data-cmp-service');

		if (serviceId) {
			return window.CMP.hasServiceConsent(serviceId);
		}

		var category = el.getAttribute('data-cmp-category');
		if (window.cmpConfig && window.cmpConfig.californiaEnabled) { return false; }
		return !!category && window.CMP.hasConsent(category);
	}

	function markReleased(el, serviceId, category) {
		el.setAttribute('data-cmp-released', serviceId || category || '');

		document.dispatchEvent(
			new CustomEvent('cmp:release', {
				detail: { tag: el.tagName, service: serviceId || null, category: category || null },
			}),
		);
	}

	var scriptQueue = [];
	var queuedScripts = new WeakSet();
	var drainingScripts = false;
	var scriptLoadFailed = false;
	var moduleSequence = 0;

	function enqueueScript(el) {
		if (queuedScripts.has(el) || scriptLoadFailed) { return; }
		queuedScripts.add(el);
		scriptQueue.push(el);
		drainScripts();
	}

	async function drainScripts() {
		if (drainingScripts) { return; }
		drainingScripts = true;
		try {
			while (scriptQueue.length) {
				var el = scriptQueue.shift();
				queuedScripts.delete(el);
				if (!el.isConnected || !mayRelease(el)) { continue; }
				await releaseScript(el);
			}
		} catch (error) {
			scriptLoadFailed = true;
			scriptQueue = [];
			document.dispatchEvent(new CustomEvent('cmp:script-error', { detail: { message: error.message } }));
		} finally {
			drainingScripts = false;
		}
	}

	function releaseScript(el) {
		var serviceId = el.getAttribute('data-cmp-service');
		var category = el.getAttribute('data-cmp-category');
		var originalType = el.getAttribute('data-cmp-original-type');
		var clone = document.createElement('script');

		Array.prototype.forEach.call(el.attributes, function (attr) {
			if ('type' === attr.name || 0 === attr.name.indexOf('data-cmp-')) {
				return;
			}

			clone.setAttribute(attr.name, attr.value);
		});

		if (originalType) {
			clone.type = originalType;
		}

		if (clone.src) { clone.async = false; }

		if (!clone.src && el.textContent) {
			clone.text = el.textContent;
		}

		return new Promise(function (resolve, reject) {
			var inlineModule = !clone.src && 'module' === clone.type;
			var moduleEvent = inlineModule ? 'cmp:module-finished:' + (++moduleSequence) : null;
			var waitsForLoad = !!clone.src || inlineModule;
			var timer;
			function moduleFinished() { finish(); }
			function finish(error) {
				clearTimeout(timer);
				if (moduleEvent) { document.removeEventListener(moduleEvent, moduleFinished); }
				clone.onload = clone.onerror = null;
				if (error) { reject(error); } else { resolve(); }
			}
			if (inlineModule) {
				document.addEventListener(moduleEvent, moduleFinished);
				clone.text += '\n;document.dispatchEvent(new Event(' + JSON.stringify(moduleEvent) + '));\n';
			}
			if (waitsForLoad) {
				clone.onload = function () {
					if ('module' === clone.type && clone.src) {
						import(clone.src).then(function () { finish(); }, finish);
					} else { finish(); }
				};
				clone.onerror = function () { finish(new Error('Consent-gated script failed to load: ' + (clone.src || 'inline module'))); };
				timer = setTimeout(function () { finish(new Error('Consent-gated script load timed out')); }, 30000);
			}
			markReleased(clone, serviceId, category);
			el.parentNode.replaceChild(clone, el);
			if (!waitsForLoad) { finish(); }
		});
	}

	function releaseEmbed(el) {
		var srcdoc = el.getAttribute('data-cmp-srcdoc');
		var src = el.getAttribute('data-cmp-src');
		var srcset = el.getAttribute('data-cmp-srcset');
		var serviceId = el.getAttribute('data-cmp-service');
		var category = el.getAttribute('data-cmp-category');

		if (srcdoc !== null) {
			el.setAttribute('srcdoc', srcdoc);
			el.removeAttribute('data-cmp-srcdoc');
		}

		if (src) {
			el.setAttribute('src', src);
			el.removeAttribute('data-cmp-src');
		}

		if (srcset) {
			el.setAttribute('srcset', srcset);
			el.removeAttribute('data-cmp-srcset');
		}

		markReleased(el, serviceId, category);

		removePlaceholder(el);
	}

	function reblockEmbed(el) {
		var srcdoc = el.getAttribute('srcdoc');
		var src = el.getAttribute('src');
		var srcset = el.getAttribute('srcset');
		var serviceId = el.getAttribute('data-cmp-service');
		var category = el.getAttribute('data-cmp-category');

		if (src) {
			el.setAttribute('data-cmp-src', src);
			el.removeAttribute('src');
		}

		if (srcset) {
			el.setAttribute('data-cmp-srcset', srcset);
			el.removeAttribute('srcset');
		}
		if (srcdoc !== null) {
			el.setAttribute('data-cmp-srcdoc', srcdoc);
			el.removeAttribute('srcdoc');
		}

		el.removeAttribute('data-cmp-released');

		document.dispatchEvent(
			new CustomEvent('cmp:reblock', {
				detail: { tag: el.tagName, service: serviceId || null, category: category || null },
			}),
		);

		showPlaceholder(el);
	}

	function placeholderMessage(el) {
		if (window.CMP.isServicePrivacyBlocked && window.CMP.isServicePrivacyBlocked(el.getAttribute('data-cmp-service'))) {
			return l10n.privacyBlocked;
		}
		if (el.hasAttribute('data-cmp-services')) {
			return l10n.multipleProviders || 'This content uses multiple services. Review and allow them in privacy settings to load it.';
		}
		var serviceId = el.getAttribute('data-cmp-service');
		var info = serviceId ? serviceInfo(serviceId) : null;
		var category = el.getAttribute('data-cmp-category');
		var categoryText = category ? categoryLabel(category) : '';
		var provider = info ? info.provider || info.name : null;

		if (provider && categoryText) {
			return format(l10n.contentByProviderRequiresConsent, [provider, categoryText]);
		}

		if (categoryText) {
			return format(l10n.contentRequiresConsent, [categoryText]);
		}

		return l10n.contentRequiresConsentGeneric;
	}

	function showPlaceholder(el) {
		if ('IFRAME' !== el.tagName || el.dataset.cmpPlaceholderShown) {
			return;
		}

		var serviceId = el.getAttribute('data-cmp-service');
		var placeholder = document.createElement('div');
		placeholder.className = 'cmp-embed-placeholder';

		var width = el.getAttribute('width');
		var height = el.getAttribute('height');

		if (width) {
			placeholder.style.width = /%$/.test(width) ? width : width + 'px';
		}

		if (height) {
			placeholder.style.height = /%$/.test(height) ? height : height + 'px';
		}

		var message = document.createElement('p');
		message.textContent = placeholderMessage(el);
		placeholder.appendChild(message);

		var info = serviceId ? serviceInfo(serviceId) : null;
		if (info && !el.hasAttribute('data-cmp-services') && l10n.connectionNotice) {
			var notice = document.createElement('p');
			notice.textContent = format(l10n.connectionNotice, [info.provider || info.name || serviceId]);
			placeholder.appendChild(notice);
		}

		var actions = document.createElement('div');
		actions.className = 'cmp-embed-placeholder__actions';

		if (serviceId && info && !el.hasAttribute('data-cmp-services') && !(window.CMP.isServicePrivacyBlocked && window.CMP.isServicePrivacyBlocked(serviceId))) {
			var enableButton = document.createElement('button');
			enableButton.type = 'button';
			enableButton.className = 'cmp-btn cmp-btn--primary';
			enableButton.textContent = format(l10n.enableService, [(serviceInfo(serviceId) || {}).name || serviceId]);
			enableButton.addEventListener('click', function () {
				window.CMP.enableService(serviceId);
			});
			actions.appendChild(enableButton);
		}

		var settingsButton = document.createElement('button');
		settingsButton.type = 'button';
		settingsButton.className = 'cmp-btn cmp-btn--secondary';
		settingsButton.textContent = l10n.openPrivacySettings;
		settingsButton.setAttribute('data-cmp-open-settings', '');
		actions.appendChild(settingsButton);

		placeholder.appendChild(actions);

		if (info && info.privacyPolicyUrl) {
			var privacyLink = document.createElement('a');
			privacyLink.className = 'cmp-embed-placeholder__privacy-link';
			privacyLink.href = info.privacyPolicyUrl;
			privacyLink.target = '_blank';
			privacyLink.rel = 'noopener noreferrer';
			privacyLink.textContent = format(l10n.providerPrivacyPolicy || '%1$s privacy policy', [info.provider || info.name || serviceId]);
			placeholder.appendChild(privacyLink);
		}

		el.style.display = 'none';
		el.insertAdjacentElement('afterend', placeholder);
		el.dataset.cmpPlaceholderShown = '1';
	}

	function removePlaceholder(el) {
		if (!el.dataset.cmpPlaceholderShown) {
			return;
		}

		el.style.display = '';
		delete el.dataset.cmpPlaceholderShown;

		var next = el.nextElementSibling;

		if (next && next.classList.contains('cmp-embed-placeholder')) {
			next.remove();
		}
	}

	function tryRelease(el) {
		if (!el.hasAttribute('data-cmp-category') && !el.hasAttribute('data-cmp-service')) {
			return;
		}

		var isReleased = el.hasAttribute('data-cmp-released');

		if (mayRelease(el)) {
			if (isReleased) {
				return;
			}

			if ('SCRIPT' === el.tagName) {
				enqueueScript(el);
			} else {
				releaseEmbed(el);
			}

			return;
		}

		if (isReleased) {
			reblockEmbed(el);
		} else {
			showPlaceholder(el);
		}
	}

	function scan(root) {
		if (root.matches && root.matches(SELECTOR)) {
			tryRelease(root);
		}

		if (root.querySelectorAll) {
			root.querySelectorAll(SELECTOR).forEach(tryRelease);
		}
	}

	var reloadScheduled = false;

	function maybeReloadAfterWithdrawal(detail) {
		if (!window.cmpConfig || !window.cmpConfig.reloadOnWithdrawal) {
			return;
		}

		var releasedScripts = document.querySelectorAll('script[data-cmp-released]');
		var configServices = window.cmpConfig.services || {};

		var affected = Array.prototype.some.call(releasedScripts, function (el) {
			var released = el.getAttribute('data-cmp-released');

			if (!released) {
				return false;
			}

			if (detail.serviceId && released === detail.serviceId) {
				return true;
			}

			if (detail.category && released === detail.category) {
				return true;
			}

			var meta = configServices[released];

			return !!(detail.category && meta && meta.category === detail.category && !window.CMP.hasServiceConsent(released));
		});

		if (affected && !reloadScheduled) {
			reloadScheduled = true;
			setTimeout(function () { window.location.reload(); }, 0);
		}
	}

	function init() {
		if (!window.CMP) {
			return;
		}

		scan(document.documentElement || document);

		document.addEventListener('cmp:change', function () {
			scan(document.documentElement || document);
		});
		document.addEventListener('cmp:privacy', function () {
			document.querySelectorAll('iframe[data-cmp-placeholder-shown]').forEach(removePlaceholder);
			scan(document.documentElement || document);
		});

		document.addEventListener('cmp:withdraw', function (event) {
			maybeReloadAfterWithdrawal(event.detail || {});
		});

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (1 === node.nodeType) {
						scan(node);
					}
				});
			});
		});

		observer.observe(document.documentElement, { childList: true, subtree: true });
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(document);
