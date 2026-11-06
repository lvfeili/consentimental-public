(function (window, document, jQuery) {
	'use strict';

	function setVar(name, value) {
		if (value) {
			document.documentElement.style.setProperty(name, value);
		} else {
			document.documentElement.style.removeProperty(name);
		}
	}

	function wireColorFields() {
		if (!jQuery || !jQuery.fn || !jQuery.fn.wpColorPicker) {
			return;
		}

		jQuery('.cmp-color-field').each(function () {
			var $field = jQuery(this);
			var cssVar = $field.data('cmp-var');

			$field.wpColorPicker({
				change: function (event, ui) {
					setVar(cssVar, ui.color.toString());
				},
				clear: function () {
					setVar(cssVar, '');
				},
			});
		});
	}

	function wireStyleFields() {
		document.querySelectorAll('.cmp-style-field').forEach(function (field) {
			var cssVar = field.getAttribute('data-cmp-var');
			var unit = field.getAttribute('data-cmp-unit') || '';

			function apply() {
				var value = field.value.trim();
				setVar(cssVar, '' === value ? '' : value + unit);
			}

			field.addEventListener('input', apply);
			apply();
		});
	}

	function wireShapeField() {
		var field = document.querySelector('[data-cmp-shape-field]');

		if (!field) {
			return;
		}

		var radiusByShape = { rounded: '', pill: '999px', square: '0' };

		function apply() {
			setVar('--cmp-btn-radius', radiusByShape[field.value] || '');
		}

		field.addEventListener('change', apply);
		apply();
	}

	function wirePositionField() {
		var field = document.querySelector('[data-cmp-position-field]');
		var viewport = document.getElementById('cmp-preview-viewport');

		if (!field || !viewport) {
			return;
		}

		function apply() {
			viewport.setAttribute('data-cmp-position', field.value);
		}

		field.addEventListener('change', apply);
		apply();
	}

	function wireTextPreview() {
		document.querySelectorAll('[data-cmp-preview-target]').forEach(function (field) {
			var target = document.getElementById('cmp-preview-' + field.getAttribute('data-cmp-preview-target'));

			if (!target) {
				return;
			}

			function apply() {
				target.textContent = '' !== field.value ? field.value : target.getAttribute('data-cmp-default') || target.textContent;
			}

			if (!target.hasAttribute('data-cmp-default')) {
				target.setAttribute('data-cmp-default', target.textContent);
			}

			field.addEventListener('input', apply);
		});
	}

	function wirePolicyPreview() {
		var field = document.querySelector('[data-cmp-preview-policy]');
		var links = document.getElementById('cmp-preview-policy-links');

		if (!field || !links) {
			return;
		}

		function apply() {
			var existing = links.querySelector('[data-cmp-privacy-link]');
			var show = '0' !== field.value || '1' === links.getAttribute('data-cmp-fallback-policy');

			if (show && !existing) {
				var link = document.createElement('a');
				link.setAttribute('data-cmp-privacy-link', '');
				link.setAttribute('href', '#');
				link.tabIndex = -1;
				link.textContent = field.getAttribute('data-cmp-preview-policy');
				links.insertBefore(link, links.firstChild);
			} else if (!show && existing) {
				existing.remove();
			}
		}

		field.addEventListener('change', apply);
	}

	function wireCustomCss() {
		var field = document.querySelector('[data-cmp-custom-css]');
		var target = document.getElementById('cmp-preview-custom-css');

		if (!field || !target) {
			return;
		}

		field.addEventListener('input', function () {
			target.textContent = field.value;
		});
	}

	function init() {
		wireColorFields();
		wireStyleFields();
		wireShapeField();
		wirePositionField();
		wireTextPreview();
		wirePolicyPreview();
		wireCustomCss();
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document, window.jQuery);
