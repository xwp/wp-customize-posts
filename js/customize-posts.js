/*global jQuery, wp */
wp.customize.bind( 'ready', function () {
	// @todo If previewing a post, load it up here, show the section, and scroll into view
	jQuery( '#accordion-section-posts' ).addClass( 'open' );
} );
