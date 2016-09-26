// Require module
require('o-date');
require('o-tabs');
require('o-overlay');
require('o-header-services');
var oViewport = require('o-viewport');

require('./filterbar');
require('./click-helper');
require('./demos');

import { gistIt } from './gist-it';
import highlight from './highlight';

// Wait until the page has loaded
if (document.readyState === 'interactive' || document.readyState === 'complete') {
	document.dispatchEvent(new CustomEvent('o.DOMContentLoaded'));
}
document.addEventListener('DOMContentLoaded', function() {
	// Dispatch a custom event that will tell all required modules to initialise
	document.dispatchEvent(new CustomEvent('o.DOMContentLoaded'));

	gistIt();
	highlight();

	if ($('.js-expanded__sidebar').length) {
		var sidebarHeight = $('.js-expanded__sidebar').height(),
			navHeight = 0;

		$('.js-expanded__sidebar').removeClass('sidebar--has-scroll');

		$('.js-expanded__sidebar').children().each(function() {
			navHeight += parseInt($(this).height());
		});

		if (sidebarHeight < navHeight) {
			$('.js-expanded__main').css('minHeight',navHeight);
		}
	}

	function updateStickyElement() {
		var scrollTop = oViewport.getScrollPosition().top;

		if (scrollTop > stickyTop) {
			$('.js-sticky-sidebar').css({
				"position": "fixed",
				"right": "20px",
				"top": "20px",
				"z-index": "10",
				"width": stickyWidth
			});
		} else {
			$('.js-sticky-sidebar').removeAttr('style').css({'top': '0', 'position': 'relative'});
		}
	}

	if ($('.js-sticky-sidebar').length) {
		var stickySidebar = $('.js-sticky-sidebar');
		var stickyTop = $('.js-sticky-sidebar').offset().top;
		var stickyWidth = $('.js-sticky-sidebar').outerWidth();

		oViewport.listenTo('scroll');

		if (window.matchMedia("(min-width: 900px)")) {
			document.body.addEventListener('oViewport.scroll', updateStickyElement, false);
		} else {
			document.body.removeEventListener('oViewport.scroll', updateStickyElement, false);
		}
	}
});
