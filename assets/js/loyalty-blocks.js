(function () {
	'use strict';
	if (!window.wp?.element || !window.wp?.plugins || !window.wc?.blocksCheckout) return;
	const {createElement: el, useState, useEffect} = wp.element;
	const {__, _n, sprintf} = wp.i18n;
	const {ExperimentalOrderMeta, extensionCartUpdate} = wc.blocksCheckout;
	if (!ExperimentalOrderMeta || !extensionCartUpdate) return;
	const domain = 'nodevora-customer-rewards-for-woocommerce';
	function Loyalty({extensions, context}) {
		const state = extensions?.['nodevora-loyalty'];
		const [value, setValue] = useState('');
		const [busy, setBusy] = useState(false);
		const [error, setError] = useState('');
		useEffect(function () { if (state?.applied) setValue(String(state.applied)); }, [state?.applied]);
		if (!state?.enabled || state.display === 'shortcode') return null;
		const isCart = context === 'woocommerce/cart';
		const card = state.display === 'both' || (isCart ? state.display === 'cart' : state.display === 'checkout');
		const id = 'nodevora-points-' + (isCart ? 'cart' : 'checkout');
		function update(action) {
			setBusy(true); setError('');
			extensionCartUpdate({namespace: 'nodevora-loyalty', data: {action, points: value}})
				.then(function () { if (action === 'remove') setValue(''); })
				.catch(function (response) { setError(response.message || __('Unable to update points. Please try again.', 'nodevora-customer-rewards-for-woocommerce')); })
				.finally(function () { setBusy(false); });
		}
		return el('section', {className: 'rewardly-loyalty-box nodevora-block-card', 'aria-labelledby': id + '-title', 'aria-busy': busy},
			el('h3', {id: id + '-title'}, __('Your loyalty rewards', 'nodevora-customer-rewards-for-woocommerce')),
			el('p', {className: 'nodevora-earning', 'aria-live': 'polite'}, sprintf(_n('This order earns up to %s point.', 'This order earns up to %s points.', state.potential, 'nodevora-customer-rewards-for-woocommerce'), state.potential)),
			state.logged_in && card ? el('div', null,
				state.balance_known ? el('p', {'aria-live': 'polite'}, sprintf(__('Available balance: %s points', 'nodevora-customer-rewards-for-woocommerce'), state.balance)) : null,
				state.message ? el('p', {role: 'status'}, state.message) : null,
				state.ready ? el('form', {className: 'nodevora-redeem-form', onSubmit: function (event) {event.preventDefault(); update('apply');}},
					el('label', {htmlFor: id}, __('Points to use', 'nodevora-customer-rewards-for-woocommerce')),
					el('p', {id: id + '-help'}, sprintf(__('You can use up to %s points on this order.', 'nodevora-customer-rewards-for-woocommerce'), state.maximum)),
					el('input', {id, type: 'number', min: 1, max: state.maximum, step: 1, inputMode: 'numeric', value, required: true, disabled: busy, 'aria-describedby': id + '-help ' + id + '-error', 'aria-invalid': !!error, onChange: function (event) {setValue(event.target.value);}}),
					el('button', {type: 'submit', className: 'rewardly-loyalty-btn', disabled: busy}, busy ? __('Updating…', 'nodevora-customer-rewards-for-woocommerce') : __('Apply points', 'nodevora-customer-rewards-for-woocommerce'))) : null,
				state.selected ? el('div', {className: 'nodevora-applied', 'aria-live': 'polite'},
					state.applied > 0 ? el('p', null, sprintf(__('%1$s points applied · %2$s discount', 'nodevora-customer-rewards-for-woocommerce'), state.applied, state.discount)) : null,
					el('button', {type: 'button', className: 'rewardly-loyalty-btn', disabled: busy, onClick: function () {update('remove');}}, __('Remove points', 'nodevora-customer-rewards-for-woocommerce'))) : null,
				el('p', {id: id + '-error', role: 'alert'}, error)) : null,
			!state.logged_in && card ? el('a', {href: state.login_url, className: 'rewardly-loyalty-btn'}, __('Log in to use your points', 'nodevora-customer-rewards-for-woocommerce')) : null);
	}
	wp.plugins.registerPlugin('nodevora-loyalty', {scope: 'woocommerce-checkout', render: function () {return el(ExperimentalOrderMeta, null, el(Loyalty));}});
})();
