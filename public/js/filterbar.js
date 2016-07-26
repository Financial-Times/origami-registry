/* global $ */

'use strict';

$(function() {
	var $componentList = $('.component-navigation__list');
	var itemSelector = 'li';

	if ($componentList.length === 0) {
		$componentList = $('.component-list tbody');
		itemSelector = 'tr';
	}

	function filterRows() {
		var rows = $('.searchable');
		var regex = new RegExp("^(.*?)("+$('#filter').val()+")(.*?)$", 'i');
		rows.hide();
		rows.filter(function () {
			var row = $(this);
			var filterMatch = true;
			$('.filter-bar input:checkbox').each(function () {
				if (!this.checked && row.hasClass(this.name + '-' + this.value)) {
					filterMatch = false;
					return filterMatch;
				}
			});
			if (filterMatch && regex.test(row.find('[data-module-name--js]').attr('data-name'))) {
				if ($('#filter').val()) {
					row.find('[data-module-name--js]').html(
						row.find('[data-module-name--js]').attr('data-name').replace(regex, '$1<span class="highlight">$2</span>$3')
					);
				} else {
					row.find('[data-module-name--js]').html(row.find('[data-module-name--js]').attr('data-name'));
				}
				return true;
			}
		}).show();

		return rows;
	}

	function navigateRows(key) {


		var rows = $componentList.find(itemSelector + ':visible'),
			current = rows.filter('.focused'),
			index = rows.index(current),
			select = 0;

		if (key === 40) { // Down
			if (index !== (rows.length - 1) && index >= 0) {
				select = index + 1;
			}
		} else { // Up
			if (index > 0) {
				select = index - 1;
			} else {
				select = rows.length - 1;
			}
		}

		$(current).removeClass('focused');
		$(rows[select]).addClass('focused');
	}

	function filterEvent(e){
		e.preventDefault();

		var rows = filterRows();

		// If using the up/down arrow keys, navigate the user through the list of components
		if (e.keyCode && (e.keyCode === 40 || e.keyCode === 38) && rows.find(':visible:eq(0)').length) {
			navigateRows(e.keyCode);
			return;
		}

		if (e.keyCode && e.keyCode === 13 && $('#filter').val() && rows.find(':visible:eq(0)').length) {
			if ($componentList.find('.focused').length) {
				location.href = $componentList.find('.focused a').attr('href');
			} else {
				location.href = rows.find('a:visible').eq(0).attr('href');
			}
		}

		// If the user has pressed any other key, disable the focused element
		$componentList.find('.focused').removeClass('focused');
	}

	$('.filter-bar').on('submit', function(e) { e.preventDefault(); });

	$('.filter-bar #filter').on('keyup', filterEvent);
	$('.filter-bar input:checkbox').on('change', filterEvent);

	filterRows();
});
