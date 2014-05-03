/*global jQuery, wp */
( function ( api, $ ) {

	api.controlConstructor.post = api.Control.extend( {
		ready: function () {
			console.info( this.container );


		}
	} );


	api.bind( 'ready', function () {

		// Auto-open the posts section if it is at the top (we're previewing a post)
		$( '#accordion-section-posts.top' ).addClass( 'open' );

	} );

} )( wp.customize, jQuery );




