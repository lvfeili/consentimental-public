(function (document) {
	'use strict';
	function update() {
		if (!window.CMP || !window.CMP.getPrivacyChoice) { return; }
		var choice = window.CMP.getPrivacyChoice();
		document.querySelectorAll('[data-cmp-california]').forEach(function (panel) {
			var status = panel.querySelector('[data-cmp-sale-status]');
			var text = choice.optedOut ? panel.getAttribute(choice.gpc ? 'data-gpc' : 'data-confirmed') : '';
			if (!choice.ready && !choice.optedOut) { text = panel.getAttribute('data-waiting'); }
			if (choice.syncFailed) { text += ' ' + panel.getAttribute('data-error'); }
			status.textContent = text;
			panel.querySelector('[data-cmp-sale-opt-out]').disabled = choice.optedOut || !!window.cmpConfig.preview;
			panel.querySelector('[data-cmp-sale-retry]').hidden = !choice.syncFailed;
		});
	}
	document.addEventListener('click', function (event) {
		var target = event.target.closest('[data-cmp-sale-opt-out], [data-cmp-sale-retry]');
		if (target && window.CMP) {
			event.preventDefault();
			if (target.hasAttribute('data-cmp-sale-retry')) { window.location.reload(); }
			else { window.CMP.optOutSaleSharing(); update(); }
		}
	});
	document.addEventListener('cmp:privacy', update);
	document.addEventListener('cmp:change', update);
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', update); }
	else { update(); }
})(document);
