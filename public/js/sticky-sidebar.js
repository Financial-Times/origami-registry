/* global $ */

'use strict';

var oViewport = require('o-viewport');

$(function() {
	var stickySidebar = $('.js-sticky-sidebar'),
		stickyTop = 0,
		stickyWidth = 0;

	function updateStickyElement() {
		var scrollTop = oViewport.getScrollPosition().top;

		if (scrollTop > stickyTop) {
			$(stickySidebar).css({
				"position": "fixed",
				"right": "20px",
				"top": "20px",
				"z-index": "10",
				"width": stickyWidth
			});
		} else {
			$(stickySidebar).removeAttr('style').css({'top': '0', 'position': 'relative'});
		}
	}

	function initStickyElement() {
		stickyTop = $(stickySidebar).offset().top;
		stickyWidth = $(stickySidebar).outerWidth();

		if (window.matchMedia("(min-width: 900px)").matches) {
			oViewport.listenTo('scroll');
			document.body.addEventListener('oViewport.scroll', updateStickyElement, false);
		} else {
			document.body.removeEventListener('oViewport.scroll', updateStickyElement, false);
		}
	}

	if ($(stickySidebar).length) {
		oViewport.listenTo(['scroll', 'resize']);

		initStickyElement();
		document.body.addEventListener('oViewport.resize', initStickyElement, false);
	}
});
