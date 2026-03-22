jQuery(function ($) {
	'use strict';

	const data = window.rewardlyAdminData || {};
	const productMap = {};
	const categoryMap = {};
	(data.productOptions || []).forEach(function (item) { productMap[item.label] = item.id; productMap[String(item.id)] = item.id; });
	(data.categoryOptions || []).forEach(function (item) { categoryMap[item.label] = item.id; categoryMap[String(item.id)] = item.id; });

	function bindSelector($scope, inputSelector, hiddenSelector, map) {
		$scope.find(inputSelector).off('.rewardly').on('input.rewardly change.rewardly blur.rewardly', function () {
			const val = $(this).val().trim();
			const id = map[val] || parseInt(val.replace(/[^0-9]/g, ''), 10) || '';
			$(this).closest('.rewardly-rule-row, .rewardly-selector-row').find(hiddenSelector).val(id);
		});
	}

	function bindRemove() {
		$(document).off('click.rewardlyRemove').on('click.rewardlyRemove', '.rewardly-remove-row', function () {
			$(this).closest('.rewardly-rule-row, .rewardly-selector-row').remove();
		});
	}

	function addCategoryRuleRow() {
		const html = '<div class="rewardly-rule-row rewardly-category-rule-row">' +
			'<input type="text" class="regular-text rewardly-category-search" list="rewardly-category-options" placeholder="' + (data.i18n && data.i18n.selectCategory ? data.i18n.selectCategory : 'Start typing a category name…') + '">' +
			'<input type="hidden" name="rewardly_loyalty_settings[pro_category_rule_term_id][]" class="rewardly-category-id" value="">' +
			'<input type="number" min="1" name="rewardly_loyalty_settings[pro_category_rule_rate][]" value="" placeholder="5">' +
			'<button type="button" class="button button-secondary rewardly-remove-row">×</button>' +
		'</div>';
		$('#rewardly-category-rules-wrap').append(html);
		bindSelector($('#rewardly-category-rules-wrap'), '.rewardly-category-search', '.rewardly-category-id', categoryMap);
	}

	function addExcludedProductRow() {
		const html = '<div class="rewardly-selector-row rewardly-product-selector-row">' +
			'<input type="text" class="regular-text rewardly-product-search" list="rewardly-product-options" placeholder="' + (data.i18n && data.i18n.selectProduct ? data.i18n.selectProduct : 'Start typing a product name…') + '">' +
			'<input type="hidden" name="rewardly_loyalty_settings[pro_excluded_product_ids][]" class="rewardly-product-id" value="">' +
			'<button type="button" class="button button-secondary rewardly-remove-row">×</button>' +
		'</div>';
		$('#rewardly-excluded-products-wrap').append(html);
		bindSelector($('#rewardly-excluded-products-wrap'), '.rewardly-product-search', '.rewardly-product-id', productMap);
	}

	function addExcludedCategoryRow() {
		const html = '<div class="rewardly-selector-row rewardly-category-selector-row">' +
			'<input type="text" class="regular-text rewardly-category-search" list="rewardly-category-options" placeholder="' + (data.i18n && data.i18n.selectCategory ? data.i18n.selectCategory : 'Start typing a category name…') + '">' +
			'<input type="hidden" name="rewardly_loyalty_settings[pro_excluded_category_ids][]" class="rewardly-category-id" value="">' +
			'<button type="button" class="button button-secondary rewardly-remove-row">×</button>' +
		'</div>';
		$('#rewardly-excluded-categories-wrap').append(html);
		bindSelector($('#rewardly-excluded-categories-wrap'), '.rewardly-category-search', '.rewardly-category-id', categoryMap);
	}

	$('#rewardly-add-category-rule').on('click', addCategoryRuleRow);
	$('#rewardly-add-excluded-product').on('click', addExcludedProductRow);
	$('#rewardly-add-excluded-category').on('click', addExcludedCategoryRow);

	bindSelector($(document), '.rewardly-product-search', '.rewardly-product-id', productMap);
	bindSelector($(document), '.rewardly-category-search', '.rewardly-category-id', categoryMap);
	bindRemove();
});

	jQuery(function ($) {
		function rewardlyToggleShortcodeHelp() {
			var val = $('#rewardly-notice-display-mode').val();
			$('#rewardly-shortcode-only-help').toggle(val === 'shortcode');
		}
		$(document).on('change', '#rewardly-notice-display-mode', rewardlyToggleShortcodeHelp);
		rewardlyToggleShortcodeHelp();
	});
