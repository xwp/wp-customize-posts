/*global jQuery, wp, _, _wpCustomizePostsSettings */

( function ( api, $ ) {
	var OldPreviewer, preview, PostData, PostsCollection, self;

	// @todo Core really needs to not make the preview a private variable
	OldPreviewer = api.Previewer;
	api.Previewer = OldPreviewer.extend({
		initialize: function( params, options ) {
			preview = this;
			OldPreviewer.prototype.initialize.call( this, params, options );
		}
	} );

	/**
	 * @type {Backbone.Model}
	 */
	PostData = Backbone.Model.extend( {
		id: null,
		setting: {},
		control: ''
	} );

	/**
	 * @type {Backbone.Model}
	 */
	PostsCollection = Backbone.Collection.extend( {
		model: PostData
	} );

	/**
	 * Namespace object for containing Customize Posts data
	 *
	 * @type {Object}
	 */
	self = api.Posts = $.extend( {}, _wpCustomizePostsSettings, {
		PostData: PostData,
		PostsCollection: PostsCollection,
		isPostPreview: new api.Value( null ),
		isSingular: new api.Value( null ),
		queriedPostId: new api.Value( null ),
		collection: new PostsCollection(),
		accordionSection: null
	} );

	// Update the model from messages passed from the preview
	api.bind( 'ready', function () {
		self.accordionSection = $( '#accordion-section-posts' );

		preview.bind( 'customize-posts', function( data ) {
			self.isPostPreview( data.isPostPreview );
			self.isSingular( data.isSingular );
			self.queriedPostId( data.queriedPostId );
			self.collection.reset( data.collection );

			//console.info( 'Preview frame rendered these posts:', queriedPosts );
			// @todo Use queriedPosts to auto-suggest posts to edit (create their controls on the fly)
			// @todo When navigating in the preview, add a post edit control automatically for queried object? Suggest all posts queried in preview.
		} );

	} );

	/**
	 * Return whether the Customizer accordion is closed
	 *
	 * @returns {boolean}
	 */
	self.isAccordionClosed = function () {
		return ( 0 === $( '.control-section.accordion-section.open' ).length );
	};

	/**
	 * Generate the ID for a Customizer post_edit control or setting
	 *
	 * @param post_id
	 * @returns {string}
	 */
	self.getCustomizeId = function ( post_id ) {
		return 'posts[' + post_id + ']';
	};

	/**
	 * Customize Control for selecting a post to edit
	 *
	 * @type {wp.customize.Control}
	 */
	api.controlConstructor.post_select = api.Control.extend( {

		/**
		 * Set up control
		 */
		ready: function () {
			var control = this;
			control.select = control.container.find( 'select:first' );

			// @todo Hide the accordion section if no posts are displayed in the preview?

			self.isSingular.bind( function () {
				control.openSectionConditionally();
			} );

			self.collection.on( 'reset', function () {
				control.populateSelect();
			} );

			control.select.on( 'change', function () {
				control.editSelectedPost();
			} );

		},

		/**
		 * Automatically open the Posts section when previewing a single
		 */
		openSectionConditionally: function () {
			if ( self.isSingular() && self.isAccordionClosed() ) {
				self.accordionSection.addClass( 'open' );
			}
		},

		/**
		 * Populate the post select with the collection's posts
		 */
		populateSelect: function () {
			var control = this;
			control.select.empty();
			self.collection.each( function ( post_data ) {
				var option;
				// @todo Skip if there is already a post_edit control open for this post
				option = new Option( post_data.get( 'setting' ).post_title, post_data.get( 'id' ) );
				control.select.append( option );
			} );

			control.select.prop( 'disabled', 0 === self.collection.length );
			control.select.prop( 'selectedIndex', -1 );

		},

		/**
		 *
		 */
		editSelectedPost: function () {
			var control, customize_id, post_id, post_edit_control, post_data, control_container;
			control = this;
			post_id = control.select.val();
			customize_id = self.getCustomizeId( post_id );

			// @todo asset the post is in the collection, or asynchronously load the post data

			post_data = self.collection.get( post_id );
			if ( ! post_data ) {
				throw new Error( 'No post data available. May need to implement async loading of post data on demand.' );
			}

			// Create setting
			if ( ! api.has( customize_id ) ) {
				api.create(
					customize_id,
					customize_id, // @todo what is this?
					post_data.get( 'setting' ),
					{
						transport: 'refresh',
						previewer: control.setting.previewer
					}
				);
			}

			// Create post_edit control
			post_edit_control = api.control( customize_id );
			if ( ! post_edit_control ) {

				// Create container element for control
				control_container = $( '<li/>' )
					.addClass( 'customize-control' )
					.addClass( 'customize-control-post_edit' );
				control_container.hide(); // to be slid-down below
				control_container.attr( 'id', 'customize-control-' + customize_id.replace( /\]/g, '' ).replace( /\[/g, '-' ) );
				control_container.append( post_data.get( 'control' ) );
				control.container.after( control_container );

				// @todo now populate fields?

				// Create control itself
				post_edit_control = new api.controlConstructor.post_edit( customize_id, {
					params: {
						settings: {
							'default': customize_id
						},
						type: 'post_edit'
					},
					previewer: control.setting.previewer
				} );

				api.control.add( customize_id, post_edit_control );

				control_container.slideDown();
			}

			control.select.prop( 'selectedIndex', -1 );
		}
	} );

	/**
	 * Customize Control for editing a post
	 *
	 * @type {wp.customize.Control}
	 */
	api.controlConstructor.post_edit = api.Control.extend( {

		/**
		 *
		 */
		ready: function () {
			var control = this;

			// @todo do we need this?
//			control.post_fields_tpl = wp.template( 'customize-posts-fields' );

//			// Update the fields when the setting changes
//			this.setting.bind( function ( to, from ) {
//				if ( ! _( from ).isEqual( to ) ) {
//					control.populateFields();
//				}
//			} );
//			control.populateFields();

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
