
(function($){

$(document).ready(function(e) {

	$('#messagesContainer .messages').each(function(i,el) {
		setTimeout(function() {
			$(el).hide(300);
		}, ( 100 * i + 3000 ));
	});

	$('.uploader').each(function() {
		maciUploader($(this));
	});

	$('#bodyAdministration .admin-form').not('.form-filters').find('textarea').not('.noeditor').each(function() {
		admin.setRichTextEditor( $(this) );
	});

	$('.list.list-default table .multipleActionsBar').each(function(i,bartr) {
		var bar, visible = false, tbody = $(bartr).parents('.list').find('tbody').first();
		$(bartr).children().first().attr('colspan', tbody.children().first().children().length);
		bar = $(bartr).find('.nav.navbar-nav').first();

		var updateBar = function() {
			$(bartr).find('.multipleActionsBarSelectedValue').first().text(
				tbody.find("input[name^=list-item-checkbox]:checked").length
			);
		};

		bar.find('.select-all').first().click(function(e) {
			e.preventDefault();
			tbody.find('input[name^=list-item-checkbox]').each(function(i,el) {
				$(el)[0].checked = true;
			});
			updateBar();
		});

		bar.find('.deselect-all').first().click(function(e) {
			e.preventDefault();
			tbody.find('input[name^=list-item-checkbox]').each(function(i,el) {
				$(el)[0].checked = false;
			});
			updateBar();
		});

		bar.find('.action').not('.select-all, .deselect-all').each(function(i,el) {
			$(el).click(function(e) {
				e.preventDefault();
				if (!tbody.find("input[name^=list-item-checkbox]:checked").length) {
					return;
				}
				var cm = $(el).attr('confirm');
				if (cm) {
					cm = cm.replace('%items%', tbody.find("input[name^=list-item-checkbox]:checked").length);
					if (!confirm(cm)) {
						return;
					}
				}
				var ids = '';
				tbody.find('input[name^=list-item-checkbox]:checked').each(function(i,el) {
					ids += $(el).parents('.list-item').find('input[name=id]').first().val() + ',';
				});
				var url = $(el).attr('href');
				if (url == '#') url = window.location.href;
				$.ajax({
					type: 'POST',
					data: {ids: ids},
					url: url,
					success: function(d,s,x) {
						window.location.reload();
					}
				});
			});
		});

		tbody.find("input[name^=list-item-checkbox]").each(function(i,el) {
			$(el).click(function(e) {
				updateBar();
			});
		});

		updateBar();
	});

	$( "div[sortable]" ).each(function(i,el) {
		$(el).sortable({
			items: '.list-item',
			stop: function(e, ui) {
				var list = $(el).find(".list-item");
				var ids = [];
				list.each(function(j,fl) {
					ids.push( parseInt( $(fl).find('input[name=id]').first().val() ) );
				});
				$.ajax({
					type: 'POST',
					data: {ids: ids},
					url: $(el).attr('sortable')
				});
			}
		});
	});

	$('.list.list-default .tablesorter').each(function(i,el) {
		var _headers = {};
		$(el).find('.list-header').each(function(j,hd) {
			if ($(hd).hasClass('no-sort')){
				_headers[j] = { sorter: false };
			}
		});
		$(el).tablesorter({
			headers: _headers
		});
	});

	var saveFilters = function(filters) {
		$.ajax({
			type: 'POST',
			data: {
				'data': {
					'set_filters': {
						'filters': filters
					}
				}
			},
			url: window.location,
			success: function(d,s,x) {
				console.log(d);
				window.location.reload();
			},
			error: function(d,s,x) {
				console.log(d);
				alert('Error!');
			}
		});
	};

	$('.filters-container').each(function(i,el) {
		var currentId = 0, list = [],
		filtersContainer = $(el), filtersBar = $(el).prev(),
		filtersNavUL = filtersBar.find('.filters-nav > ul'),
		filters = JSON.parse($(el).attr('data-content')),
		fieldList = JSON.parse($(el).attr('data-list')),
		listWrapper = $('<div/>').addClass('filters-list-wrapper').appendTo(el),
		select = $('<select/>').addClass('form-control add-filter').appendTo(filtersNavUL),
		submit = $('<button/>').addClass('btn btn-primary').click(function(e) {
			e.preventDefault();
			var data = [false];
			for (var i = 0; i < list.length; i++)
			{
				if (!list[i]) continue;
				data[data.length] = {
					'field': list[i].field,
					'value': list[i].input.val(),
					'method': list[i].method.val(),
					'connector': list[i].connector.val()
				};
			}
			saveFilters(data);
		}).appendTo(filtersNavUL);

		select.wrap($('<li/>').addClass('nav-item'));
		submit.wrap($('<li/>').addClass('nav-item'));

		$('<option/>').attr('value', 'add-filter').text('Add Filter').appendTo(select);

		var addFilter = function(index) {
			if (index === false) return;
			var _id = currentId; currentId++;
			list[_id] = { 'field': fieldList[index].field };
			var row = $('<div/>').addClass('filter-row');

			fieldList[index].connector_label.clone().appendTo(row)
				.attr('for', ('connector_' + fieldList[index].field));
			list[_id].connector = fieldList[index].connector.clone().appendTo(row)
				.attr('id', ('connector_' + fieldList[index].field));
			fieldList[index].input_label.clone().appendTo(row).removeClass('sr-only')
				.attr('for', ('filter_' + fieldList[index].field))
				.text(fieldList[index].label);
			fieldList[index].method_label.clone().appendTo(row)
				.attr('for', ('method_' + fieldList[index].field));
			list[_id].method = fieldList[index].method.clone().appendTo(row)
				.attr('id', ('method_' + fieldList[index].field));
			list[_id].input = fieldList[index].input.clone().appendTo(row)
				.attr('id', ('filter_' + fieldList[index].field));

			if (fieldList[index].type == 'text')
				list[_id].input.attr('placeholder', fieldList[index].label);

			row.appendTo(listWrapper);
			var remove = $('<button/>').addClass('btn btn-danger').click(function(e) {
				e.preventDefault();
				list[_id] = false;
				remove.parent().remove();
				refreshButtonLabel();
			}).appendTo(row).append($("<i class='fas fa-times'></i>"));
		}

		var refreshButtonLabel = function()
		{
			var found = false;
			for (var i = 0; i < list.length; i++)
				if (list[i] != false)
				{
					found = true;
					break;
				}
			if (found)
			{
				submit.text('Apply Filters');
				listWrapper.parent().show();
			}
			else
			{
				submit.text('Reset Filters');
				listWrapper.parent().hide();
			}
		}

		select.change(function(e) {
			if (select.val() == 'add-filter') return;
			addFilter(parseInt(select.val()));
			select.val('add-filter');
			refreshButtonLabel();
		});

		for (var i = 0; i < fieldList.length; i++)
		{
			fieldList[i] = {
				'label': fieldList[i].label,
				'field': fieldList[i].field,
				'type': fieldList[i].type,
				'connector_label': $(el).find('label[for=form_set_connector_for_' + fieldList[i].field + ']'),
				'connector': $(el).find('#form_set_connector_for_' + fieldList[i].field),
				'method_label': $(el).find('label[for=form_' + fieldList[i].field + '_method]'),
				'method': $(el).find('#form_' + fieldList[i].field + '_method'),
				'input_label': $(el).find('label[for=form_' + fieldList[i].field + ']'),
				'input': $(el).find('#form_' + fieldList[i].field)
			};
			$('<option/>').attr('value', i).text(fieldList[i].label).appendTo(select);
		}

		for (var i = 0; i < filters.length; i++)
		{
			var index = -1;
			for (var j = fieldList.length - 1; j >= 0; j--) {
				if (fieldList[j].field == filters[i].field)
				{
					index = j;
					break;
				}
			}
			if (index == -1) continue;
			id = currentId;
			addFilter(index);
			list[id].connector.val(filters[i].connector);
			list[id].method.val(filters[i].method);
			list[id].input.val(filters[i].value);
		}

		refreshButtonLabel();
	});

});

})(jQuery);
