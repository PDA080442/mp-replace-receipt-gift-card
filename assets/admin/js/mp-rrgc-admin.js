(function ($) {
	'use strict';

	function updateDetectionModeFields() {
		var mode = $('#mp-rrgc-detection-mode').val() || '';
		$('.mp-rrgc-detection').hide();
		$('.mp-rrgc-detection-' + mode).show();
	}

	function renderResult(payload) {
		var $out = $('#mp-rrgc-diagnostics-output');
		if (!$out.length) {
			return;
		}
		$out.text(JSON.stringify(payload, null, 2));
	}

	function postAjax(action, data) {
		var payload = $.extend({}, data || {}, {
			action: action,
			nonce: (window.mpRrgcAdmin && window.mpRrgcAdmin.nonce) ? window.mpRrgcAdmin.nonce : ''
		});

		return $.ajax({
			url: (window.mpRrgcAdmin && window.mpRrgcAdmin.ajaxUrl) ? window.mpRrgcAdmin.ajaxUrl : window.ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: payload
		});
	}

	$(function () {
		updateDetectionModeFields();
		$('#mp-rrgc-detection-mode').on('change', updateDetectionModeFields);

		$('#mp-rrgc-inspect-product-btn').on('click', function () {
			var productId = parseInt($('#mp-rrgc-inspect-product-id').val(), 10) || 0;
			renderResult({loading: true, action: 'inspect_product', product_id: productId});
			postAjax('mp_rrgc_inspect_product', {product_id: productId})
				.done(function (res) { renderResult(res); })
				.fail(function (xhr) { renderResult({ok: false, error: xhr.responseText || 'request_failed'}); });
		});

		$('#mp-rrgc-inspect-order-yk-btn').on('click', function () {
			var orderId = parseInt($('#mp-rrgc-inspect-order-yk-id').val(), 10) || 0;
			renderResult({loading: true, action: 'inspect_order_yk', order_id: orderId});
			postAjax('mp_rrgc_inspect_order_yk', {order_id: orderId})
				.done(function (res) { renderResult(res); })
				.fail(function (xhr) { renderResult({ok: false, error: xhr.responseText || 'request_failed'}); });
		});

		$('#mp-rrgc-inspect-order-rb-btn').on('click', function () {
			var orderId = parseInt($('#mp-rrgc-inspect-order-rb-id').val(), 10) || 0;
			renderResult({loading: true, action: 'inspect_order_rb', order_id: orderId});
			postAjax('mp_rrgc_inspect_order_rb', {order_id: orderId})
				.done(function (res) { renderResult(res); })
				.fail(function (xhr) { renderResult({ok: false, error: xhr.responseText || 'request_failed'}); });
		});
	});
})(jQuery);

