// Require module
require('o-date');
require('o-tabs');
require('o-overlay');
require('o-header-services');

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

	if ($('.sidebar').length) {
		var sidebarHeight = $('.sidebar').height(),
			navHeight = 0;

		$('.sidebar').removeClass('sidebar--has-scroll');

		$('.sidebar').children().each(function() {
			navHeight += parseInt($(this).height());
		});

		if (sidebarHeight < navHeight) {
			$('.component-detail__main').css('minHeight',navHeight);
		}
	}
});
