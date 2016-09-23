/* global jQuery, wp, _, tinyMCE */
/* eslint consistent-this: [ "error", "control" ], no-magic-numbers: [ "error", { "ignore": [1] } ] */

(function( api, $ ) {
	'use strict';

	// This is true because wp_editor() is forcibly called with 'default_editor' => 'tinymce'.
	var visualModeEnabled = true;

	/**
	 * An post editor control.
	 *
	 * @class
	 * @augments wp.customize.DynamicControl
	 * @augments wp.customize.Control
	 * @augments wp.customize.Class
	 */
	api.Posts.PostEditorControl = api.controlConstructor.dynamic.extend({

		initialize: function initialize( id, options ) {
			var control = this, args;

			_.bindAll(
				control,
				'handleRemoval',
				'onTextEditorChange',
				'onVisualEditorChange',
				'onMousedownEditorDragbar',
				'onMouseupEditorDragbar',
				'resizeEditorWithWindowResize',
				'updateEditorContentsOnSettingChange',
				'handleSectionExpansionToggled',
				'handleToggleEditorButtonClick'
			);

			args = {};
			args.params = _.extend(
				{
					type: 'post_editor',
					section: '',
					priority: 25,
					label: api.Posts.data.l10n.fieldContentLabel,
					active: true,
					setting_property: null,
					input_attrs: {}
				},
				options.params || {}
			);

			control.expanded = new api.Value( false );
			control.expandedArgumentsQueue = [];
			control.expanded.bind( function( expanded ) {
				var expandedArgs = control.expandedArgumentsQueue.shift();
				expandedArgs = $.extend( {}, control.defaultExpandedArguments, expandedArgs );
				control.onChangeExpanded( expanded, expandedArgs );
			});

			api.controlConstructor.dynamic.prototype.initialize.call( control, id, args );

			control.deferred.embedded.done( function() {
				control.initEditor();
			});

			api.control.bind( 'remove', control.handleRemoval );
		},

		/**
		 * Handle removal.
		 *
		 * @param {wp.customize.Control} removedControl Removed control.
		 * @returns {void}
		 */
		handleRemoval: function handleEditorControlRemoval( removedControl ) {
			var control = this, editor, section;
			if ( control !== removedControl ) {
				return;
			}
			section = api.section( control.section() );
			api.control.unbind( 'remove', control.handleRemoval );
			control.expanded.set( false );
			control.container.remove();

			editor = tinyMCE.get( 'customize-posts-content' );
			if ( editor ) {
				editor.off( 'input change keyup', control.onVisualEditorChange );
			}
			control.contentTextarea.off( 'input', control.onTextEditorChange );
			if ( control.setting ) {
				control.setting.unbind( control.updateEditorContentsOnSettingChange );
			}
			if ( section ) {
				section.expanded.unbind( control.handleSectionExpansionToggled );
			}
			control.editorToggleExpandButton.off( 'click', control.handleToggleEditorButtonClick );
			control.editorDragbar.off( 'mousedown', control.onMousedownEditorDragbar );
			control.editorDragbar.off( 'mouseup', control.onMouseupEditorDragbar );
			$( window ).off( 'resize', control.resizeEditorWithWindowResize );
		},

		/**
		 * Toggle the expanded control.
		 *
		 * @param {Boolean} expanded Expanded.
		 * @param {Object} [params]  Params.
		 * @returns {Boolean} false if state already applied
		 */
		_toggleExpanded: function( expanded, params ) {
			var control = this;

			if ( expanded && control.section() && api.section.has( control.section() ) ) {
				api.section( control.section() ).expand();
			}

			return api.Section.prototype._toggleExpanded.call( control, expanded, params );
		},

		/**
		 * Expand the control.
		 *
		 * @param {Object} [params]
		 * @returns {Boolean} false if already expanded
		 */
		expand: api.Section.prototype.expand,

		/**
		 * Collapse the control.
		 *
		 * @param {Object} [params]
		 * @returns {Boolean} false if already collapsed
		 */
		collapse: api.Section.prototype.collapse,

		/**
		 * Expand or collapse control.
		 *
		 * @param {boolean}[expanded] - If not supplied, will be inverse of current visibility
		 * @param {Object}   [params] - Optional params.
		 * @param {Function} [params.completeCallback] - Function to call when the form toggle has finished animating.
		 * @returns {void}
		 */
		onChangeExpanded: function( expanded, params ) {
			var control = this,
				editor,
				settingValue,
				setting = control.setting;

			if ( control.params.setting_property ) {
				settingValue = setting.get()[ control.params.setting_property ];
			} else {
				settingValue = setting.get();
			}

			editor = tinyMCE.get( 'customize-posts-content' );
			control.updateEditorToggleExpandButtonLabel( expanded );

			if ( expanded ) {
				control.collapseOtherControls();
				control.updateEditorHeading();

				if ( visualModeEnabled ) {
					editor.show();
				}
				if ( editor && visualModeEnabled ) {
					editor.setContent( wp.editor.autop( settingValue ) );
				} else {
					control.contentTextarea.val( settingValue );
				}
				editor.on( 'input change keyup', control.onVisualEditorChange );
				control.contentTextarea.on( 'input', control.onTextEditorChange );
				$( '#wp-customize-posts-content-wrap' ).on( 'keydown', control.stopEscKeypressEventPropagation );
				$( document.body ).addClass( 'customize-posts-content-editor-pane-open' );
				control.resizeEditor( window.innerHeight - control.editorPane.height() );
			} else {
				control.editorHeading.text( '' );
				editor.off( 'input change keyup', control.onVisualEditorChange );
				control.contentTextarea.off( 'input', control.onTextEditorChange );
				$( '#wp-customize-posts-content-wrap' ).off( 'keydown', control.stopEscKeypressEventPropagation );
				$( document.body ).removeClass( 'customize-posts-content-editor-pane-open' );

				// Cancel link and force a click event to exit fullscreen & kitchen sink mode.
				$( '.mce-active' ).click(); // Remove active status from each item. @todo This is a hack.
				visualModeEnabled = ! editor.isHidden();
				editor.hide(); // Make sure all toolbars are hidden.
				control.customizePreview.css( 'bottom', '' );
				control.collapseSidebar.css( 'bottom', '' );
			}

			if ( params && params.completeCallback ) {
				params.completeCallback();
			}
		},

		/**
		 * Update the editor heading with the control label, showing if there is more than one editor control in the section.
		 *
		 * @returns {void}
		 */
		updateEditorHeading: function updateEditorHeading() {
			var control = this, section, editorControlCount = 0;
			section = api.section( control.section() );
			if ( section ) {
				_.each( section.controls(), function iterateEditorControl( sectionControl ) {
					if ( sectionControl.extended( api.Posts.PostEditorControl ) ) {
						editorControlCount += 1;
					}
				} );
			}
			if ( editorControlCount > 1 ) {
				control.editorHeading.text( control.params.label );
				control.editorHeading.show();
			} else {
				control.editorHeading.text( '' );
				control.editorHeading.hide();
			}
		},

		/**
		 * Update the setting value when the editor changes its state.
		 *
		 * @returns {void}
		 */
		onVisualEditorChange: function onVisualEditorChange() {
			var control = this, value, editor, settingValue;
			if ( control.editorSyncSuspended ) {
				return;
			}
			editor = tinyMCE.get( 'customize-posts-content' );
			value = wp.editor.removep( editor.getContent() );
			control.editorSyncSuspended = true;

			if ( control.params.setting_property ) {
				settingValue = _.clone( control.setting.get() );
				settingValue[ control.params.setting_property ] = value;
				control.setting.set( settingValue );
			} else {
				control.setting.set( value );
			}

			control.editorSyncSuspended = false;
		},

		/**
		 * Update the setting value when the editor changes its state.
		 *
		 * @returns {void}
		 */
		onTextEditorChange: function onTextEditorChange() {
			var control = this, value, settingValue;
			if ( control.editorSyncSuspended ) {
				return;
			}
			value = control.contentTextarea.val();
			control.editorSyncSuspended = true;

			if ( control.params.setting_property ) {
				settingValue = _.clone( control.setting.get() );
				settingValue[ control.params.setting_property ] = value;
				control.setting.set( settingValue );
			} else {
				control.setting.set( value );
			}

			control.editorSyncSuspended = false;
		},

		/**
		 * Create editor control.
		 *
		 * @returns {void}
		 */
		initEditor: function initEditor() {
			var control = this,
				section = api.section( control.section() ),
				setting = control.setting;

			if ( 1 !== _.size( control.settings ) ) {
				throw new Error( 'Only one setting may be associated with a post editor control.' );
			}

			control.editorHeading = $( '#customize-posts-content-editor-title' );
			control.contentTextarea = $( '#customize-posts-content' );
			control.customizePreview = $( '#customize-preview' );
			control.editorDragbar = $( '#customize-posts-content-editor-dragbar' );
			control.editorPane = $( '#customize-posts-content-editor-pane' );
			control.editorFrame = $( '#customize-posts-content_ifr' );
			control.collapseSidebar = $( '.collapse-sidebar' );
			control.editorToggleExpandButton = control.container.find( '.toggle-post-editor' );

			control.updateEditorToggleExpandButtonLabel( control.expanded.get() );

			setting.bind( control.updateEditorContentsOnSettingChange );
			section.expanded.bind( control.handleSectionExpansionToggled );
			control.editorToggleExpandButton.on( 'click', control.handleToggleEditorButtonClick );
			control.editorDragbar.on( 'mousedown', control.onMousedownEditorDragbar );
			control.editorDragbar.on( 'mouseup', control.onMouseupEditorDragbar );
			$( window ).on( 'resize', control.resizeEditorWithWindowResize );
		},

		/**
		 * Update the editor when the setting changes its state.
		 *
		 * @param {string|object} newValue New setting value.
		 * @param {string|object} oldValue Old setting value.
		 * @returns {void}
		 */
		updateEditorContentsOnSettingChange: function updateEditorContentsOnSettingChange( newValue, oldValue ) {
			var editor,
				control = this,
				newData = control.params.setting_property ? newValue[ control.params.setting_property ] : newValue,
				oldData = control.params.setting_property ? oldValue[ control.params.setting_property ] : oldValue;

			if ( control.expanded.get() && ! control.editorSyncSuspended && newData !== oldData ) {
				control.editorSyncSuspended = true;
				editor = tinyMCE.get( 'customize-posts-content' );
				if ( editor && ! editor.isHidden() ) {
					editor.setContent( wp.editor.autop( newData ) );
				} else {
					control.contentTextarea.val( newData );
				}
				control.editorSyncSuspended = false;
			}
		},

		/**
		 * Unlink the editor from this post and collapse the editor when the section is collapsed.
		 *
		 * @param {boolean} expanded Expanded.
		 * @returns {void}
		 */
		handleSectionExpansionToggled: function handleSectionExpansionToggled( expanded ) {
			var control = this,
				section = api.section( control.section() );

			if ( section && expanded ) {
				api.Posts.postIdInput.val( section.params.post_id || false );
			} else {
				api.Posts.postIdInput.val( '' );
				control.expanded.set( false );
			}
		},

		/**
		 * Toggle the editor when clicking the button, focusing on it if it is expanded.
		 *
		 * @returns {void}
		 */
		handleToggleEditorButtonClick: function handleToggleEditorButtonClick() {
			var control = this,
				editor = tinyMCE.get( 'customize-posts-content' );
			control.expanded.set( ! control.expanded() );
			if ( control.expanded() ) {
				if ( editor ) {
					editor.focus();
				} else {
					control.contentTextarea.focus();
				}
			}
		},

		/**
		 * Start resizing the editor when the dragbar starts dragging.
		 *
		 * @returns {void}
		 */
		onMousedownEditorDragbar: function onMousedownEditorDragbar() {
			var control = this;

			// Note this could also be accomplished by removing the event handler.
			if ( ! control.expanded() ) {
				return;
			}

			$( document ).on( 'mousemove.customize-posts-editor', function( event ) {
				event.preventDefault();
				$( document.body ).addClass( 'customize-posts-content-editor-pane-resize' );
				control.editorFrame.css( 'pointer-events', 'none' );
				control.resizeEditor( event.pageY );
			} );
		},

		/**
		 * Stop resizing the editor when the dragbar starts dragging.
		 *
		 * @returns {void}
		 */
		onMouseupEditorDragbar: function onMouseupEditorDragbar() {
			var control  = this;

			// Note this could also be accomplished by removing the event handler.
			if ( ! control.expanded() ) {
				return;
			}

			$( document ).off( 'mousemove.customize-posts-editor' );
			$( document.body ).removeClass( 'customize-posts-content-editor-pane-resize' );
			control.editorFrame.css( 'pointer-events', '' );
		},

		/**
		 * Resize the editor when the viewport changes.
		 *
		 * @returns {void}
		 */
		resizeEditorWithWindowResize: function resizeEditorWithWindowResize() {
			var control = this, resizeDelay = 50;

			// Note this could also be accomplished by removing the event handler.
			if ( ! control.expanded() ) {
				return;
			}

			_.delay( function() {
				control.resizeEditor( window.innerHeight - control.editorPane.height() );
			}, resizeDelay );
		},

		/**
		 * Collapse other controls.
		 *
		 * @returns {void}
		 */
		collapseOtherControls: function collapseOtherControls() {
			var control = this;

			api.control.each( function( otherControl ) {
				if ( otherControl !== control && otherControl.extended( api.Posts.PostEditorControl ) && otherControl.expanded.get() ) {
					otherControl.expanded.set( false );
				}
			} );
		},

		/**
		 * Update editor toggle expand button text.
		 *
		 * @param {Boolean} expanded Expanded state of the editor.
		 * @returns {void}
		 */
		updateEditorToggleExpandButtonLabel: function updateEditorToggleExpandButtonLabel( expanded ) {
			var control = this;

			// @todo Allow these labels to be parameters on the control.
			control.editorToggleExpandButton.text( expanded ? api.Posts.data.l10n.closeEditor : api.Posts.data.l10n.openEditor );
		},

		/**
		 * Vertically Resize Expanded Post Editor.
		 *
		 * @param {int} position - The position of the post editor from the top of the browser window.
		 * @returns {void}
		 */
		resizeEditor: function resizeEditor( position ) {
			var control = this,
				windowHeight = window.innerHeight,
				windowWidth = window.innerWidth,
				sectionContent = $( '[id^=accordion-panel-posts] ul.accordion-section-content' ),
				mceTools = $( '#wp-customize-posts-content-editor-tools' ),
				mceToolbar = $( '.mce-toolbar-grp' ),
				mceStatusbar = $( '.mce-statusbar' ),
				minScroll = 40,
				maxScroll = 1,
				mobileWidth = 782,
				collapseMinSpacing = 56,
				collapseBottomOutsideEditor = 8,
				collapseBottomInsideEditor = 4,
				args = {},
				resizeHeight;

			if ( ! control.expanded() ) {
				return;
			}

			if ( ! _.isNaN( position ) ) {
				resizeHeight = windowHeight - position;
			}

			args.height = resizeHeight;
			args.components = mceTools.outerHeight() + mceToolbar.outerHeight() + mceStatusbar.outerHeight();

			if ( resizeHeight < minScroll ) {
				args.height = minScroll;
			}

			if ( resizeHeight > windowHeight - maxScroll ) {
				args.height = windowHeight - maxScroll;
			}

			if ( windowHeight < control.editorPane.outerHeight() ) {
				args.height = windowHeight;
			}

			control.customizePreview.css( 'bottom', args.height );
			control.editorPane.css( 'height', args.height );
			control.editorFrame.css( 'height', args.height - args.components );
			control.collapseSidebar.css( 'bottom', args.height + collapseBottomOutsideEditor );

			if ( collapseMinSpacing > windowHeight - args.height ) {
				control.collapseSidebar.css( 'bottom', mceStatusbar.outerHeight() + collapseBottomInsideEditor );
			}

			if ( windowWidth <= mobileWidth ) {
				sectionContent.css( 'padding-bottom', args.height );
			} else {
				sectionContent.css( 'padding-bottom', '' );
			}
		},

		/**
		 * Expand the editor and focus on it when the post content control is focused.
		 *
		 * @param {object} args Focus args.
		 * @param {Function} [args.completeCallback] - Optional callback function when focus has completed.
		 * @returns {void}
		 */
		focus: function focus( args ) {
			var control = this,
				editor = tinyMCE.get( 'customize-posts-content' );

			control.actuallyEmbed();

			control.expand({
				completeCallback: function() {
					if ( editor ) {
						editor.focus();
					} else {
						control.contentTextarea.focus();
					}

					if ( args && args.completeCallback ) {
						args.completeCallback();
					}
				}
			});
		},

		/**
		 * Stop propagation of escape key to prevent the editor control from being collapsed.
		 *
		 * This stops the following code from running in core:
		 * https://github.com/xwp/wordpress-develop/blob/4.6.1/src/wp-admin/js/customize-controls.js#L3971-L4007
		 *
		 * @param {jQuery.Event} event Event.
		 * @returns {void}
		 */
		stopEscKeypressEventPropagation: function( event ) {
			var escKeyCode = 27;
			if ( escKeyCode === event.which ) {
				event.stopPropagation();
			}
		}
	});

	api.controlConstructor.post_editor = api.Posts.PostEditorControl;

})( wp.customize, jQuery );
