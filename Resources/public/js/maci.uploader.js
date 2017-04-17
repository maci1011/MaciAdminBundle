
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
		_upload_limit = 3,
		_upload_index,
		_uploaded_index,
		_name = 'file',

	_obj = {

	sendFile: function(map) {
		map.status_col.text('Uploading...');
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
					_callback(dat,sts,jqx);
				}
				_obj.end(map);
			}
		});
	},

	end: function(map) {
		map.status_col.text('Uploaded!');
		_uploaded_index++;
		if (_uploaded_index == _files.length) {
			_obj.endUpload();
		} else {
			_obj.uploadNext();
		}
	},

	endUpload: function() {
		_obj.clearList();
		_select.show();
		if ($.isFunction(_end_callback)) {
			_end_callback();
		}
	},

	upload: function() {
		_select.hide();
		_obj.hideUploadButton();
		if ($.isFunction(_start_callback)) {
			_start_callback();
		}
		for (var i = (_upload_limit < _files.length ? _upload_limit : _files.length); i >= 0; i--) {
			_obj.uploadNext();
		}
	},

	uploadNext: function() {
		var uploading = _upload_index - _uploaded_index;
		if (_upload_index == _files.length || _upload_limit < uploading) return;
		_obj.sendFile(_files[_upload_index]);
		_upload_index++;
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
		var item = $('<div/>', {'class': 'row'}).appendTo(_list);
		_files.push({
			'item': item,
			'file': file,
			'name_col': $('<div/>', {'class': 'item-name col-xs-12 col-sm-8'}).text(file.name).appendTo(item),
			'status_col': $('<div/>', {'class': 'item-status col-xs-12 col-sm-4'}).appendTo(item)
		});
	},

	clearList: function() {
		_upload_index = 0;
		_uploaded_index = 0;
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
