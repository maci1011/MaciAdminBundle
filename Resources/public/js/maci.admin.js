
(function($){

var maciUploader = function (input, options) {

	var _defaults = {
			'container': true,
			'selectButton': true
		},
		_options,
		_files,
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
			data[$(fl).attr('name')] = $(fl).val();
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
				if (dat.id) {
					uploader.upload(
						('/app_dev.php/it/admin/' + dat['entity'] + '/fileUpload/' + dat['id']),
						false,
						function() {
							$(form).find('[type=reset]').click();
							if ($.isFunction(callback)) {
								callback(dat);
							}
						}
					);
				}
			}
		});
	},

	async = function(el, row, callback) {
		var relations = {};
		relations[$(el).attr('from')] = $(row).find('[name=id]').first().val();
		relations[$(el).attr('to')] = $(el).attr('toid');
		$.ajax({
			type: 'POST',
			data: {
				'relations': relations
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
			setAdmin($(dat['template']).clone().prependTo('.mediaContainer'));
		}
	},

	appendModal = function(title, content) {
		var div = $('<div/>').appendTo('body');
		div.html($(content).clone()).find('.modal-title').text(title);
		return div.children();
	},

	appendResp = function(el, dat) {
		if ($(el).attr('sync')) {
			async(el,$(dat['template']),appendItem);
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

	setModalList = function(el) {
		setModal(el, function(modal, dat) {
			var container = $(modal).find('.modal-body').children().first();
			container.find('.row').each(function(j,rw) {
				var btt = $('<button/>', {'class': 'btn btn-success'}).text('Select').appendTo(rw);
				$(rw).find('.ma-form').hide();
				btt.click(function(e) {
					e.preventDefault();
					$(rw).toggleClass('selected');
					btt.toggleClass('btn-success btn-warning');
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

	setModalForm = function(el) {
		setModal(el, function(modal, dat) {
			var form = modal.find('form').first(),
				uploader = maciUploader(form.find('[type=file]').first());
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
