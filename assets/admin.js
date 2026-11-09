(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		if (!event.target || typeof event.target.closest !== 'function') { return; }
		var button = event.target.closest('[data-plaxswap-copy]');
		if (!button) { return; }
		var target = document.getElementById(button.getAttribute('data-plaxswap-copy'));
		if (!target) { return; }
		event.preventDefault();
		var value = 'value' in target ? target.value : target.textContent;
		var original = button.textContent;

		function selectForCopy() {
			if (typeof target.select === 'function') {
				target.focus();
				target.select();
				button.textContent = 'Selected — copy to clipboard';
			} else {
				button.textContent = 'Copy the callback URL shown';
			}
		}

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(value).then(function () {
				button.textContent = 'Copied';
				window.setTimeout(function () { button.textContent = original; }, 2200);
			}, selectForCopy);
		} else {
			selectForCopy();
		}
	});
})();
