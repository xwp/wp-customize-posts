/* global wp, _ */
/* eslint consistent-this: [ "error", "control" ] */
/* eslint no-magic-numbers: [ "error", { "ignore": [0,15,1000] } ] */

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
					type: 'post_status', // Used for template.
					label: api.Posts.data.l10n.fieldStatusLabel,
					active: true,
					setting_property: 'post_status',
					field_type: 'select',
					choices: api.Posts.data.postStatusChoices, // @todo Allow post status choices to be specific to post types.
					updateChoicesInterval: 1000
				},
				options.params || {}
			);

			api.controlConstructor.dynamic.prototype.initialize.call( control, id, opt );

			control.deferred.embedded.done( function() {
				var embeddedDelay = 50, collapseSection, trashCollapseDelay = 500;

				control.selectElement = control.container.find( 'select' );
				control.optionFutureElement = control.selectElement.find( 'option[value=future]' );
				control.optionPublishElement = control.selectElement.find( 'option[value=publish]' );
				control.trashLink = control.container.find( '.trash' );
				control.untrashLink = control.container.find( '.untrash' );

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
				control.originalPostStatus = control.setting().post_status;
				control.setting.bind( function( newPostData, oldPostData ) {
					if ( newPostData.post_status !== oldPostData.post_status && 'trash' === newPostData.post_status || 'trash' === oldPostData.post_status ) {
						control.toggleTrash();
					}
				} );

				/**
				 * Collapse section.
				 *
				 * @return {void}
				 */
				collapseSection = function() {
					var section = api.section( control.section() );
					if ( section ) {
						section.collapse();
					}
				};

				// Trash the post when clicking the delete link.
				control.trashLink.on( 'click', function( e ) {
					var postData = _.clone( control.setting.get() );
					e.preventDefault();
					postData.post_status = 'trash';
					control.setting.set( postData );

					/*
					 * Collapse the section momentarily after trashing the post
					 * so that the user can visually see the status dropdown
					 * change to trash (so they can undo it later).
					 */
					_.delay( collapseSection, trashCollapseDelay );
				} );

				// Restore the original post status when clicking the untrash link.
				control.untrashLink.on( 'click', function( e ) {
					var postData = _.clone( control.setting.get() );
					e.preventDefault();
					postData.post_status = control.originalPostStatus;
					control.setting.set( postData );
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
			var control = this, data = control.setting.get(), isFuture, postTimestamp, currentTimestamp;
			postTimestamp = api.Posts.parsePostDate( data.post_date );
			currentTimestamp = api.Posts.parsePostDate( api.Posts.getCurrentTime() );
			isFuture = postTimestamp > currentTimestamp;

			/*
			 * Account for race condition when saving a post with an empty date
			 * when server time and client time aren't exactly aligned. If the
			 * status is publish, and yet the post date is less than 15 seconds
			 * into the future, consider it as not future.
			 *
			 * See also https://github.com/xwp/wp-customize-posts/issues/303
			 */
			if ( isFuture && 'publish' === data.post_status && postTimestamp - currentTimestamp < 15 * 1000 ) {
				isFuture = false;
			}

			if ( control.optionFutureElement.prop( 'disabled' ) === isFuture ) {
				control.optionFutureElement.prop( 'disabled', ! isFuture );
			}
			if ( control.optionPublishElement.prop( 'disabled' ) !== isFuture ) {
				control.optionPublishElement.prop( 'disabled', isFuture );
			}

			if ( isFuture && 'publish' === data.post_status || ! isFuture && 'future' === data.post_status ) {
				data = _.clone( data );
				data.post_status = isFuture ? 'future' : 'publish';

				// @todo Only do this if already _dirty? Otherwise, set quietly by setting _value directly and update selected status option?
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
			trashed = 'trash' === control.setting.get().post_status;

			if ( control.trashLink ) {
				control.trashLink.toggle( ! trashed );
			}
			if ( control.originalPostStatus ) {
				control.untrashLink.toggle( Boolean( trashed && control.originalPostStatus ) );
			}

			section = api.section( control.section.get() );
			if ( section ) {
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
		}
	});

})( wp.customize );
