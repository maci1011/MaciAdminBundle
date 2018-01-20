
(function($){

$(document).ready(function(e) {

	$('.list-item .list-item-add, .list-item .list-item-remove').each(function() {
		$(this).click(function(e) {
			e.preventDefault();
			$(this).parents('.list-item').toggleClass('add-item');
		});
	});

	$('.list-add-form .list-add-submit').each(function(i,el) {
		$(el).click(function(e) {
			e.preventDefault();
			var ids = '';
			$(el).parents('.container-fluid').first().parent().find('.list-default').eq(i).find('.list-item.add-item').each(function(j,fl) {
				ids += $(fl).find('input[name=id]').first().val() + ',';
			});
			$(el).prev('input[name=ids]').first().val(ids);
			console.log(ids);
			// $(el).parents('.list-add-form').submit();
		});
	});

	$('.maci_uploader_form').each(function() {
		maciUploader($(this));
	});

    $('#bodyAdministration .admin-form textarea').not('.noeditor').each(function() {
    	admin.setRichTextEditor( $(this) );
    });

    $('.list-default table .multipleActionsBar').each(function(i,bartr) {
	    var bar, visible = false, tbody = $(bartr).parents('.list-default').find('tbody').first();
    	$(bartr).children().first().attr('colspan', $(bartr).prev().find('th').length);
    	bar = $(bartr).find('.nav.navbar-nav').first();

    	var checkBar = function() {
    		if (!visible && $( "input[name^=list-item-checkbox]:checked").length) {
    			$(bartr).show(300);
    			visible = true;
    		}
    		if (visible && !$( "input[name^=list-item-checkbox]:checked").length) {
    			$(bartr).hide();
    			visible = false;
    		}
    	};

    	bar.find('.select-all').first().click(function(e) {
    		e.preventDefault();
	    	tbody.find('input[name^=list-item-checkbox]').each(function(i,el) {
	    		if (!$(el).is(':checked')) $(el)[0].checked = true;
	    		checkBar();
	    	});
    	});

    	bar.find('.deselect-all').first().click(function(e) {
    		e.preventDefault();
	    	tbody.find('input[name^=list-item-checkbox]').each(function(i,el) {
	    		if ($(el).is(':checked')) $(el)[0].checked = false;
	    		checkBar();
	    	});
    	});

    	bar.find('.action').not('.select-all, .deselect-all').each(function(i,el) {
	    	$(el).click(function(e) {
	    		e.preventDefault();
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
		        $.ajax({
		            type: 'POST',
		            data: {ids: ids},
		            url: $(el).attr('href'),
		            success: function(d,s,x) {
				        if ($(el).hasClass("remove")) {
					    	// tbody.find('input[name^=list-item-checkbox]:checked').each(function(i,el) {
					    	// 	$(el).parents('.list-item').remove();
					    	// });
					    	// if (tbody.find('.list-item').length == 0) {}
					    	window.location.reload();
				        }
		            }
		        });
		        console.log(ids);
	    	});
    	});

    	checkBar();

	    tbody.find( "input[name^=list-item-checkbox]").each(function(i,el) {
	    	$(el).click(function(e) {
	    		checkBar();
	    	});
	    });
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

});

})(jQuery);
