
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
		_start_callback = false,
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
		}
	},

	upload: function(dat) {
		_select.hide();
		_obj.hideUploadButton();
		if ($.isFunction(_start_callback)) {
			_start_callback(dat);
		}
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

	setStartCallback: function(callback) {
		_start_callback = callback;
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
