
(function($){

var maci = function (base) {

	var _admin = new Array(),

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

	callPostAjax = function(data, url, callback) {
		$.ajax({
			type: 'POST',
			data: data,
			url: url,
			success: function (dat,sts,jqx) {
				callback(dat);
			}
		});
	},

	sendFile = function(data, entity, id) {
		$.ajax({
			type: 'POST',
			data: data,
			url: '/app_dev.php/it/admin/' + entity + '/fileUpload/' + id,
			cache: false,
			dataType: 'json',
			processData: false, // Don't process the files
			contentType: false,
			success: function (dat,sts,jqx) {
				console.log('File Uploaded!');
			}
		});
	},

	modal = function(title, html) {
		var modal = $(html);
		modal.find('.modal-title').text(title);
		return modal.clone();
	},

	setModal = function(title, data) {
		var div = $('<div/>').appendTo('body');
		div.html($(data).clone()).find('.modal-title').text(title);
		return div.children();
	},

	getForm = function(dat, callback) {

		var _entity = $(dat).find('input[role=entity]').val(),
			modal = setModal(('Form: ' + _entity), dat),
			form = modal.find('form').first();

		modal.find('.modal-submit').remove();

		setForm(form, callback, modal);

		return modal;
	},

	setForm = function(form, callback, modal) {
		var data = {}, files = {};
		form.find('.modal-body input[name], .modal-body select[name], .modal-body button[name]').each(function(j,fl) {
			if ($(fl).attr('type') == 'file') {
				$(fl).on('change', function(e) {
					files[j] = {
						'input': $(fl),
						'data': e.target.files
					}
				});
			} else {
				data[$(fl).attr('name')] = $(fl).val();
			}
		});
		form.find('.modal-body input[type=submit], .modal-body button[type=submit]').last().click(function(e) {
			e.preventDefault();
			submitForm(form, callback, data, files);
			if (modal) {
				modal.modal('hide');
			}
		});
	},

	submitForm = function(form, callback, data, files) {
		$.ajax({
			type: 'POST',
			data: data,
			url: form.attr('action'),
			success: function (dat,sts,jqx) {
				callback(dat);
				if (files.length) {
					uploadFiles(dat, files);
				}
			}
		});
	},

	uploadFiles = function(row, files) {
		var _id = $(row).find('input[role=id]').val(),
			_entity = $(row).find('input[role=entity]').val();
		$.each(files, function(k, map) {
			if (map) {
				var data = new FormData();
				$.each(map.data, function(key, value) {
					data.append(key, value);
				});
				sendFile(data, _entity, _id);
			}
		});
	},

	addElement = function(list, row) {
		var add = $(row).clone(),
			last = list.find('.row').last();
		add.insertAfter(last);
	},

	removeElement = function(row) {
		var _id = $(row).find('input[role=id]').val(),
			_entity = $(row).find('input[role=entity]').val();
		callSimpeAjax(
			( '/app_dev.php/it/admin/' + _entity + '/remove/' + _id ),
			function() {
				console.log('Element Removed!');
			}
		)
	},

	setFormButton = function(btt, entity, id) {
		var modal = false, smf = -1,
			url = ( '/app_dev.php/it/admin/' + entity + '/form' + ( id ? ('/' + id) : null ) );
		var callback = function(dat) {
			smf = 1;
			modal = getForm(dat, callback);
		};
		btt.click(function(e) {
			e.preventDefault();
			if (smf == 1) {
				modal.modal();
			} else if (smf == -1) {
				smf = 0;
				callSimpeAjax(url, callback);
			}
		});
	},

	sync = function(list, row) {
		var data = {};
		data['fields'] = {};
		data['fields'][$(row).find('input[role=entity]').val()] = parseInt( $(row).find('input[role=id]').val() );
		callPostAjax(
			data,
			( '/app_dev.php/it/admin/' + list.attr('entity') + '/create' ),
			function(dat) {
				addElement(list, dat);
			}
		);
	},

	addActions = function(row, entity) {
		var _id = $(row).find('input[role=id]').val(),
			actions = $(row).find('.maci-actions'),
			edit = $('<button/>', {'class':'btn btn'}).text('Edit').appendTo(actions),
			remove = $('<button/>', {'class':'btn btn-danger'}).text('Remove').appendTo(actions);
		setFormButton(edit, entity, _id);
		remove.click(function(e) {
			removeElement(row);
		});
	},

	list = function(base_list) {

		var _ml = this,
		_list = false,

		set = function(_el) {
			_list = $(_el);
			var entity = _list.attr('entity');
			_list.find('.row').each(function() {
				addActions(this, entity);
			});
			var newbtt = $('<button/>', {'class':'btn btn-primary'}).text('New Item').insertAfter(_list);
			setFormButton(newbtt, entity, function(dat) {
				addElement(_list, dat);
			});
		};

		set(base_list);

		return {
			'list': _list
		}

	},

	input = function(base_input) {

		var _input = false,

	},

	form = function(base_form) {

		var _form = false,

		set = function(el) {

			_form = $(el);

		},

		set(base_form);

		return {
			'entity': getFormEntity(),
			'form': _form,
			'input': _input
		}

	},

	admin = function(base_admin) {

		var _form = new Array(),
			_list = new Array(),

		set = function(_el) {
    		$(_el).find('.maci-list').each(function(i,el) {
				_list[i] = list(el);
			});
    		$(_el).find('.maci-form').each(function(i,el) {
				_form[i] = form(el);
			});
		};

		set(base_admin);

		return {
			'form': _form,
			'list': _list
		}

	},

	set = function(el) {
		$(el).find('.maci-admin').each(function(i,el) {
			_admin[i] = admin(el);
		});
	};

	set(base);

	return {
		'admin': _admin
	};

}

$(document).ready(function(e) {

	var cima = maci('body');

});

})(jQuery);
