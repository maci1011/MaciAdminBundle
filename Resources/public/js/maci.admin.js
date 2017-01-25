
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

});

})(jQuery);
