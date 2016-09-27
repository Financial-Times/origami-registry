// Require module
import 'o-autoinit';
import 'o-date';
import 'o-tabs';
import 'o-overlay';
import 'o-header-services';

import './filterbar';
import './click-helper';
import './demos';
import './sticky-sidebar';

import { gistIt } from './gist-it';
import highlight from './highlight';

document.addEventListener('DOMContentLoaded', function() {
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
