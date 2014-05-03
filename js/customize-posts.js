/*global jQuery, wp */
wp.customize.bind( 'ready', function () {

	// Auto-open the posts section if it is at the top (we're previewing a post)
	jQuery( '#accordion-section-posts.top' ).addClass( 'open' );

} );
