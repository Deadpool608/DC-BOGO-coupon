jQuery(function ($) {
	console.log('DC BOGO Admin JS Loaded');
	$(document.body).trigger('wc-enhanced-select-init');

	var $applyModeRadios = $('input[name="_dc_bogo_apply_mode"]');
	var $couponCodeField = $('.dc-bogo-coupon-code-field');

	function toggleCouponCodeField() {
		if ($('#apply_mode_coupon').is(':checked')) {
			$couponCodeField.show();
		} else {
			$couponCodeField.hide();
		}
	}

	toggleCouponCodeField();
	$applyModeRadios.on('change', toggleCouponCodeField);

	var $discountRadios = $('input[name="_dc_bogo_discount_type"]');
	var $discountValueField = $('.dc-bogo-discount-value-field');
	// Update placeholder/label based on selected discount type
	function updateDiscountLabel() {
		var type = $discountRadios.filter(':checked').val();
		if (type === 'percentage') {
			$discountValueField.attr('placeholder', 'e.g., 10 for 10%');
			$discountValueField.prev('label').text('Discount (%):');
		} else if (type === 'fixed') {
			$discountValueField.attr('placeholder', 'e.g., 5 for $5 off');
			$discountValueField.prev('label').text('Discount (fixed amount):');
		} else {
			$discountValueField.attr('placeholder', '');
			$discountValueField.prev('label').text('Discount:');
		}
	}

	function toggleDiscountField() {
		if ($('#discount_type_free').is(':checked')) {
			$discountValueField.hide();
		} else {
			$discountValueField.show();
		}
		updateDiscountLabel();
	}

	toggleDiscountField();

	if ($('.dc-color-field').length) {
		try {
			// Run on load and when discount type changes
			$discountRadios.on('change', function () {
				toggleDiscountField();
				$discountValueField.val('').trigger('change');
			});

			$('.dc-color-field').wpColorPicker({
				change: function (event, ui) {
					var color = ui.color.toString();
					$('#dc-bogo-progress-full-fill').css('background-color', color);
					$('.dc-bogo-progress-bar-fill').css('background', color);
					$('#dc-bogo-progress-full-preview').css('--dc-accent', color);
				},
				clear: function () {
					var fallback = '#17a673';
					$('#dc-bogo-progress-full-fill').css(
						'background-color',
						fallback
					);
					$('.dc-bogo-progress-bar-fill').css(
						'background',
						fallback
					);
					$('.dc-bogo-milestone').css('background', fallback);
				},
			});
		} catch (err) {
			console.warn('dc-bogo: color picker init failed', err);
		}
	}

	$('#dc_bogo_progress_position').on('change', function () {
		var val = $(this).val();
		$('#dc-bogo-progress-full-preview .position-label').remove();
		var label = $(
			'<div class="position-label" style="margin-top:8px; font-size:12px; color:#6b7280;">'
		).text(val);
		$('#dc-bogo-progress-full-preview').append(label);
	});

	var $tabs = $('.dc-bogo-nav-tab-wrapper .nav-tab');
	var $tabContents = $('.dc-bogo-tab-content');

	$tabs.on('click', function (e) {
		e.preventDefault();
		var target = $(this).attr('href');

		$tabs.removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		$tabContents.removeClass('active');
		$(target).addClass('active');

		// Update hidden input for persistence
		// Update content
		$tabContents.removeClass('active').hide();
		$(target).addClass('active').show();

		$('#dc_bogo_active_tab').val(target.replace('#', ''));
	});

	var $progressBarToggle = $('#dc_bogo_show_progress_bar');
	var $progressBarOptions = $('.dc-bogo-progress-bar-options');

	$progressBarToggle
		.on('change', function () {
			if ($(this).is(':checked')) {
				$progressBarOptions.show();
				$('#dc-bogo-progress-full-preview').show();
			} else {
				$progressBarOptions.hide();
				$('#dc-bogo-progress-full-preview').hide();
			}
		})
		.trigger('change');

	var $customTagToggle = $('#dc_bogo_use_custom_tag');
	var $customTagField = $('.dc-bogo-custom-tag-field');

	$customTagToggle
		.on('change', function () {
			if ($(this).is(':checked')) {
				$customTagField.show();
			} else {
				$customTagField.hide();
			}
		})
		.trigger('change');

	$('form.dc-bogo-settings-form, form').on('submit', function () {
		var $active = $('.dc-bogo-nav-tab-wrapper .nav-tab.nav-tab-active');
		var href = $active.attr('href') || '#general';
		if ($('input[name="dc_bogo_active_tab"]').length === 0) {
			$(this).append('<input type="hidden" name="dc_bogo_active_tab" value="' + href.replace('#', '') + '" />');
		} else {
			$('input[name="dc_bogo_active_tab"]').val(href.replace('#', ''));
		}
	});

	// Helper to handle exclusion (moved to top scope)
	function handleExclusion($buyField, $getField) {
		// 1. Reset: Enable all options in Get field first
		$getField.find('option').prop('disabled', false);

		// 2. Get Buy values (force string array)
		var rawBuyVals = $buyField.val();
		var buyVals = [];
		if (Array.isArray(rawBuyVals)) {
			buyVals = rawBuyVals.map(String);
		} else if (rawBuyVals) {
			buyVals = [String(rawBuyVals)];
		}

		// 3. Disable selected Buy values in Get field
		if (buyVals.length > 0) {
			buyVals.forEach(function (v) {
				var $opt = $getField.find('option[value="' + v + '"]');
				if ($opt.length) {
					$opt.prop('disabled', true);
				}
			});
		}

		// 4. Remove conflicting selections in Get field
		var rawGetVals = $getField.val();
		var getVals = [];
		if (Array.isArray(rawGetVals)) {
			getVals = rawGetVals.map(String);
		} else if (rawGetVals) {
			getVals = [String(rawGetVals)];
		}

		var newGetVals = getVals.filter(function (v) {
			// Check if v is present in buyVals
			return buyVals.indexOf(v) === -1;
		});

		// If selection changed, update and trigger change
		if (getVals.length !== newGetVals.length) {
			$getField.val(newGetVals).trigger('change');
		}

		// Refresh Select2 if initialized - ONE TIME at the end
		if ($getField.hasClass('select2-hidden-accessible')) {
			$getField.trigger('change.select2');
		}
	}

	var $bogoTypeRadios = $('input[name="_dc_bogo_type"]');
	var $getConditionFields = $('.dc-bogo-get-conditions');

	function toggleGetConditionFields() {
		$getConditionFields.show();
	}

	toggleGetConditionFields();
	$bogoTypeRadios.on('change', toggleGetConditionFields);

	var $buyMatch = $('input[name="_dc_bogo_buy_match"]');
	var $buyProducts = $('select[name="_dc_bogo_buy_products[]"]');
	var $buyCategories = $('select[name="_dc_bogo_buy_categories[]"]');
	var $getProducts = $('select[name="_dc_bogo_get_products[]"]');
	var $getCategories = $('select[name="_dc_bogo_get_categories[]"]');

	function syncBogoFields() {
		var type = $bogoTypeRadios.filter(':checked').val();
		var match = $buyMatch.filter(':checked').val();

		if (type === 'buy_x_get_x_same') {
			// STRICT MODE: "Same Item" means exactly the same.
			// 1. Sync the "Get" match type to match "Buy"
			$('input[name="_dc_bogo_get_match"][value="' + match + '"]').prop('checked', true).trigger('change');

			// 2. Disable the "Get" match type radios so user can't change it
			$('input[name="_dc_bogo_get_match"]').prop('disabled', true);

			// 3. Sync the values and disable the fields
			if (match === 'products') {
				var val = $buyProducts.val();

				// Sync options if needed
				if (val) {
					$buyProducts.find('option:selected').each(function () {
						var id = $(this).val();
						var text = $(this).text();
						if ($getProducts.find('option[value="' + id + '"]').length === 0) {
							var newOption = new Option(text, id, true, true);
							$getProducts.append(newOption);
						}
					});
					$getProducts.val(val).trigger('change');
				} else {
					$getProducts.val(null).trigger('change');
				}

				// Disable the Get Products dropdown
				$getProducts.prop('disabled', true);
				$getCategories.prop('disabled', true); // Disable the other one too

			} else if (match === 'categories') {
				var val = $buyCategories.val();
				$getCategories.val(val).trigger('change');

				// Disable the Get Categories dropdown
				$getCategories.prop('disabled', true);
				$getProducts.prop('disabled', true);
			}

		} else if (type === 'buy_x_get_y_diff') {
			// DIFFERENT ITEM MODE: Enable everything
			$('input[name="_dc_bogo_get_match"]').prop('disabled', false);
			$getProducts.prop('disabled', false);
			$getCategories.prop('disabled', false);

			// Apply exclusion logic
			handleExclusion($buyCategories, $getCategories);
			handleExclusion($buyProducts, $getProducts);
		}
	}

	// Explicitly clear Get fields when switching to "Different Items"
	$bogoTypeRadios.on('change', function () {
		if ($(this).val() === 'buy_x_get_y_diff') {
			$getProducts.val(null).trigger('change');
			$getCategories.val(null).trigger('change');
		}
	});

	// Auto-clear Get fields AND Sync when Buy selection changes (Consolidated Handler)
	$buyProducts.on('change', function () {
		if ($bogoTypeRadios.filter(':checked').val() === 'buy_x_get_y_diff') {
			$getProducts.val(null).trigger('change');
		}
		syncBogoFields();
	});
	$buyCategories.on('change', function () {
		if ($bogoTypeRadios.filter(':checked').val() === 'buy_x_get_y_diff') {
			$getCategories.val(null).trigger('change');
		}
		syncBogoFields();
	});

	// Intercept selection to block invalid items (Handles AJAX loaded items)
	function blockInvalidSelection(e, $buyField) {
		if ($bogoTypeRadios.filter(':checked').val() !== 'buy_x_get_y_diff') {
			return;
		}

		var selectedId = e.params.args.data.id;
		var buyVals = $buyField.val() || [];
		if (!Array.isArray(buyVals)) {
			buyVals = [buyVals];
		}
		buyVals = buyVals.map(String);

		if (buyVals.indexOf(String(selectedId)) !== -1) {
			e.preventDefault();
			alert(dcBogoAdmin.i18nSameItemError);
		}
	}

	$getProducts.on('select2:selecting', function (e) {
		blockInvalidSelection(e, $buyProducts);
	});
	$getCategories.on('select2:selecting', function (e) {
		blockInvalidSelection(e, $buyCategories);
	});

	$bogoTypeRadios.on('change', syncBogoFields);
	$buyMatch.on('change', syncBogoFields);
	// Removed individual bindings for buyProducts/Categories as they are handled above



	syncBogoFields();

	$(document).on('change', '.dc-bogo-status-toggle input', function () {
		var $checkbox = $(this);
		var $label = $checkbox.closest('.dc-bogo-status-toggle');
		var postId = $label.data('rule-id');
		var isActive = $checkbox.is(':checked') ? 'true' : 'false';

		if (typeof dcBogoAdmin === 'undefined' || !dcBogoAdmin.ajaxUrl) {
			return;
		}

		$.post(dcBogoAdmin.ajaxUrl, {
			action: 'dc_bogo_toggle_rule_status',
			nonce: dcBogoAdmin.nonce,
			post_id: postId,
			is_active: isActive,
		}, function (res) {
			if (res && res.success) {
				var newStatus = res.data && res.data.new_status ? res.data.new_status : (isActive === 'true' ? 'yes' : 'no');
				var $row = $label.closest('tr');
				if (!$row || $row.length === 0) {
					$row = $('#post-' + postId);
				}
				if (!$row || $row.length === 0) {
					$row = $('tr[data-post-id="' + postId + '"]');
				}
				var $statusTd = $row.find('td.column-dc_bogo_status');
				if ($statusTd.length) {
					if (newStatus === 'yes') {
						$statusTd.html('<span style="color:green;">● ' + dcBogoAdmin.i18nActive + '</span>');
					} else {
						$statusTd.html('<span style="color:gray;">● ' + dcBogoAdmin.i18nInactive + '</span>');
					}
				}
			} else {
				$checkbox.prop('checked', !$checkbox.is(':checked'));
				alert((res && res.data && res.data.message) ? res.data.message : 'Error');
			}
		}, 'json');
	});

	function setupConditionalFields(buyOrGetPrefix) {
		var $radios = $('input[name="_dc_bogo_' + buyOrGetPrefix + '_match"]');
		var $productsField = $('.dc-bogo-' + buyOrGetPrefix + '-products-field');
		var $categoriesField = $('.dc-bogo-' + buyOrGetPrefix + '-categories-field');

		function toggleFields() {
			var selected = $radios.filter(':checked').val();
			$productsField.hide();
			$categoriesField.hide();

			if (selected === 'products') {
				$productsField.show();
			} else if (selected === 'categories') {
				$categoriesField.show();
			}
		}

		$radios.on('change', toggleFields);
		toggleFields();
	}

	setupConditionalFields('buy');
	setupConditionalFields('get');

	$(document).on('change', '.dc-bogo-progress-toggle input', function () {
		var $checkbox = $(this);
		var $label = $checkbox.closest('.dc-bogo-progress-toggle');
		var postId = $label.data('rule-id');
		var isOn = $checkbox.is(':checked') ? '1' : '0';

		if (typeof dcBogoAdmin === 'undefined' || !dcBogoAdmin.ajaxUrl) {
			return;
		}

		$.post(dcBogoAdmin.ajaxUrl, {
			action: 'dc_bogo_toggle_progress',
			nonce: dcBogoAdmin.nonce,
			post_id: postId,
			on: isOn,
		}, function (res) {
			if (!res || !res.success) {
				$checkbox.prop('checked', !$checkbox.is(':checked'));
				alert((res && res.data && res.data.message) ? res.data.message : 'Error');
			}
		}, 'json');
	});

});
