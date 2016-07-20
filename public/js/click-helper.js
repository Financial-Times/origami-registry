/* global $ */

'use strict';

$(function() {
	// Make components easier to click by making the whole row clickable
	$('.component-list').on('click', 'td', function(e) {
		e.preventDefault();

		var target = $(this).parent('tr').find('a').attr('href');

		if( target !== undefined ) {
			// Open in a new tab / window for these cases:
			// CMD + click || CTRL + click || middle click
			if (e.metaKey || e.ctrlKey || e.which === 2) {
				window.open(target, '_blank');
			} else {
				// if none of these keys are pressed… open in the same tab:
				location.href = target;
			}
		}
	});
});
