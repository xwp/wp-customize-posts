/* eslint consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	/**
	 * Patched ready method for MediaControl. Remove once fix in #36521 is available.
	 *
	 * @see FeaturedImage.updateSelection()
	 *
	 * @link https://core.trac.wordpress.org/ticket/36521
	 * @returns {void}
	 */
	api.MediaControl.prototype.ready = function() {
		var control = this;

		// Shortcut so that we don't have to use _.bind every time we add a callback.
		_.bindAll( control, 'restoreDefault', 'removeFile', 'openFrame', 'select', 'pausePlayer' );

		// Bind events, with delegation to facilitate re-rendering.
		control.container.on( 'click keydown', '.upload-button', control.openFrame );
		control.container.on( 'click keydown', '.upload-button', control.pausePlayer );
		control.container.on( 'click keydown', '.thumbnail-image img', control.openFrame );
		control.container.on( 'click keydown', '.default-button', control.restoreDefault );
		control.container.on( 'click keydown', '.remove-button', control.pausePlayer );
		control.container.on( 'click keydown', '.remove-button', control.removeFile );
		control.container.on( 'click keydown', '.remove-button', control.cleanupPlayer );

		// Resize the player controls when it becomes visible (ie when section is expanded)
		api.section( control.section() ).container
			.on( 'expanded', function() {
				if ( control.player ) {
					control.player.setControlsSize();
				}
			})
			.on( 'collapsed', function() {
				control.pausePlayer();
			});

		/**
		 * Set attachment data and render content.
		 *
		 * Note that BackgroundImage.prototype.ready applies this ready method
		 * to itself. Since BackgroundImage is an UploadControl, the value
		 * is the attachment URL instead of the attachment ID. In this case
		 * we skip fetching the attachment data because we have no ID available,
		 * and it is the responsibility of the UploadControl to set the control's
		 * attachmentData before calling the renderContent method.
		 *
		 * @param {number|string} value Attachment
		 * @returns {void}
		 */
		function setAttachmentDataAndRenderContent( value ) {
			var hasAttachmentData = $.Deferred(), attachmentId = value;

			if ( control.extended( api.UploadControl ) ) {
				hasAttachmentData.resolve();
			} else {
				attachmentId = parseInt( attachmentId, 10 );
				if ( _.isNaN( attachmentId ) || attachmentId <= 0 ) {
					delete control.params.attachment;
					hasAttachmentData.resolve();
				} else if ( control.params.attachment && control.params.attachment.id === attachmentId ) {
					hasAttachmentData.resolve();
				}
			}

			// Fetch the attachment data.
			if ( 'pending' === hasAttachmentData.state() ) {
				wp.media.attachment( attachmentId ).fetch().done( function() {
					control.params.attachment = this.attributes;
					hasAttachmentData.resolve();

					// Send attachment information to the preview for possible use in `postMessage` transport.
					wp.customize.previewer.send( control.setting.id + '-attachment-data', this.attributes );
				} );
			}

			hasAttachmentData.done( function() {
				control.renderContent();
			} );
		}

		// Ensure attachment data is initially set (for dynamically-instantiated controls).
		setAttachmentDataAndRenderContent( control.setting() );

		// Update the attachment data and re-render the control when the setting changes.
		control.setting.bind( setAttachmentDataAndRenderContent );
	};

})( wp.customize, jQuery );
