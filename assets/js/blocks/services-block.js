(function (blocks, element, blockEditor, i18n) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType('consentimental/services', {
		apiVersion: 3,
		title: __('Consent Services', 'consentimental'),
		description: __('Displays the site’s current consent service and cookie inventory.', 'consentimental'),
		icon: 'privacy',
		category: 'widgets',
		supports: { html: false },

		edit: function () {
			var blockProps = blockEditor.useBlockProps({ className: 'cmp-services-block-placeholder' });

			return el(
				'div',
				blockProps,
				el('p', null, __('Consent Services', 'consentimental')),
				el(
					'p',
					{ style: { color: '#757575' } },
					__('The service/cookie table is rendered live on the published page.', 'consentimental'),
				),
			);
		},

		save: function () {
			return null;
		},
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n);
