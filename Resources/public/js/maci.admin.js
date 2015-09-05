
(function($){

var maciUploader = function (form, options) {

	var _defaults = {},
		_options,
		_files = [],
		_form = false,
		_input = false,
		_list = false,
		_select = false,
		_reset = false,
		_upload = false,
		_callback = false,
		_end_callback = false,
		_upload_index,
		_name = 'file',

	_obj = {

	sendFile: function(i, map) {
		var data = new FormData();
		data.append(_name, map.file);
		$.ajax({
			type: 'POST',
			data: data,
			url: _form.attr('action'),
			cache: false,
			dataType: 'json',
			processData: false, // Don't process the files
			contentType: false,
			success: function (dat,sts,jqx) {
				if ($.isFunction(_callback)) {
					_callback(dat);
				}
				_obj.end(map);
				if (_upload_index == _files.length) {
					_obj.endUpload(dat);
				}
			}
		});
	},

	end: function(map) {
		$('<span/>').text(' - uploaded!').appendTo(map.item);
		_upload_index++;
	},

	endUpload: function(dat) {
		_obj.clearList();
		_select.show();
		if ($.isFunction(_end_callback)) {
			_end_callback(dat);
		} else {
			alert('Uploaded!');
		}
	},

	upload: function(dat) {
		_select.hide();
		_obj.hideUploadButton();
		_obj.startUpload(dat);
		_upload_index = 0;
		$.each(_files, function(i, map) {
			_obj.sendFile(i, map);
		});
	},

	setName: function(name) {
		_name = name;
	},

	setCallback: function(callback) {
		_callback = callback;
	},

	setEndCallback: function(callback) {
		_end_callback = callback;
	},

	getLength: function() {
		return _files ? _files.length : 0;
	},

	isMultiple: function() {
		return $(_input).is('[multiple]');
	},

	addItem: function(file) {
		var item = $('<div/>', {'class': 'row'}).appendTo(_list),
			title = $('<span/>').text(file.name).appendTo(item);
		_files.push({
			'item': item,
			'file': file
		});
	},

	clearList: function() {
		_upload_index = 0;
		_files = [];
		_list.html('');
		_obj.hideUploadButton();
	},

	setList: function(e) {
		_obj.clearList();
		if (!e.target.files.length) return;
		if (_obj.isMultiple()) {
			$.each(e.target.files, function(k, file) {
				_obj.addItem(file);
			});
		} else {
			_obj.addItem(e.target.files[0]);
		}
		_obj.showUploadButton();
	},

	hideUploadButton: function() {
		_upload.hide();
		_reset.hide();
	},

	showUploadButton: function() {
		_upload.show();
		_reset.show();
	},

	set: function(form, options) {
		_options = $.isArray(options) ? $.merge(_defaults, options) : _defaults;
		_form = form;
		_input = $(_form).find('.uploader_input');
		_list = $(_form).find('.uploader_list');
		_select = $(_form).find('.uploader_select');
		_reset = $(_form).find('.uploader_reset');
		_upload = $(_form).find('.uploader_upload');
		_input.hide().on('change', _obj.setList);
		_select.click(function(e) {
			e.preventDefault();
			_input.click();
		});
		_upload.click(function(e) {
			e.preventDefault();
			_obj.upload();
		});
		_reset.click(function(e) {
			e.preventDefault();
			_obj.clearList();
		});
		_obj.hideUploadButton();
		_obj.clearList();
	}

	}; // _obj

	if (form) {
		_obj.set(form, options);
	}

	return _obj;

}

var maciAdmin = function () {

	var _obj = {

	ajax: function(_url, _type, _data, callback) {
		$.ajax({
			type: _type,
			data: _data,
			url: _url,
			success: function (dat,sts,jqx) {
				if (dat['success']) {
					if ($.isFunction(callback)) {
						callback(dat);
					} else {
						console.log('Success!');
					}
				} else {
					alert('Error!');
				}
			},
			error: function(dat,sts,jqx) {
				alert('Error!');
			}
		});
	},

	getFormData: function(form) {
		var data = {};
		if ( CKEDITOR ) {
			for(var instanceName in CKEDITOR.instances) {
				CKEDITOR.instances[instanceName].updateElement();
			}
		}
		form.find('[name]').not('[type=file], [type=reset], [type=submit]').each(function(j,fl) {
			if ($(fl).attr('type') == 'checkbox') {
				data[$(fl).attr('name')] = $(fl).is(':checked') ? $(fl).val() : '' ;
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

	getModal: function(el, callback) {
		var data = { 'modal': true, 'clone': ($(el).attr('clone') ? true : null), 'optf': {} };
		$(el).children('input').each(function(){
			data['optf'][$(this).attr('name')] = $(this).val();
		});
		_obj.ajax($(el).attr('href'), 'GET', data, callback);
	},

	setObject: function(url, data, callback) {
		_obj.ajax(url, 'POST', data, callback);
	},

	submitForm: function(el, form, callback) {
		var method = ( form.attr('method') ? form.attr('method') : 'POST' ), data = _obj.getFormData(form);
		if ($(el).attr('clone')) {
			data['clone'] = true;
		}
		_obj.ajax(form.attr('action'), method, data, callback);
	},

	createObject: function(el,id,callback) {
		if (!$(el).attr('sync')) { return; }
		var relations = { 0: {} };
		if ($(el).attr('from') && !$(el).attr('clone')) {
			relations[0]['set'] = $(el).attr('from');
			relations[0]['type'] = $(el).attr('fromtype') ? $(el).attr('fromtype') : $(el).attr('from');
			relations[0]['val'] = id;
		}
		if ($(el).attr('to')) {
			relations[1] = {};
			relations[1]['set'] = $(el).attr('to');
			relations[1]['type'] = $(el).attr('totype') ? $(el).attr('totype') : $(el).attr('to');
			relations[1]['val'] = $(el).attr('toid');
		}
		_obj.setObject($(el).attr('sync'), { 'setfields': relations, 'clone': ($(el).attr('clone') ? id : null) }, callback);
	},

	setField: function(el, callback) {
		var val = $(el).attr('val');
		if (val[0] == '#') {
			val = $(val).html();
		}
		var fields = {
			0: {
				'set': $(el).attr('set'),
				'type': $(el).attr('type'),
				'val': val
			}
		};
		_obj.setObject($(el).attr('href'), { 'setfields': fields }, callback);
	},

	removeSubmitButton: function(modal) {
		$(modal).find('.modal-submit').remove();
	},

	setRichTextEditor: function(el) {
		if (!el.hasClass('noeditor')) {
			CKEDITOR.replace( el.get(0) );
		}
	},

	setParentInput: function(el, modal) {
		if ($(el).attr('parent')) {
			if ($(el).attr('parentval')) {
				var sel = modal.find($(el).attr('parent')),
					inp = $('<input/>', {'type': 'hidden', 'name': sel.attr('name')}).val(
						$(el).attr('parentval')
					)
				;
				var fg = sel.parents('.form-group').first();
				inp.insertAfter(fg);
				fg.remove();
			} else {
				modal.find($(el).attr('parent')).parents('.form-group').first().remove();
			}
		}
	},

	hideInputs: function(el, modal) {
		if ($(el).attr('hide')) {
			var list = $(el).attr('hide').split(',');
			$.each(list, function(i,str) {
				$(modal).find(str.trim()).parents('.form-group').first().hide();
			});
		}
	},

	setModalButton: function(el, callback) {
		var modal = false;
		$(el).click(function(e) {
			e.preventDefault();
			if (modal) {
				modal.modal();
			} else {
				_obj.getModal($(el), function(dat) {
					var div = $('<div/>').appendTo('body');
					div.html($(dat['template'])).find('.modal-title').text($(el).text());
					modal = div.children();
					if ($.isFunction(callback)) { callback(modal, dat); }
					modal.modal();
				});
			}
		});
	},

	setModalForm: function(el, modal, callback) {
		var form = modal.find('form').first();
		form.find('[type=file]').each(function() {
			$(this).hide().parents('.form-group').first().hide();
		});
		form.find('textarea').each(function() {
			_obj.setRichTextEditor($(this));
		});
		form.find('[type=submit]').click(function(e) {
			e.preventDefault();
			_obj.submitForm(el, form,callback);
			modal.modal('hide');
		});
		_obj.removeSubmitButton(modal);
	},

	setModalList: function(modal, callback) {
		var container = $(modal).find('.modal-body').first().addClass('row');
		container.find('.row').each(function(j,rw) {
			var acd = $(rw).find('.admin-list-actions').first(),
				btt = $('<a/>', {'href': '#'}).addClass('btn btn-success').text('Select');
			acd.html('');
			btt.appendTo(acd).click(function(e) {
				e.preventDefault();
				$(rw).toggleClass('selected');
				btt.toggleClass('btn-success btn-warning');
				if ($(rw).hasClass('selected')) {
					btt.text('Deselect');
				} else {
					btt.text('Select');
				}
			});
		});
		modal.find('.modal-submit').last().text('Add').click(function(e) {
			e.preventDefault();
			container.find('.row.selected').each(function(j,rw) {
				if ($.isFunction(callback)) { callback(rw); }
			});
			modal.modal('hide');
		});
	},

	setModalUploader: function(modal, callback) {
		var uploader = maciUploader(modal.find('form').first());
		uploader.setCallback(callback);
		_obj.removeSubmitButton(modal);
	},

	setFieldButton: function(el, callback) {
		$(el).click(function(e) {
			e.preventDefault();
			_obj.setField(el,callback);
		});
	},

	setFormButton: function(el, callback) {
		_obj.setModalButton(el, function(modal, data) {
			_obj.setParentInput(el, modal);
			_obj.hideInputs(el, modal);
			_obj.setModalForm(el, modal, function(dat) {
				if ($(el).attr('sync')) {
					_obj.createObject(el,dat['id'],callback);
				} else {
					if ( $.isFunction(callback) ) { callback(); }
					else { console.log('Success!') }
				}
			});
		});
	},

	setListButton: function(el, callback) {
		_obj.setModalButton(el, function(modal, data) {
			_obj.setModalList(modal, function(rw) {
				_obj.createObject(el,$(rw).find('[name=id]').first().val(),callback);
			});
		});
	},

	setUploaderButton: function(el, callback) {
		_obj.setModalButton(el, function(modal, data) {
			_obj.setModalUploader(modal, function(dat) {
				if (dat['success']) { _obj.createObject(el,dat['id'],function(dat) {
					if ( $.isFunction(callback) ) { callback(); }
				}); } else { console.log('error: ' + dat['error']) }
			});
		});
	}

	};

	return _obj;

}

$(document).ready(function(e) {

	var admin = maciAdmin();

	$('.ma-remove').each(function() {
		admin.setFormButton($(this), function(dat) {
			console.log('Removed!');
		});
	});

	$('.ma-set').each(function() {
		admin.setFieldButton($(this))
	});

	$('.ma-form').each(function() {
		admin.setFormButton($(this))
	});

	$('.ma-list').each(function() {
		admin.setListButton($(this))
	});

	$('.ma-uploader').each(function() {
		admin.setUploaderButton($(this))
	});


        
    $( "div[sortable]" ).each(function(i,el) {

    	$(el).sortable({

			items: '.sortable',

		    stop: function(e, ui) {

		        var list = $(el).find(".sortable");

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


    $('#bodyAdministration .maci-form textarea').not('.noeditor').each(function() {
    	admin.setRichTextEditor( $(this) );
    });

    console.log( $('#bodyAdministration .maci-form textarea').not('.noeditor').length );


});

})(jQuery);
