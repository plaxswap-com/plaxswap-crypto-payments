(function () {
	'use strict';

	if (!window.wc || !window.wc.wcBlocksRegistry || !window.wc.wcSettings || !window.wp || !window.wp.element) {
		return;
	}

	var settings = window.wc.wcSettings.getSetting('plaxswap_data', {});
	var el = window.wp.element.createElement;
	var decode = window.wp.htmlEntities.decodeEntities;
	var __ = window.wp.i18n.__;
	var title = decode(settings.title || __('Pay with crypto', 'plaxswap-crypto-payments'));
	var description = decode(settings.description || __('Choose your coin and network at Plaxswap checkout.', 'plaxswap-crypto-payments'));

	function Label() {
		return el('span', { className: 'plaxswap-checkout__label' },
			settings.icon ? el('img', { className: 'plaxswap-checkout__logo', src: settings.icon, width: 24, height: 24, alt: '' }) : null,
			el('span', null, title)
		);
	}

	function Content() {
		return el('div', { className: 'plaxswap-checkout' },
			settings.testMode ? el('span', { className: 'plaxswap-checkout__badge' }, __('Test payment · no real funds', 'plaxswap-crypto-payments')) : null,
			el('p', { className: 'plaxswap-checkout__description' }, description),
			el('p', { className: 'plaxswap-checkout__hint' }, __('Review your coin, network, exact amount and fees before sending. Your order updates after payment is verified.', 'plaxswap-crypto-payments'))
		);
	}

	window.wc.wcBlocksRegistry.registerPaymentMethod({
		name: 'plaxswap',
		gatewayId: 'plaxswap',
		paymentMethodId: 'plaxswap',
		label: el(Label),
		ariaLabel: title,
		content: el(Content),
		edit: el(Content),
		canMakePayment: function () { return true; },
		supports: { features: settings.supports || ['products'] }
	});
})();
