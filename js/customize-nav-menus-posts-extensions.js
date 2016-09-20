(function( api, $ ) {
	'use strict';

	if ( api.Menus.insertAutoDraftPost ) {

		/**
		 * Insert a new `auto-draft` post.
		 *
		 * @param {object} params - Parameters for the draft post to create.
		 * @param {string} params.post_type - Post type to add.
		 * @param {string} params.post_title - Post title to use.
		 * @return {jQuery.promise} Promise resolved with the added post.
		 */
		api.Menus.insertAutoDraftPost = function insertAutoDraftPost( params ) {

			var insertPromise, deferred = $.Deferred();

			insertPromise = api.Posts.insertAutoDraftPost( params.post_type );

			insertPromise.done( function insertAutoDraftPostDone( data ) {
				var postData;

				postData = _.clone( data.setting.get() );
				postData.post_title = params.post_title;
				postData.post_status = 'publish';
				data.setting.set( postData );

				deferred.resolve( {
					post_id: data.postId,
					url: api.Posts.getPostUrl( { post_id: data.postId, post_type: params.post_type } )
				} );
			} );
			insertPromise.fail( function insertAutoDraftPostFail( failure ) {
				deferred.reject( failure );
			} );

			return deferred.promise();
		};
	}

})( wp.customize, jQuery );
