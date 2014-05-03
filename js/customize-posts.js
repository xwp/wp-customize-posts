/*global jQuery, wp, _ */
( function ( api, $ ) {

	api.bind( 'ready', function () {

		// Auto-open the posts section if it is at the top (we're previewing a post)
		$( '#accordion-section-posts.top' ).addClass( 'open' );

	} );

	api.controlConstructor.post = api.Control.extend( {
		ready: function () {
			var control = this;

			// Update the fields when the setting changes
			this.setting.bind( function ( to, from ) {
				if ( ! _( from ).isEqual( to ) ) {
					control.find( ':input' ).each( function () {
						var input, key;
						input = $( this );
						key = input.data( 'key' );
						if ( key && typeof to[ key ] !== 'undefined' ) {
							input.val( to[ key ] );
						}
					} );
				}
			} );

			// Update the setting when the fields change
			this.container.find( ':input' ).on( 'input propertychange', function () {
				var input, key, data;
				input = $( this );
				key = input.data( 'key' );
				data = _( control.setting() ).clone(); // otherwise, the setting won't register as changed
				if ( input.val() !== data[ key ] ) {
					data[ key ] = input.val();
					control.setting( data );
				}
			} );

		}
	} );


} )( wp.customize, jQuery );
