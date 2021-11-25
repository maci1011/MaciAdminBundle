
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

	$('.filters-container').each(function(i,el) {
		var listWrapper = $('<div/>').addClass('filters-list-wrapper').appendTo(el);

	});

});

})(jQuery);
