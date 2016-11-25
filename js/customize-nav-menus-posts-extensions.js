/* global wp, jQuery */

wp.customize.Posts.NavMenusExtensions = (function( api, $ ) {
	'use strict';

	var component = {};

	if ( api.Menus.insertAutoDraftPost ) {

		/**
		 * Insert a new `auto-draft` post.
		 *
		 * @param {object} params - Parameters for the draft post to create.
		 * @param {string} params.post_type - Post type to add.
		 * @param {string} params.post_title - Post title to use.
		 * @return {jQuery.promise} Promise resolved with the added post.
		 */
		api.Menus.insertAutoDraftPost = function insertAutoDraftPost( params ) {

			var insertPromise, deferred = $.Deferred();

			insertPromise = api.Posts.insertAutoDraftPost( params.post_type );

			insertPromise.done( function insertAutoDraftPostDone( data ) {
				var postData;

				postData = _.clone( data.setting.get() );
				postData.post_title = params.post_title;
				postData.post_status = 'publish';
				data.setting.set( postData );

				deferred.resolve( {
					post_id: data.postId,
					url: api.Posts.getPostUrl( { post_id: data.postId, post_type: params.post_type } )
				} );
			} );
			insertPromise.fail( function insertAutoDraftPostFail( failure ) {
				deferred.reject( failure );
			} );

			return deferred.promise();
		};
	}

	/**
	 * Add an edit post button to the nav menu control.
	 *
	 * @param {wp.customize.Control} control Control.
	 * @returns {void}
	 */
	component.addEditPostButton = function addEditPostButton( control ) {
		var postTypeObj, editButton, navMenuItem = control.setting.get();
		if ( 'post_type' !== navMenuItem.type || ! navMenuItem.object_id ) {
			return;
		}
		postTypeObj = api.Posts.data.postTypes[ navMenuItem.object ];
		if ( ! postTypeObj || ! postTypeObj.current_user_can.edit_published_posts ) {
			return;
		}

		editButton = $( wp.template( 'customize-posts-edit-nav-menu-item-original-object' )( { editItemLabel: postTypeObj.labels.edit_item } ) );
		editButton.on( 'click', function onClickEditButton() {
			api.Posts.startEditPostFlow( {
				postId: navMenuItem.object_id,
				initiatingButton: editButton,
				originatingConstruct: control,
				restorePreviousUrl: true,
				returnToOriginatingConstruct: true
			} );
		} );
		control.container.find( '.menu-item-actions > .link-to-original' ).append( editButton );
	};

	/**
	 * Sync original item title when the post title changes.
	 *
	 * @param {wp.customize.Control} control Control.
	 * @returns {void}
	 */
	component.syncOriginalItemTitle = function syncOriginalItemTitle( control ) {
		var postTypeObj, settingId, navMenuItem = control.setting.get();
		if ( 'post_type' !== navMenuItem.type || ! navMenuItem.object_id ) {
			return;
		}
		postTypeObj = api.Posts.data.postTypes[ navMenuItem.object ];
		if ( ! postTypeObj ) {
			return;
		}

		settingId = 'post[' + String( navMenuItem.object ) + '][' + String( navMenuItem.object_id ) + ']';
		api( settingId, function( postSetting ) {
			var setOriginalLinkTitle = function( newPostData, oldPostData ) {
				var title, settingValue, titleEl, titleText;
				if ( ! oldPostData || newPostData.post_title !== oldPostData.post_title ) {
					title = $.trim( newPostData.post_title ) || api.Posts.data.l10n.noTitle;
				}

				if ( 'resolved' === control.deferred.embedded.state() ) {
					control.container.find( '.menu-item-actions > .link-to-original > .original-link' ).text( title );
					control.container.find( '.edit-menu-item-title:first' ).attr( 'placeholder', title );
				}

				// Skip nav menu items that are deleted.
				if ( false === control.setting.get() ) {
					return;
				}

				// Update original_title.
				settingValue = _.clone( control.setting.get() );
				settingValue.original_title = newPostData.post_title;
				control.setting._value = settingValue; // Set quietly since the original_title is readonly setting property anyway.
				control.setting.preview();

				// The following is adapted from wp.customize.Menus.MenuItemControl.prototype._setupTitleUI():
				titleEl = control.container.find( '.menu-item-title' );
				titleText = settingValue.title || settingValue.original_title || api.Menus.data.l10n.untitled;

				if ( settingValue._invalid ) {
					titleText = api.Menus.data.l10n.invalidTitleTpl.replace( '%s', titleText );
				}

				// Don't update to an empty title.
				if ( settingValue.title || settingValue.original_title ) {
					titleEl
						.text( titleText )
						.removeClass( 'no-title' );
				} else {
					titleEl
						.text( titleText )
						.addClass( 'no-title' );
				}
			};
			postSetting.bind( setOriginalLinkTitle );
			setOriginalLinkTitle( postSetting.get(), null );
		} );
	};

	/**
	 * Inject edit post button into the nav menu item controls.
	 *
	 * @param {wp.customize.Control} control Control.
	 * @returns {void}
	 */
	component.extendNavMenuItemOriginalObjectReference = function extendNavMenuItemOriginalObjectReference( control ) {
		var onceExpanded;
		if ( control.extended( api.Menus.MenuItemControl ) ) {

			component.syncOriginalItemTitle( control );

			/**
			 * Trigger once expanded.
			 *
			 * @param {Boolean} expanded Whether expanded.
			 * @returns {void}
			 */
			onceExpanded = function onceExpandedFn( expanded ) {
				if ( expanded ) {
					control.expanded.unbind( onceExpanded );
					component.addEditPostButton( control );
				}
			};

			control.deferred.embedded.done( function onDoneEmbedded() {
				if ( control.expanded.get() ) {
					onceExpanded();
				} else {
					control.expanded.bind( onceExpanded );
				}
			} );
		}
	};

	/**
	 * Update available menu items to match a changing post title.
	 *
	 * @param {wp.customize.Setting} setting Changed setting.
	 * @returns {void}
	 */
	component.watchPostSettingChanges = function watchPostSettingChanges( setting ) {
		var idParts, postId, menuItemTitle, newTitle, availableItem, availableItemId, idPrefix, menuItemTpl;
		idPrefix = 'post[';
		if ( idPrefix !== setting.id.substr( 0, idPrefix.length ) ) {
			return;
		}
		idParts = setting.id.substr( idPrefix.length ).replace( /]/g, '' ).split( '[' );
		postId = parseInt( idParts[1], 10 );
		if ( ! postId ) {
			return;
		}

		newTitle = setting.get().post_title || api.Posts.data.l10n.noTitle;
		availableItemId = 'post-' + String( postId );
		menuItemTpl = $( '#menu-item-tpl-' + availableItemId );
		if ( menuItemTpl.length ) {
			menuItemTitle = menuItemTpl.find( '.menu-item-title:first' );
			if ( $.trim( newTitle ) !== $.trim( menuItemTitle.text() ) ) {
				menuItemTitle.text( newTitle );
			}

			// Ensure the available nav menu item is shown/hidden based on whether
			if ( 'publish' !== setting.get().post_status && menuItemTpl.is( ':visible' ) ) {
				menuItemTpl.hide();
			} else if ( 'publish' === setting.get().post_status && ! menuItemTpl.is( ':visible' ) ) {
				menuItemTpl.show();
			}
		}

		availableItem = api.Menus.availableMenuItemsPanel.collection.get( availableItemId );
		if ( availableItem ) {
			availableItem.set( 'title', newTitle );
		}
	};

	/**
	 * Rewrite Ajax requests to inject Customizer state.
	 *
	 * @todo Remove this once 4.7 is the minimum requirement.
	 *
	 * @param {object} options Options.
	 * @param {string} options.type Type.
	 * @param {string} options.url URL.
	 * @returns {void}
	 */
	component.ajaxPrefilterAvailableNavMenuItemRequests = function ajaxPrefilterAvailableNavMenuItemRequests( options ) {
		var urlParser;

		if ( 'POST' !== options.type.toUpperCase() ) {
			return;
		}

		urlParser = document.createElement( 'a' );
		urlParser.href = options.url;

		// Ensure an admin ajax request.
		if ( ! /wp-admin\/admin-ajax\.php$/.test( urlParser.pathname ) ) {
			return;
		}

		// Ensure a request to search or load available nav menu items.
		if ( ! /(^|&)action=(search-available-menu-items-customizer|load-available-menu-items-customizer)(&|$)/.test( options.data ) ) {
			return;
		}

		// Add Customizer state.
		options.data += '&';
		options.data += $.param( { customized: api.previewer.query().customized } );
	};

	api.bind( 'ready', function() {
		api.control.each( component.extendNavMenuItemOriginalObjectReference );
		api.control.bind( 'add', component.extendNavMenuItemOriginalObjectReference );
		api.bind( 'change', component.watchPostSettingChanges );

		// Feature detect WP 4.7, only prefilter available item requests if WP<4.7.
		if ( ! api.requestChangesetUpdate ) {
			$.ajaxPrefilter( component.ajaxPrefilterAvailableNavMenuItemRequests );
		}
	} );

	return component;
})( wp.customize, jQuery );
