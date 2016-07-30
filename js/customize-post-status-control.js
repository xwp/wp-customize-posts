/* global wp, _ */
/* eslint consistent-this: [ "error", "control" ] */

(function( api ) {
	'use strict';

	/**
	 * Post status control extension of Dynamic Control.
	 */
	api.controlConstructor.post_status = api.controlConstructor.dynamic.extend({

		initialize: function( id, options ) {
			var control = this, opt;

			opt = {};
			opt.params = _.extend(
				{
					label: api.Posts.data.l10n.fieldStatusLabel,
					type: 'dynamic', // To re-use the dynamic template.
					active: true,
					setting_property: 'post_status',
					field_type: 'select',
					choices: api.Posts.data.postStatusChoices,
					updateChoicesInterval: 1000

				},
				options.params || {}
			);

			api.controlConstructor.dynamic.prototype.initialize.call( control, id, opt );

			control.deferred.embedded.done( function() {
				var embeddedDelay = 50;

				// Defer updating until control explicitly added, because it will short-circuit if not registered yet.
				api.control( control.id, function() {
					control.keepUpdatingChoices();
				} );

				// Update choices whenever the setting changes.
				control.setting.bind( function( newData, oldData ) {
					if ( newData.post_status !== oldData.post_status || newData.post_date !== oldData.post_date ) {
						control.updateChoices();
					}
				} );

				// Set the initial trashed UI.
				// @todo Why the delay?
				_.delay( function() {
					control.toggleTrash();
				}, embeddedDelay );

				// Update the status UI when the setting changes its state.
				control.setting.bind( function( newPostData, oldPostData ) {
					if ( newPostData.post_status !== oldPostData.post_status && 'trash' === newPostData.post_status || 'trash' === oldPostData.post_status ) {
						control.toggleTrash();
					}
				} );
			} );
		},

		/**
		 * Make sure availability of the future and publish choices corresponds to the post date and the current time.
		 *
		 * Also toggle between future and publish based on the current time.
		 *
		 * @returns {void}
		 */
		updateChoices: function updateChoices() {
			var control = this, data = control.setting.get(), isFuture, optionFuture, optionPublish;
			isFuture = data.post_date > api.Posts.getCurrentTime();

			optionFuture = control.container.find( 'select option[value=future]' );
			optionPublish = control.container.find( 'select option[value=publish]' );

			if ( optionFuture.prop( 'disabled' ) === isFuture ) {
				optionFuture.prop( 'disabled', ! isFuture );
			}
			if ( optionPublish.prop( 'disabled' ) !== isFuture ) {
				optionPublish.prop( 'disabled', isFuture );
			}

			if ( isFuture && 'publish' === data.post_status ) {
				data = _.clone( data );
				data.post_status = 'future';
				control.setting.set( data );
			} else if ( ! isFuture && 'future' === data.post_status ) {
				data = _.clone( data );
				data.post_status = 'publish';
				control.setting.set( data );
			}
		},

		/**
		 * Keep the availability of the publish and future statuses synced with post date and current time.
		 *
		 * @return {void}
		 */
		keepUpdatingChoices: function keepUpdatingChoices() {
			var control = this;

			// Stop updating once the control has been removed.
			if ( ! api.control.has( control.id ) ) {
				control.updateChoicesIntervalId = null;
				return;
			}

			control.updateChoices();
			control.updateChoicesIntervalId = setTimeout( function() {
				control.keepUpdatingChoices();
			}, control.params.updateChoicesInterval );
		},

		/**
		 * Update the UI when a post is transitioned from/to trash.
		 *
		 * @returns {void}
		 */
		toggleTrash: function() {
			var control = this, section, sectionContainer, sectionTitle, trashed;
			section = api.section( control.section.get() );
			if ( ! section ) {
				return;
			}
			trashed = 'trash' === control.setting.get().post_status;
			sectionContainer = section.container.closest( '.accordion-section' );
			sectionTitle = sectionContainer.find( '.accordion-section-title:first' );
			sectionContainer.toggleClass( 'is-trashed', trashed );
			if ( true === trashed ) {
				if ( 0 === sectionTitle.find( '.customize-posts-trashed' ).length ) {
					sectionTitle.append( wp.template( 'customize-posts-trashed' )() );
				}
			} else {
				sectionContainer.find( '.customize-posts-trashed' ).remove();
			}
		}
	});

})( wp.customize );
