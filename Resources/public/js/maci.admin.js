
$(function(){
	function initToolbarBootstrapBindings() {
		var fonts = ['Serif', 'Sans', 'Arial', 'Arial Black', 'Courier', 
			'Courier New', 'Comic Sans MS', 'Helvetica', 'Impact', 'Lucida Grande', 'Lucida Sans', 'Tahoma', 'Times',
			'Times New Roman', 'Verdana'],
			fontTarget = $('[title=Font]').siblings('.dropdown-menu');
		$.each(fonts, function (idx, fontName) {
			fontTarget.append($('<li><a data-edit="fontName ' + fontName +'" style="font-family:\''+ fontName +'\'">'+fontName + '</a></li>'));
		});
		// $('a[title]').tooltip({container:'body'});
		$('.dropdown-menu input').click(function() {return false;})
			.change(function () {$(this).parent('.dropdown-menu').siblings('.dropdown-toggle').dropdown('toggle');})
			.keydown('esc', function () {this.value='';$(this).change();});
		$('[data-role=magic-overlay]').each(function () { 
			var overlay = $(this), target = $(overlay.data('target')); 
			overlay.css('opacity', 0).css('position', 'absolute').offset(target.offset()).width(target.outerWidth()).height(target.outerHeight());
		});
		if ("onwebkitspeechchange"  in document.createElement("input")) {
			var editorOffset = $('#editor').offset();
			$('#voiceBtn').css('position','absolute').offset({top: editorOffset.top, left: editorOffset.left+$('#editor').innerWidth()-35});
		} else {
			$('#voiceBtn').hide();
		}
		$('#pictureBtn').hide();
	};
	function showErrorAlert (reason, detail) {
		var msg='';
		if (reason==='unsupported-file-type') { msg = "Unsupported format " +detail; }
		else {
			console.log("error uploading file", reason, detail);
		}
		$('<div class="alert"> <button type="button" class="close" data-dismiss="alert">&times;</button>'+ 
			'<strong>File upload error</strong> '+msg+' </div>').prependTo('#alerts');
	};
	initToolbarBootstrapBindings();  
	$('#editor').wysiwyg({ fileUploadError: showErrorAlert} );
	window.prettyPrint && prettyPrint();
});

(function($){

var maciUploader = function (input, options) {

	var _defaults = {
			'container': true,
			'selectButton': true
		},
		_options,
		_files = [],
		_div,
		_select_btt,
		_upload_btt,

	_options = $.isArray(options) ? $.merge(_defaults, options) : _defaults;

	_obj = {

	end: function(map) {
		$('<span/>').text('uploaded!').appendTo(map.item);
	},

	sendFile: function(url, map, name, callback) {
		var filename = (name ? name : 'file'),
			data = new FormData();
		data.append(filename, map.file);
		$.ajax({
			type: 'POST',
			data: data,
			url: url,
			cache: false,
			dataType: 'json',
			processData: false, // Don't process the files
			contentType: false,
			success: function (dat,sts,jqx) {
				_obj.end(map);
				if ($.isFunction(callback)) {
					callback(dat);
				}
			}
		});
	},

	upload: function(url, name, callback) {
		$.each(_files, function(k, map) {
			_obj.sendFile(url, map, name, callback);
		});
	},

	getLength: function() {
		return _files ? _files.length : 0;
	},

	isMultiple: function() {
		return $(input).is('[multiple]');
	},

	clearList: function(file) {
		_files = [];
		_div.html('');
	},

	addUploaderContainer: function(el) {
		_div = $('<div/>', {'class': 'maciUploader container'}).insertAfter(el);
	},

	addSelectFilesButton: function(el) {
		$(input).hide();
		_select_btt = $('<a/>', {'class': 'btn btn-primary', 'href': '#'}).click(function(e) {
			e.preventDefault();
			$(input).click();
		}).text('Select File').insertAfter(el);
	},

	addUploadButton: function(url, name, callback) {
		_upload_btt = $('<a/>', {'class': 'btn btn-success', 'href': '#'}).click(function(e) {
			e.preventDefault();
			_obj.upload(url, name, callback);
		}).text('Upload!').insertAfter(_select_btt);
	},

	addItem: function(file) {
		var item = $('<div/>', {'class': 'row'}).appendTo(_div),
			title = $('<span/>').text(file.name).appendTo(item);
		_files.push({
			'item': item,
			'file': file
		});
	},

	set: function(e) {
		_obj.clearList();
		if (!e.target.files.length) return;
		if (_obj.isMultiple()) {
			$.each(e.target.files, function(k, file) {
				_obj.addItem(file);
			});
		} else {
			_obj.addItem(e.target.files[0]);
		}
	}

	}; // _obj

	_obj.addUploaderContainer(input);

	_obj.addSelectFilesButton(input);

	$(input).on('change', function(e) {
		_obj.set(e);
	});

	return _obj;

}

var maciAdmin = function () {

	var

	getFormData = function(form) {
		var data = {};
		form.find('[name]').not('[type=file], [type=reset], [type=submit]').each(function(j,fl) {
			if ($(fl).attr('type') == 'checkbox') {
				data[$(fl).attr('name')] = $(fl).is(':checked');
			} else if ($(fl).attr('type') == 'radio') {
				if ($(fl).is(':checked')) {
					data[$(fl).attr('name')] = $(fl).val();
				}
			} else {
				data[$(fl).attr('name')] = $(fl).val();
			}
		});
		return data;
	},

	callSimpeAjax = function(url, callback) {
		$.ajax({
			type: 'GET',
			data: { 'modal': true },
			url: url,
			success: function (dat,sts,jqx) {
				callback(dat);
			}
		});
	},

	submitForm = function(form, uploader, callback) {
		var data = getFormData(form);
		$.ajax({
			type: 'POST',
			data: data,
			url: form.attr('action'),
			success: function (dat,sts,jqx) {
				if (dat['success']) {
					if (uploader && uploader.getLength()) {
						uploader.upload(
							('/app_dev.php/admin/' + dat['entity'] + '/fileUpload/' + dat['id']),
							false,
							function() {
								$(form).find('[type=reset]').click();
								if ($.isFunction(callback)) {
									callback(dat);
								}
							}
						);
					} else {
						callback(dat);
					}
				}
			}
		});
	},

	async = function(el,row,callback) {
		var relations = {};
		relations[0] = {};
		relations[0]['set'] = $(el).attr('from');
		relations[0]['type'] = $(el).attr('fromtype') ? $(el).attr('fromtype') : $(el).attr('from');
		relations[0]['val'] = $(row).find('[name=id]').first().val();
		relations[1] = {};
		relations[1]['set'] = $(el).attr('to');
		relations[1]['type'] = $(el).attr('totype') ? $(el).attr('totype') : $(el).attr('to');
		relations[1]['val'] = $(el).attr('toid');
		$.ajax({
			type: 'POST',
			data: {
				'setfields': relations
			},
			url: $(el).attr('sync'),
			success: function (dat,sts,jqx) {
				callback(dat);
			}
		});
	},

	removeSubmitButton = function(modal) {
		$(modal).find('.modal-submit').remove();
	},

	appendItem = function(dat) {
		if (dat['id']) {
			$('#_pageContainer').find('.noitems').parent().remove();
			var item = $(dat['template']).clone(),
				id = item.find('[name=id]').first().val(),
				row = $('#_pageContainer').find('[name=id]').filter('[value=' + id +']');
			if (row.length) {
				row = row.parents('.row').first().parent();
				item.insertAfter(row);
				row.remove();
			} else {
				item.prependTo('#_pageContainer');
			}
			setAdmin(item);
		}
	},

	appendModal = function(title, content) {
		var div = $('<div/>').appendTo('body');
		div.html($(content).clone()).find('.modal-title').text(title);
		return div.children();
	},

	appendResp = function(el, dat, row) {
		console.log(dat);
		row = ( row ? row : $(dat['template']) );
		if ($(el).attr('sync')) {
			async(el,row,appendItem);
		} else { appendItem(dat); }
	},

	setModal = function(el, callback) {
		var modal = false;
		$(el).click(function(e) {
			e.preventDefault();
			if (modal) {
				modal.modal();
			} else {
				callSimpeAjax($(el).attr('href'), function(dat) {
					modal = appendModal($(el).text(), dat['template']);
					callback(modal, dat);
					modal.modal();
				});
			}
		});
	},

	removeItem = function(el) {
		$.ajax({
			type: 'GET',
			data: {},
			url: $(el).attr('href'),
			success: function (dat,sts,jqx) {
				if (dat.result) {
					$(el).parents('.row').first().parent().remove();
				}
			}
		});
	},

	setRemoveButton = function(el) {
		$(el).click(function(e) {
			e.preventDefault();
			if (confirm('Remove Item?')) {
				removeItem(el);
			}
		});
	},

	setField = function(el) {
		$(el).click(function(e) {
			e.preventDefault();
			var val = $(el).attr('val');
			if (val[0] == '#') {
				val = $(val).html();
			}
			$.ajax({
				type: 'POST',
				data: {
					'setfields': {
						0: {
							'set': $(el).attr('set'),
							'type': $(el).attr('type'),
							'val': val
						}
					}
				},
				url: $(el).attr('href'),
				success: function (dat,sts,jqx) {
					if (dat.success) {
						alert('Set!');
					}
				}
			});
		});
	},

	setModalList = function(el) {
		setModal(el, function(modal, dat) {
			var container = $(modal).find('.modal-body').first().addClass('row');
			container.find('.row').each(function(j,rw) {
				$(rw).find('.ma-form, .ma-remove').hide();
				var btt = $('<a/>', {'href': '#'}).text('Select');
				if ($(rw).find('.maci-actions').length) {
					btt.appendTo($(rw).find('.maci-actions').first());
				} else {
					btt.addClass('btn btn-success').appendTo(rw);
				}
				btt.click(function(e) {
					e.preventDefault();
					$(rw).toggleClass('selected');
					if ($(rw).hasClass('selected')) {
						btt.text('Deselect');
					} else {
						btt.text('Select');
					}
					if (!$(rw).find('.maci-actions').length) {
						btt.toggleClass('btn-success btn-warning');
					}
				});
			});
			modal.find('.modal-submit').last().text('Add').click(function(e) {
				e.preventDefault();
				container.find('.row.selected').each(function(j,rw) {
					async(el,rw,appendItem);
				});
				modal.modal('hide');
			});
		});
	},

	setMatra = function(el) {
		setModal(el, function(modal, dat) {
			var form = modal.find('form').first();
			var isnew = ( form.attr('item-id').trim() == 'new' );
			if (isnew) {
				form.find('#language_label').val($(el).attr('alt'));
			}
			$(modal).find('.modal-body').find('[type=submit]').click(function(e) {
				e.preventDefault();
				submitForm(form,false,function(dat) {
					text = $(dat['template']).find('a').eq(1).text();
					if (text.length) {
						$(el).prev().text(text);
					}
					if (isnew) {
						var nel = $(el).clone().attr('href', ( $(el).attr('href') + '/' + dat['id'] ) ).insertAfter(el);
						$(el).remove();
						setMatra(nel);
					}
				});
				modal.modal('hide');
			});
			removeSubmitButton(modal);
		});
	},

	setModalForm = function(el) {
		setModal(el, function(modal, dat) {
			var form = modal.find('form').first(), uploader = false;
			if (form.find('[type=file]').length) {
				var first = form.find('[type=file]').first();
				uploader = maciUploader(first);
				form.find('[type=file]').not(first).parent().hide();
			}
			if ($(el).attr('parent')) {
				if ($(el).attr('parentval')) {
					var sel = form.find($(el).attr('parent')),
						inp = $('<input/>', {'type': 'hidden', 'name': sel.attr('name')}).val(
							$(el).attr('parentval')
						)
					;
					sel.parents('.form-group').first().html('').append(inp);
				} else {
					form.find($(el).attr('parent')).parents('.form-group').first().remove();
				}
			}
			$(modal).find('.modal-body').find('[type=submit]').click(function(e) {
				e.preventDefault();
				submitForm(form,uploader,function(dat) {
					appendResp(el,dat);
				});
				modal.modal('hide');
			});
			removeSubmitButton(modal);
		});
	},

	setModalUploader = function(el) {
		setModal(el, function(modal, data) {
			var input = modal.find('[type=file]').first(),
				uploader = maciUploader(input);
			uploader.addUploadButton($(el).attr('href'),false,function(dat) {
				appendResp(el,dat);
			});
			$(el).click(function(e) {
				e.preventDefault();
				input.click();
			});
			removeSubmitButton(modal);
		});
	},

	setAdmin = function(base) {
		$(base).find('.ma-remove').each(function(i,el) {
			setRemoveButton(el);
		});
		$(base).find('.ma-set').each(function(i,el) {
			setField(el);
		});
		$(base).find('.ma-tra').each(function(i,el) {
			setMatra(el);
		});
		$(base).find('.ma-list').each(function(i,el) {
			setModalList(el);
		});
		$(base).find('.ma-form').each(function(i,el) {
			setModalForm(el);
		});
		$(base).find('.ma-uploader').each(function(i,el) {
			setModalUploader(el);
		});
	};

	return {
		set: function(el) {
			setAdmin(el);
		}
	};

}

$(document).ready(function(e) {

	var admin = maciAdmin();

	admin.set('body');

});

})(jQuery);
