/* global wp */
/* eslint consistent-this: [ "error", "partial" ] */
/* eslint-disable no-magic-numbers */

(function( api ) {
	'use strict';

	if ( ! api.previewPosts ) {
		api.previewPosts = {};
	}

	/**
	 * A partial representing a post field.
	 *
	 * @class
	 * @augments wp.customize.previewPosts.DeferredPartial
	 * @augments wp.customize.selectiveRefresh.Partial
	 * @augments wp.customize.Class
	 */
	api.previewPosts.PostFieldPartial = api.previewPosts.DeferredPartial.extend({

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

			api.previewPosts.DeferredPartial.prototype.initialize.call( partial, id, args );

			partial.addInstantPreviews();
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
					setting.bind( function( postData ) {
						if ( ! postData || ! _.isString( postData.post_title ) ) {
							return;
						}
						_.each( partial.placements(), function( placement ) {
							var target = placement.container.find( '> a' );
							if ( ! target.length ) {
								target = placement.container;
							}
							target.text( postData.post_title );
						} );
					} );
				} );
			}
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
			return api.previewPosts.DeferredPartial.prototype.isRelatedSetting.call( partial, setting, newValue, oldValue );
		}

	});

	api.selectiveRefresh.partialConstructor.post_field = api.previewPosts.PostFieldPartial;

})( wp.customize );
