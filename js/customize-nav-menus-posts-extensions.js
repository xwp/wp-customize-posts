(function( api, $ ) {
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
				var title, settingValue;
				if ( ! oldPostData || newPostData.post_title !== oldPostData.post_title ) {
					title = $.trim( newPostData.post_title ) || api.Posts.data.l10n.noTitle;
				}
				control.container.find( '.menu-item-actions > .link-to-original > .original-link' ).text( title );
				control.container.find( '.edit-menu-item-title:first' ).attr( 'placeholder', title );

				// Change original_title without triggering setting change since it's a readonly value.
				settingValue = _.clone( control.setting.get() );
				settingValue.original_title = newPostData.post_title;
				control.setting.set( settingValue );
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
					component.syncOriginalItemTitle( control );
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

	api.control.each( component.extendNavMenuItemOriginalObjectReference );
	api.control.bind( 'add', component.extendNavMenuItemOriginalObjectReference );

	return component;
})( wp.customize, jQuery );
