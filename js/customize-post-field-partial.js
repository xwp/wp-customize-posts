/* global wp */
/* eslint consistent-this: [ "error", "partial" ] */
/* eslint-disable no-magic-numbers */

(function( api, $ ) {
	'use strict';

	/**
	 * A partial representing a post field.
	 *
	 * @class
	 * @augments wp.customize.selectiveRefresh.partialConstructor.deferred
	 * @augments wp.customize.selectiveRefresh.Partial
	 * @augments wp.customize.Class
	 */
	api.selectiveRefresh.partialConstructor.post_field = api.selectiveRefresh.partialConstructor.deferred.extend({

		/**
		 * @inheritdoc
		 */
		initialize: function( id, options ) {
			var partial = this, args, matches, idPattern = /^post\[(.+?)]\[(-?\d+)]\[(.+?)](?:\[(.+?)])?$/;

			args = options || {};
			args.params = args.params || {};
			matches = id.match( idPattern );
			if ( ! matches ) {
				throw new Error( 'Bad PostFieldPartial id. Expected post[:post_type][:post_id][:field_id]' );
			}
			args.params.post_type = matches[1];
			args.params.post_id = parseInt( matches[2], 10 );
			args.params.field_id = matches[3];
			args.params.placement = matches[4] || '';

			api.selectiveRefresh.partialConstructor.deferred.prototype.initialize.call( partial, id, args );

			partial.addInstantPreviews();

			// @todo If singular_only, and this is not the post singular post for this partial, then no refresh!
		},

		/**
		 * Use JavaScript to apply approximate instant previews while waiting for selective refresh to respond.
		 *
		 * This implements for post settings what was implemented for site title and tagline in #33738,
		 * where JS-based instant previews allow for immediate feedback with a low-fidelity while waiting
		 * for a high-fidelity PHP-rendered preview.
		 *
		 * @link https://github.com/xwp/wp-customize-posts/issues/43
		 * @link https://core.trac.wordpress.org/ticket/33738
		 * @returns {void}
		 */
		addInstantPreviews: function() {
			var partial = this, settingId;
			if ( 1 !== partial.settings().length ) {
				throw new Error( 'Expected one single setting.' );
			}
			settingId = partial.settings()[0];

			// Post title.
			if ( 'post_title' === partial.params.field_id ) {
				api( settingId, function( setting ) {
					setting.bind( function( newPostData, oldPostData ) {
						if ( ! newPostData || oldPostData && newPostData.post_title === oldPostData.post_title ) {
							return;
						}
						_.each( partial.placements(), function( placement ) {
							var target = placement.container.find( '> a' );
							if ( ! target.length ) {
								target = placement.container;
							}
							target.text( newPostData.post_title );
						} );

						// Add initial support for previewing title changes in wp_list_pages().
						// @todo Add selective refresh for these.
						if ( 'page' === partial.params.post_type ) {
							$( '.page_item.page-item-' + partial.params.post_id + ':not(.menu-item) > a' ).text( newPostData.post_title );
						}
					} );
				} );
			}
		},

		/**
		 * Request the new post field partial and render it into the placements.
		 *
		 * @this {wp.customize.selectiveRefresh.Partial}
		 * @return {jQuery.Promise} Promise.
		 */
		refresh: function() {
			var partial = this, refreshPromise;

			/*
			 * Force a full refresh for post_date changes which aren't on a
			 * singular query, since this will most likely mean a change to
			 * the ordering of the posts on the page.
			 */
			if ( ! api.previewPosts.data.isSingular && -1 !== _.indexOf( api.previewPosts.data.queriedOrderbyFields, partial.params.field_id ) ) {
				api.selectiveRefresh.requestFullRefresh();
				refreshPromise = $.Deferred();
				refreshPromise.reject();
				return refreshPromise;
			}

			refreshPromise = api.selectiveRefresh.partialConstructor.deferred.prototype.refresh.call( partial );

			/*
			 * If the setting was failed validation, ensure the next change to the
			 * setting will pass the isRelatedSetting check so that the partial
			 * will be refreshed even if the related field_id wasn't just changed.
			 */
			refreshPromise.done( function() {
				partial.hadInvalidSettings = false;
				_.each( partial.settings(), function( settingId ) {
					var validityState = api.settingValidities( settingId );
					if ( validityState && true !== validityState.get() ) {
						partial.hadInvalidSettings = true;
					}
				} );
			} );

			return refreshPromise;
		},

		/**
		 * @inheritdoc
		 */
		showControl: function() {
			var partial = this, settingId = partial.params.primarySetting;
			if ( ! settingId ) {
				settingId = _.first( partial.settings() );
			}
			api.preview.send( 'focus-control', settingId + '[' + partial.params.field_id + ']' );
		},

		/**
		 * @inheritdoc
		 */
		isRelatedSetting: function( setting, newValue, oldValue ) {
			var partial = this;
			if ( ! partial.hadInvalidSettings && _.isObject( newValue ) && _.isObject( oldValue ) && partial.params.field_id && newValue[ partial.params.field_id ] === oldValue[ partial.params.field_id ] ) {
				return false;
			}
			return api.selectiveRefresh.partialConstructor.deferred.prototype.isRelatedSetting.call( partial, setting, newValue, oldValue );
		}

	});

})( wp.customize, jQuery );
