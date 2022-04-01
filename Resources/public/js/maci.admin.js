
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

	var saveFilters = function(data) {
		$.ajax({
			type: 'POST',
			data: {
				'data': data
			},
			url: '/mcm/ajax',
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
		var fieldList = $(el).attr('data').split(','),
		listWrapper = $('<div/>').addClass('filters-list-wrapper').appendTo(el),
		select = $('<select/>').addClass('form-control add-filter').appendTo(el),
		submit = $('<button/>').addClass('btn btn-success').click(function(e) {
			e.preventDefault();
			var data = [];
			for (var i = 0; i < fieldList.length; i++)
			{
				if (fieldList[i].el != false) {
					// if (fieldList[i].type == 'text' && fieldList[i].el.val() == '') continue;
					data[data.length] = {
						'field': fieldList[i].field,
						'value': fieldList[i].el.val(),
						'method': fieldList[i].type == 'select' ? '=' : fieldList[i].m_el.val()
					};
				}
			}
			var rel = $(el).attr('rel').split(':');
			saveFilters({
				'set_filters': {
					'section': rel[0],
					'entity': rel[1],
					'filters': data.length ? data : 'unsetAll'
				}
			});
		}).appendTo(el);

		$('<option/>').attr('value', 'add-filter').text('Add Filter').appendTo(select);
		$('<div/>').addClass('row filter-row').appendTo(listWrapper).append($('<label/>').text('Add Filters'));

		var addFilter = function(index) {
			if (index === false || fieldList[index].el != false) return;
			var row = $('<div/>').addClass('row filter-row');
			if (fieldList[index].type == 'text')
			{
				$(el).find('label[for=form_' + fieldList[index].field + ']').clone().removeClass('sr-only')
					.attr('for', ('filter_' + fieldList[index].field)).appendTo(row);
				$(el).find('label[for=form_' + fieldList[index].field + '_method]').clone()
					.attr('for', ('filter_' + fieldList[index].field + '_method')).appendTo(row);
				var method = $(el).find('#form_' + fieldList[index].field + '_method').clone().change(function(e) {
					fieldList[index].method = method.val();
				}).attr('id', ('filter_' + fieldList[index].field + '_method')).appendTo(row);
				var input = $(el).find('#form_' + fieldList[index].field).clone().change(function(e) {
					fieldList[index].value = input.val();
				}).attr('id', ('filter_' + fieldList[index].field)).appendTo(row);
				fieldList[index].el = input;
				fieldList[index].m_el = method;
			}
			else if (fieldList[index].type == 'select')
			{
				$(el).find('label[for=form_' + fieldList[index].field + ']').clone().removeClass('sr-only')
					.attr('for', ('filter_' + fieldList[index].field)).appendTo(row);
				var input = $(el).find('#form_' + fieldList[index].field).clone().change(function(e) {
					fieldList[index].value = input.val();
					fieldList[index].method = '=';
				}).attr('id', ('filter_' + fieldList[index].field)).appendTo(row);
				fieldList[index].el = input;
			}
			else return;
			row.appendTo(listWrapper);
			var remove = $('<button/>').addClass('btn btn-danger').click(function(e) {
				e.preventDefault();
				remove.parent().remove();
				fieldList[index].el = false;
				fieldList[index].m_el = false;
				select.val('add-filter').change();
			}).appendTo(row).append($("<i class='fas fa-times'></i>"));
		}

		select.change(function(e) {
			addFilter(select.val() == 'add-filter' ? false : parseInt(select.val()));
			select.val('add-filter');
			var found = false;
			for (var i = 0; i < fieldList.length; i++)
			{
				if (fieldList[i].el != false)
				{
					found = true;
					break;
				}
			}
			if (found) submit.text('Apply');
			else submit.text('Reset');
		});

		for (var i = 0; i < fieldList.length; i++) {
			fieldList[i] = fieldList[i].split(':');
			fieldList[i] = {
				'el': false,
				'm_el': false,
				'label': fieldList[i][0],
				'field': fieldList[i][0].replace(' ', '_').toLowerCase(),
				'type': fieldList[i][1]
			};
			$('<option/>').attr('value', i).text(fieldList[i].label).appendTo(select);
			if ($('#form_set_filter_for_' + fieldList[i].field).attr('checked') == "checked") addFilter(i);
		}

		select.change();

	});

});

})(jQuery);
