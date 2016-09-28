/* global $ */

$(function() {
	var $filterBar = $('.filter-bar');
	var $componentList = $('.component-navigation__list');
	var itemSelector = 'li';
	// Define some globals to allow the filtering function to work
	// with different options - and to save duplicating code
	var regex;
	var filterWithCheckboxes = true;
	var lowResultLimit = 2;

	// Change the selector if we're on the component-listing page
	if ($componentList.length === 0) {
		$componentList = $('.component-list tbody');
		itemSelector = 'tr';
	}

	function rowsFilter() {
		var row = $(this);
		var filterMatch = true;

		if (filterWithCheckboxes) {
			$('.filter-bar input:checkbox').each(function () {
				if (!this.checked && row.hasClass(this.name + '-' + this.value)) {
					filterMatch = false;
					return filterMatch;
				}
			});
		}

		if (filterMatch && ((regex.test(row.find('[data-module-name--js]').attr('data-name'))) || (regex.test(row.find('[data-module-name--js]').attr('data-keywords'))))) {
			if ($('#filter').val() && row.find('[data-module-name--js]').length) {
				row.find('[data-module-name--js]').html(
					row.find('[data-module-name--js]').attr('data-name').replace(regex, '$1<span class="highlight">$2</span>$3')
				);
			} else {
				row.find('[data-module-name--js]').html(row.find('[data-module-name--js]').attr('data-name'));
			}
			return true;
		}
		var elem = row.find('[data-module-name--js]').attr('data-name');
		if (filterMatch && elem && regex.test(elem)) {
			if ($('#filter').val() && row.find('[data-module-name--js]').length) {
				row.find('[data-module-name--js]').html(
					row.find('[data-module-name--js]').attr('data-name').replace(regex, '$1<span class="highlight">$2</span>$3')
				);
			} else {
				row.find('[data-module-name--js]').html(row.find('[data-module-name--js]').attr('data-name'));
			}

			return true;
		}
	}

	function filterRows() {
		var rows = $('.js-searchable');
		var emptySearch = $('.empty-search');

		rows.hide();
		regex = new RegExp("^(.*?)("+$('#filter').val()+")(.*?)$", 'i');

		var filteredRows = rows.filter(rowsFilter, this);

		if (filteredRows.length <= lowResultLimit) {
			// Remove the checkboxes from the search to widen results
			filterWithCheckboxes = false;
			filteredRows = rows.filter(rowsFilter, this);
		}

		filteredRows.show();
		filterWithCheckboxes = true;
		filteredRows.length === 0 ? emptySearch.attr('aria-hidden', 'false') : emptySearch.attr('aria-hidden', 'true');
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

	// Show the filter bar and register event handlers
	if ($filterBar.length) {
		$filterBar.attr('aria-hidden', false);

		$filterBar.on('submit', function(e) { e.preventDefault(); });

		$('.filter-bar #filter').on('keyup', filterEvent);
		$('.filter-bar input:checkbox').on('change', filterEvent);

		// Run the filter script to filter out modules based on default checkboxes
		filterRows();
	}
});
