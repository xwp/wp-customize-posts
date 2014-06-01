/*global jQuery, wp, _, _wpCustomizePostsSettings */
( function ( api, $ ) {
	var OldPreviewer, preview;

	api.Posts = {};
	$.extend( api.Posts, _wpCustomizePostsSettings );

	api.bind( 'ready', function () {

		// Auto-open the posts section if it is at the top (we're previewing a post)
		$( '#accordion-section-posts.top' ).addClass( 'open' );

	} );

	// @todo Core really needs to not make the preview a private variable
	OldPreviewer = api.Previewer;
	api.Previewer = OldPreviewer.extend({
		initialize: function( params, options ) {
			preview = this;

			preview.bind( 'queried-posts', function( queriedPosts ) {
				//console.info( 'Preview frame rendered these posts:', queriedPosts );
				// @todo Use queriedPosts to auto-suggest posts to edit (create their controls on the fly)
				// @todo When navigating in the preview, add a post edit control automatically for queried object? Suggest all posts queried in preview.
			} );

			OldPreviewer.prototype.initialize.call( this, params, options );
		}
	} );

	api.controlConstructor.post_select = api.Control.extend( {
		ready: function () {

		}
	} );

	api.controlConstructor.post_edit = api.Control.extend( {

		/**
		 *
		 */
		ready: function () {
			var control = this;

			control.post_fields_tpl = wp.template( 'customize-posts-fields' );

			// Update the fields when the setting changes
			this.setting.bind( function ( to, from ) {
				if ( ! _( from ).isEqual( to ) ) {
					control.populateFields();
				}
			} );
			control.populateFields();

			// @todo Construct the control's fields with JS here, using the setting as the value
			// @todo Handle addition and deletion of postmeta

			// Update the setting when the fields change
			control.container.on( 'change input propertychange', ':input[name]', function () {
				control.updateSetting();
			} );

			// Add new meta
			control.container.on( 'click', '.add-meta', function () {
				var setting, new_field;
				setting = control.setting();
				new_field = wp.template( 'customize-posts-meta-field' )( {
					post_id: setting.ID,
					meta_key: '',
					meta_values: [ '' ]
				} );
				control.container.find( 'section.post-meta:first' ).find( 'dl' ).append( new_field ).find( '.meta-key:last' ).focus();
			} );

			// Add new value to meta
			control.container.on( 'click', '.add-meta-value', function () {
				var setting, new_li, dd;
				setting = control.setting();
				dd = $( this ).closest( 'dd' );
				new_li = wp.template( 'customize-posts-meta-field-value' )( {
					post_id: setting.ID,
					meta_key: dd.prev( 'dt' ).find( '.meta-key' ).val(),
					meta_value: '',
					i: dd.find( 'li' ).length
				} );
				new_li = $( new_li );
				dd.find( 'ul' ).append( new_li );
				new_li.find( '[name]' ).focus();
			} );

			// Delete a meta value
			control.container.on( 'click', '.delete-meta', function () {
				var ul, li, meta_key_input, meta_key, old_setting, dt, dd, prev_dd, next_dt;

				old_setting = control.setting();

				li = $( this ).closest( 'li' );
				ul = li.closest( 'ul' );
				dd = ul.closest( 'dd' );
				dt = dd.prev( 'dt' );
				prev_dd = dt.prev( 'dd' );
				next_dt = dd.next( 'dt' );

				meta_key_input = ul.closest( 'dd' ).prev( 'dt' ).find( '.meta-key' );
				meta_key = meta_key_input.val();

				li.find( ':input' ).prop( 'disabled', true );
				li.slideUp( function () {
					var value_lis;

					li.remove();

					value_lis = ul.find( 'li' );

					// Restore focus
					if ( ! value_lis.length ) {
						// Eliminate dt/dd for meta since last value removed
						dd.slideUp( function () {
							dd.remove();
						} );
						dt.slideUp( function () {
							dt.remove();
						} );
						if ( next_dt.length ) {
							next_dt.find( ':input:first' ).focus();
						} else if ( prev_dd.length ) {
							prev_dd.find( ':input:last' ).focus();
						} else {
							control.container.find( 'button.add-meta' ).focus();
						}
					} else {
						// Reset indicies for remaining meta values
						value_lis.each( function ( i ) {
							var old_li, new_li;
							old_li = $( this );
							new_li = wp.template( 'customize-posts-meta-field-value' )( {
								post_id: old_setting.ID,
								meta_key: meta_key,
								meta_value: old_li.find( '[name]' ).val(),
								i: i
							} );
							old_li.replaceWith( new_li );
						} );

						ul.find( '.delete-meta:first' ).focus();
					}

					control.updateSetting();
				} );


			} );

			// Update the input names
			control.container.on( 'change', '.meta-key', function () {
				var meta_key_input, dd;
				meta_key_input = $( this );
				dd = meta_key_input.closest( 'dt' ).next( 'dd' );
				dd.find( '.meta-value' ).each( function () {
					var meta_value_input, name;
					meta_value_input = $( this );
					name = meta_value_input.prop( 'id' );
					name = name.replace( /\[meta\]\[.*?\]/, '[meta][' + meta_key_input.val() + ']' );
					meta_value_input.attr( {
						id: name,
						name: name
					} );
				} ).trigger( 'change' );
			} );

		},

		/**
		 *
		 * @param id
		 * @returns {Array}
		 */
		parseKeys: function ( id ) {
			return id.replace( /^.*?\[/, '' ).replace( /\]$/, '' ).split( /\]\[/ );
		},

		/**
		 *
		 */
		populateFields: function () {
			var control, new_fields, old_fields, new_fields_container, old_fields_signature, new_fields_signature;

			control = this;
			new_fields_container = $( '<div>' + control.post_fields_tpl( control.setting() ) + '</div>' );
			new_fields_container.find( 'select.post_author' ).val( control.setting().post_author );
			new_fields_container.find( 'select.post_status' ).val( control.setting().post_status );
			new_fields_container.find( 'select.comment_status' ).val( control.setting().comment_status );


			old_fields = control.container.find( '[name]' );
			new_fields = new_fields_container.find( '[name]' );
			old_fields_signature = _( old_fields ).pluck( 'name' ).join( ',' );
			new_fields_signature = _( new_fields ).pluck( 'name' ).join( ',' );

			if ( old_fields_signature !== new_fields_signature ) {
				control.container.empty();
				control.container.append( new_fields_container.children() );
			} else {
				old_fields.each( function () {
					var old_field, new_field;
					old_field = $( this );
					new_field = new_fields_container.find( '[name="' + this.name + '"]' );
					if ( old_field.val() !== new_field.val() ) {
						old_field.val( new_field.val() );
					}
				} );
			}
		},

		/**
		 *
		 */
		updateSetting: function () {
			var control, new_setting;
			control = this;

			new_setting = {};
			control.container.find( '[name]' ).each( function () {
				var input, keys, leaf_key, sub_setting, level_key;

				input = $( this );
				keys = control.parseKeys( input.prop( 'name' ) );
				new_setting.ID = keys.shift();
				leaf_key = keys.pop(); // we want the top-level key

				sub_setting = new_setting;
				while ( keys.length ) {
					level_key = keys.shift();
					if ( typeof sub_setting[ level_key ] === 'undefined' ) {
						sub_setting[ level_key ] = {};
					}
					sub_setting = sub_setting[ level_key ];
				}

				sub_setting[ leaf_key ] = input.val();
			} );

			control.setting( new_setting );

		}
	} );


} )( wp.customize, jQuery );
