// Require module
require('o-date');
require('o-tabs');
require('o-overlay');
require('o-header-services');

require('./filterbar');
require('./click-helper');
require('./demos');
require('./sticky-sidebar');

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
});
