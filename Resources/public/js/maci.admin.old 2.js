
modal = function(title, html) {
	var modal = $(html);
	modal.find('.modal-title').text(title);
	return modal.clone();
},

form = function(base_form) {

	var _mf = this,
	_form = false,

	input = function(base_input) {

		var _input = false,
		parent = false,
		options = false,

		getSelectList = function(id, dat) {
			var div = $('<div/>').appendTo('body');
			div.html( modal(('List: ' + _actions[id].entity), dat) );
			_actions[id].modal = div.children();

			div.find('input[type=checkbox]').each(function(i,el) {
				$(el).attr('type', 'radio');
			});

			div.find('input[value=' + _input.val() + ']').click();

			if (!div.find('.row').length) {
				div.find('.modal-submit').last().remove();
			} else {
				div.find('.modal-submit').last().click(function(e) {
					e.preventDefault();
					var c = div.find('input[type=radio]:checked')
					_actions[id].input.val( c.val() );
					addElement(c.parents('.row').first());
					_actions[id].modal.modal('hide');
				});
			}

			_actions[id].newItem = function(e) {
				if (e.action_id != id && _actions[e.action_id].entity == _actions[id].entity) {
					var row = $(e.data);
					row.find('input').attr('type','radio');
					row.appendTo( div.find('.modal-body .row').parent() );
				}
			}

			_actions[id].modal.modal();
		},

		getMultipleList = function(id, dat) {
			var div = $('<div/>').appendTo('body');
			div.html( modal(('List: ' + _actions[id].entity), dat) );
			_actions[id].modal = div.children();

			// _input.children().each(function(j,fl) {
			// 	div.find('input[value=' + $(fl).find('input').first().val() + ']').click();
			// });

			if (!div.find('.row').length) {
				div.find('.modal-submit').last().remove();
			} else {
				div.find('.modal-submit').last().click(function(e) {
					e.preventDefault();
					div.find('input[type=checkbox]:checked').each(function(j,fl) {
						if (_input.attr('sync') && _actions[id].entity != _input.attr('entity')) {
							var data = {};
							data['fields'] = {};
							data['fields'][_actions[id].entity] = parseInt($(fl).val());
							createNewElement(data);
						} else {
							addElement($(fl).parents('.row').first());
						}
					});
					_actions[id].modal.modal('hide');
				});
			}

			_actions[id].newItem = function(e) {
				if (e.action_id != id && _actions[e.action_id].entity == _actions[id].entity) {
					var row = $(e.data);
					row.appendTo( div.find('.modal-body .row').parent() );
				}
			}

			_actions[id].modal.modal();
		},

		setModalForm = function(id, dat) {
			var div = $('<div/>').appendTo('body');
			div.html( modal(('Form: ' + _actions[id].entity), dat) );
			div.find('.modal-submit').remove();
			_actions[id].modal = div.children();

			_actions[id].files = [];
			_actions[id].modal.find('.modal-body input[type=file]').each(function(j,fl) {
				$(fl).on('change', function(e) {
					_actions[id].files[j] = {
						'input': $(fl),
						'data': e.target.files
					}
				});
			});

			_actions[id].modal.find('.modal-body input[type=submit], .modal-body button[type=submit]').last().click(function(e) {
				e.preventDefault();
				submitForm(id);
				_actions[id].modal.modal('hide');
			});

			_actions[id].modal.modal();
		},

		editElement = function(_eid, el) {
			var id = _actions.length;
			_actions[id] = {
				'action': 'edit',
				'el': $(el),
				'entity': _input.attr('entity'),
				'input': _input,
				'use_modal': true,
				'modal': false
			};
			$(el).click(function(e) {
				e.preventDefault();
				$.ajax({
					type: 'GET',
					data: { 'modal': _actions[id].use_modal },
					url: '/app_dev.php/it/admin/' + _input.attr('entity') + '/form/' + _eid,
					success: function (dat,sts,jqx) {
						setModalForm(id, dat);
					}
				});
			});
		},

		submitForm = function(id) {
			var data = {};
			_actions[id].modal.find('.modal-body input[name], .modal-body select[name], .modal-body button[name]').each(function(j,fl) {
				if ($(fl).attr('type') != 'file') {
					data[$(fl).attr('name')] = $(fl).val();
				}
			});
			$.ajax({
				type: 'POST',
				data: data,
				url: _actions[id].url,
				success: function (dat,sts,jqx) {
					if (_actions[id].action == 'new') {
						if (_input.attr('sync') && _actions[id].entity != _input.attr('entity')) {
							var data = {};
							data['fields'] = {};
							data['fields'][_actions[id].entity] = parseInt($(dat).find('input').first().val());
							createNewElement(data);
						} else {
							addElement($(fl).parents('.row').first());
						}
					} else {
						addElement(dat, _actions[id].el)
					}
					if (_actions[id].files.length) {
						uploadFiles(id, dat);
					}
					callEvent({
						'action_id': id,
						'call': 'newItem',
						'data': dat
					});
				}
			});
		},

		uploadFiles = function(id, dat) {
			var _eid = $(dat).find('input').first().val();
			$.each(_actions[id].files, function(k, map) {
				if (map) {
					var data = new FormData();
					$.each(map.data, function(key, value) {
						data.append(key, value);
					});
					$.ajax({
						type: 'POST',
						data: data,
						url: '/app_dev.php/it/admin/' + _actions[id].entity + '/fileUpload/' + _eid,
						cache: false,
						dataType: 'json',
						processData: false, // Don't process the files
						contentType: false,
						success: function (dat,sts,jqx) {
							console.log('File Uploaded!');
							_actions[id].files[k] = false;
						}
					});
				}
			});
		},

		removeElement = function(id) {
			_input.find('input[value=' + id +']').parents('.row').first().remove();
			if (_input.attr('sync')) {
				$.ajax({
					type: 'GET',
					data: {},
					url: '/app_dev.php/it/admin/' + _input.attr('entity') + '/remove/' + id,
					success: function (dat,sts,jqx) {
						console.log('Element Removed!');
					}
				});
			}
		},

		reorderList = function() {
	        _input.sortable({
	            stop: function(e, ui) {
	                var list = _input.find('.row');
	                var ids = [];
	                list.each(function () {
	                    ids.push( $(this).find('input').first().val() );
	                });
	                $.ajax({
	                    type: 'POST',
	                    data: { ids: ids },
	                    url: '/app_dev.php/it/admin/' + _input.attr('entity') + '/reorder',
	                    success: function () {
							console.log('List Reordered!');
	                    }
	                });
	            }
	        });
		},

		addElement = function(el, edit) {
			if (!( _input.attr('admin') == 'multiple' )) {
				_input.html('');
			}
			var add = $(el).clone(),
				input = add.find('input'),
				id = input.val(),
				after = ( edit ? edit : input),
				btt = $('<button/>', { 'class': 'btn btn-success'}).insertAfter(after);
			input.attr('type','hidden');
			btt.text('Edit');
			editElement(id, btt);
			$('<button/>', { 'class': 'btn btn-danger'}).insertAfter(after).click(function(e){
				e.preventDefault();
				if (confirm('Remove Item?')) {
					removeElement(id);
				}
			}).text('Remove');
			if (edit) {
				edit.remove();
			}
			add.appendTo(_input);
		},

		createNewElement = function(data) {
			data.entity = data.entity ? data.entity : _input.attr('entity');
			data.fields[getFormEntity()] = parseInt(_form.attr('item-id'));
			$.ajax({
				type: 'POST',
				data: data,
				url: '/app_dev.php/it/admin/' + data.entity + '/create',
				success: function (dat,sts,jqx) {
					addElement(dat);
				}
			});
		},

		getPreview = function(id) {
			var entity = _input.attr('entity');
			if (entity != '#' && $.isNumeric(id)) {
				$.ajax({
					type: 'GET',
					data: {},
					url: '/app_dev.php/it/admin/' + entity + '/item/' + id,
					success: function (dat,sts,jqx) {
						addElement(dat);
					}
				});
			}
		},

		callEvent = function(e) {
			$.each(_actions, function(i, action) {
				if ($.isFunction(action[e.call])) {
					var f = action[e.call]; f(e);
				}
			});
		},

		getAjax = function(id) {
			$.ajax({
				type: 'GET',
				data: { 'modal': _actions[id].use_modal },
				url: _actions[id].url,
				success: function (dat,sts,jqx) {
					_actions[id].handler(id, dat);
				}
			});
		},

		setModal = function(id) {
			_actions[id].el.click(function(e) {
				e.preventDefault();
				if(_actions[id].modal) {
					_actions[id].modal.modal();
				} else {
					getAjax(id);
				}
			});
		},

		setActions = function() {
			var entity = _input.attr('entity');
			parent.find('button[maciaction]').each(function(j,fl) {
				var attr = $(fl).attr('maciaction'),
					btt_entity = $(fl).attr('entity'),
					id = _actions.length;
				_actions[id] = {
					'action': attr,
					'el': $(fl),
					'entity': (btt_entity ? btt_entity : entity),
					'input': _input,
					'use_modal': true,
					'modal': false
				};
				if (attr == 'select') {
					_actions[id].url = '/app_dev.php/it/admin/' + _actions[id].entity + '/list';
					_actions[id].handler = getSelectList;
				} else if (attr == 'add') {
					_actions[id].url = '/app_dev.php/it/admin/' + _actions[id].entity + '/list';
					_actions[id].handler = getMultipleList;
				} else if (attr == 'new') {
					_actions[id].url = '/app_dev.php/it/admin/' + _actions[id].entity + '/form';
					_actions[id].handler = setModalForm;
				}
				if (_actions[id].use_modal) {
					setModal(id);
				}
			});
		},

		setSelect = function(el) {
			_input = $('<div/>', {
				'admin': $(el).attr('maciadmin'),
				'name': $(el).attr('name'),
				'entity': $(el).attr('entity'),
				'sync': $(el).attr('sync')
			}).insertAfter(el);
			if ($(el).val()) {
				getPreview($(el).val());
			}
			$(el).remove();
			parent = _input.parent();
			getPreview(_input.val());
			setActions();
		},

		setMultiple = function(el) {
			_input = $('<div/>', {
				'admin': $(el).attr('maciadmin'),
				'name': $(el).attr('name'),
				'entity': $(el).attr('entity'),
				'sync': $(el).attr('sync')
			}).insertAfter(el);
			var filters = {};
			filters[getFormEntity()] = parseInt(_form.attr('item-id'));
			$.ajax({
				type: 'POST',
				data: { 'filters': filters },
				url: '/app_dev.php/it/admin/' + _input.attr('entity') + '/list',
				success: function (dat,sts,jqx) {
					_input.html(dat);
					if (_input.find('.row').length) {
						_input.find('.row').each(function(j,fl) {
							addElement(fl);
							$(fl).remove();
						});
						var div = _input.children().first();
						if (div.hasClass('maci-sortable') || div.find('.maci-sortable').length) {
							div.remove();
							reorderList();
						} else if (!div.hasClass('row')) {
							div.remove();
						}
					} else {
						_input.find('p').remove();
					}
				}
			});
			$(el).remove();
			parent = _input.parent();
			setActions();
		},

		set = function(el) {
			if ($(el).attr('maciadmin') == 'select') {
				setSelect(el);
			} else if ($(el).attr('maciadmin') == 'multiple') {
				setMultiple(el);
			}
		};

		set(base_input);

		return {
			'input': _input
		}
		
	},

	set = function(el) {

		_form = $(el);

		$(el).find('select[maciadmin]').each(function(i,el) {
			_input[i] = input(el);
		});

	},

	getFormEntity = function() {
		return _form.attr('entity');	
	};

	set(base_form);

	return {
		'entity': getFormEntity(),
		'form': _form,
		'input': _input
	}

},
