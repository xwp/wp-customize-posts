/*global jQuery, wp, _ */
( function ( api, $ ) {
	var OldPreviewer, preview;

	api.bind( 'ready', function () {

		// Auto-open the posts section if it is at the top (we're previewing a post)
		$( '#accordion-section-posts.top' ).addClass( 'open' );

	} );

	// @todo Core really needs to not make the preview a private variable
	OldPreviewer = api.Previewer;
	api.Previewer = OldPreviewer.extend({
		initialize: function( params, options ) {
			preview = this;

			preview.bind( 'queried-posts', function( queriedPosts ) {
				//console.info( 'Preview frame rendered these posts:', queriedPosts );
				// @todo Use queriedPosts to auto-suggest posts to edit (create their controls on the fly)
				// @todo When navigating in the preview, add a post edit control automatically for queried object? Suggest all posts queried in preview.
			} );

			OldPreviewer.prototype.initialize.call( this, params, options );
		}
	} );

	api.controlConstructor.post = api.Control.extend( {
		ready: function () {
			var control = this;

			// Update the fields when the setting changes
			this.setting.bind( function ( to, from ) {
				if ( ! _( from ).isEqual( to ) ) {
					control.container.find( ':input[id]' ).each( function () {
						var input, keys, key, value;
						input = $( this );

						keys = input.prop( 'id' ).replace( /^.*?\[/, '' ).replace( /\]$/, '' ).split( /\]\[/ );
						keys.shift(); // get rid of the ID

						value = to;
						while ( keys.length && typeof value !== 'undefined' ) {
							key = keys.shift();
							value = value[ key ];
						}

						if ( typeof value !== 'undefined' && input.val() !== value ) {
							input.val( value );
						}
					} );
				}
			} );
			// @todo Construct the control's fields with JS here, using the setting as the value
			// @todo Handle addition and deletion of postmeta

			// Update the setting when the fields change
			this.container.on( 'input propertychange', ':input', function () {
				var input, leaf_key, keys, data, subdata;
				input = $( this );

				keys = input.prop( 'id' ).replace( /^.*?\[/, '' ).replace( /\]$/, '' ).split( /\]\[/ );
				keys.shift(); // get rid of the post ID, e.g. from 'posts[519][meta][single2][0]'
				leaf_key = keys.pop(); // we want the top-level key

				data = JSON.parse( JSON.stringify( control.setting() ) ); // hacky deeply clone

				subdata = data;
				while ( keys.length && typeof subdata !== 'undefined' ) {
					subdata = subdata[ keys.shift() ];
				}

				if ( typeof subdata !== 'undefined' ) {
					subdata[ leaf_key ] = input.val();
					control.setting( data );
				}
			} );

		}
	} );


} )( wp.customize, jQuery );
