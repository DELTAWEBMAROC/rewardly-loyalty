jQuery(function ($) {
	'use strict';
	const notice = document.querySelector('.rewardly-product-points-notice');
	if (!notice) return;
	const data = window.rewardlyProductData || {};
	const initialRate = Number(notice.dataset.pointsRate) || 0;
	const initialPrice = Number(notice.dataset.defaultPrice) || 0;
	const ratio = Math.max(1, Number(notice.dataset.redeemPointsPerDh) || 20);
	const decimals = Number.isInteger(Number(data.decimals)) ? Number(data.decimals) : 2;
	const scale = Math.pow(10, decimals);
	let rate = initialRate;
	let price = initialPrice;
	const form = notice.closest('.summary')?.querySelector('form.cart');
	function update() {
		const quantity = Math.max(1, Number(form?.querySelector('input.qty')?.value) || 1);
		const points = Math.floor(price * rate * quantity);
		notice.hidden = points <= 0;
		notice.querySelector('.rewardly-product-points-notice__points').textContent = points;
		const amount = Math.floor(points * scale / ratio) / scale;
		let formatted;
		try { formatted = new Intl.NumberFormat(data.locale || 'en', {style: 'currency', currency: data.currency || 'USD', minimumFractionDigits: decimals, maximumFractionDigits: decimals}).format(amount); }
		catch (error) { formatted = amount.toFixed(decimals) + ' ' + (data.currency || 'USD'); }
		notice.querySelector('.rewardly-product-points-notice__amount').textContent = '(' + formatted + ')';
	}
	$('form.variations_form').on('found_variation.nodevora', function (event, variation) {
		rate = Number(variation.nodevora_points_rate) || 0;
		price = Number(variation.nodevora_earning_price) || 0;
		update();
	}).on('reset_data.nodevora hide_variation.nodevora', function () { rate = initialRate; price = initialPrice; update(); });
	if (form) form.addEventListener('input', update);
	update();
});
