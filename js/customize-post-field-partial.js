/* global wp */
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
			var partial = this, matches, baseSelector, idPattern = /^post\[(.+?)]\[(-?\d+)]\[(.+?)]$/;

			options = options || {};
			options.params = options.params || {};
			matches = id.match( idPattern );
			if ( ! matches ) {
				throw new Error( 'Bad PostFieldPartial id. Expected post[:post_type][:post_id][:field_id]' );
			}
			options.params.post_type = matches[1];
			options.params.post_id = parseInt( matches[2], 10 );
			options.params.field_id = matches[3];

			if ( ! options.params.selector ) {
				baseSelector = '.hentry.post-' + String( options.params.post_id ) + '.type-' + options.params.post_type;
				if ( 'post_title' === options.params.field_id ) {
					options.params.selector = baseSelector + ' .entry-title';
				} else if ( 'post_content' === options.params.field_id ) {
					options.params.selector = baseSelector + ' .entry-content';
				}
			}
			api.selectiveRefresh.Partial.prototype.initialize.call( partial, id, options );
		},

		/**
		 * @inheritdoc
		 */
		showControl: function() {
			var partial = this, settingId = partial.params.primarySetting;
			if ( ! settingId ) {
				settingId = _.first( partial.settings() );
			}

			if ( 'post_content' === partial.params.field_id ) {

			}

			// @todo Load inline TinyMCE editor

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
