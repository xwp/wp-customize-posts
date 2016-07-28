/* global jQuery, wp, _, _wpCustomizePostsExports, console */
/* eslint no-magic-numbers: [ "error", { "ignore": [0,1,2,3,4,5,7,89,10,11,12,23,28,29,30,31,59,9999] } ], consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	var component;

	if ( ! api.Posts ) {
		api.Posts = {};
	}

	component = api.Posts;

	component.data = {
		postTypes: {},
		l10n: {
			sectionCustomizeActionTpl: '',
			fieldTitleLabel: '',
			fieldContentLabel: '',
			fieldExcerptLabel: ''
		},
		gmtOffset: 0,
		postIdInput: null
	};

	component.fetchedPosts = {};

	if ( 'undefined' !== typeof _wpCustomizePostsExports ) {
		_.extend( component.data, _wpCustomizePostsExports );
	}

	api.panelConstructor.posts = component.PostsPanel;
	api.sectionConstructor.post = component.PostSection;

	api.controlConstructor.post_discussion_fields = api.controlConstructor.dynamic.extend({
		initialize: function( id, args ) {
			args.params.type = 'post_discussion_fields';
			args.params.field_type = 'checkbox';
			api.controlConstructor.dynamic.prototype.initialize.call( this, id, args );
		}
	});

	/**
	 * Post Date control extension of Dynamic Control.
	 *
	 * This updates both the post_date and post_date_gmt.
	 *
	 * @todo update Post Status as appropriate.
	 */
	api.controlConstructor.post_date = api.controlConstructor.dynamic.extend({
		/**
		 * Add bidirectional data binding links between inputs and the setting properties.
		 *
		 * @private
		 */
		_setUpSettingPropertyLinks: function() {

			var control = this,
				nodes,
				inputs,
				newPostDate,
				newPostDateGmt,
				newPostStatus,
				postData,
				originalPostStatus,
				currentPostStatus,
				newDate;

			if ( ! control.setting ) {
				return;
			}

			inputs = control.container.find( '.date-input' );
			newPostDate = control.container.find( '.post-date' );
			newPostDateGmt = control.container.find( '.post-date-gmt' );
			newPostStatus = control.container.find( '.post-status' );

			// This will update post_date, post_date_gmt, and post_status.
			nodes = {
				post_date: newPostDate,
				post_date_gmt: newPostDateGmt,
				post_status: newPostStatus
			};

			postData = _.clone( control.setting.get() );
			currentPostStatus = originalPostStatus = postData.post_status;

			watchInputs();

			/**
			 * Return an array of Date pieces.
			 *
			 * "Pieces" here refers to each part of the date,
			 * (e.g., "month," "day," "year," etc.).
			 *
			 * @returns {object} Object of date pieces.
			 */
			function getValidDateInputs() {
				var result = {}, month, monthInt, day, year, hour, min, monthMax, febMax;
				month = control.container.find( '.date-input.month' );
				day = control.container.find( '.date-input.day' );
				year = control.container.find( '.date-input.year' );
				hour = control.container.find( '.date-input.hour' );
				min = control.container.find( '.date-input.min' );

				month.removeClass( 'error' );
				day.removeClass( 'error' );
				year.removeClass( 'error' );
				hour.removeClass( 'error' );
				min.removeClass( 'error' );

				result.month = month.val();
				monthInt = parseInt( result.month, 10 );
				result.monthIndex = monthInt - 1;
				result.day = day.val();
				result.year = year.val();
				result.hour = hour.val();
				result.min = min.val();

				// Using validateRange to check if result.year is a number.
				if ( 4 !== result.year.length || ! validateRange( result.year, 0, 9999 ) ) {
					year.addClass( 'error' );
					return false;
				}

				if ( ! validateRange( result.hour, 0, 23 ) ) {
					hour.addClass( 'error' );
					return false;
				}

				if ( ! validateRange( result.min, 0, 59 ) ) {
					min.addClass( 'error' );
					return false;
				}

				febMax = 0 === result.year % 4 ? 29 : 28;
				monthMax = 30;
				if ( 1 === monthInt ||
					3 === monthInt ||
					5 === monthInt ||
					7 === monthInt ||
					8 === monthInt ||
					10 === monthInt ||
					12 === monthInt ) {
					monthMax = 31;
				}

				if ( ! validateRange( result.day, 1, monthMax ) ) {
					day.addClass( 'error' );
					return false;
				} else if ( 2 === monthInt ) {
					if ( ! validateRange( result.day, 1, febMax ) ) {
						day.addClass( 'error' );
						return false;
					}
				}

				return result;
			}

			/**
			 * Format a Date Object.
			 *
			 * Returns 'Y-m-d H:i:00' format.
			 *
			 * @param {object} dateObj A Date object.
			 *
			 * @returns {string} A formatted date String.
			 */
			function getFormattedDate( dateObj ) {
				var year, month, day, hour, min;
				year = dateObj.getFullYear();
				month = ( dateObj.getMonth() < 9 ? '0' : '' ) + ( dateObj.getMonth() + 1 );
				day = ( dateObj.getDate() < 10 ? '0' : '' ) + dateObj.getDate();
				hour = ( dateObj.getHours() < 10 ? '0' : '' ) + dateObj.getHours();
				min = ( dateObj.getMinutes() < 10 ? '0' : '' ) + dateObj.getMinutes();
				return year + '-' + month + '-' + day + ' ' + hour + ':' + min + ':00';
			}

			/**
			 * Check if a number is between two others.
			 *
			 * @param {number} value Input value.
			 * @param {number} min Minimum value.
			 * @param {number} max Maximum value.
			 * @returns {boolean} If in range.
			 */
			function validateRange( value, min, max ) {
				if ( isNaN( value ) ) {
					return false;
				}
				return min <= value && max >= value;
			}

			/**
			 * Watch our inputs.
			 *
			 * When a date input is updated, update the
			 * hidden input values, then trigger change.
			 *
			 * Wrapping this in a function
			 * to prevent _setUpSettingPropertyLinks()
			 * from possibly returning false.
			 *
			 * @returns {bool} If the date inputs are invalid.
			 */
			function watchInputs() {
				inputs.change( function() {
					var dateInputs = getValidDateInputs();

					if ( false === dateInputs ) {
						return false;
					}

					newDate = new Date(
						dateInputs.year,
						dateInputs.monthIndex,
						dateInputs.day,
						dateInputs.hour,
						dateInputs.min
					);
					newPostDate.val( getFormattedDate( newDate ) ).trigger( 'change' );

					// Convert the newDate to GMT using WP's gmt_offset option.
					newDate.setUTCHours( newDate.getUTCHours() - parseFloat( api.Posts.data.gmtOffset ) );
					newPostDateGmt.val( getFormattedDate( newDate )  ).trigger( 'change' );

					updatePostStatus();
				});
			}

			/**
			 * Get the current GMT time.
			 *
			 * Fetches browser local "now" time, then
			 * converts it to a Date object, offset to GMT.
			 * This can then be compared to newDate using simple
			 * arithmetic operators.
			 *
			 * Used when updating the Post Status.
			 *
			 * @returns {object} Date object.
			 */
			function getGmtNow() {
				var browserNow = new Date();
				return new Date( browserNow.getUTCFullYear(), browserNow.getUTCMonth(), browserNow.getUTCDate(),  browserNow.getUTCHours(), browserNow.getUTCMinutes(), browserNow.getUTCSeconds() );
			}

			/**
			 * Update the post status.
			 *
			 * Updating the Post Status control dropdown automatically
			 * updates this Post Date control in the background. So
			 * that input can override all the below if it is
			 * updated after the date is changed.
			 *
			 * Logic:
			 *  - If we start with 'draft,' do nothing.
			 *  - If we start with 'pending,' do nothing.
			 *  - If we start with 'future' (Scheduled)...
			 *     - If new date is in the past, set to 'publish'.
			 *  - If we start with 'publish'...
			 *     - If new date is in the future, set to 'future'.
			 *
			 * @todo handle 'private.'
			 *
			 * @todo update the visible Post Status control when Post Date is changed.
			 * (It does update once the preview is Saved.)
			 *
			 * @todo
			 * Dynamically remove "Scheduled" and "Published" options from Post Status
			 * dropdown if they are not logically allowed by the new date,
			 * just like the post edit screen UI.
			 *
			 * Otherwise, we can end up with a Scheduled post status and a
			 * published date in the past.
			 */
			function updatePostStatus() {
				var gmtNow = getGmtNow();

				// If the date is in the future, and it is currently published, schedule it.
				if ( newDate > gmtNow && 'publish' === originalPostStatus ) {
					newPostStatus.val( 'future' );
					currentPostStatus = 'future';

					// If we originally had 'future', and now the date is in the past, set it to publish.
				} else if ( newDate <= gmtNow && 'future' === originalPostStatus ) {
					newPostStatus.val( 'publish' );
					currentPostStatus = 'publish';

					/*
					 * If we start out with a draft or pending (for instance), ensure
					 * we stay on that status.
					 *
					 * This also covers when we start with publish, then move the date to future,
					 * then move the back to the past.
					 */
				} else if ( newDate <= gmtNow && currentPostStatus !== originalPostStatus ) {
					newPostStatus.val( originalPostStatus );
					currentPostStatus = originalPostStatus;
				}
			}

			// Set the values.
			_.each( nodes, function( el ) {
				var node = $( el ),
					element,
					propertyName = node.data( 'customizeSettingPropertyLink' );

				element = new api.Element( node );
				control.propertyElements.push( element );
				element.set( control.setting()[ propertyName ] );

				// Saves the setting
				element.bind( function( newPropertyValue ) {
					var newSetting = control.setting();
					if ( newPropertyValue === newSetting[ propertyName ] ) {
						return;
					}
					newSetting = _.clone( newSetting );
					newSetting[ propertyName ] = newPropertyValue;
					control.setting.set( newSetting );
				} );
				control.setting.bind( function( newValue ) {
					if ( newValue[ propertyName ] !== element.get() ) {
						element.set( newValue[ propertyName ] );
					}
				} );
			});
		}
	});

	/*
	 * Create initial post type-specific constructors for panel and sections.
	 * Note plugins can override the panel and section constructors by making customize-posts a script dependency.
	 */
	_.each( component.data.postTypes, function( postType ) {
		var panelType, sectionType;
		panelType = 'posts[' + postType.name + ']';
		if ( ! api.panelConstructor[ panelType ] ) {
			api.panelConstructor[ panelType ] = api.panelConstructor.posts.extend({
				postType: postType
			});
		}
		sectionType = 'post[' + postType.name + ']';
		if ( ! api.sectionConstructor[ sectionType ] ) {
			api.sectionConstructor[ sectionType ] = api.sectionConstructor.post.extend({
				postType: postType
			});
		}
	} );

	/**
	 * Parse post/postmeta setting ID.
	 *
	 * @param {string} settingId Setting ID.
	 * @returns {object|null} Parsed setting or null if error.
	 */
	component.parseSettingId = function parseSettingId( settingId ) {
		var parsed = {}, idParts;
		idParts = settingId.replace( /]/g, '' ).split( '[' );
		if ( 'post' !== idParts[0] && 'postmeta' !== idParts[0] ) {
			return null;
		}
		parsed.settingType = idParts[0];
		if ( 'post' === parsed.settingType && 3 !== idParts.length || 'postmeta' === parsed.settingType && 4 !== idParts.length ) {
			return null;
		}

		parsed.postType = idParts[1];
		if ( ! parsed.postType ) {
			return null;
		}

		parsed.postId = parseInt( idParts[2], 10 );
		if ( isNaN( parsed.postId ) || parsed.postId <= 0 ) {
			return null;
		}

		if ( 'postmeta' === parsed.settingType ) {
			parsed.metaKey = idParts[3];
			if ( ! parsed.metaKey ) {
				return null;
			}
		}
		return parsed;
	};

	/**
	 * Get the post preview URL.
	 *
	 * @param {object} params - Parameters to configure the preview URL.
	 * @param {number} params.post_id - Post ID to preview.
	 * @param {string} [params.post_type] - Post type to preview.
	 * @return {string} Preview URL.
	 */
	component.getPreviewUrl = function( params ) {
		var url = api.settings.url.home, args = {};

		if ( ! params || ! params.post_id ) {
			throw new Error( 'Missing params' );
		}

		args.preview = true;
		if ( 'page' === params.post_type ) {
			args.page_id = params.post_id;
		} else {
			args.p = params.post_id;
			if ( params.post_type && 'post' !== params.post_type ) {
				args.post_type = params.post_type;
			}
		}

		return url + '?' + $.param( args );
	};

	/**
	 * Insert a new stubbed `auto-draft` post.
	 *
	 * @param {string} postType Post type to create.
	 * @return {jQuery.promise} Promise resolved with the added section.
	 */
	component.insertAutoDraftPost = function( postType ) {
		var request, deferred = $.Deferred(), done;

		request = wp.ajax.post( 'customize-posts-insert-auto-draft', {
			'customize-posts-nonce': api.settings.nonce['customize-posts'],
			'wp_customize': 'on',
			'post_type': postType
		} );

		/**
		 * Done inserting auto-draft post.
		 *
		 * @param {object} data Data.
		 * @param {int}    data.postId Post ID.
		 * @param {string} data.postSettingId Post setting ID.
		 * @param {object} data.settings Setting, mapping setting IDs to setting params for posts/postmeta.
		 * @returns {void}
		 */
		done = function doneInsertAutoDraftPost( data ) {
			var section;
			component.addPostSettings( data.settings );

			if ( ! data.postSettingId || ! api.has( data.postSettingId ) ) {
				deferred.reject( 'no_setting' );
				return;
			}

			section = component.addPostSection( data.postSettingId );
			if ( ! section ) {
				deferred.reject( 'no_section' );
				return;
			}

			deferred.resolve( {
				postId: data.postId,
				section: section,
				setting: api( data.postSettingId )
			} );
		};

		request.done( done );
		request.fail( function( response ) {
			var error = response || '';

			if ( 'undefined' !== typeof response.message ) {
				error = response.message;
			}

			console.error( error );
			deferred.reject( error );
		} );

		return deferred.promise();
	};

	/**
	 * Handle receiving customized-posts messages from the preview.
	 *
	 * @param {object} data Data from preview.
	 * @return {void}
	 */
	component.receivePreviewData = function receivePreviewData( data ) {
		var postIds;
		component.previewedQuery.set( data );
		postIds = component.previewedQuery.get().postIds;
		if ( postIds.length > 0 ) {
			component.ensurePosts( postIds );
		}
	};

	/**
	 * Gather posts data.
	 *
	 * @param {int[]} postIds Post IDs.
	 * @returns {{}} Mapping of post ID to relevant data about the post.
	 */
	component.gatherFetchedPostsData = function gatherFetchedPostsData( postIds ) {
		var postsData = {};
		_.each( postIds, function( postId ) {
			var postType, postData, id;
			postType = component.fetchedPosts[ postId ];
			if ( postType ) {
				id = 'post[' + postType + '][' + String( postId ) + ']';
				postData = {
					postType: postType,
					customizeId: id,
					section: api.section( id ),
					setting: api( id )
				};
			} else {
				postData = null;
			}
			postsData[ postId ] = postData;
		} );
		return postsData;
	};

	/**
	 * Fetch settings for posts and ensure sections are added for the given post IDs.
	 *
	 * @param {int[]} postIds Post IDs.
	 * @returns {jQuery.promise} Promise resolved with an object mapping ids to setting and section.
	 */
	component.ensurePosts = function ensurePosts( postIds ) {
		var request, deferred = $.Deferred(), newPostIds;

		newPostIds = _.filter( postIds, function( postId ) {
			return ! component.fetchedPosts[ postId ];
		} );
		if ( 0 === newPostIds.length ) {
			deferred.resolve( component.gatherFetchedPostsData( postIds ) );
			return deferred;
		}

		request = wp.ajax.post( 'customize-posts-fetch-settings', {
			'customize-posts-nonce': api.settings.nonce['customize-posts'],
			'wp_customize': 'on',
			'post_ids': newPostIds
		} );

		request.done( function( settings ) {
			component.addPostSettings( settings );

			_.each( settings, function( settingParams, settingId ) {
				if ( 'post' === settingParams.type ) {
					component.addPostSection( settingId );
				}
			} );

			deferred.resolve( component.gatherFetchedPostsData( postIds ) );
		} );
		request.fail( function() {
			deferred.reject();
		} );

		return deferred.promise();
	};

	/**
	 * Add post settings.
	 *
	 * @param {object} settings Mapping of setting IDs to setting params for posts and postmeta.
	 * @returns {int[]} Post IDs for added settings.
	 */
	component.addPostSettings = function addPostSettings( settings ) {
		var postIds = [];
		_.each( settings, function( settingArgs, id ) {
			var setting, parsedSettingId = component.parseSettingId( id );
			if ( ! parsedSettingId ) {
				return;
			}
			postIds.push( parsedSettingId.postId );
			component.fetchedPosts[ parsedSettingId.postId ] = parsedSettingId.postType;

			setting = api( id );
			if ( ! setting ) {
				setting = api.create( id, id, settingArgs.value, {
					transport: settingArgs.transport,
					previewer: api.previewer
				} );

				/*
				 * Ensure that the setting gets created in the preview as well. When the post/postmeta settings
				 * are sent to the preview, this is the point at which the related selective refresh partials
				 * will also be created.
				 */
				api.previewer.send( 'customize-posts-setting', _.extend( { id: id }, settingArgs ) );
			}
		} );
		return _.unique( postIds );
	};

	/**
	 * Add a section for a post.
	 *
	 * @param {string} settingId - Setting ID for post.
	 * @return {wp.customize.Section|null} Added (or existing) section, or null if not able to be added.
	 */
	component.addPostSection = function( settingId ) {
		var section, parsedSettingId, sectionId, panelId, sectionType, Constructor, htmlParser, postTypeObj;
		parsedSettingId = component.parseSettingId( settingId );
		if ( ! parsedSettingId ) {
			throw new Error( 'Bad setting ID' );
		}
		postTypeObj = component.data.postTypes[ parsedSettingId.postType ];

		if ( ! postTypeObj ) {
			if ( 'undefined' !== typeof console && console.error ) {
				console.error( 'Unrecognized post type: ' + parsedSettingId.postType );
			}
			return null;
		}
		if ( ! postTypeObj.show_in_customizer ) {
			return null;
		}

		sectionType = 'post[' + parsedSettingId.postType + ']';
		panelId = 'posts[' + parsedSettingId.postType + ']';
		sectionId = 'post[' + parsedSettingId.postType + '][' + String( parsedSettingId.postId ) + ']';

		if ( api.section.has( sectionId ) ) {
			return api.section( sectionId );
		}

		Constructor = api.sectionConstructor[ sectionType ] || api.sectionConstructor.post;

		htmlParser = $( '<div>' ).html( component.data.l10n.sectionCustomizeActionTpl.replace( '%s', api.panel( panelId ).params.title ) );
		section = new Constructor( sectionId, {
			params: {
				id: sectionId,
				panel: panelId,
				post_type: parsedSettingId.postType,
				post_id: parsedSettingId.postId,
				active: true,
				customizeAction: htmlParser.text()
			}
		});
		api.section.add( sectionId, section );

		return section;
	};

	/**
	 * Emulate sanitize_title_with_dashes().
	 *
	 * @todo This can be more verbose, supporting Unicode.
	 *
	 * @param {string} title Title
	 * @returns {string} slug
	 */
	component.sanitizeTitleWithDashes = function sanitizeTitleWithDashes( title ) {
		var slug = $.trim( title ).toLowerCase();
		slug = slug.replace( /[^a-z0-9\-_]+/g, '-' );
		slug = slug.replace( /--+/g, '-' );
		slug = slug.replace( /^-+|-+$/g, '' );
		return slug;
	};

	/**
	 * Handle purging the trash after Customize `saved`.
	 *
	 * @returns {void}
	 */
	component.purgeTrash = function purgeTrash() {
		api.section.each( function( section ) {
			if ( section.extended( component.PostSection ) && 'trash' === api( section.id ).get().post_status ) {
				api.section.remove( section.id );
				section.active.set( false );
				section.collapse();
				section.container.remove();
				if ( true === component.previewedQuery.get().isSingular ) {
					api.previewer.previewUrl( api.settings.url.home );
				}
			}
		} );
	};

	/**
	 * Update settings quietly.
	 *
	 * Update all of the settings without causing the overall dirty state to change.
	 *
	 * This was originally part of the Customize Setting Validation plugin.
	 *
	 * @link https://github.com/xwp/wp-customize-setting-validation/blob/2e5ddc66a870ad7b1aee5f8e414bad4b78e120d2/js/customize-setting-validation.js#L186-L209
	 *
	 * @param {object} settingValues Setting IDs mapped to values.
	 * @return {void}
	 */
	component.updateSettingsQuietly = function updateSettingsQuietly( settingValues ) {
		var wasSaved = api.state( 'saved' ).get();
		_.each( settingValues, function( value, settingId ) {
			var setting = api( settingId ), wasDirty;
			if ( setting && ! _.isEqual( setting.get(), value ) ) {
				wasDirty = setting._dirty;
				setting.set( value );
				setting._dirty = wasDirty;
			}
		} );
		api.state( 'saved' ).set( wasSaved );
	};

	api.bind( 'ready', function() {

		// Add a post_ID input for editor integrations (like Shortcake) to be able to know the post being edited.
		component.postIdInput = $( '<input type="hidden" id="post_ID" name="post_ID">' );
		$( 'body' ).append( component.postIdInput );

		component.previewedQuery = new api.Value();
		component.previewedQuery.validate = function( query ) {
			var mergedQuery = _.extend(
				{
					isSingular: false,
					isPostPreview: false,
					queriedPostId: 0,
					postIds: []
				},
				query
			);
			return mergedQuery;
		};
		component.previewedQuery.set( {} );

		api.previewer.bind( 'customized-posts', component.receivePreviewData );

		// Purge trashed posts and update client settings with saved values from server.
		api.bind( 'saved', function( data ) {
			if ( data.saved_post_setting_values ) {
				component.updateSettingsQuietly( data.saved_post_setting_values );
			}

			component.purgeTrash();
		} );

		/**
		 * Ensure a post is added to the Customizer and focus on its section when an edit post link is clicked in preview.
		 */
		api.previewer.bind( 'edit-post', function( postId ) {
			var ensuredPromise = api.Posts.ensurePosts( [ postId ] );
			ensuredPromise.done( function( postsData ) {
				var postData = postsData[ postId ];
				if ( postData ) {
					postData.section.focus();
				}
			} );
		} );

		/**
		 * Focus on the control requested from the preview.
		 *
		 * If the control doesn't exist yet, try to determine the section it would
		 * be part of by parsing its ID, and then if that section exists, expand it.
		 * Once expanded, try finding the control again, since controls for post
		 * sections may get embedded only once section.contentsEmbedded is resolved.
		 *
		 * @param {string} controlId Control ID.
		 * @return {void}
		 */
		function focusControl( controlId ) {
			var control, section, postSectionId, matches;

			/**
			 * Attempt focus on the control.
			 *
			 * @returns {boolean} Whether the control exists.
			 */
			function tryFocus() {
				control = api.control( controlId );
				if ( control ) {
					control.focus();
					return true;
				}
				return false;
			}
			if ( tryFocus() ) {
				return;
			}

			matches = controlId.match( /^post(?:meta)?\[(.+?)]\[(\d+)]/ );
			if ( ! matches ) {
				return;
			}
			postSectionId = 'post[' + matches[1] + '][' + matches[2] + ']';
			section = api.section( postSectionId );
			if ( ! section || ! section.extended( component.PostSection ) ) {
				return;
			}
			section.expand();
			section.contentsEmbedded.done( function() {
				var ms = 500;

				// @todo It is not clear why a delay is needed for focus to work. It could be due to focus failing during animation.
				_.delay( tryFocus, ms );
			} );
		}

		component.focusControl = focusControl;
		api.previewer.bind( 'focus-control', component.focusControl );
	} );

})( wp.customize, jQuery );
