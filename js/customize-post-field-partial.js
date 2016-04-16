/* global wp */
/* eslint consistent-this: [ "error", "partial" ] */

(function( api ) {
	'use strict';

	if ( ! api.previewPosts ) {
		api.previewPosts = {};
	}

	/**
	 * A partial representing a post field.
	 *
	 * @class
	 * @augments wp.customize.selectiveRefresh.Partial
	 * @augments wp.customize.Class
	 */
	api.previewPosts.PostFieldPartial = api.selectiveRefresh.Partial.extend({

		/**
		 * @inheritdoc
		 */
		initialize: function( id, options ) {
			var partial = this, args, matches, baseSelector, idPattern = /^post\[(.+?)]\[(-?\d+)]\[(.+?)](?:\[(.+?)])?$/;

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

			if ( ! args.params.selector ) {
				baseSelector = '.hentry.post-' + String( args.params.post_id ) + '.type-' + args.params.post_type;
				if ( 'post_title' === args.params.field_id ) {
					args.params.selector = baseSelector + ' .entry-title';
				} else if ( 'post_content' === args.params.field_id ) {
					args.params.selector = baseSelector + ' .entry-content';
				} else if ( 'post_excerpt' === args.params.field_id ) {
					args.params.selector = baseSelector + ' .entry-summary';
				} else if ( 'comment_status' === args.params.field_id ) {
					if ( 'comments-area' === args.params.placement ) {
						args.params.selector = baseSelector + ' .comments-area';
					} else if ( 'comments-link' === args.params.placement ) {
						args.params.selector = baseSelector + ' .comments-link';
					}
				} else if ( 'ping_status' === args.params.field_id ) {
					args.params.selector = baseSelector + ' .comments-area';
				} else if ( 'post_author' === args.params.field_id ) {
					if ( 'author-bio' === args.params.placement ) {
						args.params.selector = baseSelector + ' .author-info';
					} else if ( 'byline' === args.params.placement ) {
						args.params.selector = baseSelector + ' .byline a.fn';
					}
				}
			}
			api.selectiveRefresh.Partial.prototype.initialize.call( partial, id, args );
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
			if ( _.isObject( newValue ) && _.isObject( oldValue ) && partial.params.field_id && newValue[ partial.params.field_id ] === oldValue[ partial.params.field_id ] ) {
				return false;
			}
			return api.selectiveRefresh.Partial.prototype.isRelatedSetting.call( partial, setting );
		}

	});

	api.selectiveRefresh.partialConstructor.post_field = api.previewPosts.PostFieldPartial;

})( wp.customize );
