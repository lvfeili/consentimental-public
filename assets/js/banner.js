(function (document) {
	'use strict';

	var FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	var banner;
	var customize;
	var backdrop;
	var floatingButton;
	var lastFocusedElement = null;
	var preview = !!(window.cmpConfig && window.cmpConfig.preview);
	var focusTrapHandler = null;

	function toggleHidden(el, hidden) {
		if (el) {
			el.hidden = hidden;
		}
	}

	function isHidden(el) {
		return !el || el.hidden;
	}

	function trapFocus(container) {
		var focusable = container.querySelectorAll(FOCUSABLE_SELECTOR);

		if (0 === focusable.length) {
			return;
		}

		var first = focusable[0];
		var last = focusable[focusable.length - 1];

		first.focus();

		focusTrapHandler = function (event) {
			if ('Escape' === event.key) {
				event.preventDefault();
				closeCustomize();

				return;
			}

			if ('Tab' !== event.key) {
				return;
			}

			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		};

		container.addEventListener('keydown', focusTrapHandler);
	}

	function releaseFocusTrap(container) {
		if (focusTrapHandler) {
			container.removeEventListener('keydown', focusTrapHandler);
			focusTrapHandler = null;
		}
	}

	var bannerTrapHandler = null;
	var bannerFocused = false;

	function requiresDecision() {
		return !(window.cmpConfig && false === window.cmpConfig.requireDecisionBeforeInteraction);
	}

	function manageBannerFocus(shown) {
		var modal = shown && requiresDecision();

		banner.setAttribute('aria-modal', modal ? 'true' : 'false');

		if (modal && !bannerTrapHandler) {
			bannerTrapHandler = function (event) {
				if ('Tab' !== event.key) {
					return;
				}

				var focusable = banner.querySelectorAll(FOCUSABLE_SELECTOR);
				var first = focusable[0];
				var last = focusable[focusable.length - 1];

				if (event.shiftKey && (document.activeElement === first || document.activeElement === banner)) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			};
			banner.addEventListener('keydown', bannerTrapHandler);
		} else if (!modal && bannerTrapHandler) {
			banner.removeEventListener('keydown', bannerTrapHandler);
			bannerTrapHandler = null;
		}

		if (modal && !bannerFocused) {
			bannerFocused = true;
			banner.setAttribute('tabindex', '-1');
			banner.focus({ preventScroll: true });
		}
	}

	function updateVisibility() {
		var needsConsent = window.CMP.needsConsent();
		var requireDecision = requiresDecision();

		toggleHidden(banner, !needsConsent);
		manageBannerFocus(needsConsent);
		toggleHidden(backdrop, isHidden(customize) ? (!needsConsent || !requireDecision) : false);

		if (floatingButton) {
			toggleHidden(floatingButton, needsConsent);
		}
	}

	function serviceTogglesFor(categorySlug) {
		return Array.prototype.slice.call(customize.querySelectorAll('[data-cmp-service-category="' + categorySlug + '"]'));
	}

	function updateCategoryFromServices(categoryToggle) {
		if (categoryToggle.disabled) {
			return;
		}

		var slug = categoryToggle.getAttribute('data-cmp-category-toggle');
		var services = serviceTogglesFor(slug);

		if (0 === services.length) {
			return;
		}

		var checkedCount = 0;

		services.forEach(function (toggle) {
			if (toggle.checked) {
				checkedCount += 1;
			}
		});

		categoryToggle.checked = checkedCount === services.length;
		categoryToggle.indeterminate = checkedCount > 0 && checkedCount < services.length;
	}

	function populateCustomizeFromState() {
		customize.querySelectorAll('[data-cmp-service-toggle]').forEach(function (toggle) {
			var privacyBlocked = window.CMP.isServicePrivacyBlocked && window.CMP.isServicePrivacyBlocked(toggle.getAttribute('data-cmp-service-toggle'));
			if (!toggle.hasAttribute('data-cmp-original-disabled')) { toggle.setAttribute('data-cmp-original-disabled', toggle.disabled ? '1' : '0'); }
			toggle.disabled = privacyBlocked || toggle.getAttribute('data-cmp-original-disabled') === '1';
			if (privacyBlocked) { toggle.checked = false; return; }
			if (toggle.disabled) {
				toggle.checked = true;

				return;
			}

			toggle.checked = window.CMP.hasServiceConsent(toggle.getAttribute('data-cmp-service-toggle'));
		});

		customize.querySelectorAll('[data-cmp-category-toggle]').forEach(function (toggle) {
			if (toggle.disabled) {
				toggle.checked = true;

				return;
			}

			var slug = toggle.getAttribute('data-cmp-category-toggle');

			if (0 === serviceTogglesFor(slug).length) {
				toggle.checked = window.CMP.hasConsent(slug);

				return;
			}

			updateCategoryFromServices(toggle);
		});
	}

	function openCustomize(triggerElement) {
		lastFocusedElement = triggerElement || document.activeElement;

		populateCustomizeFromState();
		toggleHidden(banner, true);
		toggleHidden(backdrop, false);
		toggleHidden(customize, false);
		trapFocus(customize);
	}

	function closeCustomize() {
		releaseFocusTrap(customize);
		toggleHidden(customize, true);
		updateVisibility();

		if (lastFocusedElement && 'function' === typeof lastFocusedElement.focus) {
			lastFocusedElement.focus();
		}

		lastFocusedElement = null;
	}

	function closeEverything() {
		if (isHidden(customize)) {
			updateVisibility();

			return;
		}

		closeCustomize();
	}

	function readCustomizeSelections() {
		var categories = {};
		var services = {};

		customize.querySelectorAll('[data-cmp-category-toggle]').forEach(function (toggle) {
			categories[toggle.getAttribute('data-cmp-category-toggle')] = toggle.checked;
		});

		customize.querySelectorAll('[data-cmp-service-toggle]').forEach(function (toggle) {
			services[toggle.getAttribute('data-cmp-service-toggle')] = toggle.checked;
		});

		return { categories: categories, services: services };
	}

	function handleAction(event) {
		var target = event.target.closest('[data-cmp-action]');

		if (!target) {
			return;
		}

		var action = target.getAttribute('data-cmp-action');

		if (preview && 'customize' !== action) {
			if (!isHidden(customize)) {
				releaseFocusTrap(customize);
				toggleHidden(customize, true);
			}

			toggleHidden(banner, false);
			toggleHidden(backdrop, true);
			banner.focus({ preventScroll: true });

			return;
		}

		if ('accept-all' === action) {
			window.CMP.acceptAll();
			closeEverything();
		} else if ('reject-all' === action) {
			window.CMP.rejectAll();
			closeEverything();
		} else if ('customize' === action) {
			openCustomize(target);
		} else if ('save' === action) {
			var selections = readCustomizeSelections();
			window.CMP.setConsent(selections.categories, selections.services);
			closeCustomize();
		}
	}

	function handleToggleChange(event) {
		var target = event.target;

		if (target.matches('[data-cmp-category-toggle]')) {
			var slug = target.getAttribute('data-cmp-category-toggle');

			serviceTogglesFor(slug).forEach(function (toggle) {
				if (!toggle.disabled) {
					toggle.checked = target.checked;
				}
			});

			target.indeterminate = false;
		} else if (target.matches('[data-cmp-service-toggle]')) {
			var categorySlug = target.getAttribute('data-cmp-service-category');
			var categoryToggle = customize.querySelector('[data-cmp-category-toggle="' + categorySlug + '"]');

			if (categoryToggle) {
				updateCategoryFromServices(categoryToggle);
			}
		}
	}

	function init() {
		banner = document.getElementById('cmp-banner');

		if (!banner) {
			return;
		}

		customize = document.getElementById('cmp-customize');
		backdrop = document.querySelector('[data-cmp-backdrop]');
		floatingButton = document.getElementById('cmp-floating-button');

		document.addEventListener('click', handleAction);
		customize.addEventListener('change', handleToggleChange);

		document.querySelectorAll('[data-cmp-open-settings]').forEach(function (trigger) {
			trigger.addEventListener('click', function () {
				openCustomize(trigger);
			});
		});

		document.addEventListener('cmp:open-settings', function () {
			openCustomize(null);
		});

		document.addEventListener('cmp:change', updateVisibility);
		document.addEventListener('cmp:privacy', function () {
			if (!isHidden(customize)) { populateCustomizeFromState(); }
		});

		if (preview) {
			toggleHidden(banner, false);
			manageBannerFocus(true);

			return;
		}

		updateVisibility();
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(document);
