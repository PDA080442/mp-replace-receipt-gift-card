(function ($) {
	'use strict';

	function updateDetectionModeFields() {
		var mode = $('#mp-rrgc-detection-mode').val() || '';
		$('.mp-rrgc-detection').hide();
		$('.mp-rrgc-detection-' + mode).show();
	}

	$(function () {
		updateDetectionModeFields();
		$('#mp-rrgc-detection-mode').on('change', updateDetectionModeFields);
	});
})(jQuery);

