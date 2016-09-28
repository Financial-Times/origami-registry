// Require module
require('o-date');
require('o-tabs');
require('o-overlay');
require('o-header-services');
require('o-autoinit');

require('./filterbar');
require('./click-helper');
require('./demos');

import stickySidebar from './sticky-sidebar';
import { gistIt } from './gist-it';
import highlight from './highlight';

document.addEventListener('DOMContentLoaded', function() {

	if ($('.demo__source').length) {
		gistIt();
		highlight();
	}

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

	if ($('.js-sticky-sidebar').length) {
		stickySidebar();
	}
});
