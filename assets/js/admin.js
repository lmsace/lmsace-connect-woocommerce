/**
 * Admin js file. Contains Test connection, Import courses.
 *
 * @package lmsace-connect
 */

jQuery(document).ready(function($) {

	/**
	 * Register test connection and import courses event handler.
	 */
	function register_event_handler() {

		$('#test_connection').on('click', function() {
			console.log('test connection calling...');
			testConnection($(this));
		});

		$('[name="start_import"]').click(function() {
			console.log('Sync Courses Started....');
			startSyncCourses($(this));
		});

		if ( $("#login_lms").length ) {

			$("#login_lms").change(function() {
				hideRedirectLess();
			})
		}
	}

	/**
	 * Test the connection details works, is the moodle return data.
	 * @param {element} elem test connection element.
	 */
	function testConnection(elem)  {
		var site_url = $('[name="lac_connection_settings[site_url]"]').val();
		var site_token = $('[name="lac_connection_settings[site_token]"]').val();
		if (site_url == '' || site_token == '') {
			alert('Please enter connection details');
			return false;
		}
		addLoader(elem);
		var args = {site_url: site_url, site_token: site_token };
		var data = { callback: 'test_connection', args: args };
		var result = admin_request(data);
		$('#lac-connection-form').ajaxSubmit({
			success: function() {
				jQuery('.lac-results').html("<div id='save-message' class='notice notice-success'></div>");
            	jQuery('#save-message').append("<p>"+lac_jsdata.strings.settingssaved+"</p>").show();
			},
			timeout: 5000
		});
		setTimeout("jQuery('#save-message, .lmsace-notice').hide('slow');", 5000);
		result.then(function(response) {
			response = JSON.parse(response);
			elem.next('span').removeClass('error');
			if (response.error == true) {
				elem.next('span').addClass('error');
			}
			elem.next('span').html(response.message);
		});
	}

	/**
	 * Start the course import. Fetch the course import options and send to backend using admin request.
	 * @param {object} elem
	 * @returns {bool|null}
	 */
	function startSyncCourses(elem) {
		// Import options.
		var syncOption = $.map($('[name*="lac_import_settings[import_options]"]:checked'), function(e, key) {
			return e.value
		});

		var selectedCourses = $('[name="lac_courses"]').val();
		if (selectedCourses == '') {
			alert('Select courses to import');
			return true;
		}
		addLoader(elem);
		var args = { import_option: syncOption, courses: selectedCourses  };
		var data = { callback: 'import_courses', args: args };
		var result = admin_request(data);
		result.then(function(response) {
			response = JSON.parse(response);
			elem.next('span').removeClass('error');
			if (response.error == true) {
				elem.next('span').addClass('error');
			}
			elem.next('span').html(response.message);
		});
	}

	/**
	 * Send request to wordpress admin ajax.
	 * @param {object} data
	 * @returns {object} status of the request.
	 */
	function admin_request(data) {
		data.action = 'lac_admin_client';
		data.nonce_ajax = lac_jsdata.nonce;
		return $.ajax({
			url: lac_jsdata.admin_ajax_url,
			// method: "post",
			data: data,
			success: function(result) {
				return result;
			},
			error:function(e) {
				return e;
			}
		}).then(function(msg) {
			return msg;
		})
	}

	/**
	 * Convert course select option to selector with search in product page.
	 */
	function course_selectbox() {
		if ($('.woocommerce_options_panel#course_options').length) {
			$('#lac_moodle_courses').select2();
		}
	}

	/**
	 * Import courses list table - datatable.
	 */
	function importTable() {

		var args = {from: 0, limit: 0};
		var importCourseTable = $('#import-course-list').DataTable({
			ajax: {
				url: lac_jsdata.admin_ajax_url+"?callback=get_courses_list&action=lac_admin_client",
				cache: false,
				data: {args: args}
			},
			columnDefs: [ {
				orderable: false,
				className: 'select-checkbox',
				targets:   0
			} ],
			columns: [
				{ data: 'select'},
				{ data: 'id' },
				{ data: 'fullname' },
				{ data: 'shortname' },
				{ data: 'categoryname' },
				{ data: 'idnumber' },
				{ data: 'visible' },
			],
			pageLength: 25,
			lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, "All"] ],
			select: {
				style:    'multi',
				selector: 'td:first-child'
			},
			language: {
				searchPlaceholder: "Keywords..."
			},
			initComplete: function () {
				// Reload course data in table.
				$('<button class="page-title-action refresh-courses"><i class="refresh-button"></i>'+lac_jsdata.strings.reloadcourses+'</button>')
					.appendTo( $('#import-course-list_length') ).on('click', function(e) {
						importCourseTable.ajax.reload();
					});
				// Category selector filter.
				this.api().columns(4).every( function () {
					var column = this;
					var select = $('<select><option value="">'+lac_jsdata.strings.allcategories+'</option></select>')
						.prependTo( $('#import-course-list_filter') )
						.on( 'change', function() {
							var val = $.fn.dataTable.util.escapeRegex(
								$(this).val()
							);

							column
								.search( val ? '^'+val+'$' : '', true, false )
								.draw();
						});

					column.data().unique().sort().each( function ( d, j ) {
						if (d == '') return;
						select.append( '<option value="'+d+'">'+d+'</option>' )
					} );
					select.wrap('<div class="category-selector"/>');
					select.parent().prepend('<label>' + lac_jsdata.strings.filtercategories + ': </label>');
				});
			}
		});

		// Update the selected courses input when the course selected in table.
		importCourseTable.on('select', function( e, dt, type, indexes ) {
			if ( type === 'row' ) {
				var data = importCourseTable.rows( { selected: true } ).data().pluck( 'id' );
				$('[name=lac_courses]').val( data.join(',') );
			}
		});

		// Remove the deselected course value from input when the course deselected in table.
		importCourseTable.on('deselect', function( e, dt, type, indexes ) {
			if ( type === 'row' ) {
				var data = importCourseTable.rows( { selected: true } ).data().pluck( 'id' );
				$('[name=lac_courses]').val( data.join(',') );
			}
		});

		importCourseTable.on('search.dt', function() {
			importCourseTable.rows().deselect();
			$("input[name=import_select_all]").prop('checked', false);
		})

		importCourseTable.on('page.dt', function() {
			importCourseTable.rows().deselect();
			$("input[name=import_select_all]").prop('checked', false);
		})

		importCourseTable.on('length.dt', function() {
			importCourseTable.rows().deselect();
			$("input[name=import_select_all]").prop('checked', false);
		})

		$("input[name=import_select_all]").on( "click", function(e) {
			if ($(this).is( ":checked" )) {
				importCourseTable.rows({ page: 'current' } ).select();
			} else {
				importCourseTable.rows().deselect();
			}
		});

	}

	// Add the loader gif icon during the ajax call.
	function addLoader(elem) {
		loader = $('<img alt="loading"/>');
		loader.attr('src', lac_jsdata.loaderurl );
		elem.parent().find('span.result').html(loader);
	}

	function hideRedirectLess() {

		if ( !$("#login_lms").length ) {
			return '';
		}

		if ($("#login_lms").val() == 'course') {
			$("#redirectless_login").prop('disabled', true);
			$("#redirectless_login").prop('readonly', true);
		} else {
			$("#redirectless_login").prop('disabled', false);
			$("#redirectless_login").prop('readonly', false);
		}
	}

	// Init.
	register_event_handler();
	importTable();
	course_selectbox();

	hideRedirectLess();
})
