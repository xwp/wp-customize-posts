/*global jQuery, wp, _, Backbone, _wpCustomizePostsSettings */

( function ( api, $ ) {
	var OldPreviewer, preview, PostData, PostsCollection, self;

	// @todo Core really needs to not make the preview a private variable
	OldPreviewer = api.Previewer;
	api.Previewer = OldPreviewer.extend( {
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
		setting: {}, // @todo this should be settingData
		control: '', // @todo this should be controlContent

		/**
		 *
		 * @returns {wp.customize.Setting|undefined}
		 */
		getSetting: function () {
			return api( self.createCustomizeId( this.id ) );
		},

		/**
		 *
		 * @returns {wp.customize.Control|undefined}
		 */
		getControl: function () {
			return api.control( self.createCustomizeId( this.id ) );
		},

		/**
		 * Create the customizer control and setting for this post
		 */
		customize: function () {
			self.editPost( this.id );
		},

		/**
		 *
		 * @returns {String}
		 */
		getTitle: function () {
			var setting = api( self.createCustomizeId( this.id ) );
			if ( setting ) {
				return setting().post_title;
			} else {
				return this.get( 'setting' ).post_title;
			}
		}
	} );

	/**
	 * @type {Backbone.Model}
	 */
	PostsCollection = Backbone.Collection.extend( {
		model: PostData,
		comparator: function ( model ) {
			return model.getTitle();
		}
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
		self.section.init();

		preview.bind( 'customize-posts', function( data ) {
			self.isPostPreview( data.isPostPreview );
			self.isSingular( data.isSingular );
			self.queriedPostId( data.queriedPostId );
			self.updateCollection( data.collection );

			// @todo When navigating in the preview, add a post edit control automatically for queried object? Suggest all posts queried in preview.
		} );

		api.bind( 'add', self.setupSettingModelSync );

	} );

	/**
	 * Encapsultation of model for Customize Section
	 */
	self.section = {
		container: null,

		init: function () {
			var section = this;
			section.container = $( '#accordion-section-posts' );

			// Toggle visibility of customize section
			self.collection.on( 'all', function () {
				if ( self.collection.length ) {
					section.container.slideDown();
				} else {
					section.container.slideUp();
				}
			} );

			self.isSingular.bind( function () {
				section.openSectionConditionally();
			} );
		},

		/**
		 * Return whether the Customizer accordion is closed
		 *
		 * @returns {Boolean}
		 */
		isAccordionClosed: function () {
			return ( 0 === $( '.control-section.accordion-section.open' ).length );
		},

		/**
		 * Automatically open the Posts section when previewing a single
		 */
		openSectionConditionally: function () {
			var section = this;
			if ( self.isSingular() && section.isAccordionClosed() ) {
				section.container.find( '.accordion-section-title:first' ).trigger( 'click' );
			}
		}

	};

	/**
	 * Remove any posts from self.collection() that aren't in collection, and
	 * which aren't currently being edited in the Customizer.
	 *
	 * @param {Array} new_collection
	 */
	self.updateCollection = function ( new_collection ) {
		var keep_post_ids;
		keep_post_ids = _.pluck( new_collection, 'id' );

		// Append any IDs for posts currently being edited
		api.each( function ( setting ) {
			var post_id = self.parseCustomizeId( setting.id );
			if ( post_id ) {
				keep_post_ids.push( post_id );
			}
		} );

		// Remove any post not kept
		self.collection.remove( self.collection.filter( function ( post_data ) {
			return ( -1 === keep_post_ids.indexOf( post_data.id ) );
		} ) );

		// Add/update any new post_data to the collection
		_.each( new_collection, function ( post_data ) {
			var model = self.collection.get( post_data.id );
			if ( model ) {
				model.set( post_data );
			} else {
				self.collection.add( post_data );
			}
		} );
		self.collection.sort();
	};

	/**
	 * Trigger updates on the Backbone PostData model when the corresponding Customize Setting changes
	 *
	 * @param {wp.customize.Setting} setting
	 */
	self.setupSettingModelSync = function ( setting ) {
		var handler, post_id;
		post_id = self.parseCustomizeId( setting.id );
		if ( post_id ) {
			handler = function () {
				var post_data = self.collection.get( post_id );
				if ( post_data ) {
					post_data.trigger( 'change' );
				}
			};
			setting.bind( handler );
		}
	};

	/**
	 * Pattern for a Customize ID
	 * @type {RegExp}
	 */
	self.customizeIdRegex = /^posts\[(\d+)\]$/;

	/**
	 * Generate the ID for a Customizer post_edit control or setting
	 *
	 * @param {Number} post_id
	 * @returns {String}
	 */
	self.createCustomizeId = function ( post_id ) {
		return 'posts[' + post_id + ']';
	};

	/**
	 * Obtain the post ID from a given customize post setting/control ID
	 *
	 * @param {String} customize_id
	 * @return {Number|null} the post ID contained in the customize ID, or null
	 */
	self.parseCustomizeId = function ( customize_id ) {
		var matches = customize_id.match( self.customizeIdRegex );
		if ( matches ) {
			return parseInt( matches[1], 10 );
		} else {
			return null;
		}
	};

	/**
	 * Multidimensional helper function.
	 *
	 * Port of WP_Customize_Setting::multidimensional()
	 *
	 * @todo This should be migrated to customize-base.js
	 *
	 * @param {Object} root
	 * @param {Array} keys
	 * @param {Boolean} create Default is false.
	 * @return {null|Object} Keys are 'root', 'node', and 'key'.
	 */
	self.multidimensional = function ( root, keys, create ) {
		var last, node, key, i;

		if ( create && ! root ) {
			root = {};
		}

		if ( ! root || ! keys.length ) {
			return undefined;
		}

		last = keys.pop();
		node = root;

		for ( i = 0; i < keys.length; i += 1 ) {
			key = keys[ i ];

			if ( create && typeof node[ key ] === 'undefined' ) {
				node[ key ] = {};
			}

			if ( typeof node !== 'object' || typeof node[ key ] === 'undefined' ) {
				return undefined;
			}

			node = node[ key ];
		}

		if ( create && typeof node[ last ] === 'undefined' ) {
			node[ last ] = {};
		}

		if ( typeof node[ last ] === 'undefined' ) {
			return undefined;
		}

		return {
			'root': root,
			'node': node,
			'key': last
		};
	};

	/**
	 * Will attempt to replace a specific value in a multidimensional array.
	 *
	 * Port of WP_Customize_Setting::multidimensional_replace()
	 *
	 * @param {Object} root
	 * @param {Array} keys
	 * @param {*} value The value to update.
	 * @return {*}
	 */
	self.multidimensionalReplace = function ( root, keys, value ) {
		var result;
		if ( typeof value === 'undefined' ) {
			return root;
		} else if ( ! keys.length ) { // If there are no keys, we're replacing the root.
			return value;
		}

		result = this.multidimensional( root, keys, true );

		if ( result ) {
			result.node[ result.key ] = value;
		}

		return root;
	};

	/**
	 * Will attempt to fetch a specific value from a multidimensional array.
	 *
	 * Port of WP_Customize_Setting::multidimensional_get()
	 *
	 * @todo Should be ported over to customize-base.js
	 *
	 * @param {Object} root
	 * @param {Array} keys
	 * @param {*} [defaultValue] A default value which is used as a fallback. Default is null.
	 * @return {*} The requested value or the default value.
	 */
	self.multidimensionalGet = function ( root, keys, defaultValue ) {
		var result;
		if ( typeof defaultValue === 'undefined' ) {
			defaultValue = null;
		}

		if ( ! keys || ! keys.length ) { // If there are no keys, test the root.
			return ( typeof root !== 'undefined' ) ? root : defaultValue;
		}

		result = this.multidimensional( root, keys );
		return typeof result !== 'undefined' ? result.node[ result.key ] : defaultValue;
	};


	/**
	 * Will attempt to check if a specific value in a multidimensional array is set.
	 *
	 * Port of WP_Customize_Setting::multidimensional_isset()
	 *
	 * @todo Fold this into customize-base.js
	 *
	 * @param {Object} root
	 * @param {Array} keys
	 * @return {Boolean} True if value is set, false if not.
	 */
	self.multidimensionalIsset = function ( root, keys ) {
		var result, noValue;
		noValue = {};
		result = this.multidimensionalGet( root, keys, noValue );
		return result !== noValue;
	};

	/**
	 * Create a post's setting and post_edit control if they don't already exist.
	 *
	 * @param post_id
	 */
	self.editPost = function ( post_id ) {
		var customize_id, post_edit_control, post_data, control_container;
		customize_id = self.createCustomizeId( post_id );

		// @todo asset the post is in the collection, or asynchronously load the post data

		post_data = self.collection.get( post_id );
		if ( ! post_data ) {
			throw new Error( 'No post data available. May need to implement async loading of post data on demand.' );
		}

		// Create setting
		if ( ! api.has( customize_id ) ) {
			api.create(
				customize_id,
				customize_id, // @todo what is this parameter for?
				post_data.get( 'setting' ),
				{
					transport: 'refresh',
					previewer: api( 'selected_posts' ).previewer
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
			api.control( 'selected_posts' ).container.after( control_container );

			// Create control itself
			post_edit_control = new api.controlConstructor.post_edit( customize_id, {
				params: {
					settings: {
						'default': customize_id
					},
					type: 'post_edit'
				},
				previewer: api( 'selected_posts' ).previewer
			} );

			api.control.add( customize_id, post_edit_control );

			control_container.slideDown();
		}
	},

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
			var control, toggle_control_created;
			control = this;
			control.select = control.container.find( 'select:first' );

			self.collection.on( 'sort add remove reset change', function () {
				control.populateSelect();
			} );
			control.select.on( 'change', function () {
				control.editSelectedPost();
			} );

			toggle_control_created = function ( other_control ) {
				if ( other_control instanceof api.controlConstructor.post_edit ) {
					control.populateSelect();
				}
			};
			api.control.bind( 'add', toggle_control_created );
			api.control.bind( 'remove', toggle_control_created );

		},

		/**
		 * Populate the post select with the collection's posts
		 */
		populateSelect: function () {
			var control = this;
			control.select.empty();
			self.collection.each( function ( post_data ) {
				var option;
				if ( ! api.control( self.createCustomizeId( post_data.id ) ) ) {
					option = new Option( post_data.get( 'setting' ).post_title, post_data.get( 'id' ) );
					control.select.append( option );
				}
			} );

			control.select.prop( 'disabled', 0 === self.collection.length );
			control.select.prop( 'selectedIndex', -1 );
			if ( control.select.prop( 'length' ) === 0 ) {
				control.container.slideUp();
			} else {
				control.container.slideDown();
			}

		},

		/**
		 * Edit the selected post.
		 */
		editSelectedPost: function () {
			var control, post_id;
			control = this;
			post_id = control.select.val();
			if ( post_id ) {
				self.editPost( post_id );
				control.select.prop( 'selectedIndex', -1 );
			}
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

			// Update the fields when the setting changes
			this.setting.bind( function ( to, from ) {
				if ( ! _( from ).isEqual( to ) ) {
					control.populateFields();
				}
			} );
			control.populateFields();

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
		 * Given a multidimensional ID/name like "posts[519][meta][single1][0]",
		 * return the keys as an array, like: [ '519', 'meta', 'single1', '0' ]
		 *
		 * @param {String} id
		 * @returns {Array}
		 */
		parseKeys: function ( id ) {
			return id.replace( /^.*?\[/, '' ).replace( /\]$/, '' ).split( /\]\[/ );
		},

		/**
		 * Update the control's fields with the values in the setting
		 */
		populateFields: function () {
			var control, fields;
			// @todo Should this use wp.customize.Element?

			control = this;
			fields = control.container.find( '[name]' );
			fields.each( function () {
				var field, keys, value;
				field = $( this );
				keys = control.parseKeys( field.prop( 'name' ) );
				keys.shift(); // remove ID
				value = self.multidimensionalGet( control.setting(), keys );
				if ( field.val() !== value ) {
					field.val( value );
				}
			} );
		},

		/**
		 *
		 */
		updateSetting: function () {
			var control, new_setting;
			control = this;

			new_setting = {};
			new_setting.ID = control.setting().ID;
			control.container.find( '[name]' ).each( function () {
				var input, keys;
				input = $( this );
				keys = control.parseKeys( input.prop( 'name' ) );
				keys.shift(); // get rid of ID
				self.multidimensionalReplace( new_setting, keys, input.val() );
			} );

			control.setting( new_setting );

		}
	} );


} )( wp.customize, jQuery );
