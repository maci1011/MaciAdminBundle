
(function($){

$(document).ready(function(e) {

	$('.list-item .list-item-add, .list-item .list-item-remove').each(function() {
		$(this).click(function(e) {
			e.preventDefault();
			$(this).parents('.list-item').toggleClass('add-item');
		});
	});

	$('.list-add-form .list-add-submit').each(function() {
		$(this).click(function(e) {
			e.preventDefault();
			var ids = '';
			$(this).parents('.list-add-form').parent().prev().find('.list-item.add-item').each(function() {
				ids += $(this).find('input[name=id]').val() + ',';
			});
			$(this).prev().val(ids);
			$(this).parents('.list-add-form').submit();
		});
	});

	$('.maci_uploader_form').each(function() {
		maciUploader($(this));
	});

    $('#bodyAdministration .admin-form textarea').not('.noeditor').each(function() {
    	admin.setRichTextEditor( $(this) );
    });

    $( "div[sortable]" ).each(function(i,el) {
    	$(el).sortable({
			items: '.list-item',
		    stop: function(e, ui) {
		        var list = $(el).find(".list-item");
		        var ids = [];
		        list.each(function(j,fl) {
		            ids.push( parseInt( $(fl).find('input[type=hidden]').eq(0).val() ) );
		        });
		        $.ajax({
		            type: 'POST',
		            data: {ids: ids},
		            url: $(el).attr('sortable'),
		            success: function () {
		                console.log('Reorded!');
		            }
		        });
		    }
		});
    });

});

})(jQuery);
