/* global jQuery, QUnit, wp, _ */

jQuery( window ).on( 'load', function() {

	'use strict';

	var api = wp.customize,
		posts = api.Posts;

	QUnit.test( 'Test if wp.customize.Posts object exits', function( assert ) {
		assert.ok( _.isObject( posts ) );
		assert.ok( ! _.isEmpty( posts ) );
	});

	QUnit.test( 'Test if wp.customize.Posts.data has keys', function( assert ) {
		assert.ok( _.isObject( posts.data ) );
		assert.ok( _.isObject( posts.data.postTypes ) );
		assert.ok( _.has( posts.data, 'initialServerDate' ) );
		assert.ok( _.has( posts.data, 'initialServerTimestamp' ) );
		assert.ok( _.has( posts.data, 'l10n' ) );
		assert.ok( _.has( posts.data, 'postIdInput' ) );
		assert.ok( 'number', typeof posts.data.initialClientTimestamp );
	});

	QUnit.test( 'Test if wp.customize.controlConstructor.post_discussion_fields', function( assert ) {
		var control = new api.controlConstructor.post_discussion_fields( 'test_id', {
			params: {}
		} );
		assert.ok( _.has( api.controlConstructor, 'post_discussion_fields' ) );
		assert.ok( control.extended( api.controlConstructor.dynamic ) );
		assert.equal( control.params.type, 'post_discussion_fields' );
		assert.equal( control.params.field_type, 'checkbox' );
	});

});
