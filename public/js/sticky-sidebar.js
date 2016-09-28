/* global $ */

'use strict';

module.exports = function() {
	var oViewport = require('o-viewport'),
		stickySidebar = $('.js-sticky-sidebar'),
		stickyTop = 0;

	function updateStickyElement() {
		var scrollTop = oViewport.getScrollPosition().top;

		if (scrollTop > stickyTop) {
			$(stickySidebar).addClass('js-sticky-sidebar--active');
		} else {
			$(stickySidebar).removeClass('js-sticky-sidebar--active');
		}
	}

	function initStickyElement() {
		stickyTop = $(stickySidebar).offset().top;

		if (window.matchMedia("(min-width: 900px)").matches) {
			oViewport.listenTo('scroll');
			document.body.addEventListener('oViewport.scroll', updateStickyElement, false);
		} else {
			document.body.removeEventListener('oViewport.scroll', updateStickyElement, false);
		}
	}

	oViewport.listenTo(['scroll', 'resize']);

	initStickyElement();
	document.body.addEventListener('oViewport.resize', initStickyElement, false);
}
