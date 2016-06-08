/* global $ */

'use strict';

$(function() {
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

			console.log(row.find('[data-module-name--js]').attr('data-name'));

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

	function filterEvent(e){
		e.preventDefault();

		var rows = filterRows();

		if (e.keyCode && e.keyCode === 13 && $('#filter').val() && rows.find(':visible:eq(0)').length){
			location.href = rows.find(':visible:eq(0) a').attr('href');
		}
	}

	$('.filter-bar').on('submit', function(e) { e.preventDefault(); });

	$('.filter-bar #filter').on('keyup', filterEvent);
	$('.filter-bar input:checkbox').on('change', filterEvent);

	filterRows();
});
