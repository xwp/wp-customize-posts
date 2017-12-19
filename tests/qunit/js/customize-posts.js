/* global jQuery, QUnit, wp, _ */

jQuery( window ).on( 'load', function() {

	'use strict';

	var api = wp.customize,
		posts = api.Posts;

	api.settings = api.settings || {};

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
		assert.ok( _.isEqual( typeof posts.data.initialClientTimestamp, 'number' )  );
	});

	QUnit.test( 'Test if wp.customize.Posts.controlConstructor.post_discussion_fields', function( assert ) {
		var control = new api.controlConstructor.post_discussion_fields( 'test_id', {
			params: {}
		} );
		assert.ok( _.has( api.controlConstructor, 'post_discussion_fields' ) );
		assert.ok( control.extended( api.controlConstructor.dynamic ) );
		assert.equal( control.params.type, 'post_discussion_fields' );
		assert.equal( control.params.field_type, 'checkbox' );
	});

	QUnit.test( 'Test wp.customize.Posts.parseSettingId', function( assert ) {
		var parsed = posts.parseSettingId( 'post[post][203]' );

		assert.ok( _.isEqual( posts.parseSettingId( '2' ), null ) );
		assert.ok( _.isEqual( parsed.postId, 203 ) ); // eslint-disable-line
		assert.ok( _.isEqual( parsed.postType, 'post' ) );
		assert.ok( _.isEqual( parsed.settingType, 'post' ) );

		parsed = posts.parseSettingId( 'post[page][204]' );
		assert.ok( _.isEqual( parsed.postId, 204 ) ); // eslint-disable-line
		assert.ok( _.isEqual( parsed.postType, 'page' ) );
		assert.ok( _.isEqual( parsed.settingType, 'post' ) );

		parsed = posts.parseSettingId( 'postmeta[post][202][hello_world]' );
		assert.ok( _.isEqual( parsed.postId, 202 ) ); // eslint-disable-line
		assert.ok( _.isEqual( parsed.postType, 'post' ) );
		assert.ok( _.isEqual( parsed.settingType, 'postmeta' ) );
		assert.ok( _.isEqual( parsed.metaKey, 'hello_world' ) );
	});

	QUnit.test( 'Test wp.customize.Posts.getPostUrl', function( assert ) {
		api.settings.url = api.settings.url || {};
		api.settings.url.home = 'http://example.org';

		assert.ok( _.isEqual( posts.getPostUrl( {
			post_type: 'post',
			post_id: 23, // eslint-disable-line
			preview: true
		} ), 'http://example.org?preview=true&p=23' ) );

		assert.ok( _.isEqual( posts.getPostUrl( {
			post_type: 'page',
			post_id: 24, // eslint-disable-line
			preview: true
		} ), 'http://example.org?preview=true&page_id=24' ) );
	});

	QUnit.test( 'Test wp.customize.Posts.getPreviewUrl', function( assert ) {
		api.settings.url = api.settings.url || {};
		api.settings.url.home = 'http://example.org';

		assert.ok( _.isEqual( posts.getPreviewUrl( {
			post_type: 'post',
			post_id: 23, // eslint-disable-line
			preview: true
		} ), 'http://example.org?preview=true&p=23' ) );
	});

	QUnit.test( 'Test wp.customize.Posts.sanitizeTitleWithDashes', function( assert ) {
		assert.ok( _.isEqual( posts.sanitizeTitleWithDashes( 'hello world' ), 'hello-world' ) );
		assert.ok( _.isEqual( posts.sanitizeTitleWithDashes( 'hello_world' ), 'hello_world' ) );
		assert.ok( _.isEqual( posts.sanitizeTitleWithDashes( 'hello %## world' ), 'hello-world' ) );
		assert.ok( _.isEqual( posts.sanitizeTitleWithDashes( 'HelLo %## woRld' ), 'hello-world' ) );
		assert.ok( _.isEqual( posts.sanitizeTitleWithDashes( 'HelLo %## _ woRld' ), 'hello-_-world' ) );
		assert.ok( _.isEqual( posts.sanitizeTitleWithDashes( '' ), '' ) );
		assert.ok( _.isEqual( posts.sanitizeTitleWithDashes( 10 ), '10' ) ); // eslint-disable-line
	});

	QUnit.test( 'Test wp.customize.Posts.parsePostDate', function( assert ) {
		var date = posts.parsePostDate( '2017-12-14 19:26:36' );
		assert.ok( date instanceof Date );
		assert.ok( _.isEqual( date.getFullYear(), 2017 ) ); // eslint-disable-line
		assert.ok( _.isEqual( date.getDate(), 14 ) ); // eslint-disable-line
		assert.ok( _.isEqual( date.getMonth() + 1, 12 ) ); // eslint-disable-line
		assert.ok( _.isEqual( date.getHours(), 19 ) ); // eslint-disable-line
		assert.ok( _.isEqual( date.getMinutes(), 26 ) ); // eslint-disable-line
		assert.ok( _.isEqual( date.getSeconds(), 36 ) ); // eslint-disable-line
	});

	QUnit.test( 'Test if wp.customize.Posts.formatDate object exits', function( assert ) {
		var dateString = '2017-12-14 19:26:36',
			date = posts.parsePostDate( dateString );

		assert.ok( _.isEqual( posts.formatDate( date ), dateString ) );
	});

});
