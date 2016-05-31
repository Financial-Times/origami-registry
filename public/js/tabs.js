/* global $ */

'use strict';

$(function() {
	$('[data-tabs]').each(function() {
		var $cont = $(this).parent();
		$(this).on('click', 'a', function(e) {
			$(this).attr('aria-selected', 'true').siblings('a').attr('aria-selected', 'false');
			$cont.find("[id='"+$(this).attr('href').replace(/^\#/, '')+"']").removeAttr('aria-hidden').siblings('[id]').attr('aria-hidden', true);
			e.preventDefault();
		});
		$(this).find('a[href]').eq(0).click();
	});

	// If a hash-fragment is in the URL, activate that tab and scroll to it, allowing for demos to load and claim space
	if (location.hash && $('a[href="'+location.hash+'"]').length) {
		var target = $('a[href="'+location.hash+'"]');
		var tolerance = 10;
		var scrollFunc = function() {
			target.get(0).scrollIntoView();
		};
		target.click();
		scrollFunc();
		$(window).on('load', scrollFunc);
		$(window).on('scroll', function userScroll() {
			tolerance--;
			if (!tolerance) $(window).off('load', scrollFunc).off('scroll', userScroll);
		});
	}
});
