/* global wp */
(function( api, $ ) {
	'use strict';

	/**
	 * A dynamic control.
	 *
	 * @class
	 * @augments wp.customize.Control
	 * @augments wp.customize.Class
	 */
	api.DynamicControl = api.Control.extend({

		initialize: function( id, options ) {
			var control = this;

			options = options || {};
			options.params = options.params || {};
			if ( ! options.params.type ) {
				options.params.type = 'dynamic';
			}
			if ( ! options.params.content ) {
				options.params.content = $( '<li></li>' );
				options.params.content.attr( 'id', 'customize-control-' + id.replace( /]/g, '' ).replace( /\[/g, '-' ) );
				options.params.content.attr( 'class', 'customize-control customize-control-' + options.params.type );
			}

			api.Control.prototype.initialize.call( control, id, options );
			control.propertyElements = [];
		},

		_setUpSettingProperty: function() {
			var control = this, nodes, radios;
			if ( ! control.params.setting_property || ! control.setting ) {
				return;
			}

			nodes = control.container.find( '[data-customize-setting-property-link]' );
			radios = {};

			nodes.each( function() {
				var node = $( this ),
					name,
					element,
					propertyName = node.data( 'customizeSettingPropertyLink' );

				if ( node.is( ':radio' ) ) {
					name = node.prop( 'name' );
					if ( radios[ name ] ) {
						return;
					}
					radios[ name ] = true;
					node = nodes.filter( '[name="' + name + '"]' );
				}

				element = new api.Element( node );
				control.propertyElements.push( element );
				element.set( control.setting()[ propertyName ] );

				element.bind( function( newPropertyValue ) {
					var newSetting = control.setting();
					if ( newPropertyValue === newSetting[ propertyName ] ) {
						return;
					}
					newSetting = _.clone( newSetting );
					newSetting[ propertyName ] = newPropertyValue;
					control.setting.set( newSetting );
				} );
				control.setting.bind( function( newValue ) {
					if ( newValue[ propertyName ] !== element.get() ) {
						element.set( newValue[ propertyName ] );
					}
				} );
			});
		},

		/**
		 * @inheritdoc
		 */
		ready: function() {
			var control = this;

			control._setUpSettingProperty();

			api.Control.prototype.ready.call( control );

			// @todo build out the controls for the post when Control is expanded.
			// @todo Let the Control title include the post title.
			control.deferred.embedded.done(function() {});
		},

		/**
		 * Embed the control in the document.
		 *
		 * Override the embed() method to do nothing,
		 * so that the control isn't embedded on load,
		 * unless the containing section is already expanded.
		 */
		embed: function() {
			var control = this,
				sectionId = control.section();
			if ( ! sectionId ) {
				return;
			}
			api.section( sectionId, function( section ) {
				if ( section.expanded() || api.settings.autofocus.control === control.id ) {
					control.actuallyEmbed();
				} else {
					section.expanded.bind( function( expanded ) {
						if ( expanded ) {
							control.actuallyEmbed();
						}
					} );
				}
			} );
		},

		/**
		 * Deferred embedding of control when actually
		 *
		 * This function is called in Section.onChangeExpanded() so the control
		 * will only get embedded when the Section is first expanded.
		 */
		actuallyEmbed: function() {
			var control = this;
			if ( 'resolved' === control.deferred.embedded.state() ) {
				return;
			}
			control.renderContent();
			control.deferred.embedded.resolve(); // This triggers control.ready().
		},

		/**
		 * This is not working with autofocus.
		 *
		 * @param args
		 */
		focus: function( args ) {
			var control = this;
			control.actuallyEmbed();
			api.Control.prototype.focus.call( control, args );
		}
	});

	api.controlConstructor.dynamic = api.DynamicControl;

})( wp.customize, jQuery );
