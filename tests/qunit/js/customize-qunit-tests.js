/* global QUnit, wp, _ */

(function() {

	'use strict';

	QUnit.test( 'Test if wp.customize.Posts object exits', function( assert ) {
		assert.ok( _.isObject( wp.customize.Posts ) );
		assert.ok( ! _.isEmpty( wp.customize.Posts ) );
	});
})();
