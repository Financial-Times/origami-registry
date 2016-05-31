/* global $ */

'use strict';

$(function() {
	// Make components easier to click by making the whole row clickable
	$('.component-list').on('click', 'td', function(e) {
		e.preventDefault();

		// Open in a new tab / window for these cases:
		// CMD + click || CTRL + click || middle click
		if (e.metaKey || e.ctrlKey || e.which === 2) {
			window.open($(this).parent('tr').find('a').attr('href'), '_blank');
		} else {
			// if none of these keys are pressed… open in the same tab:
			location.href = $(this).parent('tr').find('a').attr('href');
		}
	});
});
