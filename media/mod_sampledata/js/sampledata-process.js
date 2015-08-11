/**
 * @copyright  Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

!(function ($) {
	"use strict";

	var inProgress = false;

	var sampledataAjax = function(type, steps, step) {
		if (step > steps) {
			return;
		}
		var stepClass = 'sampledata-steps-' + type + '-' + step,
			$stepLi = $('<li class="' + stepClass + '"><img src="' + window.modSampledataIconProgress + '" width="30" height="30" ></li>'),
			$progress = $(".sampledata-progress-" + type + " progress");

		$("div.sampledata-progress-" + type + " ul").append($stepLi);

		var request = $.ajax({
			url: window.modSampledataUrl,
    		type: 'POST',
    		dataType: 'json',
    		data: {
    			type: type,
    			plugin: 'SampledataApplyStep' + step,
    			step: step
    		}
		});
		request.done(function(response){
			$stepLi.children('img').remove();

			if (response.success && response.data && response.data.length > 0) {
				var success, value, resultClass, $msg;

				// Display all messages that we got
				for(var i = 0, l = response.data.length; i < l; i++) {
					value   = response.data[i];
					success = value.success;
					resultClass = success ? 'success' : 'error';
					$stepLi.append($('<div>', {
						html: value.message,
						'class': 'alert alert-' + resultClass,
					}));
				}

				// Update progress
				$progress.val(step/steps);

				// Move on next step
				if (success) {
					step++;
					sampledataAjax(type, steps, step);
				}

    		} else {
    			$progress.hide();
    			$stepLi.addClass('alert alert-error');
    			$stepLi.html(Joomla.JText._('MOD_SAMPLEDATA_INVALID_RESPONSE'));
    			inProgress = false;
    		}
		});
    	request.fail(function(jqXHR, textStatus){
    		alert('Something went wrong! Please reboot the Windows and try again!');
    	});
	};

	window.sampledataApply = function(el) {
		var $el = $(el), type = $el.data('type'), steps = $el.data('steps');

		// Check whether the work in progress or we alredy proccessed with current item
		if (inProgress || $el.data('processed')) {
			return;
		}

		// Make sure that use run this not by random clicking on the page links
		if (!confirm(Joomla.JText._('MOD_SAMPLEDATA_CONFIRM_START'))) {
			return false;
		}

		// Turn on the progress container
		$('.sampledata-progress-' + type).show();
		$el.data('processed', true)

		inProgress = true;
		sampledataAjax(type, steps, 1);
		return false;
	};

})(jQuery);
