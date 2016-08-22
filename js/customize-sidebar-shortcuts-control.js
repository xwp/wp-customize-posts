/* global jQuery, wp, _ */
/* eslint no-magic-numbers: [ "error", { "ignore": [0] } ], consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	/**
	 * Sidebar shortcuts control extension of Dynamic Control.
	 */
	api.controlConstructor.sidebar_shortcuts = api.controlConstructor.dynamic.extend({

		/**
		 * Initialize.
		 *
		 * @param {string} id Control ID.
		 * @param {object} options Options.
		 * @param {object} options.params Params.
		 * @returns {void}
		 */
		initialize: function( id, options ) {
			var control = this, opt;

			if ( ! api.Widgets ) {
				throw new Error( 'The widgets component is not loaded.' );
			}

			opt = {};
			opt.params = _.extend(
				{
					type: 'sidebar_shortcuts',
					content_template: wp.template( 'customize-sidebar-shortcuts-control' ),
					label: api.Posts.data.l10n.fieldWidgetAreasLabel,
					active: true
				},
				options.params || {}
			);

			api.controlConstructor.dynamic.prototype.initialize.call( control, id, opt );
		},

		/**
		 * Ready.
		 *
		 * @returns {void}
		 */
		ready: function() {
			var control = this;
			api.controlConstructor.dynamic.prototype.ready.call( control );

			control.activeSidebarTemplate = wp.template( 'customize-sidebar-shortcuts-control-active-sidebar' );
			control.widgetAreasContainer = control.container.find( 'ul.active-sidebar-sections' );
			control.noSidebarsRenderedNotice = control.container.find( '.no-sidebars-rendered-notice' );

			control.widgetAreasContainer.on( 'click', 'button', function() {
				var button = $( this ), section, returnPromise;
				section = api.section( button.data( 'section-id' ) );
				returnPromise = control.focusConstructWithBreadcrumb( section, control );
				returnPromise.done( function() {
					button.focus();
				} );

			} );

			_.bindAll(
				control,
				'handleSidebarSectionAdd',
				'handleSidebarSectionRemove',
				'renderSidebarButtons'
			);
			control.renderSidebarButtons = _.debounce( control.renderSidebarButtons );

			api.section.each( control.handleSidebarSectionAdd );
			api.section.bind( 'add', control.handleSidebarSectionAdd );
			api.section.bind( 'remove', control.handleSidebarSectionRemove );
		},

		/**
		 * Handle sidebar section added.
		 *
		 * @param {wp.customize.Section} section Section.
		 * @returns {void}
		 */
		handleSidebarSectionAdd: function handleSidebarSectionAdd( section ) {
			var control = this;
			if ( section.extended( api.Widgets.SidebarSection ) ) {
				section.active.bind( control.renderSidebarButtons );
				control.renderSidebarButtons();
			}
		},

		/**
		 * Handle sidebar section removed.
		 *
		 * @param {wp.customize.Section} section Section.
		 * @returns {void}
		 */
		handleSidebarSectionRemove: function handleSidebarSectionRemove( section ) {
			var control = this;
			if ( section.extended( api.Widgets.SidebarSection ) ) {
				section.active.unbind( control.renderSidebarButtons );
				control.renderSidebarButtons();
			}
		},

		/**
		 * Render sidebar buttons.
		 *
		 * @returns {void}
		 */
		renderSidebarButtons: function renderSidebarButtons() {
			var control = this, activeSections = [];

			api.section.each( function( section ) {
				if ( section.extended( api.Widgets.SidebarSection ) && section.active.get() ) {
					activeSections.push( section );
				}
			} );

			activeSections.sort( function( a, b ) {
				return a.priority.get() - b.priority.get();
			} );

			control.widgetAreasContainer.empty();
			_.each( activeSections, function( activeSection ) {
				var li = $( $.trim( control.activeSidebarTemplate( {
					section_id: activeSection.id,
					sidebar_name: activeSection.params.title
				} ) ) );
				control.widgetAreasContainer.append( li );
			} );

			control.widgetAreasContainer.toggle( 0 !== activeSections.length );
			control.noSidebarsRenderedNotice.toggle( 0 === activeSections.length );
		},

		/**
		 * Focus (expand) one construct and then focus on another construct after the first is collapsed.
		 *
		 * This overrides the back button to serve the purpose of breadcrumb navigation.
		 * This is modified from WP Core.
		 *
		 * @link https://github.com/xwp/wordpress-develop/blob/e7bbb482d6069d9c2d0e33789c7d290ac231f056/src/wp-admin/js/customize-widgets.js#L2143-L2193
		 * @param {wp.customize.Section|wp.customize.Panel|wp.customize.Control} focusConstruct - The object to initially focus.
		 * @param {wp.customize.Section|wp.customize.Panel|wp.customize.Control} returnConstruct - The object to return focus.
		 * @returns {void}
		 */
		focusConstructWithBreadcrumb: function focusConstructWithBreadcrumb( focusConstruct, returnConstruct ) {
			var deferred = $.Deferred(), onceCollapsed;
			focusConstruct.focus();
			onceCollapsed = function( isExpanded ) {
				if ( ! isExpanded ) {
					focusConstruct.expanded.unbind( onceCollapsed );
					returnConstruct.focus( {
						completeCallback: function() {
							deferred.resolve();
						}
					} );
				}
			};
			focusConstruct.expanded.bind( onceCollapsed );
			return deferred;
		}
	});

})( wp.customize, jQuery );
