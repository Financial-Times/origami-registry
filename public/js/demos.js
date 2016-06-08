/* global $,console */

'use strict';

$(function() {
	$('.demo-embed-link').on('click', function(e) {
		e.preventDefault();
	});

	$('.demo-selector input').on('change', function() {
		var demo = $('#demo-'+this.value);
		demo[(this.checked?'add':'remove')+'Class']('visible');
		if (demo.hasClass('visible')) {
			var ifr = demo.find('.demo-frame').get(0);
			ifr.src = ifr.src;
		}
	});
	$('#transparency-toggle').on('change', function() {
		$('.demo-frame')[(this.checked?'add':'remove')+'Class']('transparency-checkerboard');
	});
	$('#code-toggle').on('change', function() {
		$('.demo-container').toggleClass('with-source');
	});
	$('.demo-source').each(function(i, source) {
		var $source = $(source);
		$.getJSON($source.attr('data-src'), function(resp) {
			if (resp.code) {
				var codeEl = $('<code class="html"></code>').text(resp.code).addClass('js-auto-select-target');
				$source.append(codeEl).prepend('<button class="auto-select-action js-auto-select-action">Select full code snippet</button>');
			} else if (resp.errors) {
				var html = "<div class='demo-source demo-source--errors'><p>There are problems with this demo:</p><ul>";
				resp.errors.forEach(function(error) {
					html += '<li><strong>Line '+error.line+':</strong> '+error.message+'</li>';
				});
				html += '</ul></div>';
				$source.replaceWith(html);
			}
		});
	});
});

$(function() {
	$('iframe').on('load', function() {
		$(this).closest('.demo-wrapper').find('.activity').removeClass('activity');
	});
});

$(function() {
	$('.link-refresh').click(function(e) {
		var btn = $(this);
		btn.addClass('activity');
		e.preventDefault();
		$.post(btn.attr('href'), function(data) {
			btn.removeClass('activity');
			if (typeof data === 'string') {
				location.href = data;
			} else {
				window.alert('Failed to refresh - see console for details');
				console.error(data);
			}
		});
	});
	$('.version-select').on('change', function() {
		if ($(this.selectedOptions[0]).hasClass('option-refresh')) {
			$(this).attr('disabled', 'disabled').parent().addClass('activity');
			$.post(this.value, function(redirurl) {
				location.href = redirurl;
			});
		} else {
			location.href = this.value;
		}
	});

	// Auto select demo source code when clicked
	function selectText(element) {
		var doc = document;
		var range;
		var selection;

		if (doc.body.createTextRange) {
			range = document.body.createTextRange();
			range.moveToElementText(element);
			range.select();
		} else if (window.getSelection) {
			selection = window.getSelection();
			range = document.createRange();
			range.selectNodeContents(element);
			selection.removeAllRanges();
			selection.addRange(range);
		}
	}

	$('body').on('click', '.js-auto-select-action', function(e) {
		e.preventDefault();

		var selectFrom = $(this).parent().find('.js-auto-select-target').get(0);

		selectText(selectFrom);
	});

});
