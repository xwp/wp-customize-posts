/* global jQuery, wp, _, tinyMCE */
/* eslint consistent-this: [ "error", "control" ] */

(function( api, $ ) {
	'use strict';

	/**
	 * An editor control.
	 *
	 * @class
	 * @augments wp.customize.DynamicControl
	 * @augments wp.customize.Control
	 * @augments wp.customize.Class
	 */
	api.EditorControl = api.controlConstructor.dynamic.extend({

		initialize: function initialize( id, options ) {
			var control = this, args;

			args = {};
			args.params = _.extend(
				{
					type: 'editor',
					section: '',
					priority: 25,
					label: api.Posts.data.l10n.fieldContentLabel,
					active: true,
					field_type: 'textarea',
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
		},

		/**
		 * Toggle the expanded control.
		 *
		 * @param {Boolean} expanded
		 * @param {Object} [params]
		 * @returns {Boolean} false if state already applied
		 */
		_toggleExpanded: api.Section.prototype._toggleExpanded,

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
		 * @param {boolean}  [expanded] - If not supplied, will be inverse of current visibility
		 * @param {Object}   [params] - Optional params.
		 * @param {Function} [params.completeCallback] - Function to call when the form toggle has finished animating.
		 * @returns {void}
		 */
		onChangeExpanded: function( expanded, params ) {
			var control = this,
				editor,
				setting = control.setting,
				isMeta = null === control.params.setting_property,
				textarea = $( '#customize-posts-content' ),
				settingValue = isMeta ? setting.get() : setting()[ control.params.setting_property ];

			editor = tinyMCE.get( 'customize-posts-content' );
			control.updateEditorToggleExpandButtonLabel( expanded );

			if ( expanded ) {
				control.collapseOtherControls();

				if ( editor && ! editor.isHidden() ) {
					editor.setContent( wp.editor.autop( settingValue ) );
				} else {
					textarea.val( settingValue );
				}
				editor.on( 'input change keyup', control.onVisualEditorChange );
				textarea.on( 'input', control.onTextEditorChange );
				$( document.body ).addClass( 'customize-posts-content-editor-pane-open' );
				control.resizeEditor( window.innerHeight - control.editorPane.height() );
			} else {
				editor.off( 'input change keyup', control.onVisualEditorChange );
				textarea.off( 'input', control.onTextEditorChange );
				$( document.body ).removeClass( 'customize-posts-content-editor-pane-open' );

				// Cancel link and force a click event to exit fullscreen & kitchen sink mode.
				editor.execCommand( 'wp_link_cancel' );
				$( '.mce-active' ).click();
				control.customizePreview.css( 'bottom', '' );
				control.collapseSidebar.css( 'bottom', '' );
			}

			if ( params && params.completeCallback ) {
				params.completeCallback();
			}
		},

		/**
		 * Update the setting value when the editor changes its state.
		 *
		 * @returns {void}
		 */
		onVisualEditorChange: function onVisualEditorChange() {
			var control = this, value, editor;
			if ( control.editorSyncSuspended ) {
				return;
			}
			editor = tinyMCE.get( 'customize-posts-content' );
			value = wp.editor.removep( editor.getContent() );
			control.editorSyncSuspended = true;

			if ( null === control.params.setting_property ) { // @todo Not right.
				control.setting.set( value );
			} else {
				control.propertyElements[0].set( value );
			}

			control.editorSyncSuspended = false;
		},

		/**
		 * Update the setting value when the editor changes its state.
		 *
		 * @returns {void}
		 */
		onTextEditorChange: function onTextEditorChange() {
			var control = this, textarea, value;
			textarea = $( '#customize-posts-content' );
			value = textarea.val();
			if ( control.editorSyncSuspended ) {
				return;
			}
			control.editorSyncSuspended = true;

			if ( null === control.params.setting_property ) { // @todo Not right.
				control.setting.set( value );
			} else {
				control.propertyElements[0].set( value );
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
				setting = control.setting,
				isMeta  = null === control.params.setting_property;

			control.customizePreview = $( '#customize-preview' );
			control.editorDragbar    = $( '#customize-posts-content-editor-dragbar' );
			control.editorPane       = $( '#customize-posts-content-editor-pane' );
			control.editorFrame      = $( '#customize-posts-content_ifr' );
			control.collapseSidebar  = $( '.collapse-sidebar' );

			control.editorToggleExpandButton = $( '<button type="button" class="button"></button>' );
			control.updateEditorToggleExpandButtonLabel( control.expanded.get() );
			control.onTextEditorChange = _.bind( control.onTextEditorChange, control );
			control.onVisualEditorChange = _.bind( control.onVisualEditorChange, control );

			/**
			 * Update the editor when the setting changes its state.
			 */
			setting.bind( function( newPostData, oldPostData ) {
				var editor, textarea = $( '#customize-posts-content' ),
					newData = isMeta ? newPostData : newPostData[ control.params.setting_property ],
					oldData = isMeta ? oldPostData : oldPostData[ control.params.setting_property ];

				if ( control.expanded.get() && ! control.editorSyncSuspended && newData !== oldData ) {
					control.editorSyncSuspended = true;
					editor = tinyMCE.get( 'customize-posts-content' );
					if ( editor && ! editor.isHidden() ) {
						editor.setContent( wp.editor.autop( newData ) );
					} else {
						textarea.val( newData );
					}
					control.editorSyncSuspended = false;
				}
			} );

			/**
			 * Unlink the editor from this post and collapse the editor when the section is collapsed.
			 */
			section.expanded.bind( function( expanded ) {
				if ( expanded ) {
					api.Posts.postIdInput.val( section.params.post_id );
				} else {
					api.Posts.postIdInput.val( '' );
					control.expanded.set( false );
				}
			} );

			/**
			 * Toggle the editor when clicking the button, focusing on it if it is expanded.
			 */
			control.editorToggleExpandButton.on( 'click', function() {
				var editor = tinyMCE.get( 'customize-posts-content' );
				control.expanded.set( ! control.expanded() );
				if ( control.expanded() ) {
					editor.focus();
				}
			} );

			// Resize the editor.
			control.editorDragbar.on( 'mousedown', function() {

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
			} );

			// Remove editor resize.
			control.editorDragbar.on( 'mouseup', function() {

				// Note this could also be accomplished by removing the event handler.
				if ( ! control.expanded() ) {
					return;
				}

				$( document ).off( 'mousemove.customize-posts-editor' );
				$( document.body ).removeClass( 'customize-posts-content-editor-pane-resize' );
				control.editorFrame.css( 'pointer-events', '' );
			} );

			// Resize the editor when the viewport changes.
			$( window ).on( 'resize', function() {
				var resizeDelay = 50;

				// Note this could also be accomplished by removing the event handler.
				if ( ! control.expanded() ) {
					return;
				}

				_.delay( function() {
					control.resizeEditor( window.innerHeight - control.editorPane.height() );
				}, resizeDelay );
			} );

			control.injectButton();
		},

		/**
		 * Collapse other controls.
		 *
		 * @returns {void}
		 */
		collapseOtherControls: function collapseOtherControls() {
			var control = this;

			api.control.each( function( otherControl ) {
				if ( otherControl !== control && otherControl.extended( api.EditorControl ) && otherControl.expanded.get() ) {
					otherControl.expanded.set( false );
				}
			} );
		},

		/**
		 * Inject button in place of textarea.
		 *
		 * @returns {void}
	     */
		injectButton: function injectButton() {
			var control = this,
				textarea = control.container.find( 'textarea:first' );

			control.editorToggleExpandButton.attr( 'id', textarea.attr( 'id' ) );
			textarea.attr( 'id', '' );
			control.container.append( control.editorToggleExpandButton );
		},

		/**
		 * Update editor toggle expand button text.
		 *
		 * @param {Boolean} expanded Expanded state of the editor.
		 * @returns {void}
		 */
		updateEditorToggleExpandButtonLabel: function updateEditorToggleExpandButtonLabel( expanded ) {
			var control = this;
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
				textarea = $( '#customize-posts-content' ),
				editor = tinyMCE.get( 'customize-posts-content' );

			control.actuallyEmbed();

			control.expand({
				completeCallback: function() {
					if ( editor ) {
						editor.focus();
					} else {
						textarea.focus();
					}

					if ( args && args.completeCallback ) {
						args.completeCallback();
					}
				}
			});
		}
	});

	api.controlConstructor.editor = api.EditorControl;

})( wp.customize, jQuery );
