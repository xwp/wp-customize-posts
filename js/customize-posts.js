/*global jQuery, wp, _, Backbone, _wpCustomizePostsSettings */

( function ( api, $ ) {
	var PostData, PostsCollection, self;

	/**
	 * @type {Backbone.Model}
	 */
	PostData = Backbone.Model.extend( {
		id: null,
		setting: {}, // @todo this should be settingData
		control: '', // @todo this should be controlContent

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

		api.previewer.bind( 'customize-posts', function( data ) {
			self.isPostPreview( data.isPostPreview );
			self.isSingular( data.isSingular );
			self.queriedPostId( data.queriedPostId );
			self.updateCollection( data.collection );

			// @todo When navigating in the preview, add a post edit control automatically for queried object? Suggest all posts queried in preview.
		} );

		api.bind( 'add', self.setupSettingModelSync );

	} );

	/**
	 * Encapsulation of model for Customize Section
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

			// Update the setting when the fields change
			control.container.on( 'change input propertychange', ':input[name]', function () {
				control.updateSetting();
			} );

			// Add new meta
			self.meta_field_tpl = wp.template( 'customize-posts-meta-fields' );
			control.container.on( 'click', '.add-meta', function () {
				var setting, new_fields;
				setting = control.setting();
				new_fields = $( self.meta_field_tpl( {
					post_id: setting.ID,
					id: control.generateTempMetaId(),
					key: '',
					value: '',
					is_serialized: false
				} ) );
				new_fields.hide();
				control.container
					.find( 'section.post-meta:first' )
					.find( 'ul.post-meta' )
					.append( new_fields );
				new_fields.slideDown( function () {
					new_fields.find( '.meta-key:first' ).focus();
				} );
			} );

			// Delete a meta value
			control.container.on( 'click', '.delete-meta', function () {
				var li, prev_li, next_li;

				li = $( this ).closest( 'li' );

				prev_li = li.prev( 'li' );
				next_li = li.next( 'li' );

				li.find( ':input' ).prop( 'disabled', true );

				li.find( '.meta-value' ).data( 'deleted', true );
				control.updateSetting();

				li.slideUp( function () {
					if ( next_li.length ) {
						next_li.find( ':input:first' ).focus();
					} else if ( prev_li.length ) {
						prev_li.find( ':input:last' ).focus();
					} else {
						control.container.find( 'button.add-meta' ).focus();
					}

				} );

			} );

			control.setupFeaturedImageField();

			// When updating the customizer settings, update any inserted post meta with their new IDs
			api.bind( 'save', function ( request ) {
				// See https://core.trac.wordpress.org/ticket/29098 for how we could hook directly into the saved event instead
				request.done( function ( response ) {
					if ( response && response.success && response.data.inserted_post_meta_ids ) {
						control.updateTempMetaIdsWithInsertedIds( response.data.inserted_post_meta_ids );
					}
				} );
			} );

		},

		featuredImage: null,

		setFeaturedImage: function ( id ) {
			var control = this,
				container = control.container.find( '.post-thumbnail' ),
				input = container.find( 'input.thumbnail-id' );

			id = parseInt( id, 10 );
			if ( isNaN( id ) || id < 0 ) {
				container.removeClass( 'populated' );
				input.val( '0' );
			} else {
				container.addClass( 'populated' );
				input.val( id );
			}
			control.updateSetting();
		},

		/**
		 * @todo move into separate control
		 */
		setupFeaturedImageField: function () {
			var controller,
				control = this,
				container = control.container.find( '.post-thumbnail' );

			controller = wp.media.controller.FeaturedImage.extend( {
				/**
				 * @since 3.5.0
				 */
				updateSelection: function() {
					var selection = this.get( 'selection' ),
						id = parseInt( control.setting().thumbnail_id, 10 ),
						attachment;

					if ( ! isNaN( id ) && id > 0 ) {
						attachment = wp.media.model.Attachment.get( id );
						attachment.fetch();
					}

					selection.reset( attachment ? [ attachment ] : [] );
				}
			} );

			/**
			 * wp.media.featuredImage
			 * @namespace
			 */
			control.featuredImage = {
				/**
				 * Get the featured image post ID
				 */
				get: function() {
					var id = control.setting().thumbnail_id;
					return ( isNaN( id ) || id <= 0 ) ? -1 : id;
				},

				/**
				 * Set the featured image id, save the post thumbnail data and
				 * set the HTML in the post meta box to the new featured image.
				 *
				 * @param {number} id The post ID of the featured image, or -1 to unset it.
				 */
				set: function( id ) {
					control.setFeaturedImage( id );
				},

				/**
				 * The Featured Image workflow
				 *
				 * @global wp.media.controller.FeaturedImage
				 * @global wp.media.view.l10n
				 *
				 * @this wp.media.featuredImage
				 *
				 * @returns {wp.media.view.MediaFrame.Select} A media workflow.
				 */
				frame: function() {
					if ( this._frame ) {
						return this._frame;
					}

					this._frame = wp.media( {
						state: 'featured-image',
						states: [ new controller() ]
					} );

					this._frame.on( 'toolbar:create:featured-image', function( toolbar ) {
						/**
						 * @this wp.media.view.MediaFrame.Select
						 */
						this.createSelectToolbar( toolbar, {
							text: wp.media.view.l10n.setFeaturedImage
						} );
					}, this._frame );

					this._frame.state( 'featured-image' ).on( 'select', this.select );
					return this._frame;
				},

				/**
				 * 'select' callback for Featured Image workflow, triggered when
				 *  the 'Set Featured Image' button is clicked in the media modal.
				 */
				select: function() {
					var selection = this.get( 'selection' ).single();
					if ( selection.id ) {
						container.find( 'img' ).attr( 'src', selection.get( 'sizes' ).thumbnail.url );
					}
					control.featuredImage.set( selection ? selection.id : -1 );
				},

				/**
				 * Open the content media manager to the 'featured image' tab when
				 * the post thumbnail is clicked.
				 *
				 * Update the featured image id when the 'remove' link is clicked.
				 *
				 * @global wp.media.view.settings
				 */
				init: function () {
					container.find( 'img, .select-featured-image' ).on( 'click', function () {
						control.featuredImage.frame().open();
					} );
					container.find( '.remove-featured-image' ).on( 'click', function () {
						control.setFeaturedImage( -1 );
					} );
				}
			};

			control.featuredImage.init();

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
				value = api.multidimensionalGet( control.setting(), keys );
				if ( field.val() !== value ) {
					field.val( value );
				}
			} );
		},

		/**
		 * Update the setting from the data in the fields.
		 */
		updateSetting: function () {
			var control, new_setting;
			control = this;

			new_setting = {};
			new_setting.meta = _.clone( control.setting().meta );

			new_setting.ID = control.setting().ID;
			control.container.find( '[name]' ).each( function () {
				var input, keys, value;
				input = $( this );
				keys = control.parseKeys( input.prop( 'name' ) );
				keys.shift(); // get rid of ID
				if ( input.data( 'deleted' ) ) {
					value = null;
				} else {
					value = input.val();
				}
				api.multidimensionalReplace( new_setting, keys, value );
			} );

			control.setting( new_setting );
		},

		/**
		 * Generate a temporary meta ID for a postmeta.
		 *
		 * @returns {string}
		 */
		generateTempMetaId: function () {
			return 'new' + ( new Date().valueOf() ).toString();
		},

		/**
		 * Update post meta temp IDs with their newly-inserted IDs from the DB.
		 *
		 * @param {Object} mapping
		 */
		updateTempMetaIdsWithInsertedIds: function ( mapping ) {
			var control, setting, fields;

			control = this;
			setting = control.setting();

			$.each( mapping, function ( temp_meta_id, new_meta_id ) {
				// Update setting. Note that this doesn't cause the Customizer enter a dirty state since the meta object is nested.
				if ( setting.meta[ temp_meta_id ] ) {
					setting.meta[ new_meta_id ] = setting.meta[ temp_meta_id ];
					delete setting.meta[ temp_meta_id ];
				}

				// Update fields
				// @todo It would be better technically to just apply the new setting to the template instead
				fields = control.container.find( '[id*=' + temp_meta_id + '], [name*=' + temp_meta_id + ']' );
				fields.each( function () {
					var field = $( this );
					$.each( [ 'id', 'name' ], function ( i, attr_name ) {
						if ( field.prop( attr_name ) ) {
							field.prop( attr_name, field.prop( attr_name ).replace( temp_meta_id, new_meta_id ) );
						}
					} );

				} );
			} );
		}
	} );


} )( wp.customize, jQuery );
