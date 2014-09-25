/*global wp */


/**
 * Given a multidimensional ID/name like "posts[519][post_title]"
 * return an object containing the 'base' property 'posts',
 * and a 'keys' property containing [ '519', 'post_title' ].
 *
 * Port of logic in WP_Customize_Setting::__construct()
 *
 * @param {String} id
 * @returns {Object}
 */
wp.customize.parseIdData = function ( id ) {
	var id_data = {
		base: '',
		keys: []
	};

	// Parse the ID for array keys.
	id_data.keys = id.replace( /\]/g, '' ).split( /\[/ );
	id_data.base = id_data.keys.shift();
	return id_data;
};

/**
 * Multidimensional helper function.
 *
 * Port of WP_Customize_Setting::multidimensional()
 *
 * @todo This should be migrated to customize-base.js
 *
 * @param {Object} root
 * @param {Array} keys
 * @param {Boolean} create Default is false.
 * @return {null|Object} Keys are 'root', 'node', and 'key'.
 */
wp.customize.multidimensional = function ( root, keys, create ) {
	var last, node, key, i;

	if ( create && ! root ) {
		root = {};
	}

	if ( ! root || ! keys.length ) {
		return undefined;
	}

	last = keys.pop();
	node = root;

	for ( i = 0; i < keys.length; i += 1 ) {
		key = keys[ i ];

		if ( create && typeof node[ key ] === 'undefined' ) {
			node[ key ] = {};
		}

		if ( typeof node !== 'object' || typeof node[ key ] === 'undefined' ) {
			return undefined;
		}

		node = node[ key ];
	}

	if ( create && typeof node[ last ] === 'undefined' ) {
		node[ last ] = {};
	}

	if ( typeof node[ last ] === 'undefined' ) {
		return undefined;
	}

	return {
		'root': root,
		'node': node,
		'key': last
	};
};

/**
 * Will attempt to replace a specific value in a multidimensional array.
 *
 * Port of WP_Customize_Setting::multidimensional_replace()
 *
 * @param {Object} root
 * @param {Array} keys
 * @param {*} value The value to update.
 * @return {*}
 */
wp.customize.multidimensionalReplace = function ( root, keys, value ) {
	var result;
	if ( typeof value === 'undefined' ) {
		return root;
	} else if ( ! keys.length ) { // If there are no keys, we're replacing the root.
		return value;
	}

	result = this.multidimensional( root, keys, true );

	if ( result ) {
		result.node[ result.key ] = value;
	}

	return root;
};

/**
 * Will attempt to fetch a specific value from a multidimensional array.
 *
 * Port of WP_Customize_Setting::multidimensional_get()
 *
 * @todo Should be ported over to customize-base.js
 *
 * @param {Object} root
 * @param {Array} keys
 * @param {*} [defaultValue] A default value which is used as a fallback. Default is null.
 * @return {*} The requested value or the default value.
 */
wp.customize.multidimensionalGet = function ( root, keys, defaultValue ) {
	var result;
	if ( typeof defaultValue === 'undefined' ) {
		defaultValue = null;
	}

	if ( ! keys || ! keys.length ) { // If there are no keys, test the root.
		return ( typeof root !== 'undefined' ) ? root : defaultValue;
	}

	result = this.multidimensional( root, keys );
	return typeof result !== 'undefined' ? result.node[ result.key ] : defaultValue;
};


/**
 * Will attempt to check if a specific value in a multidimensional array is set.
 *
 * Port of WP_Customize_Setting::multidimensional_isset()
 *
 * @todo Fold this into customize-base.js
 *
 * @param {Object} root
 * @param {Array} keys
 * @return {Boolean} True if value is set, false if not.
 */
wp.customize.multidimensionalIsset = function ( root, keys ) {
	var result, noValue;
	noValue = {};
	result = this.multidimensionalGet( root, keys, noValue );
	return result !== noValue;
};
