/* global $,console */

'use strict';

$(function() {
	$('.demo__source').each(function(i, source) {
		var $source = $(source);
		$.getJSON($source.attr('data-src'), function(resp) {
			if (resp.code) {
				var codeEl = $('<code class="html"></code>').text(resp.code).addClass('js-auto-select-target');
				$source.append(codeEl).prepend('<button class="demo__auto-select-action js-auto-select-action">Select full code snippet</button>');
			} else if (resp.errors) {
				var html = "<div class='demo__source demo__source--errors'><p>There are problems with this demo:</p><ul>";
				resp.errors.forEach(function(error) {
					html += '<li><strong>Line '+error.line+':</strong> '+error.message+'</li>';
				});
				html += '</ul></div>';
				$source.replaceWith(html);
			}
		});
	});

	$('iframe').on('load', function() {
		$(this).closest('.demo').find('.js-activity').removeClass('js-activity');
	});

	$('.link-refresh').click(function(e) {
		var btn = $(this);
		btn.addClass('js-activity');
		e.preventDefault();
		$.post(btn.attr('href'), function(data) {
			btn.removeClass('js-activity');
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
			$(this).attr('disabled', 'disabled').parent().addClass('js-activity');
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
