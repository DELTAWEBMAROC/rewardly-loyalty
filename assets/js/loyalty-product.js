jQuery(function ($) {
	'use strict';

	const $notice = $('.rewardly-product-points-notice');
	if (!$notice.length) {
		return;
	}

	const pointsPerDh = parseInt($notice.data('points-per-dh'), 10) || 1;
	const redeemPointsPerDh = parseInt($notice.data('redeem-points-per-dh'), 10) || 20;
	const defaultPrice = parseFloat($notice.data('default-price')) || 0;
	const $pointsNode = $notice.find('.rewardly-product-points-notice__points');
	const $amountNode = $notice.find('.rewardly-product-points-notice__amount');

	function formatMoney(amount) {
		try {
			return new Intl.NumberFormat('fr-FR', {
				style: 'currency',
				currency: 'MAD',
				minimumFractionDigits: 0,
				maximumFractionDigits: 2
			}).format(amount);
		} catch (e) {
			return amount + ' MAD';
		}
	}

	function calculatePoints(price) {
		if (!price || price <= 0) {
			return 0;
		}
		return Math.floor(price * pointsPerDh);
	}

	function calculateAmount(points) {
		if (!points || points <= 0) {
			return 0;
		}
		return Math.floor(points / redeemPointsPerDh);
	}

	function updateNotice(price) {
		const points = calculatePoints(price);
		const amount = calculateAmount(points);
		if (points <= 0) {
			return;
		}
		$pointsNode.text(points);
		$amountNode.text('(' + formatMoney(amount) + ')');
	}

	function moveNotice() {
		const $summary = $('.single-product .summary, .woocommerce div.product .summary').first();
		const $targetForm = $summary.find('form.variations_form, form.cart').first();
		if ($summary.length && $targetForm.length) {
			$notice.insertBefore($targetForm);
		}
	}

	updateNotice(defaultPrice);
	moveNotice();

	$('form.variations_form').on('found_variation', function (event, variation) {
		if (variation && variation.display_price) {
			updateNotice(parseFloat(variation.display_price));
		}
	});

	$('form.variations_form').on('reset_data hide_variation', function () {
		updateNotice(defaultPrice);
	});

	setTimeout(moveNotice, 150);
	setTimeout(moveNotice, 500);
});
